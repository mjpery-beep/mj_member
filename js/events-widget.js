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
    var toArray = typeof Utils.toArray === 'function'
        ? Utils.toArray
        : function (collection) {
            if (!collection) {
                return [];
            }

            if (Array.isArray(collection)) {
                return collection.slice();
            }

            try {
                return Array.prototype.slice.call(collection);
            } catch (error) {
                var fallback = [];
                for (var idx = 0; idx < collection.length; idx += 1) {
                    fallback.push(collection[idx]);
                }
                return fallback;
            }
        };

    var settings = window.mjMemberEventsWidget || {};
    var ajaxUrl = settings.ajaxUrl || '';
    var nonce = settings.nonce || '';
    var loginUrl = settings.loginUrl || '';
    var strings = settings.strings || {};
    var activeSignup = null;
    var defaultCtaLabel = strings.cta || "S'inscrire";
    var registeredCtaLabel = strings.registered || strings.confirm || "Confirmer l'inscription";
    var eventWidgets = [];
    var reservationPanel = {
        root: null,
        list: null,
        empty: null,
        feedback: null,
        eventId: 0,
        loading: false,
        pending: null,
        bound: false,
    };

    function resolveReservationPanel() {
        if (reservationPanel.root) {
            return reservationPanel;
        }

        if (typeof document === 'undefined') {
            return null;
        }

        var root = document.querySelector('[data-mj-event-reservations]');
        if (!root) {
            return null;
        }

        reservationPanel.root = root;
        reservationPanel.list = root.querySelector('[data-mj-event-reservations-list]');
        reservationPanel.empty = root.querySelector('[data-mj-event-reservations-empty]');
        reservationPanel.feedback = root.querySelector('[data-mj-event-reservations-feedback]');

        var eventAttr = root.getAttribute('data-event-id') || '0';
        var eventId = parseInt(eventAttr, 10);
        if (Number.isNaN(eventId) || eventId < 0) {
            eventId = 0;
        }
        reservationPanel.eventId = eventId;

        if (!reservationPanel.bound) {
            root.addEventListener('click', function (event) {
                var target = event.target;
                if (!target || typeof target.closest !== 'function') {
                    return;
                }
                var button = target.closest('[data-mj-event-cancel]');
                if (!button || !root.contains(button)) {
                    return;
                }
                handleReservationCancel(button);
            });
            reservationPanel.bound = true;
        }

        return reservationPanel;
    }

    function updateReservationFeedback(panel, message, isError) {
        if (!panel || !panel.feedback) {
            return;
        }
        panel.feedback.textContent = message || '';
        if (isError) {
            panel.feedback.classList.add('is-error');
        } else {
            panel.feedback.classList.remove('is-error');
        }
    }

    function toggleReservationLoading(panel, isLoading) {
        if (!panel || !panel.root) {
            return;
        }
        if (isLoading) {
            panel.root.classList.add('is-loading');
        } else {
            panel.root.classList.remove('is-loading');
        }
    }

    function buildReservationElement(entry) {
        var listItem = document.createElement('li');
        listItem.className = 'mj-member-event-single__reservation';
        listItem.setAttribute('data-mj-event-reservation', '');

        var memberId = entry && entry.member_id ? parseInt(entry.member_id, 10) : 0;
        if (Number.isNaN(memberId) || memberId < 0) {
            memberId = 0;
        }
        listItem.setAttribute('data-member-id', String(memberId));

        var registrationId = entry && entry.registration_id ? parseInt(entry.registration_id, 10) : 0;
        if (Number.isNaN(registrationId) || registrationId < 0) {
            registrationId = 0;
        }
        listItem.setAttribute('data-registration-id', String(registrationId));

        if (entry && entry.status_key) {
            listItem.setAttribute('data-status-key', String(entry.status_key));
        }

        var header = document.createElement('div');
        header.className = 'mj-member-event-single__reservation-header';

        var main = document.createElement('div');
        main.className = 'mj-member-event-single__reservation-main';

        var title = document.createElement('p');
        title.className = 'mj-member-event-single__reservation-title';
        title.textContent = entry && entry.name ? String(entry.name) : (strings.reservationUnknown || 'Participant');
        main.appendChild(title);

        if (entry && entry.status_label) {
            var status = document.createElement('span');
            var baseClass = 'mj-member-event-single__reservation-status';
            if (entry.status_class) {
                baseClass += ' ' + String(entry.status_class);
            }
            status.className = baseClass;
            status.textContent = String(entry.status_label);
            main.appendChild(status);
        }

        header.appendChild(main);

        if (entry && entry.can_cancel && memberId > 0) {
            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'mj-member-event-single__reservation-cancel';
            cancel.setAttribute('data-mj-event-cancel', '');
            cancel.setAttribute('data-member-id', String(memberId));
            cancel.setAttribute('data-registration-id', String(registrationId));
            cancel.textContent = strings.unregister || 'Se désinscrire';
            header.appendChild(cancel);
        }

        listItem.appendChild(header);

        if (entry && entry.created_label) {
            var meta = document.createElement('p');
            meta.className = 'mj-member-event-single__reservation-meta';
            var template = strings.reservationCreated || 'Réservé le %s';
            meta.textContent = template.replace('%s', String(entry.created_label));
            listItem.appendChild(meta);
        }

        if (entry && Array.isArray(entry.occurrences) && entry.occurrences.length) {
            var occurrencesList = document.createElement('ul');
            occurrencesList.className = 'mj-member-event-single__reservation-occurrences';
            for (var i = 0; i < entry.occurrences.length; i += 1) {
                var occurrenceLabel = entry.occurrences[i];
                if (!occurrenceLabel) {
                    continue;
                }
                var occurrenceItem = document.createElement('li');
                occurrenceItem.className = 'mj-member-event-single__reservation-occurrence';
                occurrenceItem.textContent = String(occurrenceLabel);
                occurrencesList.appendChild(occurrenceItem);
            }
            listItem.appendChild(occurrencesList);
        }

        return listItem;
    }

    function updateReservationPanel(data) {
        var panel = resolveReservationPanel();
        if (!panel) {
            return;
        }

        if (panel.list) {
            panel.list.innerHTML = '';
        }

        var hasReservations = false;
        if (data && Array.isArray(data.reservations) && data.reservations.length) {
            hasReservations = true;
            for (var i = 0; i < data.reservations.length; i += 1) {
                var entry = data.reservations[i];
                if (!panel.list) {
                    break;
                }
                panel.list.appendChild(buildReservationElement(entry));
            }
        }

        panel.root.setAttribute('data-has-reservations', hasReservations ? '1' : '0');
        panel.root.classList.toggle('is-empty', !hasReservations);

        if (panel.empty) {
            if (data && data.empty_message) {
                panel.empty.textContent = String(data.empty_message);
            }
            panel.empty.hidden = hasReservations;
        }
    }

    function refreshReservationPanel(options) {
        var panel = resolveReservationPanel();
        if (!panel || !panel.eventId || !ajaxUrl || !nonce) {
            return Promise.resolve(null);
        }

        if (panel.loading && panel.pending) {
            return panel.pending;
        }

        var showFeedback = !(options && options.silent);
        var successMessage = options && options.successMessage ? String(options.successMessage) : '';
        var loadingMessage = strings.reservationsLoading || strings.loading || '';

        panel.loading = true;
        toggleReservationLoading(panel, true);

        if (showFeedback && loadingMessage) {
            updateReservationFeedback(panel, loadingMessage, false);
        }

        var payload = new window.FormData();
        payload.append('action', 'mj_member_get_event_reservations');
        payload.append('nonce', nonce);
        payload.append('event_id', panel.eventId);

        panel.pending = window.fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, status: response.status, json: json };
            }).catch(function () {
                return { ok: response.ok, status: response.status, json: null };
            });
        }).then(function (result) {
            if (!result.ok || !result.json) {
                var networkMessage = strings.reservationsError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                updateReservationFeedback(panel, networkMessage, true);
                throw new Error(networkMessage);
            }

            if (!result.json.success) {
                var failMessage = strings.reservationsError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                if (result.json.data && result.json.data.message) {
                    failMessage = result.json.data.message;
                }
                updateReservationFeedback(panel, failMessage, true);
                throw new Error(failMessage);
            }

            updateReservationPanel(result.json.data || {});

            if (successMessage) {
                updateReservationFeedback(panel, successMessage, false);
            } else if (!showFeedback) {
                // keep previous feedback untouched when silent
            } else {
                updateReservationFeedback(panel, '', false);
            }

            return result.json.data;
        }).catch(function (error) {
            var errorMessage = strings.reservationsError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
            if (error && error.message) {
                errorMessage = error.message;
            }
            updateReservationFeedback(panel, errorMessage, true);
            throw error;
        }).finally(function () {
            panel.loading = false;
            toggleReservationLoading(panel, false);
            panel.pending = null;
        });

        return panel.pending;
    }

    function sendUnregisterRequest(eventId, memberId, registrationId) {
        if (!ajaxUrl || !nonce) {
            return Promise.reject(new Error(strings.genericError || 'Une erreur est survenue. Merci de réessayer.'));
        }

        var payload = new window.FormData();
        payload.append('action', 'mj_member_unregister_event');
        payload.append('nonce', nonce);
        payload.append('event_id', eventId);
        payload.append('member_id', memberId);
        if (registrationId > 0) {
            payload.append('registration_id', registrationId);
        }

        return window.fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, status: response.status, json: json };
            }).catch(function () {
                return { ok: response.ok, status: response.status, json: null };
            });
        }).then(function (result) {
            if (!result.ok || !result.json) {
                var messageNetwork = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                throw new Error(messageNetwork);
            }

            if (!result.json.success) {
                var messageFail = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                if (result.json.data && result.json.data.message) {
                    messageFail = result.json.data.message;
                }
                throw new Error(messageFail);
            }

            return result.json.data || {};
        });
    }

    function handleReservationCancel(button) {
        var panel = resolveReservationPanel();
        if (!panel || panel.loading) {
            return;
        }

        var memberId = parseInt(button.getAttribute('data-member-id'), 10);
        if (Number.isNaN(memberId) || memberId <= 0) {
            return;
        }

        var registrationId = parseInt(button.getAttribute('data-registration-id'), 10);
        if (Number.isNaN(registrationId) || registrationId < 0) {
            registrationId = 0;
        }

        if (strings.unregisterConfirm && !window.confirm(strings.unregisterConfirm)) {
            return;
        }

        panel.loading = true;
        toggleReservationLoading(panel, true);
        updateReservationFeedback(panel, strings.reservationsLoading || strings.loading || '', false);
        button.disabled = true;

        sendUnregisterRequest(panel.eventId, memberId, registrationId)
            .then(function () {
                return refreshReservationPanel({
                    silent: true,
                    successMessage: strings.unregisterSuccess || strings.reservationsUpdated || 'Inscription annulée.',
                });
            }).catch(function (error) {
                var messageError = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                if (error && error.message) {
                    messageError = error.message;
                }
                updateReservationFeedback(panel, messageError, true);
            }).finally(function () {
                panel.loading = false;
                toggleReservationLoading(panel, false);
                button.disabled = false;
            });
    }

    function setFeedback(element, message, isError) {
        if (!element) {
            return;
        }
        element.textContent = message || '';
        if (isError) {
            element.classList.add('is-error');
        } else {
            element.classList.remove('is-error');
        }
    }

    function closeSignup(signup) {
        if (!signup) {
            return;
        }
        signup.classList.remove('is-open');
        signup.setAttribute('hidden', 'hidden');
        if (activeSignup === signup) {
            activeSignup = null;
        }
    }

    function openLoginModal() {
        var trigger = document.querySelector('[data-mj-login-trigger]');
        if (trigger) {
            var targetId = trigger.getAttribute('data-target');
            var modal = targetId ? document.getElementById(targetId) : null;

            if (typeof window.mjMemberOpenLoginModal === 'function' && window.mjMemberOpenLoginModal(targetId || '')) {
                return;
            }

            if (!modal) {
                if (loginUrl) {
                    window.location.href = loginUrl;
                }
                return;
            }

            trigger.setAttribute('data-force-open', '1');
            trigger.click();

            if (!document.body.classList.contains('mj-member-login-open')) {
                document.body.classList.add('mj-member-login-open');
            }

            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');

            var focusable = modal.querySelector('input:not([disabled]):not([type="hidden"])');
            if (!focusable) {
                focusable = modal.querySelector('button:not([disabled]), a[href], textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])');
            }
            if (focusable && typeof focusable.focus === 'function') {
                focusable.focus();
            }

            return;
        }

        if (loginUrl) {
            window.location.href = loginUrl;
        }
    }

    function setupInlineRegistrationForm(form) {
        if (!form || form.dataset.mjEventRegistrationInit === '1') {
            return;
        }

        form.dataset.mjEventRegistrationInit = '1';

        var eventAttr = form.getAttribute('data-event-id') || '0';
        var eventId = parseInt(eventAttr, 10);
        if (Number.isNaN(eventId) || eventId <= 0) {
            eventId = 0;
        }

        var noteAttr = form.getAttribute('data-note-max') || '0';
        var noteMaxLength = parseInt(noteAttr, 10);
        if (Number.isNaN(noteMaxLength) || noteMaxLength <= 0) {
            noteMaxLength = 400;
        }

        var submitButton = form.querySelector('[data-mj-event-registration-submit]');
        var feedbackNode = form.querySelector('[data-mj-event-registration-feedback]');
        var membersEmptyNode = form.querySelector('[data-mj-event-members-empty]');
        var noteField = form.querySelector('textarea[name="note"]');
        var noteContainer = form.querySelector('.mj-member-event-single__registration-note');
        var radios = form.querySelectorAll('input[name="participant"]');
        var originalSubmitLabel = submitButton ? submitButton.textContent : '';

        var registrationConfig = null;
        var configAttr = form.getAttribute('data-registration-config') || '';
        if (configAttr) {
            try {
                registrationConfig = JSON.parse(configAttr);
            } catch (error) {
                registrationConfig = null;
            }
        }

        var occurrenceItems = Array.isArray(registrationConfig && registrationConfig.occurrences)
            ? registrationConfig.occurrences
            : [];
        var occurrenceSummaryText = '';
        var scheduleMode = 'fixed';
        if (registrationConfig) {
            if (typeof registrationConfig.occurrenceSummary === 'string') {
                occurrenceSummaryText = registrationConfig.occurrenceSummary;
            } else if (typeof registrationConfig.occurrence_summary === 'string') {
                occurrenceSummaryText = registrationConfig.occurrence_summary;
            } else if (typeof registrationConfig.occurrenceSummaryText === 'string') {
                occurrenceSummaryText = registrationConfig.occurrenceSummaryText;
            }

            if (typeof registrationConfig.scheduleMode === 'string') {
                scheduleMode = registrationConfig.scheduleMode;
            } else if (typeof registrationConfig.schedule_mode === 'string') {
                scheduleMode = registrationConfig.schedule_mode;
            }
        }
        var occurrenceAssignments = registrationConfig && registrationConfig.assignments && typeof registrationConfig.assignments === 'object'
            ? registrationConfig.assignments
            : null;
        var selectedOccurrenceMap = Object.create(null);
        if (occurrenceAssignments && occurrenceAssignments.mode === 'custom' && Array.isArray(occurrenceAssignments.occurrences)) {
            for (var occIdx = 0; occIdx < occurrenceAssignments.occurrences.length; occIdx += 1) {
                var normalizedValue = normalizeOccurrenceValue(occurrenceAssignments.occurrences[occIdx]);
                if (normalizedValue) {
                    selectedOccurrenceMap[normalizedValue] = true;
                }
            }
        }

        var allowOccurrenceSelection = false;
        if (registrationConfig) {
            if (Object.prototype.hasOwnProperty.call(registrationConfig, 'allowOccurrenceSelection')) {
                allowOccurrenceSelection = !!registrationConfig.allowOccurrenceSelection;
            } else if (typeof registrationConfig.occurrenceSelectionMode === 'string') {
                allowOccurrenceSelection = registrationConfig.occurrenceSelectionMode !== 'all_occurrences';
            }
        }

        var occurrenceFieldset = null;
        var occurrenceHelpNode = null;
        var occurrenceCheckboxes = [];
        var occurrenceStoreField = null;
        var requiresOccurrenceSelection = allowOccurrenceSelection && occurrenceItems.length > 0;

        function ensureOccurrenceStore() {
            if (!form) {
                return null;
            }
            if (occurrenceStoreField && form.contains(occurrenceStoreField)) {
                return occurrenceStoreField;
            }
            var existing = form.querySelector('input[type="hidden"][data-event-occurrences-selected]');
            if (existing) {
                occurrenceStoreField = existing;
                return occurrenceStoreField;
            }
            var field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'occurrences_json';
            field.value = '[]';
            field.setAttribute('data-event-occurrences-selected', '1');
            form.appendChild(field);
            occurrenceStoreField = field;
            return occurrenceStoreField;
        }

        function sanitizeOccurrenceValues(values) {
            if (!Array.isArray(values)) {
                return [];
            }
            var normalized = [];
            var registry = Object.create(null);
            for (var idx = 0; idx < values.length; idx += 1) {
                var normalizedValue = normalizeOccurrenceValue(values[idx]);
                if (!normalizedValue || registry[normalizedValue]) {
                    continue;
                }
                registry[normalizedValue] = true;
                normalized.push(normalizedValue);
            }
            return normalized;
        }

        function readOccurrenceStore() {
            var field = ensureOccurrenceStore();
            if (!field) {
                return [];
            }
            var raw = field.value || '';
            if (!raw) {
                return [];
            }
            try {
                var parsed = JSON.parse(raw);
                return sanitizeOccurrenceValues(parsed);
            } catch (error) {
                return [];
            }
        }

        function writeOccurrenceStore(values) {
            var field = ensureOccurrenceStore();
            if (!field) {
                return;
            }
            var normalized = sanitizeOccurrenceValues(values);
            try {
                field.value = JSON.stringify(normalized);
            } catch (error) {
                field.value = '[]';
            }
        }

        function notifyOccurrenceSelectionChanged(source) {
            if (!form || typeof form.dispatchEvent !== 'function' || typeof window.CustomEvent !== 'function') {
                return;
            }
            var payload = readOccurrenceStore();
            try {
                form.dispatchEvent(new CustomEvent('mj-member:event-single-occurrences-sync', {
                    detail: {
                        source: source || 'legacy',
                        occurrences: payload.slice ? payload.slice() : payload,
                    },
                }));
            } catch (error) {
                // ignore
            }
        }

        ensureOccurrenceStore();

        function determineOccurrenceTimePreference() {
            var activeCount = 0;
            var timeKeySet = Object.create(null);

            for (var idx = 0; idx < occurrenceItems.length; idx += 1) {
                var entry = occurrenceItems[idx];
                if (!entry) {
                    continue;
                }
                if (!entry.isPast) {
                    activeCount += 1;
                }
                var timeKey = getOccurrenceTimeKey(entry);
                if (timeKey) {
                    timeKeySet[timeKey] = true;
                }
            }

            if (activeCount <= 1) {
                return true;
            }

            var uniqueTimes = 0;
            for (var key in timeKeySet) {
                if (Object.prototype.hasOwnProperty.call(timeKeySet, key)) {
                    uniqueTimes += 1;
                    if (uniqueTimes > 1) {
                        return true;
                    }
                }
            }

            return false;
        }

        function collectSelectedOccurrences() {
            if (!occurrenceCheckboxes.length) {
                return readOccurrenceStore();
            }

            var collected = [];
            for (var idx = 0; idx < occurrenceCheckboxes.length; idx += 1) {
                var checkbox = occurrenceCheckboxes[idx];
                if (checkbox && !checkbox.disabled && checkbox.checked) {
                    collected.push(checkbox.value);
                }
            }

            var sanitized = sanitizeOccurrenceValues(collected);
            writeOccurrenceStore(sanitized);
            return sanitized;
        }

        function resetOccurrenceSelection() {
            for (var idx = 0; idx < occurrenceCheckboxes.length; idx += 1) {
                var checkbox = occurrenceCheckboxes[idx];
                if (!checkbox) {
                    continue;
                }
                if (!checkbox.disabled) {
                    checkbox.checked = false;
                }
                var occurrenceItem = checkbox.closest('.mj-member-events__signup-calendar-occurrence, .mj-member-events__signup-occurrence-item');
                if (occurrenceItem) {
                    occurrenceItem.classList.remove('is-selected');
                }
                var dayNode = checkbox.closest('.mj-member-events__signup-calendar-day');
                syncInlineCalendarDay(dayNode);
            }

            writeOccurrenceStore([]);
            notifyOccurrenceSelectionChanged('legacy');
        }

        function syncInlineCalendarDay(dayNode) {
            if (!dayNode) {
                return;
            }
            var hasSelection = false;
            var occurrences = dayNode.querySelectorAll('.mj-member-events__signup-calendar-occurrence');
            Array.prototype.forEach.call(occurrences, function (item) {
                if (item.classList.contains('is-selected')) {
                    hasSelection = true;
                }
            });
            dayNode.classList.toggle('has-selection', hasSelection);
        }

        function updateSubmitAvailability(availableCountOverride) {
            var availableCount = typeof availableCountOverride === 'number' ? availableCountOverride : null;
            if (submitButton) {
                if (form.dataset.submitting === '1') {
                    submitButton.disabled = true;
                } else {
                    var hasParticipants = false;
                    if (availableCount !== null) {
                        hasParticipants = availableCount > 0;
                    } else {
                        var selectableParticipant = form.querySelector('input[name="participant"]:not([disabled])');
                        hasParticipants = !!selectableParticipant;
                    }
                    var hasOccurrences = !requiresOccurrenceSelection || collectSelectedOccurrences().length > 0;
                    submitButton.disabled = !hasParticipants || !hasOccurrences;
                }
            }

            if (occurrenceHelpNode) {
                var shouldWarn = requiresOccurrenceSelection && collectSelectedOccurrences().length === 0;
                occurrenceHelpNode.classList.toggle('is-warning', shouldWarn);
            }
        }

        function buildInlineCalendar() {
            var normalized = [];
            var seenValues = Object.create(null);
            var hasEnabledOccurrence = false;

            for (var idx = 0; idx < occurrenceItems.length; idx += 1) {
                var entry = occurrenceItems[idx];
                if (!entry) {
                    continue;
                }

                var occurrenceValue = normalizeOccurrenceValue(entry.start);
                if (!occurrenceValue || seenValues[occurrenceValue]) {
                    continue;
                }

                var occurrenceDate = parseOccurrenceDate(entry);
                if (!occurrenceDate) {
                    continue;
                }

                seenValues[occurrenceValue] = true;
                normalized.push({
                    value: occurrenceValue,
                    entry: entry,
                    date: occurrenceDate,
                });

                if (!entry.isPast) {
                    hasEnabledOccurrence = true;
                }
            }

            if (!normalized.length) {
                return false;
            }

            normalized.sort(function (a, b) {
                return a.date.getTime() - b.date.getTime();
            });

            var monthMap = Object.create(null);
            var monthOrder = [];

            normalized.forEach(function (item) {
                var year = item.date.getFullYear();
                var monthIndex = item.date.getMonth();
                var dayIndex = item.date.getDate();
                var monthKey = year + '-' + String(monthIndex + 1).padStart(2, '0');

                if (!monthMap[monthKey]) {
                    monthMap[monthKey] = {
                        year: year,
                        month: monthIndex,
                        days: Object.create(null),
                    };
                    monthOrder.push(monthKey);
                }

                var monthData = monthMap[monthKey];
                if (!monthData.days[dayIndex]) {
                    monthData.days[dayIndex] = {
                        iso: year + '-' + String(monthIndex + 1).padStart(2, '0') + '-' + String(dayIndex).padStart(2, '0'),
                        occurrences: [],
                    };
                }
                monthData.days[dayIndex].occurrences.push(item);
            });

            var calendarRoot = document.createElement('div');
            calendarRoot.className = 'mj-member-events__signup-calendar';

            var locale = resolveFormatterLocale();
            var monthFormatter = null;
            var weekdayFormatter = null;
            try {
                monthFormatter = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
            } catch (error) {
                monthFormatter = null;
            }
            try {
                weekdayFormatter = new Intl.DateTimeFormat(locale, { weekday: 'short' });
            } catch (error) {
                weekdayFormatter = null;
            }

            var weekdayLabels = [];
            if (weekdayFormatter) {
                var reference = new Date(2020, 5, 1);
                for (var offset = 0; offset < 7; offset += 1) {
                    var candidate = new Date(reference.getTime());
                    candidate.setDate(reference.getDate() + offset);
                    var labelCandidate = weekdayFormatter.format(candidate) || '';
                    labelCandidate = labelCandidate.replace(/\.$/, '');
                    if (labelCandidate.length > 3) {
                        labelCandidate = labelCandidate.slice(0, 3);
                    }
                    if (labelCandidate) {
                        labelCandidate = labelCandidate.charAt(0).toUpperCase() + labelCandidate.slice(1).toLowerCase();
                    }
                    weekdayLabels.push(labelCandidate);
                }
            } else {
                weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            }

            var builtCount = 0;

            monthOrder.forEach(function (monthKey) {
                var monthData = monthMap[monthKey];
                if (!monthData) {
                    return;
                }

                var monthNode = document.createElement('section');
                monthNode.className = 'mj-member-events__signup-calendar-month';

                var titleNode = document.createElement('p');
                titleNode.className = 'mj-member-events__signup-calendar-title';
                var titleLabel = monthFormatter ? monthFormatter.format(new Date(monthData.year, monthData.month, 1)) : monthKey.replace('-', ' / ');
                if (titleLabel && titleLabel.charAt) {
                    titleLabel = titleLabel.charAt(0).toUpperCase() + titleLabel.slice(1);
                }
                titleNode.textContent = titleLabel;
                monthNode.appendChild(titleNode);

                var weekdayRow = document.createElement('ol');
                weekdayRow.className = 'mj-member-events__signup-calendar-weekdays';
                for (var w = 0; w < 7; w += 1) {
                    var weekdayNode = document.createElement('li');
                    weekdayNode.className = 'mj-member-events__signup-calendar-weekday';
                    weekdayNode.textContent = weekdayLabels[w] || '';
                    weekdayRow.appendChild(weekdayNode);
                }
                monthNode.appendChild(weekdayRow);

                var dayGrid = document.createElement('ol');
                dayGrid.className = 'mj-member-events__signup-calendar-grid';

                var firstDay = new Date(monthData.year, monthData.month, 1);
                var daysInMonth = new Date(monthData.year, monthData.month + 1, 0).getDate();
                var startOffset = (firstDay.getDay() + 6) % 7;

                for (var blank = 0; blank < startOffset; blank += 1) {
                    var blankCell = document.createElement('li');
                    blankCell.className = 'mj-member-events__signup-calendar-day is-empty';
                    blankCell.setAttribute('aria-hidden', 'true');
                    dayGrid.appendChild(blankCell);
                }

                for (var dayIndex = 1; dayIndex <= daysInMonth; dayIndex += 1) {
                    var dayEntry = monthData.days[dayIndex] || null;
                    var dayNode = document.createElement('li');
                    dayNode.className = 'mj-member-events__signup-calendar-day';
                    dayNode.dataset.date = monthData.year + '-' + String(monthData.month + 1).padStart(2, '0') + '-' + String(dayIndex).padStart(2, '0');

                    var weekdayIndex = (startOffset + dayIndex - 1) % 7;
                    if (weekdayIndex >= 5) {
                        dayNode.classList.add('is-weekend');
                    }

                    var dayNumber = document.createElement('span');
                    dayNumber.className = 'mj-member-events__signup-calendar-day-number';
                    dayNumber.textContent = String(dayIndex);
                    dayNode.appendChild(dayNumber);

                    if (dayEntry && dayEntry.occurrences.length) {
                        var occurrenceList = document.createElement('ul');
                        occurrenceList.className = 'mj-member-events__signup-calendar-occurrences';

                        dayEntry.occurrences.forEach(function (entryItem) {
                            var occurrenceNode = document.createElement('li');
                            occurrenceNode.className = 'mj-member-events__signup-calendar-occurrence';
                            var occurrenceIsPast = !!entryItem.entry.isPast;
                            if (occurrenceIsPast) {
                                occurrenceNode.classList.add('is-past');
                            }

                            var occurrenceLabelNode = document.createElement('label');
                            occurrenceLabelNode.className = 'mj-member-events__signup-calendar-checkbox';

                            var checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.name = 'occurrence[]';
                            checkbox.value = entryItem.value;
                            checkbox.disabled = occurrenceIsPast;
                            if (!occurrenceIsPast) {
                                requiresOccurrenceSelection = true;
                            }
                            if (selectedOccurrenceMap[entryItem.value]) {
                                checkbox.checked = true;
                                occurrenceNode.classList.add('is-selected');
                            }

                            occurrenceLabelNode.appendChild(checkbox);

                            var pill = document.createElement('span');
                            pill.className = 'mj-member-events__signup-calendar-pill';
                            var timeLabel = formatOccurrenceTime(entryItem.entry);
                            pill.textContent = timeLabel || strings.occurrenceCalendarAllDay || 'Toute la journée';
                            occurrenceLabelNode.appendChild(pill);

                            occurrenceNode.appendChild(occurrenceLabelNode);

                            occurrenceCheckboxes.push(checkbox);

                            (function (inputNode, itemNode, dayContext) {
                                inputNode.addEventListener('change', function () {
                                    if (inputNode.checked) {
                                        itemNode.classList.add('is-selected');
                                    } else {
                                        itemNode.classList.remove('is-selected');
                                    }
                                    syncInlineCalendarDay(dayContext);
                                    updateSubmitAvailability();
                                    notifyOccurrenceSelectionChanged('legacy');
                                });
                            })(checkbox, occurrenceNode, dayNode);

                            occurrenceList.appendChild(occurrenceNode);
                            syncInlineCalendarDay(dayNode);
                            builtCount += 1;
                        });

                        dayNode.appendChild(occurrenceList);
                    } else {
                        dayNode.classList.add('is-disabled');
                    }

                    dayGrid.appendChild(dayNode);
                }

                monthNode.appendChild(dayGrid);
                calendarRoot.appendChild(monthNode);
            });

            if (!builtCount) {
                return false;
            }

            occurrenceFieldset.appendChild(calendarRoot);

            if (!hasEnabledOccurrence) {
                requiresOccurrenceSelection = false;
            }

            return true;
        }

        function buildInlineOccurrenceList(includeTime) {
            var listRoot = document.createElement('ul');
            listRoot.className = 'mj-member-events__signup-occurrence-list';

            var seenValues = Object.create(null);
            var builtCount = 0;
            var hasEnabledOccurrence = false;

            for (var idx = 0; idx < occurrenceItems.length; idx += 1) {
                var entry = occurrenceItems[idx];
                if (!entry) {
                    continue;
                }

                var occurrenceValue = normalizeOccurrenceValue(entry.start);
                if (!occurrenceValue || seenValues[occurrenceValue]) {
                    continue;
                }
                seenValues[occurrenceValue] = true;

                var occurrenceItem = document.createElement('li');
                occurrenceItem.className = 'mj-member-events__signup-occurrence-item';
                var occurrenceIsPast = !!entry.isPast;
                if (occurrenceIsPast) {
                    occurrenceItem.classList.add('is-past');
                }

                var labelNode = document.createElement('label');
                labelNode.className = 'mj-member-events__signup-occurrence-label';

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'occurrence[]';
                checkbox.value = occurrenceValue;
                checkbox.disabled = occurrenceIsPast;
                if (!occurrenceIsPast) {
                    hasEnabledOccurrence = true;
                }
                if (selectedOccurrenceMap[occurrenceValue]) {
                    checkbox.checked = true;
                    occurrenceItem.classList.add('is-selected');
                }
                labelNode.appendChild(checkbox);

                var textNode = document.createElement('span');
                textNode.textContent = formatOccurrenceLabel(entry, includeTime);
                labelNode.appendChild(textNode);

                if (occurrenceIsPast) {
                    var badge = document.createElement('span');
                    badge.className = 'mj-member-events__signup-occurrence-badge';
                    badge.textContent = strings.occurrencePast || 'Passée';
                    labelNode.appendChild(badge);
                }

                occurrenceItem.appendChild(labelNode);
                listRoot.appendChild(occurrenceItem);
                occurrenceCheckboxes.push(checkbox);

                (function (inputNode, itemNode) {
                    inputNode.addEventListener('change', function () {
                        if (inputNode.checked) {
                            itemNode.classList.add('is-selected');
                        } else {
                            itemNode.classList.remove('is-selected');
                        }
                        updateSubmitAvailability();
                        notifyOccurrenceSelectionChanged('legacy');
                    });
                })(checkbox, occurrenceItem);

                builtCount += 1;
            }

            if (!builtCount) {
                return false;
            }

            occurrenceFieldset.appendChild(listRoot);

            if (!hasEnabledOccurrence) {
                requiresOccurrenceSelection = false;
            }

            return true;
        }

        function buildOccurrenceSelector() {
            if (!allowOccurrenceSelection || !occurrenceItems.length) {
                requiresOccurrenceSelection = false;
                writeOccurrenceStore([]);
                notifyOccurrenceSelectionChanged('legacy');
                return;
            }

            if (occurrenceFieldset && occurrenceFieldset.parentNode) {
                occurrenceFieldset.parentNode.removeChild(occurrenceFieldset);
            }

            occurrenceCheckboxes = [];
            occurrenceHelpNode = null;
            requiresOccurrenceSelection = allowOccurrenceSelection && occurrenceItems.length > 0;

            occurrenceFieldset = document.createElement('fieldset');
            occurrenceFieldset.className = 'mj-member-event-single__registration-occurrences';

            var legendNode = document.createElement('legend');
            legendNode.textContent = strings.occurrenceLegend || 'Quelles dates ?';
            occurrenceFieldset.appendChild(legendNode);

            if (occurrenceSummaryText) {
                var summaryNode = document.createElement('p');
                summaryNode.className = 'mj-member-events__signup-occurrence-summary';
                summaryNode.textContent = occurrenceSummaryText;
                occurrenceFieldset.appendChild(summaryNode);
            }

            var includeTime = determineOccurrenceTimePreference();
            var calendarBuilt = buildInlineCalendar();
            if (!calendarBuilt) {
                buildInlineOccurrenceList(includeTime);
            }

            if (!occurrenceCheckboxes.length) {
                var emptyNotice = document.createElement('p');
                emptyNotice.className = 'mj-member-events__signup-occurrence-empty';
                emptyNotice.textContent = strings.occurrenceAvailableEmpty || 'Aucune occurrence disponible.';
                occurrenceFieldset.appendChild(emptyNotice);
                requiresOccurrenceSelection = false;
            } else {
                occurrenceHelpNode = document.createElement('p');
                occurrenceHelpNode.className = 'mj-member-events__signup-occurrence-help';
                if (scheduleMode === 'recurring' || scheduleMode === 'series') {
                    occurrenceHelpNode.textContent = strings.occurrenceHelpRecurring || "Selectionne les dates qui t interessent.";
                } else {
                    occurrenceHelpNode.textContent = strings.occurrenceHelp || 'Coche les occurrences auxquelles tu participeras.';
                }
                occurrenceFieldset.appendChild(occurrenceHelpNode);
            }

            if (noteContainer) {
                form.insertBefore(occurrenceFieldset, noteContainer);
            } else if (submitButton) {
                form.insertBefore(occurrenceFieldset, submitButton);
            } else {
                form.appendChild(occurrenceFieldset);
            }

            if (requiresOccurrenceSelection && occurrenceHelpNode) {
                occurrenceHelpNode.classList.toggle('is-warning', collectSelectedOccurrences().length === 0);
            }

            updateSubmitAvailability();
            notifyOccurrenceSelectionChanged('legacy');
        }

        form.addEventListener('mj-member:event-single-occurrences', function (event) {
            var detail = event && event.detail ? event.detail : {};
            var origin = typeof detail.origin === 'string' ? detail.origin : 'external';
            var values = sanitizeOccurrenceValues(detail.occurrences);

            writeOccurrenceStore(values);

            if (occurrenceCheckboxes.length) {
                var lookup = Object.create(null);
                for (var i = 0; i < values.length; i += 1) {
                    lookup[values[i]] = true;
                }

                for (var idx = 0; idx < occurrenceCheckboxes.length; idx += 1) {
                    var checkbox = occurrenceCheckboxes[idx];
                    if (!checkbox) {
                        continue;
                    }
                    var normalizedValue = normalizeOccurrenceValue(checkbox.value);
                    var shouldCheck = !!lookup[normalizedValue];
                    if (!checkbox.disabled) {
                        checkbox.checked = shouldCheck;
                    }
                    var occurrenceItem = checkbox.closest('.mj-member-events__signup-calendar-occurrence, .mj-member-events__signup-occurrence-item');
                    if (occurrenceItem) {
                        occurrenceItem.classList.toggle('is-selected', shouldCheck && !checkbox.disabled);
                    }
                    var dayNode = checkbox.closest('.mj-member-events__signup-calendar-day');
                    syncInlineCalendarDay(dayNode);
                }
            }

            if (occurrenceHelpNode) {
                occurrenceHelpNode.classList.toggle('is-warning', requiresOccurrenceSelection && values.length === 0);
            }

            if (origin !== 'legacy') {
                updateSubmitAvailability();
            }
        });

        function setSubmitting(isSubmitting) {
            form.dataset.submitting = isSubmitting ? '1' : '0';
            if (!submitButton) {
                return;
            }

            if (isSubmitting) {
                if (!submitButton.dataset.originalLabel) {
                    submitButton.dataset.originalLabel = submitButton.textContent || '';
                }
                submitButton.disabled = true;
                if (strings.loading) {
                    submitButton.textContent = strings.loading;
                }
            } else {
                submitButton.disabled = false;
                var restoredLabel = submitButton.dataset.originalLabel || originalSubmitLabel;
                if (restoredLabel) {
                    submitButton.textContent = restoredLabel;
                }
                updateSubmitAvailability();
            }
        }

        function ensureSelection() {
            var checked = form.querySelector('input[name="participant"]:checked:not([disabled])');
            if (checked) {
                return;
            }
            var firstAvailable = form.querySelector('input[name="participant"]:not([disabled])');
            if (firstAvailable) {
                firstAvailable.checked = true;
            }
        }

        function updateMembersState() {
            var availableCount = 0;
            Array.prototype.forEach.call(radios, function (radio) {
                if (!radio.disabled) {
                    availableCount += 1;
                }
            });

            if (membersEmptyNode) {
                membersEmptyNode.hidden = availableCount > 0;
            }

            ensureSelection();
            updateSubmitAvailability(availableCount);
        }

        function markParticipantAsRegistered(radio, responseData) {
            if (!radio) {
                return;
            }

            radio.checked = false;
            radio.disabled = true;

            var item = radio.closest('[data-mj-event-member]');
            if (!item) {
                return;
            }

            item.classList.add('is-registered');
            if (responseData && responseData.registration_id) {
                item.setAttribute('data-registration-id', String(responseData.registration_id));
            }

            var statusNode = item.querySelector('[data-role="status"]');
            if (statusNode) {
                var statusText = '';
                if (responseData && responseData.is_waitlist) {
                    statusText = strings.waitlistStatus || "En liste d'attente";
                    item.setAttribute('data-status-key', 'liste_attente');
                } else {
                    statusText = strings.pendingStatus || strings.registered || strings.success || 'Inscription envoyée';
                    item.setAttribute('data-status-key', 'en_attente');
                }
                statusNode.textContent = statusText;
            }
        }

        Array.prototype.forEach.call(radios, function (radio) {
            radio.addEventListener('change', function () {
                if (radio.disabled) {
                    return;
                }
                setFeedback(feedbackNode, '', false);
                updateSubmitAvailability();
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (form.dataset.submitting === '1') {
                return;
            }

            if (!ajaxUrl || !nonce) {
                setFeedback(feedbackNode, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                return;
            }

            if (!eventId) {
                setFeedback(feedbackNode, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                return;
            }

            var selected = form.querySelector('input[name="participant"]:checked');
            if (!selected || selected.disabled) {
                setFeedback(feedbackNode, strings.selectParticipant || 'Merci de sélectionner un participant.', true);
                return;
            }

            var memberId = selected.value || '';
            if (!memberId) {
                setFeedback(feedbackNode, strings.selectParticipant || 'Merci de sélectionner un participant.', true);
                return;
            }

            setSubmitting(true);
            setFeedback(feedbackNode, '', false);

            var payload = new window.FormData();
            payload.append('action', 'mj_member_register_event');
            payload.append('nonce', nonce);
            payload.append('event_id', String(eventId));
            payload.append('member_id', memberId);

            if (noteField) {
                var noteValue = noteField.value || '';
                if (noteValue && noteMaxLength > 0 && noteValue.length > noteMaxLength) {
                    noteValue = noteValue.slice(0, noteMaxLength);
                }
                payload.append('note', noteValue);
            } else {
                payload.append('note', '');
            }

            var selectedOccurrences = collectSelectedOccurrences();
            if (requiresOccurrenceSelection && selectedOccurrences.length === 0) {
                setFeedback(feedbackNode, strings.occurrenceMissing || 'Merci de sélectionner au moins une occurrence.', true);
                if (occurrenceHelpNode) {
                    occurrenceHelpNode.classList.add('is-warning');
                }
                setSubmitting(false);
                updateSubmitAvailability();
                return;
            }

            if (selectedOccurrences.length) {
                try {
                    payload.append('occurrences', JSON.stringify(selectedOccurrences));
                } catch (error) {
                    // silently ignore JSON issues
                }
            }

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload,
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, status: response.status, json: json };
                }).catch(function () {
                    return { ok: response.ok, status: response.status, json: null };
                });
            }).then(function (result) {
                if (!result.ok || !result.json) {
                    var messageError = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json && result.json.data && result.json.data.message) {
                        messageError = result.json.data.message;
                    }
                    setFeedback(feedbackNode, messageError, true);
                    return null;
                }

                if (!result.json.success) {
                    var messageFail = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json.data && result.json.data.message) {
                        messageFail = result.json.data.message;
                    }
                    setFeedback(feedbackNode, messageFail, true);
                    return null;
                }

                return result.json.data || {};
            }).then(function (data) {
                if (!data) {
                    return;
                }

                var serverMessage = data.message || strings.success || 'Inscription enregistrée !';
                var paymentInfo = data.payment || null;
                var feedbackMessage = serverMessage;

                if (paymentInfo && paymentInfo.checkout_url) {
                    feedbackMessage = serverMessage + ' ' + (strings.paymentFallback || "Si la page de paiement ne s'ouvre pas, copiez ce lien : ") + ' ' + paymentInfo.checkout_url;
                }

                setFeedback(feedbackNode, feedbackMessage, !!data.payment_error);

                if (noteField) {
                    noteField.value = '';
                }

                markParticipantAsRegistered(selected, data);
                resetOccurrenceSelection();
                updateMembersState();

                refreshReservationPanel({ silent: true });

                if (paymentInfo && paymentInfo.checkout_url) {
                    var opened = window.open(paymentInfo.checkout_url, '_blank', 'noopener,noreferrer');
                    if (!opened) {
                        window.location.href = paymentInfo.checkout_url;
                    }
                }
            }).catch(function (error) {
                var errorMessage = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                if (error && error.message) {
                    errorMessage = error.message;
                }
                setFeedback(feedbackNode, errorMessage, true);
            }).finally(function () {
                setSubmitting(false);
                updateMembersState();
                updateSubmitAvailability();
            });
        });

        buildOccurrenceSelector();
        updateMembersState();
    }

    function initEventRegistrationForms() {
        if (typeof document === 'undefined') {
            return;
        }

        var forms = document.querySelectorAll('[data-mj-event-registration]');
        toArray(forms).forEach(function (form) {
            setupInlineRegistrationForm(form);
        });
    }

    function parsePayload(button) {
        var raw = button.getAttribute('data-registration');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function recomputePayloadState(payload) {
        if (!payload) {
            return { total: 0, available: 0, ineligible: 0 };
        }

        var participants = Array.isArray(payload.participants) ? payload.participants : [];
        var available = 0;
        var total = 0;
        var ineligible = 0;

        for (var i = 0; i < participants.length; i++) {
            var participant = participants[i];
            if (!participant) {
                continue;
            }
            total += 1;

            var participantEligible = true;
            if (Object.prototype.hasOwnProperty.call(participant, 'eligible')) {
                participantEligible = !!participant.eligible;
            } else if (Object.prototype.hasOwnProperty.call(participant, 'isEligible')) {
                participantEligible = participant.isEligible !== 0 && participant.isEligible !== false;
            }

            if (!participantEligible) {
                ineligible += 1;
            }

            if (!participant.isRegistered && participantEligible) {
                available += 1;
            }
        }

        payload.hasParticipants = total > 0;
        payload.hasAvailableParticipants = available > 0;
        payload.hasIneligibleParticipants = ineligible > 0;
        payload.ineligibleCount = ineligible;
        payload.allRegistered = total > 0 && available === 0;

        return { total: total, available: available, ineligible: ineligible };
    }

    function normalizeOccurrenceValue(value) {
        if (typeof value === 'string') {
            return value.trim();
        }
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).trim();
    }

    function resolveFormatterLocale() {
        if (strings && typeof strings.locale === 'string' && strings.locale) {
            var prepared = strings.locale.replace(/_/g, '-');
            return prepared;
        }
        if (typeof document !== 'undefined' && document.documentElement && document.documentElement.lang) {
            return document.documentElement.lang;
        }
        return 'fr-FR';
    }

    function getOccurrenceTimeKey(entry) {
        if (!entry) {
            return '';
        }

        var timestamp = entry.timestamp !== undefined ? parseInt(entry.timestamp, 10) : NaN;
        if (!Number.isNaN(timestamp) && timestamp > 0) {
            var date = new Date(timestamp * 1000);
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            return hours + ':' + minutes;
        }

        var start = entry.start ? String(entry.start) : '';
        if (start) {
            var match = start.match(/(\d{1,2})[:h](\d{2})/);
            if (match) {
                return String(match[1]).padStart(2, '0') + ':' + match[2];
            }
        }

        return '';
    }

    function formatOccurrenceTime(entry) {
        var timestamp = entry.timestamp !== undefined ? parseInt(entry.timestamp, 10) : NaN;
        if (!Number.isNaN(timestamp) && timestamp > 0) {
            try {
                var locale = resolveFormatterLocale();
                var formatter = new Intl.DateTimeFormat(locale, {
                    hour: '2-digit',
                    minute: '2-digit',
                });
                return formatter.format(new Date(timestamp * 1000));
            } catch (error) {
                // ignore and fall back below
            }
        }

        var start = entry.start ? String(entry.start) : '';
        if (start) {
            var match = start.match(/(\d{1,2})[:h](\d{2})/);
            if (match) {
                return String(match[1]).padStart(2, '0') + ':' + match[2];
            }
        }

        return '';
    }

    function formatOccurrenceLabel(entry, includeTime) {
        if (!entry) {
            return '';
        }

        var timestamp = entry.timestamp !== undefined ? parseInt(entry.timestamp, 10) : NaN;
        if (!Number.isNaN(timestamp) && timestamp > 0) {
            try {
                var locale = resolveFormatterLocale();
                var formatter = new Intl.DateTimeFormat(locale, {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                });
                var formatted = formatter.format(new Date(timestamp * 1000));
                if (formatted && formatted.charAt) {
                    formatted = formatted.charAt(0).toUpperCase() + formatted.slice(1);
                }
                if (includeTime) {
                    var timeLabel = formatOccurrenceTime(entry);
                    if (timeLabel) {
                        formatted += ' · ' + timeLabel;
                    }
                }
                return formatted;
            } catch (error) {
                // ignore and fall back to raw label
            }
        }

        var raw = entry.label ? String(entry.label) : '';
        if (raw) {
            var cleaned = raw.replace(/\s?(\d{1,2}[:h]\d{2})$/u, '').trim();
            if (cleaned) {
                if (includeTime) {
                    var timeFromRaw = formatOccurrenceTime(entry);
                    if (timeFromRaw) {
                        cleaned += ' · ' + timeFromRaw;
                    }
                }
                return cleaned;
            }
        }
        var fallback = entry.start ? String(entry.start) : raw;
        if (includeTime) {
            var timeFallback = formatOccurrenceTime(entry);
            if (timeFallback) {
                fallback += ' · ' + timeFallback;
            }
        }
        return fallback;
    }

    function resolveButtonMessages(button) {
        var baseLabel = defaultCtaLabel;
        var registeredLabel = registeredCtaLabel;

        if (button) {
            var customLabel = button.getAttribute('data-cta-label');
            if (customLabel) {
                baseLabel = customLabel;
            }

            var customRegistered = button.getAttribute('data-cta-registered-label');
            if (customRegistered) {
                registeredLabel = customRegistered;
            }
        }

        return {
            defaultLabel: baseLabel,
            registeredLabel: registeredLabel,
        };
    }

    function updateButtonState(button, payload) {
        if (!button) {
            return;
        }

        var labels = resolveButtonMessages(button);
        var label = labels.defaultLabel;
        var registeredLabel = labels.registeredLabel;

        if (payload && payload.hasAvailableParticipants === false) {
            button.classList.add('is-registered');
            button.textContent = registeredLabel;
            return;
        }

        button.classList.remove('is-registered');
        button.textContent = label;
    }

    function buildSignup(card, button, signup, feedback, payload) {
        var isPersistentSignup = !!(signup && signup.dataset && signup.dataset.persistent === '1');

        if (activeSignup && activeSignup !== signup && !isPersistentSignup) {
            closeSignup(activeSignup);
        }

        signup.removeAttribute('hidden');
        signup.classList.add('is-open');
        signup.innerHTML = '';
        activeSignup = signup;

        recomputePayloadState(payload);
        updateButtonState(button, payload);

        var preferredParticipantId = '';
        if (payload && Object.prototype.hasOwnProperty.call(payload, 'preselectParticipantId')) {
            preferredParticipantId = payload.preselectParticipantId !== null && payload.preselectParticipantId !== undefined
                ? String(payload.preselectParticipantId)
                : '';
            try {
                delete payload.preselectParticipantId;
            } catch (error) {
                payload.preselectParticipantId = undefined;
            }
        }

        var scheduleMode = (payload && typeof payload.scheduleMode === 'string') ? payload.scheduleMode : 'fixed';
        var isRecurringEvent = scheduleMode === 'recurring';
        var isFixedSchedule = scheduleMode === 'fixed' || scheduleMode === 'range';

        var participantStates = Object.create(null);
        var participantIndex = Object.create(null);

        var occurrenceSummaryText = '';
        if (button) {
            var rawSummary = button.getAttribute('data-occurrence-summary');
            if (rawSummary) {
                occurrenceSummaryText = rawSummary.trim();
            }
        }

        var form = document.createElement('form');
        form.className = 'mj-member-events__signup-form';
        form.setAttribute('data-event-id', payload.eventId || '');

        var inlineCta = null;
        var inlineCtaRegisterLabel = strings.confirmInline || strings.confirm || "Confirmer l'inscription";
        var inlineCtaUpdateLabel = strings.updateInline || strings.updateOccurrences || 'Mettre à jour';
        var inlineCtaLoadingLabel = strings.loading || 'En cours...';

        function setInlineCtaHidden(hidden) {
            if (!inlineCta) {
                return;
            }
            inlineCta.hidden = !!hidden;
        }

        function setInlineCtaDisabled(disabled) {
            if (!inlineCta) {
                return;
            }
            if (disabled) {
                inlineCta.classList.add('is-disabled');
                inlineCta.setAttribute('aria-disabled', 'true');
            } else {
                inlineCta.classList.remove('is-disabled');
                inlineCta.removeAttribute('aria-disabled');
            }
        }

        function setInlineCtaLabel(label) {
            if (!inlineCta) {
                return;
            }
            inlineCta.textContent = label;
        }

        function triggerInlineSubmit() {
            if (form.dataset.submitting === '1') {
                return;
            }
            if (!inlineCta || inlineCta.classList.contains('is-disabled')) {
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        }

        function syncCalendarDayState(dayNode) {
            if (!dayNode) {
                return;
            }
            var hasHighlight = false;
            var occurrenceItems = dayNode.querySelectorAll('.mj-member-events__signup-calendar-occurrence');
            Array.prototype.forEach.call(occurrenceItems, function (item) {
                if (item.classList.contains('is-assigned') || item.classList.contains('is-selected')) {
                    hasHighlight = true;
                }
            });
            if (hasHighlight) {
                dayNode.classList.add('has-selection');
            } else {
                dayNode.classList.remove('has-selection');
            }
        }

        var title = document.createElement('p');
        title.className = 'mj-member-events__signup-title';
        title.textContent = strings.chooseParticipant || 'Choisissez le participant';
        form.appendChild(title);

        var participants = Array.isArray(payload.participants) ? payload.participants : [];
        var participantsCount = participants.length;
        var availableParticipantsCount = 0;
        var hasIneligible = false;
        var hasUnregistered = false;
        var firstSelectable = null;
        var preferredRadio = null;
        var infoMessage = null;
        var noteField = null;
        var noteWrapper = null;
        var noteMaxLength = parseInt(payload.noteMaxLength, 10);
        if (!noteMaxLength || noteMaxLength <= 0) {
            noteMaxLength = 400;
        }
        var occurrences = Array.isArray(payload.occurrences) ? payload.occurrences : [];
        var allowOccurrenceSelection = true;
        if (payload && Object.prototype.hasOwnProperty.call(payload, 'allowOccurrenceSelection')) {
            allowOccurrenceSelection = !!payload.allowOccurrenceSelection;
        } else if (payload && typeof payload.occurrenceSelectionMode === 'string') {
            allowOccurrenceSelection = payload.occurrenceSelectionMode !== 'all_occurrences';
        }
        var assignments = payload && payload.assignments && typeof payload.assignments === 'object' ? payload.assignments : {};
        var selectedOccurrenceMap = Object.create(null);
        if (assignments.mode === 'custom' && Array.isArray(assignments.occurrences)) {
            for (var occIdx = 0; occIdx < assignments.occurrences.length; occIdx++) {
                var assignmentValue = normalizeOccurrenceValue(assignments.occurrences[occIdx]);
                if (assignmentValue) {
                    selectedOccurrenceMap[assignmentValue] = true;
                }
            }
        }
        var hasCustomPreselection = assignments.mode === 'custom' && Object.keys(selectedOccurrenceMap).length > 0;
        var hasEnabledOccurrence = false;
        var occurrenceFieldset = null;
        var occurrenceColumns = null;
        var occurrenceList = null;
        var occurrenceHelp = null;
        var occurrenceRegisteredSection = null;
        var occurrenceAvailableSection = null;
        var occurrenceControls = [];
        var syncOccurrenceSections = function () {};
        var formFeedback = null;

        if (!participants.length) {
            var empty = document.createElement('p');
            empty.className = 'mj-member-events__signup-empty';
            empty.textContent = strings.noParticipant || "Aucun profil disponible pour l'instant.";
            form.appendChild(empty);
        } else {
            var list = document.createElement('ul');
            list.className = 'mj-member-events__signup-options';

            for (var i = 0; i < participants.length; i++) {
                var entry = participants[i] || {};
                var participantId = entry.id !== undefined ? String(entry.id) : '';
                if (participantId === '') {
                    continue;
                }

                participantIndex[participantId] = entry;
                var entryAssignments = entry.occurrenceAssignments && typeof entry.occurrenceAssignments === 'object'
                    ? entry.occurrenceAssignments
                    : { mode: 'all', occurrences: [] };
                participantStates[participantId] = {
                    isRegistered: !!entry.isRegistered,
                    assignments: entryAssignments,
                    registrationId: entry.registrationId ? parseInt(entry.registrationId, 10) || 0 : 0,
                };

                var isRegistered = !!entry.isRegistered;
                var registrationId = parseInt(entry.registrationId, 10);
                if (Number.isNaN(registrationId)) {
                    registrationId = 0;
                }

                var isEligible = true;
                if (Object.prototype.hasOwnProperty.call(entry, 'eligible')) {
                    isEligible = !!entry.eligible;
                } else if (Object.prototype.hasOwnProperty.call(entry, 'isEligible')) {
                    isEligible = entry.isEligible !== 0 && entry.isEligible !== false;
                }

                var ineligibleReasons = [];
                if (Array.isArray(entry.ineligibleReasons) && entry.ineligibleReasons.length) {
                    ineligibleReasons = entry.ineligibleReasons.slice();
                } else if (Array.isArray(entry.ineligible_reasons) && entry.ineligible_reasons.length) {
                    ineligibleReasons = entry.ineligible_reasons.slice();
                }
                ineligibleReasons = ineligibleReasons.map(function (reason) {
                    return reason ? String(reason) : '';
                }).filter(function (reason) {
                    return reason !== '';
                });

                if (!isRegistered) {
                    hasUnregistered = true;
                }

                var option = document.createElement('li');
                option.className = 'mj-member-events__signup-option';
                option.dataset.participantId = participantId;
                if (isRegistered) {
                    option.classList.add('is-registered');
                }
                if (!isEligible) {
                    option.classList.add('is-ineligible');
                    hasIneligible = true;
                }

                var label = document.createElement('label');
                label.className = 'mj-member-events__signup-label';

                var input = document.createElement('input');
                input.type = 'radio';
                input.name = 'participant';
                input.value = participantId;
                input.required = true;
                input.disabled = !isEligible;

                if (!isRegistered && isEligible) {
                    availableParticipantsCount += 1;
                    if (!firstSelectable) {
                        firstSelectable = input;
                    }
                }

                if (preferredParticipantId && participantId === preferredParticipantId && !input.disabled) {
                    preferredRadio = input;
                }

                if (!firstSelectable && !preferredRadio && isRegistered && !availableParticipantsCount && !input.disabled) {
                    firstSelectable = input;
                }

                var span = document.createElement('span');
                span.className = 'mj-member-events__signup-name';
                span.textContent = entry.label || ('#' + participantId);

                label.appendChild(input);
                label.appendChild(span);

                var statusText = '';
                var statusClassName = 'mj-member-events__signup-status';
                var currentStatus = entry.registrationStatus ? String(entry.registrationStatus) : '';

                if (isRegistered) {
                    statusText = strings.alreadyRegistered || 'Déjà inscrit';
                    if (currentStatus === 'liste_attente') {
                        statusText = strings.waitlistStatus || 'En liste d\'attente';
                    } else if (currentStatus === 'en_attente') {
                        statusText = strings.pendingStatus || 'En attente';
                    } else if (currentStatus === 'valide') {
                        statusText = strings.confirmedStatus || 'Confirmé';
                    }
                }

                if (!isEligible) {
                    statusText = strings.ineligibleStatus || 'Conditions non respectées';
                    statusClassName += ' is-ineligible';
                }

                if (statusText) {
                    var status = document.createElement('span');
                    status.className = statusClassName;
                    status.textContent = statusText;
                    label.appendChild(status);
                }

                option.appendChild(label);

                if (!isEligible && ineligibleReasons.length) {
                    var reasonsList = document.createElement('ul');
                    reasonsList.className = 'mj-member-events__signup-reasons';
                    for (var reasonIndex = 0; reasonIndex < ineligibleReasons.length; reasonIndex++) {
                        var reasonItem = document.createElement('li');
                        reasonItem.textContent = ineligibleReasons[reasonIndex];
                        reasonsList.appendChild(reasonItem);
                    }
                    option.appendChild(reasonsList);
                }

                list.appendChild(option);
            }

            var radioToCheck = preferredRadio || firstSelectable;
            if (radioToCheck) {
                radioToCheck.checked = true;
            }

            form.appendChild(list);
        }

        if (occurrences.length) {
            if (!allowOccurrenceSelection) {
                if (occurrenceSummaryText) {
                    var occurrenceSummaryMessage = document.createElement('p');
                    occurrenceSummaryMessage.className = 'mj-member-events__signup-occurrence-summary';
                    occurrenceSummaryMessage.textContent = occurrenceSummaryText;
                    form.appendChild(occurrenceSummaryMessage);
                }

                var autoAssignment = document.createElement('p');
                autoAssignment.className = 'mj-member-events__signup-occurrence-summary';
                autoAssignment.textContent = strings.occurrenceAutoAssigned || 'Toutes les occurrences sont incluses automatiquement.';
                form.appendChild(autoAssignment);
            } else {
                var timeKeySet = Object.create(null);
                var activeOccurrenceCount = 0;
                for (var occIdxLoop = 0; occIdxLoop < occurrences.length; occIdxLoop++) {
                    var occurrenceCandidate = occurrences[occIdxLoop];
                    if (!occurrenceCandidate || occurrenceCandidate.isPast) {
                        continue;
                    }
                    activeOccurrenceCount++;
                    var keyCandidate = getOccurrenceTimeKey(occurrenceCandidate);
                    if (keyCandidate) {
                        timeKeySet[keyCandidate] = true;
                    }
                }
                var includeTime = false;
                if (activeOccurrenceCount <= 1) {
                    includeTime = true;
                } else {
                    var timeKeyCount = 0;
                    for (var timeKey in timeKeySet) {
                        if (Object.prototype.hasOwnProperty.call(timeKeySet, timeKey)) {
                            timeKeyCount++;
                            if (timeKeyCount > 1) {
                                includeTime = true;
                                break;
                            }
                        }
                    }
                }

                occurrenceFieldset = document.createElement('fieldset');
                occurrenceFieldset.className = 'mj-member-events__signup-occurrences';

                var occurrenceLegend = document.createElement('legend');
                occurrenceLegend.textContent = strings.occurrenceLegend || 'Quelles dates ?';
                occurrenceFieldset.appendChild(occurrenceLegend);

                if (occurrenceSummaryText) {
                    var occurrenceSummaryNode = document.createElement('p');
                    occurrenceSummaryNode.className = 'mj-member-events__signup-occurrence-summary';
                    occurrenceSummaryNode.textContent = occurrenceSummaryText;
                    occurrenceFieldset.appendChild(occurrenceSummaryNode);
                }

                function parseOccurrenceDate(entry) {
                    if (!entry) {
                        return null;
                    }
                    var parsedTimestamp = entry.timestamp !== undefined ? parseInt(entry.timestamp, 10) : NaN;
                    if (!Number.isNaN(parsedTimestamp) && parsedTimestamp > 0) {
                        return new Date(parsedTimestamp * 1000);
                    }
                    var startValue = entry.start ? String(entry.start) : '';
                    if (startValue) {
                        var normalized = startValue.replace(' ', 'T');
                        var normalizedDate = new Date(normalized);
                        if (!Number.isNaN(normalizedDate.getTime())) {
                            return normalizedDate;
                        }
                        var fallbackDate = new Date(startValue);
                        if (!Number.isNaN(fallbackDate.getTime())) {
                            return fallbackDate;
                        }
                    }
                    return null;
                }

                function buildOccurrenceCalendar() {
                    var normalized = [];
                    var seenValues = Object.create(null);

                    for (var idx = 0; idx < occurrences.length; idx++) {
                        var entry = occurrences[idx];
                        if (!entry) {
                            continue;
                        }

                        var occurrenceValue = normalizeOccurrenceValue(entry.start);
                        if (!occurrenceValue || seenValues[occurrenceValue]) {
                            continue;
                        }

                        var occurrenceDate = parseOccurrenceDate(entry);
                        if (!occurrenceDate) {
                            return false;
                        }

                        seenValues[occurrenceValue] = true;
                        normalized.push({
                            value: occurrenceValue,
                            entry: entry,
                            date: occurrenceDate,
                        });
                    }

                    if (!normalized.length) {
                        return false;
                    }

                    normalized.sort(function (a, b) {
                        return a.date.getTime() - b.date.getTime();
                    });

                    var monthMap = Object.create(null);
                    var monthOrder = [];

                    normalized.forEach(function (item) {
                        var year = item.date.getFullYear();
                        var monthIndexValue = item.date.getMonth();
                        var dayIndexValue = item.date.getDate();
                        var monthKey = year + '-' + String(monthIndexValue + 1).padStart(2, '0');

                        if (!monthMap[monthKey]) {
                            monthMap[monthKey] = {
                                year: year,
                                month: monthIndexValue,
                                days: Object.create(null),
                            };
                            monthOrder.push(monthKey);
                        }

                        var monthData = monthMap[monthKey];
                        if (!monthData.days[dayIndexValue]) {
                            monthData.days[dayIndexValue] = {
                                iso: year + '-' + String(monthIndexValue + 1).padStart(2, '0') + '-' + String(dayIndexValue).padStart(2, '0'),
                                occurrences: [],
                            };
                        }
                        monthData.days[dayIndexValue].occurrences.push(item);
                    });

                    var calendarRoot = document.createElement('div');
                    calendarRoot.className = 'mj-member-events__signup-calendar';

                    var locale = resolveFormatterLocale();
                    var monthFormatter = null;
                    var weekdayFormatter = null;
                    try {
                        monthFormatter = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
                    } catch (error) {
                        monthFormatter = null;
                    }
                    try {
                        weekdayFormatter = new Intl.DateTimeFormat(locale, { weekday: 'short' });
                    } catch (error) {
                        weekdayFormatter = null;
                    }

                    var weekdayLabels = [];
                    if (weekdayFormatter) {
                        var referenceDate = new Date(2020, 5, 1);
                        for (var offset = 0; offset < 7; offset++) {
                            var dateCandidate = new Date(referenceDate.getTime());
                            dateCandidate.setDate(referenceDate.getDate() + offset);
                            var weekdayLabel = weekdayFormatter.format(dateCandidate) || '';
                            weekdayLabel = weekdayLabel.replace(/\.$/, '');
                            if (weekdayLabel.length > 3) {
                                weekdayLabel = weekdayLabel.slice(0, 3);
                            }
                            if (weekdayLabel) {
                                weekdayLabel = weekdayLabel.charAt(0).toUpperCase() + weekdayLabel.slice(1).toLowerCase();
                            }
                            weekdayLabels.push(weekdayLabel);
                        }
                    } else {
                        weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                    }

                    var builtCount = 0;

                    monthOrder.forEach(function (monthKey) {
                        var monthData = monthMap[monthKey];
                        if (!monthData) {
                            return;
                        }

                        var monthNode = document.createElement('section');
                        monthNode.className = 'mj-member-events__signup-calendar-month';

                        var monthTitle = document.createElement('p');
                        monthTitle.className = 'mj-member-events__signup-calendar-title';
                        var monthLabel = monthFormatter ? monthFormatter.format(new Date(monthData.year, monthData.month, 1)) : monthKey.replace('-', ' / ');
                        if (monthLabel && monthLabel.charAt) {
                            monthLabel = monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);
                        }
                        monthTitle.textContent = monthLabel;
                        monthNode.appendChild(monthTitle);

                        var weekdayRow = document.createElement('ol');
                        weekdayRow.className = 'mj-member-events__signup-calendar-weekdays';
                        for (var wd = 0; wd < 7; wd++) {
                            var weekdayItem = document.createElement('li');
                            weekdayItem.className = 'mj-member-events__signup-calendar-weekday';
                            weekdayItem.textContent = weekdayLabels[wd] || '';
                            weekdayRow.appendChild(weekdayItem);
                        }
                        monthNode.appendChild(weekdayRow);

                        var dayGrid = document.createElement('ol');
                        dayGrid.className = 'mj-member-events__signup-calendar-grid';

                        var firstDay = new Date(monthData.year, monthData.month, 1);
                        var daysInMonth = new Date(monthData.year, monthData.month + 1, 0).getDate();
                        var startOffset = (firstDay.getDay() + 6) % 7;
                        for (var blank = 0; blank < startOffset; blank++) {
                            var emptyCell = document.createElement('li');
                            emptyCell.className = 'mj-member-events__signup-calendar-day is-empty';
                            emptyCell.setAttribute('aria-hidden', 'true');
                            dayGrid.appendChild(emptyCell);
                        }

                        for (var dayIndex = 1; dayIndex <= daysInMonth; dayIndex++) {
                            var dayEntry = monthData.days[dayIndex] || null;
                            var dayCell = document.createElement('li');
                            dayCell.className = 'mj-member-events__signup-calendar-day';
                            dayCell.dataset.date = monthData.year + '-' + String(monthData.month + 1).padStart(2, '0') + '-' + String(dayIndex).padStart(2, '0');

                            var weekdayIndex = (startOffset + dayIndex - 1) % 7;
                            if (weekdayIndex >= 5) {
                                dayCell.classList.add('is-weekend');
                            }

                            var dayNumber = document.createElement('span');
                            dayNumber.className = 'mj-member-events__signup-calendar-day-number';
                            dayNumber.textContent = String(dayIndex);
                            dayCell.appendChild(dayNumber);

                            if (dayEntry && dayEntry.occurrences.length) {
                                var dayList = document.createElement('ul');
                                dayList.className = 'mj-member-events__signup-calendar-occurrences';

                                dayEntry.occurrences.forEach(function (itemData) {
                                    var occurrenceEntry = itemData.entry;
                                    var occurrenceValue = itemData.value;
                                    var occurrenceIsPast = !!occurrenceEntry.isPast;

                                    var occurrenceItem = document.createElement('li');
                                    occurrenceItem.className = 'mj-member-events__signup-calendar-occurrence';
                                    if (occurrenceIsPast) {
                                        occurrenceItem.classList.add('is-past');
                                    } else {
                                        hasEnabledOccurrence = true;
                                    }

                                    var occurrenceLabelNode = document.createElement('label');
                                    occurrenceLabelNode.className = 'mj-member-events__signup-calendar-checkbox';

                                    var occurrenceInput = document.createElement('input');
                                    occurrenceInput.type = 'checkbox';
                                    occurrenceInput.name = 'occurrence[]';
                                    occurrenceInput.value = occurrenceValue;
                                    occurrenceInput.checked = false;
                                    if (occurrenceIsPast) {
                                        occurrenceInput.disabled = true;
                                    }

                                    occurrenceLabelNode.appendChild(occurrenceInput);

                                    var pill = document.createElement('span');
                                    pill.className = 'mj-member-events__signup-calendar-pill';
                                    var timeLabel = formatOccurrenceTime(occurrenceEntry);
                                    if (timeLabel) {
                                        pill.textContent = timeLabel;
                                    } else {
                                        pill.textContent = strings.occurrenceCalendarAllDay || 'Toute la journée';
                                    }
                                    occurrenceLabelNode.appendChild(pill);

                                    occurrenceItem.appendChild(occurrenceLabelNode);

                                    var occurrenceActions = document.createElement('div');
                                    occurrenceActions.className = 'mj-member-events__signup-occurrence-actions';
                                    occurrenceActions.hidden = true;

                                    var occurrenceUnregister = document.createElement('button');
                                    occurrenceUnregister.type = 'button';
                                    occurrenceUnregister.className = 'mj-member-events__signup-occurrence-toggle';
                                    occurrenceUnregister.textContent = strings.unregisterOccurrence || strings.unregister || 'Se désinscrire';
                                    occurrenceUnregister.dataset.occurrenceValue = occurrenceValue;
                                    occurrenceUnregister.hidden = true;

                                    occurrenceActions.appendChild(occurrenceUnregister);
                                    occurrenceItem.appendChild(occurrenceActions);

                                    if (hasCustomPreselection && selectedOccurrenceMap[occurrenceValue]) {
                                        occurrenceInput.checked = true;
                                        occurrenceItem.classList.add('is-selected');
                                    }

                                    (function (inputNode, itemNode, cellNode) {
                                        inputNode.addEventListener('change', function () {
                                            if (inputNode.checked) {
                                                itemNode.classList.add('is-selected');
                                            } else {
                                                itemNode.classList.remove('is-selected');
                                            }
                                            syncCalendarDayState(cellNode);
                                            updateInlineCtaAvailability();
                                        });
                                    })(occurrenceInput, occurrenceItem, dayCell);

                                    dayList.appendChild(occurrenceItem);

                                    occurrenceControls.push({
                                        element: occurrenceItem,
                                        value: occurrenceValue,
                                        isPast: occurrenceIsPast,
                                        checkbox: occurrenceInput,
                                        actions: occurrenceActions,
                                        unregister: occurrenceUnregister,
                                        label: occurrenceLabelNode,
                                        registeredList: dayList,
                                        availableList: dayList,
                                        registeredEmpty: null,
                                        availableEmpty: null,
                                        registeredSection: null,
                                        availableSection: null,
                                    });

                                    syncCalendarDayState(dayCell);
                                    builtCount++;
                                });

                                dayCell.appendChild(dayList);
                            } else {
                                dayCell.classList.add('is-disabled');
                            }

                            dayGrid.appendChild(dayCell);
                        }

                        monthNode.appendChild(dayGrid);
                        calendarRoot.appendChild(monthNode);
                    });

                    if (!builtCount) {
                        return false;
                    }

                    occurrenceFieldset.appendChild(calendarRoot);

                    occurrenceHelp = document.createElement('p');
                    occurrenceHelp.className = 'mj-member-events__signup-occurrence-help';
                    if (isRecurringEvent) {
                        occurrenceHelp.textContent = strings.occurrenceHelpRecurring || 'Sélectionnez de nouvelles dates ou retirez une réservation.';
                    } else {
                        occurrenceHelp.textContent = strings.occurrenceHelp || 'Cochez les occurrences auxquelles vous participerez.';
                    }
                    occurrenceFieldset.appendChild(occurrenceHelp);

                    return true;
                }

                function createOccurrenceSection(title, modifier) {
                    var section = document.createElement('section');
                    section.className = 'mj-member-events__signup-occurrence-section';
                    if (modifier) {
                        section.className += ' is-' + modifier;
                    }

                    var heading = document.createElement('p');
                    heading.className = 'mj-member-events__signup-occurrence-heading';
                    heading.textContent = title;
                    section.appendChild(heading);

                    var listNode = document.createElement('ul');
                    listNode.className = 'mj-member-events__signup-occurrence-list';
                    section.appendChild(listNode);

                    var emptyState = document.createElement('p');
                    emptyState.className = 'mj-member-events__signup-occurrence-empty';
                    emptyState.textContent = modifier === 'registered'
                        ? (strings.occurrenceRegisteredEmpty || 'Aucune réservation active.')
                        : (strings.occurrenceAvailableEmpty || 'Toutes les dates sont déjà réservées.');
                    emptyState.hidden = true;
                    section.appendChild(emptyState);

                    return { section: section, list: listNode, empty: emptyState };
                }

                function buildOccurrenceListLayout() {
                    var initialCount = occurrenceControls.length;
                    var occurrenceSeen = Object.create(null);

                    occurrenceColumns = null;
                    occurrenceRegisteredSection = null;
                    occurrenceAvailableSection = null;
                    occurrenceList = null;

                    if (!isRecurringEvent) {
                        occurrenceList = document.createElement('ul');
                        occurrenceList.className = 'mj-member-events__signup-occurrence-list';
                    } else {
                        occurrenceColumns = document.createElement('div');
                        occurrenceColumns.className = 'mj-member-events__signup-occurrence-columns';

                        occurrenceRegisteredSection = createOccurrenceSection(
                            strings.occurrenceRegisteredTitle || 'Vos réservations',
                            'registered'
                        );
                        occurrenceAvailableSection = createOccurrenceSection(
                            strings.occurrenceAvailableTitle || 'Autres dates disponibles',
                            'available'
                        );

                        occurrenceColumns.appendChild(occurrenceRegisteredSection.section);
                        occurrenceColumns.appendChild(occurrenceAvailableSection.section);
                    }

                    for (var occIndex = 0; occIndex < occurrences.length; occIndex++) {
                        var occurrenceEntry = occurrences[occIndex];
                        if (!occurrenceEntry) {
                            continue;
                        }

                        var occurrenceValue = normalizeOccurrenceValue(occurrenceEntry.start);
                        if (!occurrenceValue || occurrenceSeen[occurrenceValue]) {
                            continue;
                        }
                        occurrenceSeen[occurrenceValue] = true;

                        var occurrenceLabel = formatOccurrenceLabel(occurrenceEntry, includeTime);
                        var occurrenceIsPast = !!occurrenceEntry.isPast;

                        var occurrenceItem = document.createElement('li');
                        occurrenceItem.className = 'mj-member-events__signup-occurrence-item';
                        if (occurrenceIsPast) {
                            occurrenceItem.classList.add('is-past');
                        }

                        var occurrenceLabelNode = document.createElement('label');
                        occurrenceLabelNode.className = 'mj-member-events__signup-occurrence-label';

                        var occurrenceInput = document.createElement('input');
                        occurrenceInput.type = 'checkbox';
                        occurrenceInput.name = 'occurrence[]';
                        occurrenceInput.value = occurrenceValue;
                        occurrenceInput.checked = false;

                        if (occurrenceIsPast) {
                            occurrenceInput.disabled = true;
                        } else {
                            hasEnabledOccurrence = true;
                        }

                        occurrenceLabelNode.appendChild(occurrenceInput);

                        var occurrenceText = document.createElement('span');
                        occurrenceText.textContent = occurrenceLabel;
                        occurrenceLabelNode.appendChild(occurrenceText);

                        if (occurrenceIsPast) {
                            var occurrenceBadge = document.createElement('span');
                            occurrenceBadge.className = 'mj-member-events__signup-occurrence-badge';
                            occurrenceBadge.textContent = strings.occurrencePast || 'Passée';
                            occurrenceLabelNode.appendChild(occurrenceBadge);
                        }

                        occurrenceItem.appendChild(occurrenceLabelNode);

                        var occurrenceActions = document.createElement('div');
                        occurrenceActions.className = 'mj-member-events__signup-occurrence-actions';
                        occurrenceActions.hidden = true;

                        var occurrenceUnregister = document.createElement('button');
                        occurrenceUnregister.type = 'button';
                        occurrenceUnregister.className = 'mj-member-events__signup-occurrence-toggle';
                        occurrenceUnregister.textContent = strings.unregisterOccurrence || strings.unregister || 'Se désinscrire';
                        occurrenceUnregister.dataset.occurrenceValue = occurrenceValue;
                        occurrenceUnregister.hidden = true;

                        occurrenceActions.appendChild(occurrenceUnregister);
                        occurrenceItem.appendChild(occurrenceActions);

                        if (hasCustomPreselection && selectedOccurrenceMap[occurrenceValue]) {
                            occurrenceInput.checked = true;
                            occurrenceItem.classList.add('is-selected');
                        }

                        (function (inputNode, itemNode) {
                            inputNode.addEventListener('change', function () {
                                if (inputNode.checked) {
                                    itemNode.classList.add('is-selected');
                                } else {
                                    itemNode.classList.remove('is-selected');
                                }
                                updateInlineCtaAvailability();
                            });
                        })(occurrenceInput, occurrenceItem);

                        if (isRecurringEvent && occurrenceAvailableSection) {
                            occurrenceAvailableSection.list.appendChild(occurrenceItem);
                        } else if (occurrenceList) {
                            occurrenceList.appendChild(occurrenceItem);
                        }

                        occurrenceControls.push({
                            element: occurrenceItem,
                            value: occurrenceValue,
                            isPast: occurrenceIsPast,
                            checkbox: occurrenceInput,
                            actions: occurrenceActions,
                            unregister: occurrenceUnregister,
                            label: occurrenceLabelNode,
                            registeredList: occurrenceRegisteredSection ? occurrenceRegisteredSection.list : null,
                            availableList: occurrenceAvailableSection ? occurrenceAvailableSection.list : occurrenceList,
                            registeredEmpty: occurrenceRegisteredSection ? occurrenceRegisteredSection.empty : null,
                            availableEmpty: occurrenceAvailableSection ? occurrenceAvailableSection.empty : null,
                            registeredSection: occurrenceRegisteredSection ? occurrenceRegisteredSection.section : null,
                            availableSection: occurrenceAvailableSection ? occurrenceAvailableSection.section : null,
                        });
                    }

                    if (isRecurringEvent && occurrenceColumns) {
                        occurrenceFieldset.appendChild(occurrenceColumns);
                    } else if (occurrenceList && occurrenceList.children.length) {
                        occurrenceFieldset.appendChild(occurrenceList);
                    }

                    if (isRecurringEvent && occurrenceColumns) {
                        occurrenceHelp = document.createElement('p');
                        occurrenceHelp.className = 'mj-member-events__signup-occurrence-help';
                        occurrenceHelp.textContent = strings.occurrenceHelpRecurring || 'Sélectionnez de nouvelles dates ou retirez une réservation.';
                        occurrenceFieldset.appendChild(occurrenceHelp);
                    } else if (!isRecurringEvent && occurrenceList && occurrenceList.children.length) {
                        occurrenceHelp = document.createElement('p');
                        occurrenceHelp.className = 'mj-member-events__signup-occurrence-help';
                        occurrenceHelp.textContent = strings.occurrenceHelp || 'Cochez les occurrences auxquelles vous participerez.';
                        occurrenceFieldset.appendChild(occurrenceHelp);
                    }

                    if (isRecurringEvent && occurrenceColumns) {
                        function updateOccurrenceSectionState(sectionData, count) {
                            if (!sectionData || !sectionData.section || !sectionData.empty) {
                                return;
                            }
                            if (typeof count !== 'number') {
                                count = sectionData.list ? sectionData.list.children.length : 0;
                            }
                            if (count > 0) {
                                sectionData.section.classList.remove('is-empty');
                                sectionData.empty.hidden = true;
                            } else {
                                sectionData.section.classList.add('is-empty');
                                sectionData.empty.hidden = false;
                            }
                        }

                        syncOccurrenceSections = function () {
                            var registeredCount = 0;
                            var availableCount = 0;

                            for (var cIdx = 0; cIdx < occurrenceControls.length; cIdx++) {
                                var controlEntry = occurrenceControls[cIdx];
                                var parentNode = controlEntry && controlEntry.element ? controlEntry.element.parentNode : null;
                                if (controlEntry.registeredList && parentNode === controlEntry.registeredList) {
                                    registeredCount++;
                                } else if (controlEntry.availableList && parentNode === controlEntry.availableList) {
                                    availableCount++;
                                }
                            }

                            updateOccurrenceSectionState(occurrenceRegisteredSection, registeredCount);
                            updateOccurrenceSectionState(occurrenceAvailableSection, availableCount);

                            if (occurrenceRegisteredSection && occurrenceRegisteredSection.section) {
                                occurrenceRegisteredSection.section.hidden = false;
                            }
                            if (occurrenceAvailableSection && occurrenceAvailableSection.section) {
                                occurrenceAvailableSection.section.hidden = false;
                            }
                        };
                    }

                    return occurrenceControls.length > initialCount;
                }

                var calendarBuilt = buildOccurrenceCalendar();

                var listBuilt = calendarBuilt;
                if (!calendarBuilt) {
                    listBuilt = buildOccurrenceListLayout();
                }

                if (!calendarBuilt && !listBuilt) {
                    var occurrenceEmpty = document.createElement('p');
                    occurrenceEmpty.className = 'mj-member-events__signup-occurrence-empty';
                    occurrenceEmpty.textContent = strings.occurrenceEmpty || 'Aucune occurrence disponible.';
                    occurrenceFieldset.appendChild(occurrenceEmpty);
                }

                form.appendChild(occurrenceFieldset);
                if (isRecurringEvent && typeof syncOccurrenceSections === 'function') {
                    syncOccurrenceSections();
                }
            }
        }

        if (availableParticipantsCount === 0 && participants.length && !isRecurringEvent) {
            infoMessage = document.createElement('p');
            infoMessage.className = 'mj-member-events__signup-info';
            if (hasIneligible && hasUnregistered) {
                infoMessage.textContent = strings.noEligibleParticipant || 'Aucun profil éligible n’est disponible pour cette inscription.';
            } else {
                infoMessage.textContent = strings.allRegistered || 'Tous les profils sont déjà inscrits pour cet événement.';
            }
            form.appendChild(infoMessage);
        }

        noteWrapper = document.createElement('div');
        noteWrapper.className = 'mj-member-events__signup-note';
        noteWrapper.hidden = true;
        var noteLabel = document.createElement('label');
        var noteId = 'mj-member-events-note-' + (payload.eventId || Date.now());
        noteLabel.setAttribute('for', noteId);
        noteLabel.textContent = strings.noteLabel || "Message pour l'équipe (optionnel)";
        noteField = document.createElement('textarea');
        noteField.id = noteId;
        noteField.name = 'note';
        noteField.maxLength = noteMaxLength;
        noteField.placeholder = strings.notePlaceholder || 'Précisez une remarque utile.';
        noteWrapper.appendChild(noteLabel);
        noteWrapper.appendChild(noteField);
        form.appendChild(noteWrapper);

        inlineCta = document.createElement('div');
        inlineCta.className = 'mj-member-events__signup-cta';
        inlineCta.setAttribute('role', 'button');
        inlineCta.setAttribute('tabindex', '0');
        inlineCta.textContent = inlineCtaRegisterLabel;
        form.appendChild(inlineCta);

        formFeedback = document.createElement('div');
        formFeedback.className = 'mj-member-events__signup-feedback';
        formFeedback.setAttribute('aria-live', 'polite');
        form.appendChild(formFeedback);

        signup.appendChild(form);

        inlineCta.addEventListener('click', triggerInlineSubmit);
        inlineCta.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                triggerInlineSubmit();
            }
        });

        var currentParticipantId = '';

        function normalizeAssignments(assignmentsInput) {
            var normalized = { mode: 'all', occurrences: [] };
            if (!assignmentsInput || typeof assignmentsInput !== 'object') {
                return normalized;
            }
            var mode = assignmentsInput.mode === 'custom' ? 'custom' : 'all';
            if (mode === 'custom' && Array.isArray(assignmentsInput.occurrences)) {
                var occurrencesSet = Object.create(null);
                for (var idxAssign = 0; idxAssign < assignmentsInput.occurrences.length; idxAssign++) {
                    var normalizedValue = normalizeOccurrenceValue(assignmentsInput.occurrences[idxAssign]);
                    if (normalizedValue && !occurrencesSet[normalizedValue]) {
                        occurrencesSet[normalizedValue] = true;
                        normalized.occurrences.push(normalizedValue);
                    }
                }
                if (normalized.occurrences.length === 0) {
                    normalized.mode = 'all';
                } else {
                    normalized.mode = 'custom';
                }
            }
            return normalized;
        }

        function collectAvailableOccurrenceValues() {
            var values = [];
            for (var ocIdx = 0; ocIdx < occurrenceControls.length; ocIdx++) {
                var control = occurrenceControls[ocIdx];
                if (!control.isPast) {
                    values.push(control.value);
                }
            }
            return values;
        }

        function expandAssignments(assignmentsInput) {
            var normalized = normalizeAssignments(assignmentsInput);
            if (normalized.mode === 'custom') {
                return normalized.occurrences.slice();
            }
            return collectAvailableOccurrenceValues();
        }

        function computeAssignedSet(assignmentsInput) {
            var assignedSet = Object.create(null);
            var expanded = expandAssignments(assignmentsInput);
            for (var occIdxSet = 0; occIdxSet < expanded.length; occIdxSet++) {
                assignedSet[expanded[occIdxSet]] = true;
            }
            return assignedSet;
        }

        function getParticipantState(participantId) {
            if (!participantId) {
                return null;
            }
            if (!participantStates[participantId]) {
                participantStates[participantId] = {
                    isRegistered: false,
                    assignments: { mode: 'all', occurrences: [] },
                    registrationId: 0,
                };
            }
            return participantStates[participantId];
        }

        function updateOptionActiveState(participantId) {
            var optionNodes = form.querySelectorAll('.mj-member-events__signup-option');
            optionNodes.forEach(function (node) {
                if (!node.dataset.participantId) {
                    node.classList.remove('is-active');
                    return;
                }
                if (participantId && node.dataset.participantId === participantId) {
                    node.classList.add('is-active');
                } else {
                    node.classList.remove('is-active');
                }
            });
        }

        function setParticipantRegistrationState(participantId, isRegistered, registrationId) {
            var state = getParticipantState(participantId);
            if (state) {
                state.isRegistered = !!isRegistered;
                state.registrationId = registrationId || 0;
            }
            if (participantIndex[participantId]) {
                participantIndex[participantId].isRegistered = !!isRegistered;
                participantIndex[participantId].registrationId = registrationId || 0;
            }
        }

        function refreshOccurrenceDisplay(participantId) {
            if (!occurrenceControls.length) {
                return;
            }
            var state = getParticipantState(participantId) || { isRegistered: false, assignments: { mode: 'all', occurrences: [] } };
            var assignedSet = computeAssignedSet(state.assignments);

            for (var idx = 0; idx < occurrenceControls.length; idx++) {
                var control = occurrenceControls[idx];
                var isAssigned = !!assignedSet[control.value];
                var dayNode = control.element ? control.element.closest('.mj-member-events__signup-calendar-day') : null;
                control.element.classList.remove('is-assigned');
                control.element.classList.remove('is-available');
                control.element.classList.remove('is-selected');

                control.checkbox.checked = false;
                control.checkbox.disabled = control.isPast;
                control.checkbox.hidden = false;
                if (control.label) {
                    control.label.hidden = false;
                    control.label.classList.remove('is-disabled');
                }
                control.unregister.hidden = true;
                control.unregister.disabled = false;
                control.unregister.dataset.participantId = '';
                if (control.actions) {
                    control.actions.hidden = true;
                }

                if (state.isRegistered && isAssigned) {
                    control.element.classList.add('is-assigned');
                    control.checkbox.hidden = true;
                    control.checkbox.disabled = true;
                    if (control.label) {
                        control.label.classList.add('is-disabled');
                    }
                    control.unregister.hidden = false;
                    control.unregister.disabled = control.isPast;
                    control.unregister.dataset.participantId = participantId;
                    control.unregister.dataset.occurrenceValue = control.value;
                    if (control.actions) {
                        control.actions.hidden = false;
                    }
                    if (control.registeredList && control.element.parentNode !== control.registeredList) {
                        control.registeredList.appendChild(control.element);
                    }
                } else {
                    if (control.isPast) {
                        control.checkbox.disabled = true;
                        if (control.label) {
                            control.label.classList.add('is-disabled');
                        }
                    }
                    control.element.classList.add('is-available');
                    if (control.availableList && control.element.parentNode !== control.availableList) {
                        control.availableList.appendChild(control.element);
                    }
                }

                if (!state.isRegistered && hasCustomPreselection && selectedOccurrenceMap[control.value] && !control.checkbox.disabled && !control.checkbox.hidden) {
                    control.checkbox.checked = true;
                    control.element.classList.add('is-selected');
                }

                syncCalendarDayState(dayNode);
            }

            if (occurrenceFieldset) {
                occurrenceFieldset.hidden = !isRecurringEvent && !allowOccurrenceSelection;
            }
            if (occurrenceHelp) {
                occurrenceHelp.hidden = !isRecurringEvent && !allowOccurrenceSelection;
            }

            syncOccurrenceSections();
        }

        function toggleNoteVisibility(participantId) {
            var state = getParticipantState(participantId);
            var shouldShowNote = state ? !state.isRegistered : true;
            if (noteWrapper) {
                noteWrapper.hidden = !shouldShowNote;
            }
        }

        function updateSubmitState(participantId) {
            var state = getParticipantState(participantId);

            if (!inlineCta) {
                return;
            }

            if (!state || !state.isRegistered) {
                inlineCta.dataset.mode = 'register';
                setInlineCtaLabel(inlineCtaRegisterLabel);
                setInlineCtaHidden(false);
                setInlineCtaDisabled(false);
                return;
            }

            if (isFixedSchedule) {
                setInlineCtaHidden(true);
                return;
            }

            inlineCta.dataset.mode = 'update';
            setInlineCtaLabel(inlineCtaUpdateLabel);
            setInlineCtaHidden(false);
            setInlineCtaDisabled(false);
        }

        function handleParticipantChange() {
            var selected = form.querySelector('input[name="participant"]:checked');
            currentParticipantId = selected ? selected.value : '';
            updateOptionActiveState(currentParticipantId);
            toggleNoteVisibility(currentParticipantId);
            updateSubmitState(currentParticipantId);
            refreshOccurrenceDisplay(currentParticipantId);
            updateInlineCtaAvailability();
        }

        function collectCheckedOccurrences() {
            var values = [];
            for (var idx = 0; idx < occurrenceControls.length; idx++) {
                var control = occurrenceControls[idx];
                if (control.checkbox && !control.checkbox.disabled && !control.checkbox.hidden && control.checkbox.checked) {
                    values.push(control.value);
                }
            }
            return values;
        }

        function performAssignmentsUpdate(participantId, baseAssignments, newAssignments, onsuccess) {
            if (!ajaxUrl || !nonce) {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                return;
            }

            var participantEntry = participantIndex[participantId];
            if (!participantEntry) {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                return;
            }

            var unionSet = Object.create(null);
            for (var idxBase = 0; idxBase < baseAssignments.length; idxBase++) {
                unionSet[baseAssignments[idxBase]] = true;
            }
            for (var idxNew = 0; idxNew < newAssignments.length; idxNew++) {
                unionSet[newAssignments[idxNew]] = true;
            }

            var merged = Object.keys(unionSet);
            if (!merged.length) {
                if (typeof onsuccess === 'function') {
                    onsuccess({ skip: true });
                }
                return;
            }

            form.dataset.submitting = '1';
            setInlineCtaDisabled(true);
            setInlineCtaLabel(inlineCtaLoadingLabel);
            setFeedback(formFeedback, '', false);

            var payloadData = new window.FormData();
            payloadData.append('action', 'mj_member_update_event_assignments');
            payloadData.append('nonce', nonce);
            payloadData.append('event_id', payload.eventId || '');
            payloadData.append('member_id', participantId);
            if (participantEntry.registrationId) {
                payloadData.append('registration_id', participantEntry.registrationId);
            }
            try {
                payloadData.append('occurrences', JSON.stringify(merged));
            } catch (error) {
                payloadData.append('occurrences', '[]');
            }

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payloadData,
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, status: response.status, json: json };
                }).catch(function () {
                    return { ok: response.ok, status: response.status, json: null };
                });
            }).then(function (result) {
                if (!result.ok || !result.json) {
                    var messageError = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json && result.json.data && result.json.data.message) {
                        messageError = result.json.data.message;
                    }
                    setFeedback(formFeedback, messageError, true);
                    return;
                }

                if (!result.json.success) {
                    var messageFail = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json.data && result.json.data.message) {
                        messageFail = result.json.data.message;
                    }
                    setFeedback(formFeedback, messageFail, true);
                    return;
                }

                var responseData = result.json.data || {};
                var updatedAssignments = responseData.assignments || { mode: 'custom', occurrences: merged };

                updateParticipantAssignments(participantId, updatedAssignments);
                setParticipantRegistrationState(participantId, true, participantStates[participantId].registrationId);

                payload.participants = participants;
                payload.assignments = updatedAssignments;
                recomputePayloadState(payload);

                try {
                    button.setAttribute('data-registration', JSON.stringify(payload));
                } catch (error) {
                    // ignore
                }

                if (feedback) {
                    setFeedback(feedback, responseData.message || strings.updateSuccess || 'Participation mise à jour.', false);
                }

                if (typeof onsuccess === 'function') {
                    onsuccess({ assignments: updatedAssignments });
                }

                payload.preselectParticipantId = participantId;
                buildSignup(card, button, signup, feedback, payload);
            }).catch(function () {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
            }).then(function () {
                form.dataset.submitting = '0';
                updateSubmitState(currentParticipantId);
                updateInlineCtaAvailability();
            });
        }

        if (occurrenceControls.length) {
            occurrenceControls.forEach(function (control) {
                control.unregister.addEventListener('click', function () {
                    if (form.dataset.submitting === '1') {
                        return;
                    }

                    var targetParticipantId = control.unregister.dataset.participantId || currentParticipantId;
                    if (!targetParticipantId) {
                        return;
                    }

                    if (!ajaxUrl || !nonce) {
                        setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                        return;
                    }

                    if (strings.unregisterConfirm && !window.confirm(strings.unregisterConfirm)) {
                        return;
                    }

                    var state = getParticipantState(targetParticipantId);
                    if (!state) {
                        return;
                    }

                    var expanded = expandAssignments(state.assignments);
                    var remaining = [];
                    for (var idxRemain = 0; idxRemain < expanded.length; idxRemain++) {
                        if (expanded[idxRemain] !== control.value) {
                            remaining.push(expanded[idxRemain]);
                        }
                    }

                    if (!remaining.length) {
                        // No occurrences left, fallback to full unregister.
                        var unregisterEntry = participantIndex[targetParticipantId];
                        if (!unregisterEntry) {
                            return;
                        }

                        var payloadData = new window.FormData();
                        payloadData.append('action', 'mj_member_unregister_event');
                        payloadData.append('nonce', nonce);
                        payloadData.append('event_id', payload.eventId || '');
                        payloadData.append('member_id', unregisterEntry.id || '');
                        if (state.registrationId) {
                            payloadData.append('registration_id', state.registrationId);
                        }

                        form.dataset.submitting = '1';
                        control.unregister.disabled = true;
                        setFeedback(formFeedback, '', false);

                        window.fetch(ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: payloadData,
                        }).then(function (response) {
                            return response.json().then(function (json) {
                                return { ok: response.ok, status: response.status, json: json };
                            }).catch(function () {
                                return { ok: response.ok, status: response.status, json: null };
                            });
                        }).then(function (result) {
                            if (!result.ok || !result.json || !result.json.success) {
                                var messageFail = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                if (result.json && result.json.data && result.json.data.message) {
                                    messageFail = result.json.data.message;
                                }
                                setFeedback(formFeedback, messageFail, true);
                                return;
                            }

                            if (feedback) {
                                setFeedback(feedback, strings.unregisterSuccess || 'Inscription annulée.', false);
                            }

                            setParticipantRegistrationState(String(unregisterEntry.id), false, 0);
                            updateParticipantAssignments(String(unregisterEntry.id), { mode: 'all', occurrences: [] });

                            payload.participants = participants;
                            recomputePayloadState(payload);
                            try {
                                button.setAttribute('data-registration', JSON.stringify(payload));
                            } catch (error) {
                                // ignore
                            }

                            refreshReservationPanel({ silent: true });
                            payload.preselectParticipantId = unregisterEntry.id;
                            buildSignup(card, button, signup, feedback, payload);
                        }).catch(function () {
                            setFeedback(formFeedback, strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                        }).then(function () {
                            form.dataset.submitting = '0';
                            control.unregister.disabled = false;
                        });

                        return;
                    }

                    performAssignmentsUpdate(targetParticipantId, remaining, [], function () {
                        // handled in rebuild
                    });
                });
            });
        }

        var participantRadios = form.querySelectorAll('input[name="participant"]');
        Array.prototype.forEach.call(participantRadios, function (radio) {
            radio.addEventListener('change', function () {
                handleParticipantChange();
            });
        });

        handleParticipantChange();

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (form.dataset.submitting === '1') {
                return;
            }

            if (!participants.length) {
                setFeedback(formFeedback, strings.noParticipant || "Aucun profil disponible pour l'instant.", true);
                return;
            }

            var selected = form.querySelector('input[name="participant"]:checked');
            if (!selected) {
                setFeedback(formFeedback, strings.selectParticipant || 'Merci de sélectionner un participant.', true);
                return;
            }

            if (!ajaxUrl || !nonce) {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                return;
            }

            var selectedParticipantId = selected.value;
            var selectedState = getParticipantState(selectedParticipantId);
            var selectedEntry = participantIndex[selectedParticipantId];

            form.dataset.submitting = '1';
            setInlineCtaDisabled(true);
            setInlineCtaLabel(inlineCtaLoadingLabel);
            setFeedback(formFeedback, '', false);

            var payloadData = new window.FormData();
            payloadData.append('action', 'mj_member_register_event');
            payloadData.append('nonce', nonce);
            payloadData.append('event_id', payload.eventId || '');
            payloadData.append('member_id', selected.value);

            if (noteField) {
                var noteValue = noteField.value || '';
                if (noteValue.length > noteMaxLength) {
                    noteValue = noteValue.slice(0, noteMaxLength);
                }
                payloadData.append('note', noteValue);
            } else {
                payloadData.append('note', '');
            }

            var selectedOccurrenceValues = collectCheckedOccurrences();
            var wantsAssignmentUpdate = isRecurringEvent && selectedState && selectedState.isRegistered;

            if (wantsAssignmentUpdate) {
                form.dataset.submitting = '0';
                inlineCta.dataset.mode = 'update';
                setInlineCtaDisabled(false);
                setInlineCtaLabel(inlineCtaUpdateLabel);

                if (!selectedOccurrenceValues.length) {
                    setFeedback(formFeedback, strings.occurrenceMissing || 'Merci de sélectionner au moins une occurrence.', true);
                    return;
                }

                var baseAssignments = expandAssignments(selectedState.assignments);
                performAssignmentsUpdate(selectedParticipantId, baseAssignments, selectedOccurrenceValues, function () {
                    // handled in rebuild
                });
                return;
            }

            if (occurrenceControls.length) {
                if (!selectedOccurrenceValues.length) {
                    setFeedback(formFeedback, strings.occurrenceMissing || 'Merci de sélectionner au moins une occurrence.', true);
                    form.dataset.submitting = '0';
                    updateSubmitState(currentParticipantId);
                    updateInlineCtaAvailability();
                    return;
                }
                try {
                    payloadData.append('occurrences', JSON.stringify(selectedOccurrenceValues));
                } catch (error) {
                    selectedOccurrenceValues = [];
                }
            }

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payloadData,
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, status: response.status, json: json };
                }).catch(function () {
                    return { ok: response.ok, status: response.status, json: null };
                });
            }).then(function (result) {
                if (!result.ok || !result.json) {
                    var messageError = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json && result.json.data && result.json.data.message) {
                        messageError = result.json.data.message;
                    }
                    setFeedback(formFeedback, messageError, true);
                    return;
                }

                if (!result.json.success) {
                    var messageFail = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json.data && result.json.data.message) {
                        messageFail = result.json.data.message;
                    }
                    setFeedback(formFeedback, messageFail, true);
                    return;
                }

                setFeedback(formFeedback, '', false);

                var responseData = result.json.data || {};
                var serverMessage = responseData.message || strings.success || 'Inscription enregistrée !';
                var isWaitlist = !!responseData.is_waitlist;
                var paymentInfo = responseData.payment || null;
                var paymentRequired = !!responseData.payment_required;
                var paymentError = !!responseData.payment_error;

                if (feedback) {
                    var feedbackMessage = serverMessage;
                    if (paymentInfo && paymentInfo.checkout_url) {
                        feedbackMessage = serverMessage + ' ' + (strings.paymentFallback || 'Si la page de paiement ne s\'ouvre pas, copiez ce lien : ') + ' ' + paymentInfo.checkout_url;
                    }
                    setFeedback(feedback, feedbackMessage, paymentError);
                }

                if (noteField) {
                    noteField.value = '';
                }

                var selectedId = parseInt(selected.value, 10);
                var createdRegistrationId = 0;
                if (result.json.data && typeof result.json.data.registration_id !== 'undefined') {
                    createdRegistrationId = parseInt(result.json.data.registration_id, 10);
                    if (Number.isNaN(createdRegistrationId)) {
                        createdRegistrationId = 0;
                    }
                }

                for (var idx = 0; idx < participants.length; idx++) {
                    var participantItem = participants[idx];
                    if (!participantItem) {
                        continue;
                    }

                    if (parseInt(participantItem.id, 10) === selectedId) {
                        participantItem.isRegistered = true;
                        if (createdRegistrationId > 0) {
                            participantItem.registrationId = createdRegistrationId;
                            participantItem.registrationStatus = isWaitlist ? 'liste_attente' : 'en_attente';
                        }
                        participantItem.occurrenceAssignments = selectedOccurrenceValues.length
                            ? { mode: 'custom', occurrences: selectedOccurrenceValues.slice() }
                            : { mode: 'all', occurrences: [] };
                        setParticipantRegistrationState(String(participantItem.id), true, createdRegistrationId);
                        updateParticipantAssignments(String(participantItem.id), participantItem.occurrenceAssignments);
                    }
                }

                payload.participants = participants;
                payload.noteMaxLength = noteMaxLength;
                if (selectedOccurrenceValues.length) {
                    payload.assignments = {
                        mode: 'custom',
                        occurrences: selectedOccurrenceValues.slice(),
                    };
                } else if (Array.isArray(payload.occurrences) && payload.occurrences.length) {
                    payload.assignments = {
                        mode: 'all',
                        occurrences: [],
                    };
                }
                recomputePayloadState(payload);

                try {
                    button.setAttribute('data-registration', JSON.stringify(payload));
                } catch (error) {
                    // Ignore JSON errors silently
                }

                updateButtonState(button, payload);

                refreshReservationPanel({ silent: true });
                payload.preselectParticipantId = selectedParticipantId;
                buildSignup(card, button, signup, feedback, payload);

                if (paymentInfo && paymentInfo.checkout_url) {
                    // Open Stripe Checkout in a new context without blocking user interaction.
                    var opened = window.open(paymentInfo.checkout_url, '_blank', 'noopener,noreferrer');
                    if (!opened) {
                        window.location.href = paymentInfo.checkout_url;
                    }
                }
            }).catch(function () {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
            }).then(function () {
                form.dataset.submitting = '0';
                updateSubmitState(currentParticipantId);
                updateInlineCtaAvailability();
                handleParticipantChange();
            });
        });

        function updateInlineCtaAvailability() {
            if (!inlineCta || inlineCta.hidden) {
                return;
            }
            if (form.dataset.submitting === '1') {
                setInlineCtaDisabled(true);
                return;
            }

            if (!currentParticipantId) {
                setInlineCtaDisabled(true);
                return;
            }

            var state = getParticipantState(currentParticipantId);
            if (!state) {
                setInlineCtaDisabled(true);
                return;
            }

            if (state.isRegistered && isFixedSchedule) {
                setInlineCtaDisabled(true);
                return;
            }

            if (occurrenceControls.length) {
                var selectedOccurrences = collectCheckedOccurrences();
                if (!selectedOccurrences.length && (!state.isRegistered || !isRecurringEvent)) {
                    setInlineCtaDisabled(true);
                    return;
                }
            }

            setInlineCtaDisabled(false);
            if (inlineCta.dataset.mode === 'update') {
                setInlineCtaLabel(inlineCtaUpdateLabel);
            } else {
                setInlineCtaLabel(inlineCtaRegisterLabel);
            }
        }

        if (isPersistentSignup) {
            activeSignup = null;
        } else {
            var firstRadio = signup.querySelector('input[name="participant"]');
            if (firstRadio) {
                firstRadio.focus();
            }
        }
    }

    function parseTypesAttr(attr) {
        if (!attr) {
            return [];
        }
        return attr.split(',').map(function (item) {
            return item.trim();
        }).filter(function (value) {
            return value.length > 0;
        });
    }

    function collectEventWidgets() {
        var roots = document.querySelectorAll('.mj-member-events');
        eventWidgets = Array.prototype.map.call(roots, function (root) {
            var cards = Array.prototype.map.call(root.querySelectorAll('.mj-member-events__item'), function (card) {
                return {
                    node: card,
                    types: parseTypesAttr(card.getAttribute('data-location-types')),
                };
            });

            var messageNode = root.querySelector('.mj-member-events__filtered-empty');
            return {
                id: root.getAttribute('data-mj-events-root') || '',
                root: root,
                cards: cards,
                message: messageNode,
                messageText: messageNode ? messageNode.textContent : '',
            };
        });
    }

    function applyEventsFilter(instance, slug) {
        if (!instance) {
            return;
        }

        var normalizedSlug = slug ? slug.toString().trim() : '';
        var visibleCount = 0;

        instance.cards.forEach(function (entry) {
            var matches = !normalizedSlug || entry.types.indexOf(normalizedSlug) !== -1;
            if (matches) {
                entry.node.removeAttribute('hidden');
                entry.node.classList.remove('is-filtered-out');
                visibleCount += 1;
            } else {
                entry.node.setAttribute('hidden', 'hidden');
                entry.node.classList.add('is-filtered-out');
                if (activeSignup && entry.node.contains(activeSignup)) {
                    closeSignup(activeSignup);
                }
            }
        });

        if (instance.message) {
            if (visibleCount === 0) {
                instance.message.textContent = strings.filterNoResult || instance.messageText || 'Aucun événement ne correspond à ce filtre.';
                instance.message.hidden = false;
            } else {
                instance.message.hidden = true;
            }
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('mj-member:refresh-reservations', function () {
            refreshReservationPanel({ silent: true });
        });
    }

    domReady(function () {
        initEventRegistrationForms();

        var loginButtons = document.querySelectorAll('[data-mj-event-open-login]');
        toArray(loginButtons).forEach(function (loginButton) {
            loginButton.addEventListener('click', function (event) {
                event.preventDefault();
                openLoginModal();
            });
        });

        var buttons = document.querySelectorAll('.mj-member-events__cta');

        toArray(buttons).forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.disabled) {
                    return;
                }

                var card = button.closest('.mj-member-events__item');
                if (!card) {
                    return;
                }

                var signup = card.querySelector('.mj-member-events__signup');
                var feedback = card.querySelector('.mj-member-events__feedback');

                if (!signup) {
                    return;
                }

                if (button.getAttribute('data-requires-login') === '1') {
                    setFeedback(feedback, strings.loginRequired || 'Connectez-vous pour continuer.', false);
                    openLoginModal();
                    return;
                }

                var payload = parsePayload(button);
                if (!payload) {
                    setFeedback(feedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                    return;
                }

                buildSignup(card, button, signup, feedback, payload);
            });
        });

        var autoSignups = document.querySelectorAll('.mj-member-events__signup[data-autoload="1"]');
        toArray(autoSignups).forEach(function (signup) {
            var card = signup.closest('.mj-member-events__item');
            if (!card) {
                return;
            }

            var button = card.querySelector('.mj-member-events__cta');
            var feedback = card.querySelector('.mj-member-events__feedback');
            if (!button) {
                return;
            }

            if (button.getAttribute('data-requires-login') === '1') {
                signup.removeAttribute('hidden');
                signup.classList.add('is-open');
                signup.innerHTML = '';

                var loginNotice = document.createElement('p');
                loginNotice.className = 'mj-member-events__signup-empty';
                loginNotice.textContent = strings.loginRequired || 'Connecte-toi pour continuer.';
                signup.appendChild(loginNotice);

                signup.dataset.persistent = '1';
                return;
            }

            var payload = parsePayload(button);
            if (!payload) {
                return;
            }

            if (typeof button.blur === 'function') {
                button.blur();
            }
            button.setAttribute('tabindex', '-1');
            button.setAttribute('hidden', 'hidden');

            if (!signup.dataset.persistent) {
                signup.dataset.persistent = '1';
            }

            buildSignup(card, button, signup, feedback, payload);

            if (signup.dataset.persistent === '1' && activeSignup === signup) {
                activeSignup = null;
            }
        });

        document.addEventListener('click', function (event) {
            if (!activeSignup) {
                return;
            }

            if (!activeSignup.contains(event.target)) {
                var card = activeSignup.closest('.mj-member-events__item');
                var button = card ? card.querySelector('.mj-member-events__cta') : null;
                if (button && event.target === button) {
                    return;
                }

                closeSignup(activeSignup);
            }
        });

        collectEventWidgets();
        resolveReservationPanel();
    });

    window.addEventListener('mjMemberEvents:filterByLocationType', function (event) {
        if (!eventWidgets.length) {
            collectEventWidgets();
        }

        var detail = event && event.detail ? event.detail : {};
        var slug = detail && detail.type ? detail.type.toString().trim() : '';
        var instanceId = detail && detail.instanceId ? detail.instanceId.toString() : '';
        var selector = detail && detail.selector ? detail.selector.toString() : '';
        var selectorMatches = null;

        if (selector) {
            try {
                selectorMatches = toArray(document.querySelectorAll(selector));
            } catch (error) {
                selectorMatches = [];
            }
        }

        eventWidgets.forEach(function (instance) {
            if (instanceId && instance.id !== instanceId) {
                return;
            }

            if (selectorMatches) {
                if (!selectorMatches.length) {
                    return;
                }
                if (selectorMatches.indexOf(instance.root) === -1) {
                    return;
                }
            }

            applyEventsFilter(instance, slug);
        });
    });
})();
