(function () {
    'use strict';

    var settings = window.mjMemberEventsWidget || {};
    var ajaxUrl = settings.ajaxUrl || '';
    var nonce = settings.nonce || '';
    var loginUrl = settings.loginUrl || '';
    var strings = settings.strings || {};
    var activeSignup = null;
    var defaultCtaLabel = strings.cta || "S'inscrire";
    var registeredCtaLabel = strings.registered || strings.confirm || "Confirmer l'inscription";
    var eventWidgets = [];

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
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
            return { total: 0, available: 0 };
        }

        var participants = Array.isArray(payload.participants) ? payload.participants : [];
        var available = 0;
        var total = 0;

        for (var i = 0; i < participants.length; i++) {
            var participant = participants[i];
            if (!participant) {
                continue;
            }
            total += 1;
            if (!participant.isRegistered) {
                available += 1;
            }
        }

        payload.hasParticipants = total > 0;
        payload.hasAvailableParticipants = available > 0;
        payload.allRegistered = total > 0 && available === 0;

        return { total: total, available: available };
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
        return entry.start ? String(entry.start) : raw;
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
        if (activeSignup && activeSignup !== signup) {
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

        var title = document.createElement('p');
        title.className = 'mj-member-events__signup-title';
        title.textContent = strings.chooseParticipant || 'Choisissez le participant';
        form.appendChild(title);

        var participants = Array.isArray(payload.participants) ? payload.participants : [];
        var participantsCount = participants.length;
        var availableParticipantsCount = 0;
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

                var option = document.createElement('li');
                option.className = 'mj-member-events__signup-option';
                if (isRegistered) {
                    option.classList.add('is-registered');
                }

                var label = document.createElement('label');
                label.className = 'mj-member-events__signup-label';

                var input = document.createElement('input');
                input.type = 'radio';
                input.name = 'participant';
                input.value = participantId;
                input.required = true;
                input.disabled = false;

                if (!isRegistered) {
                    availableParticipantsCount += 1;
                    if (!firstSelectable) {
                        firstSelectable = input;
                    }
                }

                if (preferredParticipantId && participantId === preferredParticipantId) {
                    preferredRadio = input;
                }

                if (!firstSelectable && !preferredRadio && isRegistered && !availableParticipantsCount) {
                    firstSelectable = input;
                }

                var span = document.createElement('span');
                span.className = 'mj-member-events__signup-name';
                span.textContent = entry.label || ('#' + participantId);

                label.appendChild(input);
                label.appendChild(span);

                if (isRegistered) {
                    var status = document.createElement('span');
                    status.className = 'mj-member-events__signup-status';
                    var statusLabel = strings.alreadyRegistered || 'Déjà inscrit';
                    var currentStatus = entry.registrationStatus ? String(entry.registrationStatus) : '';
                    if (currentStatus === 'liste_attente') {
                        statusLabel = strings.waitlistStatus || 'En liste d\'attente';
                    } else if (currentStatus === 'en_attente') {
                        statusLabel = strings.pendingStatus || 'En attente';
                    } else if (currentStatus === 'valide') {
                        statusLabel = strings.confirmedStatus || 'Confirmé';
                    }
                    status.textContent = statusLabel;
                    label.appendChild(status);
                }

                option.appendChild(label);

                if (isRegistered && ajaxUrl && nonce) {
                    (function (participantEntry, currentRegistrationId) {
                        var controls = document.createElement('div');
                        controls.className = 'mj-member-events__signup-controls';

                        var unregisterButton = document.createElement('button');
                        unregisterButton.type = 'button';
                        unregisterButton.className = 'mj-member-events__signup-toggle';
                        unregisterButton.textContent = strings.unregister || 'Se désinscrire';

                        controls.appendChild(unregisterButton);
                        option.appendChild(controls);

                        unregisterButton.addEventListener('click', function () {
                            if (form.dataset.submitting === '1') {
                                return;
                            }

                            if (!ajaxUrl || !nonce) {
                                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                                return;
                            }

                            if (strings.unregisterConfirm && !window.confirm(strings.unregisterConfirm)) {
                                return;
                            }

                            form.dataset.submitting = '1';
                            unregisterButton.disabled = true;
                            setFeedback(formFeedback, '', false);
                            if (feedback) {
                                setFeedback(feedback, '', false);
                            }

                            var payloadData = new window.FormData();
                            payloadData.append('action', 'mj_member_unregister_event');
                            payloadData.append('nonce', nonce);
                            payloadData.append('event_id', payload.eventId || '');
                            payloadData.append('member_id', participantEntry.id || '');
                            if (currentRegistrationId > 0) {
                                payloadData.append('registration_id', currentRegistrationId);
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
                                    var messageNetwork = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                    setFeedback(formFeedback, messageNetwork, true);
                                    return;
                                }

                                if (!result.json.success) {
                                    var messageFail = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                    if (result.json.data && result.json.data.message) {
                                        messageFail = result.json.data.message;
                                    }
                                    setFeedback(formFeedback, messageFail, true);
                                    return;
                                }

                                participantEntry.isRegistered = false;
                                participantEntry.registrationId = 0;
                                participantEntry.registrationStatus = '';
                                participantEntry.registrationCreatedAt = '';
                                participantEntry.occurrenceAssignments = {
                                    mode: 'all',
                                    occurrences: [],
                                };

                                participantStates[String(participantEntry.id)] = {
                                    isRegistered: false,
                                    assignments: participantEntry.occurrenceAssignments,
                                    registrationId: 0,
                                };

                                payload.participants = participants;
                                recomputePayloadState(payload);

                                try {
                                    button.setAttribute('data-registration', JSON.stringify(payload));
                                } catch (error) {
                                    // Ignore serialization errors silently
                                }

                                updateButtonState(button, payload);

                                if (feedback) {
                                    setFeedback(feedback, strings.unregisterSuccess || 'Inscription annulée.', false);
                                }

                                form.dataset.submitting = '0';
                                unregisterButton.disabled = false;

                                payload.preselectParticipantId = participantEntry.id;
                                buildSignup(card, button, signup, feedback, payload);
                            }).catch(function () {
                                var messageError = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                setFeedback(formFeedback, messageError, true);
                            }).then(function () {
                                if (form.dataset.submitting !== '0') {
                                    form.dataset.submitting = '0';
                                }
                                unregisterButton.disabled = false;
                            });
                        });
                    })(entry, registrationId);
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
                var occurrenceSummary = document.createElement('p');
                occurrenceSummary.className = 'mj-member-events__signup-occurrence-summary';
                occurrenceSummary.textContent = occurrenceSummaryText;
                occurrenceFieldset.appendChild(occurrenceSummary);
            }

            var occurrenceSeen = Object.create(null);

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

                var list = document.createElement('ul');
                list.className = 'mj-member-events__signup-occurrence-list';
                section.appendChild(list);

                var empty = document.createElement('p');
                empty.className = 'mj-member-events__signup-occurrence-empty';
                empty.textContent = modifier === 'registered'
                    ? (strings.occurrenceRegisteredEmpty || 'Aucune réservation active.')
                    : (strings.occurrenceAvailableEmpty || 'Toutes les dates sont déjà réservées.');
                empty.hidden = true;
                section.appendChild(empty);

                return { section: section, list: list, empty: empty };
            }

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
                if (!isRecurringEvent) {
                    return;
                }

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

                if (isRecurringEvent && occurrenceAvailableSection) {
                    occurrenceAvailableSection.list.appendChild(occurrenceItem);
                } else if (occurrenceList) {
                    occurrenceList.appendChild(occurrenceItem);
                }

                occurrenceControls.push({
                    element: occurrenceItem,
                    value: occurrenceValue,
                    isPast: !!occurrenceIsPast,
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
                if (hasEnabledOccurrence) {
                    occurrenceHelp = document.createElement('p');
                    occurrenceHelp.className = 'mj-member-events__signup-occurrence-help';
                    occurrenceHelp.textContent = strings.occurrenceHelp || 'Cochez les occurrences auxquelles vous participerez.';
                    occurrenceFieldset.appendChild(occurrenceHelp);
                }
            } else if (!isRecurringEvent) {
                var occurrenceEmpty = document.createElement('p');
                occurrenceEmpty.className = 'mj-member-events__signup-occurrence-empty';
                occurrenceEmpty.textContent = strings.occurrenceEmpty || 'Aucune occurrence disponible.';
                occurrenceFieldset.appendChild(occurrenceEmpty);
            }

            if (isRecurringEvent && occurrenceColumns) {
                occurrenceHelp = document.createElement('p');
                occurrenceHelp.className = 'mj-member-events__signup-occurrence-help';
                occurrenceHelp.textContent = strings.occurrenceHelpRecurring || 'Sélectionnez de nouvelles dates ou retirez une réservation.';
                occurrenceFieldset.appendChild(occurrenceHelp);
            }

            form.appendChild(occurrenceFieldset);
            if (isRecurringEvent) {
                syncOccurrenceSections();
            }
        }

        if (availableParticipantsCount === 0 && participants.length && !isRecurringEvent) {
            infoMessage = document.createElement('p');
            infoMessage.className = 'mj-member-events__signup-info';
            infoMessage.textContent = strings.allRegistered || 'Tous les profils sont déjà inscrits pour cet événement.';
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

        var actions = document.createElement('div');
        actions.className = 'mj-member-events__signup-actions';

        var submit = document.createElement('button');
        submit.type = 'submit';
        submit.className = 'mj-member-events__signup-submit';
        submit.textContent = strings.confirm || "Confirmer l'inscription";

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'mj-member-events__signup-cancel';
        cancel.textContent = strings.cancel || 'Annuler';

        actions.appendChild(submit);
        actions.appendChild(cancel);
        form.appendChild(actions);

        formFeedback = document.createElement('div');
        formFeedback.className = 'mj-member-events__signup-feedback';
        formFeedback.setAttribute('aria-live', 'polite');
        form.appendChild(formFeedback);

        signup.appendChild(form);

        cancel.addEventListener('click', function () {
            closeSignup(signup);
            setFeedback(formFeedback, '', false);
            if (feedback) {
                setFeedback(feedback, '', false);
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

        function updateParticipantAssignments(participantId, assignmentsInput) {
            var normalized = normalizeAssignments(assignmentsInput);
            if (participantStates[participantId]) {
                participantStates[participantId].assignments = normalized;
            }
            if (participantIndex[participantId]) {
                participantIndex[participantId].occurrenceAssignments = normalized;
            }
        }

        function setParticipantRegistrationState(participantId, isRegistered, registrationId) {
            if (participantStates[participantId]) {
                participantStates[participantId].isRegistered = !!isRegistered;
                participantStates[participantId].registrationId = registrationId || 0;
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
                control.element.classList.remove('is-assigned');
                control.element.classList.remove('is-available');

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
            }

            if (occurrenceFieldset) {
                occurrenceFieldset.hidden = !isRecurringEvent;
            }
            if (occurrenceHelp) {
                occurrenceHelp.hidden = !isRecurringEvent;
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
            if (!submit) {
                return;
            }

            if (!state || !state.isRegistered) {
                submit.hidden = false;
                submit.disabled = false;
                submit.textContent = strings.confirm || "Confirmer l'inscription";
                return;
            }

            if (isFixedSchedule) {
                submit.hidden = true;
                submit.disabled = true;
                submit.textContent = strings.registered || strings.confirm || "Confirmer l'inscription";
            } else {
                submit.hidden = false;
                submit.disabled = false;
                submit.textContent = strings.updateOccurrences || strings.confirm || 'Mettre à jour';
            }
        }

        function handleParticipantChange() {
            var selected = form.querySelector('input[name="participant"]:checked');
            currentParticipantId = selected ? selected.value : '';
            toggleNoteVisibility(currentParticipantId);
            updateSubmitState(currentParticipantId);
            refreshOccurrenceDisplay(currentParticipantId);
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
            submit.disabled = true;
            submit.textContent = strings.loading || 'En cours...';
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
                submit.disabled = false;
                submit.textContent = strings.updateOccurrences || strings.confirm || 'Mettre à jour';
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
            submit.disabled = true;
            submit.textContent = strings.loading || 'En cours...';
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
                submit.disabled = false;
                submit.textContent = strings.updateOccurrences || strings.confirm || 'Mettre à jour';

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
                    submit.disabled = false;
                    submit.textContent = strings.confirm || "Confirmer l'inscription";
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

                payload.preselectParticipantId = selectedParticipantId;
                closeSignup(signup);

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
                submit.disabled = false;
                submit.textContent = strings.confirm || "Confirmer l'inscription";
                handleParticipantChange();
            });
        });

        var firstRadio = signup.querySelector('input[name="participant"]');
        if (firstRadio) {
            firstRadio.focus();
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

    ready(function () {
        var buttons = document.querySelectorAll('.mj-member-events__cta');

        Array.prototype.forEach.call(buttons, function (button) {
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
                selectorMatches = Array.prototype.slice.call(document.querySelectorAll(selector));
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
