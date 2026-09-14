(function(){
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

    function initCalendar(root, config) {
        if (!root) {
            return;
        }

        // Optional EventPage modal opened from event links.
        var eventPageModal = null;
        var eventPageModalFrame = null;
        var eventPageModalTitle = null;
        var previousBodyOverflow = '';

        function ensureEventPageModal() {
            if (eventPageModal) {
                return true;
            }

            if (!document || !document.body) {
                return false;
            }

            eventPageModal = document.createElement('div');
            eventPageModal.className = 'mj-cal-eventpage-modal';
            eventPageModal.hidden = true;

            var backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'mj-cal-eventpage-modal__backdrop';
            backdrop.setAttribute('aria-label', 'Fermer');

            var panel = document.createElement('div');
            panel.className = 'mj-cal-eventpage-modal__panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('aria-label', 'Page événement');

            var header = document.createElement('div');
            header.className = 'mj-cal-eventpage-modal__header';

            eventPageModalTitle = document.createElement('span');
            eventPageModalTitle.className = 'mj-cal-eventpage-modal__title';
            eventPageModalTitle.textContent = 'Page événement';

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'mj-cal-eventpage-modal__close';
            closeBtn.setAttribute('aria-label', 'Fermer');
            closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

            eventPageModalFrame = document.createElement('iframe');
            eventPageModalFrame.className = 'mj-cal-eventpage-modal__frame';
            eventPageModalFrame.setAttribute('title', 'EventPage');
            eventPageModalFrame.setAttribute('loading', 'eager');
            eventPageModalFrame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            eventPageModalFrame.addEventListener('load', function() {
                // Same-origin iframe: hide site chrome so only EventPage content is visible.
                try {
                    if (!eventPageModalFrame || !eventPageModalFrame.contentDocument) {
                        return;
                    }
                    var iframeDoc = eventPageModalFrame.contentDocument;
                    var head = iframeDoc.head || iframeDoc.getElementsByTagName('head')[0];
                    if (!head) {
                        return;
                    }
                    if (iframeDoc.getElementById('mj-cal-eventpage-modal-style')) {
                        return;
                    }
                    var style = iframeDoc.createElement('style');
                    style.id = 'mj-cal-eventpage-modal-style';
                    style.textContent = [
                        '#site-header,',
                        '#site-footer,',
                        '.site-header,',
                        '.site-footer,',
                        '#masthead,',
                        '#colophon,',
                        'header[role="banner"],',
                        'footer[role="contentinfo"] { display: none !important; }',
                        'html, body { margin: 0 !important; padding: 0 !important; }'
                    ].join(' ');
                    head.appendChild(style);
                } catch (error) {
                    // Ignore when iframe document cannot be accessed.
                }
            });

            header.appendChild(eventPageModalTitle);
            header.appendChild(closeBtn);
            panel.appendChild(header);
            panel.appendChild(eventPageModalFrame);
            eventPageModal.appendChild(backdrop);
            eventPageModal.appendChild(panel);
            document.body.appendChild(eventPageModal);

            function closeEventPageModal() {
                if (!eventPageModal || eventPageModal.hidden) {
                    return;
                }
                eventPageModal.hidden = true;
                if (eventPageModalFrame) {
                    eventPageModalFrame.setAttribute('src', 'about:blank');
                }
                document.body.style.overflow = previousBodyOverflow;
            }

            backdrop.addEventListener('click', closeEventPageModal);
            closeBtn.addEventListener('click', closeEventPageModal);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeEventPageModal();
                }
            });

            eventPageModal._close = closeEventPageModal;

            return true;
        }

        function openEventPageModal(href, titleText) {
            if (!href) {
                return;
            }
            if (!ensureEventPageModal()) {
                window.location.href = href;
                return;
            }

            if (eventPageModalTitle) {
                eventPageModalTitle.textContent = titleText || 'Page événement';
            }
            if (eventPageModalFrame) {
                eventPageModalFrame.setAttribute('src', href);
            }

            previousBodyOverflow = document.body.style.overflow || '';
            document.body.style.overflow = 'hidden';
            eventPageModal.hidden = false;
        }

        // ---- Create modal (delegated to shared module) ----
        // MjCreateEventModal is a separately-enqueued script (js/create-event-modal.js).
        // It should always execute before this one (see AssetsManager::requirePackage()),
        // but we retry briefly here as a safety net against any loading-order edge case.
        var ccmInstance = null;
        var ccmModalEl = root.querySelector('[data-ccm-modal]');
        function tryInitCcm(attemptsLeft) {
            if (ccmInstance || !ccmModalEl) {
                return;
            }
            if (window.MjCreateEventModal) {
                ccmInstance = window.MjCreateEventModal.init(root, config);
                if (!ccmInstance) {
                    console.warn('[Calendar] CCM modal markup found but MjCreateEventModal.init() returned null.');
                }
                return;
            }
            if (attemptsLeft > 0) {
                setTimeout(function () { tryInitCcm(attemptsLeft - 1); }, 100);
                return;
            }
            console.warn('[Calendar] CCM modal markup found but MjCreateEventModal never became available.');
        }
        tryInitCcm(20);

        var occurrenceModal = null;
        var occurrenceEvents = null;
        var occurrenceEditorHost = null;
        var occurrenceEditorMount = null;

        function createRegMgrApi() {
            if (!config || !config.ajaxUrl || !config.registrationManagerNonce) {
                return null;
            }
            if (window.MjRegMgrServices && typeof window.MjRegMgrServices.createApiService === 'function') {
                return window.MjRegMgrServices.createApiService({
                    ajaxUrl: config.ajaxUrl,
                    nonce: config.registrationManagerNonce
                });
            }
            return null;
        }

        function normalizeOccurrenceTarget(eventData, occurrenceTs) {
            var occurrences = eventData && Array.isArray(eventData.occurrences) ? eventData.occurrences : [];
            if (!occurrences.length || !occurrenceTs) {
                return occurrences.length ? occurrences[0] : null;
            }
            var targetTs = parseInt(occurrenceTs, 10) || 0;
            return occurrences.find(function(occurrence) {
                if (!occurrence) {
                    return false;
                }
                if (occurrence.timestamp && parseInt(occurrence.timestamp, 10) === targetTs) {
                    return true;
                }
                var startValue = occurrence.start || (occurrence.date && occurrence.startTime ? occurrence.date + ' ' + occurrence.startTime + ':00' : '');
                if (!startValue) {
                    return false;
                }
                var parsed = Date.parse(String(startValue).replace(' ', 'T'));
                return !Number.isNaN(parsed) && Math.floor(parsed / 1000) === targetTs;
            }) || occurrences[0] || null;
        }

        function ensureOccurrenceEditorHost() {
            if (occurrenceEditorHost) {
                return occurrenceEditorHost;
            }
            if (!document || !document.body) {
                return null;
            }
            occurrenceEditorHost = document.createElement('div');
            occurrenceEditorHost.className = 'mj-member-events-calendar__occurrence-editor-host';
            occurrenceEditorMount = document.createElement('div');
            occurrenceEditorMount.className = 'mj-member-events-calendar__occurrence-editor-mount';
            occurrenceEditorHost.appendChild(occurrenceEditorMount);
            document.body.appendChild(occurrenceEditorHost);
            return occurrenceEditorHost;
        }

        function renderOccurrenceEditor(eventData, editorData, targetOccurrence) {
            var preact = window.preact;
            var render = window.preactRender || (preact && preact.render);
            var OccurrenceEditor = window.MjRegMgrOccurrenceEditor || {};
            var Panel = typeof OccurrenceEditor.OccurrenceEncoderPanel === 'function'
                ? OccurrenceEditor.OccurrenceEncoderPanel
                : null;
            var api = createRegMgrApi();

            if (!preact || typeof render !== 'function' || !Panel || !api || !ensureOccurrenceEditorHost()) {
                return false;
            }

            var h = preact.h;
            var eventId = eventData && eventData.id ? parseInt(eventData.id, 10) : 0;
            if (!eventId) {
                return false;
            }

            var Host = function() {
                var hooks = window.preactHooks || {};
                var useState = hooks.useState;
                if (typeof useState !== 'function') {
                    return null;
                }
                var state = useState(eventData);
                var currentEvent = state[0];
                var setCurrentEvent = state[1];

                return h(Panel, {
                    event: currentEvent,
                    occurrences: Array.isArray(currentEvent.occurrences) ? currentEvent.occurrences : [],
                    initialEditorOccurrenceId: targetOccurrence && targetOccurrence.id ? String(targetOccurrence.id) : '',
                    calendarContextEvents: [],
                    calendarContextQuery: {},
                    strings: {},
                    locale: (config && config.locale) || 'fr',
                    apiPost: api.post,
                    globalLocationOptions: editorData && editorData.form && editorData.form.options ? editorData.form.options.locations : null,
                    globalMemberOptions: editorData && editorData.form && editorData.form.options ? editorData.form.options.animateurs : null,
                    globalVolunteerOptions: editorData && editorData.form && editorData.form.options ? editorData.form.options.volunteers : null,
                    onPersistOccurrences: function(nextOccurrences, scheduleSummary, generatorPlan, options) {
                        return api.saveEventOccurrences(eventId, nextOccurrences, scheduleSummary, generatorPlan, options).then(function(response) {
                            if (response && response.event) {
                                setCurrentEvent(response.event);
                            }
                            window.location.reload();
                            return response;
                        });
                    },
                    onBatchesUpdate: function(batches) {
                        setCurrentEvent(function(previous) {
                            return previous ? Object.assign({}, previous, { occurrenceGenerationBatches: batches }) : previous;
                        });
                    },
                    onNotify: function(notice) {
                        if (notice && notice.message) {
                            window.alert(notice.message);
                        }
                    }
                });
            };

            render(h(Host), occurrenceEditorMount);
            return true;
        }

        function openExistingOccurrenceEditor(eventId, occurrenceTs) {
            var api = createRegMgrApi();
            if (!api || !eventId) {
                return false;
            }
            Promise.all([
                api.getEventDetails(eventId),
                api.getEventEditor(eventId)
            ]).then(function(results) {
                var eventData = results[0] && results[0].event ? results[0].event : null;
                var editorData = results[1] || null;
                var targetOccurrence = normalizeOccurrenceTarget(eventData, occurrenceTs);
                if (!targetOccurrence || !renderOccurrenceEditor(eventData, editorData, targetOccurrence)) {
                    window.location.href = '/mon-compte/gestionnaire/?event=' + encodeURIComponent(String(eventId)) + '#mj-event-occurrence-editor';
                }
            }).catch(function(error) {
                window.alert(error && error.message ? error.message : 'Impossible de charger l\'éditeur d\'occurrence.');
            });
            return true;
        }

        function createOccurrenceModal() {
            if (occurrenceModal) {
                return occurrenceModal;
            }

            var modal = document.createElement('div');
            modal.className = 'mj-calendar-occurrence-modal';
            modal.hidden = true;
            modal.innerHTML = '<div class="mj-calendar-occurrence-modal__backdrop" data-occurrence-close></div>' +
                '<section class="mj-calendar-occurrence-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mj-calendar-occurrence-title">' +
                    '<header class="mj-calendar-occurrence-modal__header"><h2 id="mj-calendar-occurrence-title">Créer une occurrence</h2><button type="button" data-occurrence-close aria-label="Fermer">&times;</button></header>' +
                    '<div class="mj-calendar-occurrence-modal__body">' +
                        '<input type="search" class="mj-calendar-occurrence-modal__search" placeholder="Rechercher un événement" aria-label="Rechercher un événement" data-occurrence-search>' +
                        '<div class="mj-calendar-occurrence-modal__events" data-occurrence-events></div>' +
                        '<div class="mj-calendar-occurrence-modal__schedule"><label>Date<input type="date" data-occurrence-date></label><label>Début<input type="time" value="14:00" data-occurrence-start></label><label>Fin<input type="time" value="17:00" data-occurrence-end></label></div>' +
                        '<p class="mj-calendar-occurrence-modal__feedback" data-occurrence-feedback aria-live="polite"></p>' +
                    '</div>' +
                    '<footer class="mj-calendar-occurrence-modal__footer"><button type="button" data-occurrence-close>Annuler</button><button type="button" data-occurrence-submit disabled>Créer l\'occurrence</button></footer>' +
                '</section>';
            document.body.appendChild(modal);

            function close() {
                modal.hidden = true;
            }

            toArray(modal.querySelectorAll('[data-occurrence-close]')).forEach(function(button) {
                button.addEventListener('click', close);
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    close();
                }
            });
            modal._close = close;
            occurrenceModal = modal;
            return modal;
        }

        function requestOccurrenceEvents(callback) {
            if (occurrenceEvents) {
                callback(occurrenceEvents);
                return;
            }
            var formData = new FormData();
            formData.append('action', 'mj_calendar_list_occurrence_events');
            formData.append('nonce', config.deleteNonce);
            fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(response) {
                    if (!response || !response.success) {
                        throw new Error(response && response.data && response.data.message ? response.data.message : 'Impossible de charger les événements.');
                    }
                    occurrenceEvents = response.data.events || [];
                    callback(occurrenceEvents);
                })
                .catch(function(error) { callback([], error.message); });
        }

        function openOccurrenceModal(day) {
            if (!config || !config.ajaxUrl || !config.deleteNonce) {
                return;
            }
            var modal = createOccurrenceModal();
            var search = modal.querySelector('[data-occurrence-search]');
            var list = modal.querySelector('[data-occurrence-events]');
            var date = modal.querySelector('[data-occurrence-date]');
            var feedback = modal.querySelector('[data-occurrence-feedback]');
            var submit = modal.querySelector('[data-occurrence-submit]');
            var selectedId = 0;
            date.value = day;
            search.value = '';
            feedback.textContent = 'Chargement des événements...';
            submit.disabled = true;
            modal.hidden = false;

            function render(events, errorMessage) {
                list.textContent = '';
                if (errorMessage) {
                    feedback.textContent = errorMessage;
                    return;
                }
                var query = search.value.trim().toLocaleLowerCase();
                var visibleEvents = events.filter(function(event) {
                    return !query || (event.title || '').toLocaleLowerCase().indexOf(query) !== -1;
                });
                feedback.textContent = visibleEvents.length ? '' : 'Aucun événement trouvé.';
                visibleEvents.forEach(function(event) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'mj-calendar-occurrence-modal__event';
                    item.setAttribute('data-event-id', event.id);
                    if (event.coverUrl) {
                        var image = document.createElement('img');
                        image.src = event.coverUrl;
                        image.alt = '';
                        item.appendChild(image);
                    }
                    var title = document.createElement('span');
                    title.textContent = event.title || 'Événement sans titre';
                    item.appendChild(title);
                    item.addEventListener('click', function() {
                        selectedId = parseInt(event.id, 10) || 0;
                        toArray(list.querySelectorAll('.is-selected')).forEach(function(selected) { selected.classList.remove('is-selected'); });
                        item.classList.add('is-selected');
                        submit.disabled = !selectedId;
                    });
                    list.appendChild(item);
                });
            }

            requestOccurrenceEvents(function(events, errorMessage) {
                render(events, errorMessage);
                search.oninput = function() { render(events); };
            });

            submit.onclick = function() {
                if (!selectedId) {
                    return;
                }
                var formData = new FormData();
                formData.append('action', 'mj_calendar_create_occurrence');
                formData.append('nonce', config.deleteNonce);
                formData.append('event_id', selectedId);
                formData.append('date', date.value);
                formData.append('start_time', modal.querySelector('[data-occurrence-start]').value);
                formData.append('end_time', modal.querySelector('[data-occurrence-end]').value);
                submit.disabled = true;
                feedback.textContent = 'Création en cours...';
                fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(response) { return response.json(); })
                    .then(function(response) {
                        if (!response || !response.success) {
                            throw new Error(response && response.data && response.data.message ? response.data.message : 'Impossible de créer l\'occurrence.');
                        }
                        window.location.reload();
                    })
                    .catch(function(error) {
                        feedback.textContent = error.message;
                        submit.disabled = false;
                    });
            };
        }

        // ---- Day note modal (Preact form shared with the day-notes management widget) ----
        var noteModalContainer = null;
        var noteModalOpen = false;
        var noteModalCurrent = null;

        function ensureNoteModalContainer() {
            if (!noteModalContainer) {
                noteModalContainer = document.createElement('div');
                noteModalContainer.className = 'mj-day-note-modal-root';
                document.body.appendChild(noteModalContainer);
            }
            return noteModalContainer;
        }

        function renderNoteModal() {
            var DayNoteForm = window.MjDayNoteForm;
            var preactLib = window.preact;
            if (!DayNoteForm || !preactLib || !preactLib.h || !preactLib.render) {
                return;
            }
            var container = ensureNoteModalContainer();
            var hh = preactLib.h;

            var noteConfig = {
                ajaxUrl: config.noteAjaxUrl || config.ajaxUrl,
                nonce: config.noteNonce,
                noteTypes: Array.isArray(config.noteTypes) ? config.noteTypes : [],
                groupOptions: Array.isArray(config.noteGroupOptions) ? config.noteGroupOptions : undefined,
                members: Array.isArray(config.noteAssignableMembers) ? config.noteAssignableMembers : [],
                canManageTypes: !!config.noteCanManageTypes,
            };

            var noteForEdit = noteModalCurrent && noteModalCurrent.id ? noteModalCurrent
                : (noteModalCurrent ? { note_date: noteModalCurrent.note_date } : null);

            preactLib.render(hh(DayNoteForm.NoteFormModal, {
                isOpen: noteModalOpen,
                note: noteForEdit,
                config: noteConfig,
                onClose: closeNoteModal,
                onSaved: function () { refreshDayNotes(); },
                onDeleted: function () { refreshDayNotes(); },
            }), container);
        }

        // ---- Live refresh of the day-note previews after create/edit/delete
        // (no full page reload: also sidesteps any page/element caching) ----
        var NOTE_PAGE_SIZE = 4;

        // Applies the currently checked type-filter checkboxes to a single
        // element (mirrors applyFilters()'s per-item logic, defined further
        // down but reachable here via closure by call time) and registers it
        // so a later filter toggle keeps finding it.
        function applyCurrentFilterToItem(item) {
            if (typeof typeItems !== 'undefined' && typeItems.indexOf(item) === -1) {
                typeItems.push(item);
            }
            if (typeof filterInputs === 'undefined' || !filterInputs.length) {
                return;
            }
            var activeMap = {};
            var hasChecked = false;
            filterInputs.forEach(function (input) {
                if (input.checked) {
                    activeMap[input.value] = true;
                    hasChecked = true;
                }
            });
            var typeKey = item.getAttribute('data-calendar-type') || '';
            var isKnown = item.getAttribute('data-calendar-type-known') === '1';
            var passesType = !hasChecked || !isKnown || Object.prototype.hasOwnProperty.call(activeMap, typeKey);
            if (passesType && passesNoteTypeFilter(item)) {
                item.classList.remove('is-filtered-out');
            } else {
                item.classList.add('is-filtered-out');
            }
        }

        // Sub-filter by note type (only meaningful for [data-calendar-type="note"]
        // items; relies on noteTypeFilterInputsRef, set once the sub-filter
        // checkboxes are queried further down).
        function getActiveNoteTypeMap() {
            if (typeof noteTypeFilterInputsRef === 'undefined' || !noteTypeFilterInputsRef || !noteTypeFilterInputsRef.length) {
                return null;
            }
            var map = {};
            var hasChecked = false;
            for (var i = 0; i < noteTypeFilterInputsRef.length; i++) {
                if (noteTypeFilterInputsRef[i].checked) {
                    map[noteTypeFilterInputsRef[i].value] = true;
                    hasChecked = true;
                }
            }
            return hasChecked ? map : {};
        }

        function passesNoteTypeFilter(item) {
            if (item.getAttribute('data-calendar-type') !== 'note') {
                return true;
            }
            var noteTypeMap = getActiveNoteTypeMap();
            if (noteTypeMap === null) {
                return true;
            }
            var typeId = item.getAttribute('data-note-type-id') || '0';
            return Object.prototype.hasOwnProperty.call(noteTypeMap, typeId);
        }

        var NOTE_ROLE_LABELS = { animateur: 'Animateur', coordinateur: 'Coordinateur', benevole: 'Bénévole', jeune: 'Jeune', tuteur: 'Tuteur' };

        function noteVisibilityLabel(v) {
            if (v === 'private') return 'Personnel';
            if (v === 'staff') return 'Staff';
            if (v === 'all') return 'Tous';
            if (v && v.indexOf('role:') === 0) {
                var role = v.slice(5);
                return NOTE_ROLE_LABELS[role] || (role.charAt(0).toUpperCase() + role.slice(1));
            }
            return v || '';
        }

        function noteVisibilityIcon(v) {
            if (v === 'private') return '🔒';
            if (v === 'staff') return '🏢';
            if (v === 'all') return '👥';
            if (v && v.indexOf('role:') === 0) return '🎭';
            return '👁️';
        }

        function buildDayNoteRow(note) {
            var row = document.createElement('div');
            row.className = 'mj-member-events-calendar__day-note';
            row.setAttribute('data-note-id', note.id);
            row.setAttribute('data-calendar-type-item', '1');
            row.setAttribute('data-calendar-type', 'note');
            row.setAttribute('data-calendar-type-known', '1');
            row.setAttribute('data-calendar-count-exclude', '1');
            row.setAttribute('data-note-type-id', String(note.note_type_id || 0));
            if (note.color) {
                row.style.backgroundColor = note.color;
            }

            var tooltipEl = document.createElement('div');
            tooltipEl.className = 'mj-member-events-calendar__day-note-tooltip';
            tooltipEl.setAttribute('role', 'tooltip');
            var tooltipImgUrl = note.media && note.media[0] ? note.media[0].url : '';
            if (tooltipImgUrl) {
                var tooltipImg = document.createElement('img');
                tooltipImg.className = 'mj-member-events-calendar__day-note-tooltip-image';
                tooltipImg.src = tooltipImgUrl;
                tooltipImg.alt = '';
                tooltipEl.appendChild(tooltipImg);
            }
            if (note.content) {
                var tooltipDesc = document.createElement('p');
                tooltipDesc.className = 'mj-member-events-calendar__day-note-tooltip-desc';
                tooltipDesc.textContent = note.content;
                tooltipEl.appendChild(tooltipDesc);
            }

            var tooltipAvatarsEl = document.createElement('div');
            tooltipAvatarsEl.className = 'mj-member-events-calendar__day-note-tooltip-avatars';
            if (note.author_avatar) {
                var creatorGroupEl = document.createElement('span');
                creatorGroupEl.className = 'mj-member-events-calendar__day-note-tooltip-avatar-group mj-member-events-calendar__day-note-tooltip-avatar-group--creator';
                var tooltipAuthorImg = document.createElement('img');
                tooltipAuthorImg.className = 'mj-member-events-calendar__day-note-avatar';
                tooltipAuthorImg.src = note.author_avatar;
                tooltipAuthorImg.alt = '';
                if (note.author_name) tooltipAuthorImg.title = note.author_name;
                creatorGroupEl.appendChild(tooltipAuthorImg);
                tooltipAvatarsEl.appendChild(creatorGroupEl);
            }
            if (note.assigned_avatars && note.assigned_avatars.length) {
                var assigneesGroupEl = document.createElement('span');
                assigneesGroupEl.className = 'mj-member-events-calendar__day-note-tooltip-avatar-group mj-member-events-calendar__day-note-tooltip-avatar-group--assignees';
                note.assigned_avatars.forEach(function (url) {
                    var img = document.createElement('img');
                    img.className = 'mj-member-events-calendar__day-note-avatar';
                    img.src = url;
                    img.alt = '';
                    assigneesGroupEl.appendChild(img);
                });
                tooltipAvatarsEl.appendChild(assigneesGroupEl);
            }
            tooltipEl.appendChild(tooltipAvatarsEl);

            var tooltipMeta = document.createElement('div');
            tooltipMeta.className = 'mj-member-events-calendar__day-note-tooltip-meta';
            if (note.note_type_label) {
                var typeTag = document.createElement('span');
                typeTag.className = 'mj-member-events-calendar__day-note-tooltip-tag';
                typeTag.textContent = ((note.note_type_emoji || '') + ' ' + note.note_type_label).trim();
                tooltipMeta.appendChild(typeTag);
            }
            var visTag = document.createElement('span');
            visTag.className = 'mj-member-events-calendar__day-note-tooltip-tag';
            visTag.textContent = (noteVisibilityIcon(note.visibility) + ' ' + noteVisibilityLabel(note.visibility)).trim();
            tooltipMeta.appendChild(visTag);
            tooltipEl.appendChild(tooltipMeta);
            row.appendChild(tooltipEl);

            var avatarsEl = document.createElement('span');
            avatarsEl.className = 'mj-member-events-calendar__day-note-avatars';
            (note.assigned_avatars || []).slice(0, 3).forEach(function (url) {
                var img = document.createElement('img');
                img.className = 'mj-member-events-calendar__day-note-avatar';
                img.src = url;
                img.alt = '';
                avatarsEl.appendChild(img);
            });
            row.appendChild(avatarsEl);

            if (note.emoji) {
                var emojiEl = document.createElement('span');
                emojiEl.className = 'mj-member-events-calendar__day-note-emoji';
                emojiEl.setAttribute('aria-hidden', 'true');
                emojiEl.textContent = note.emoji;
                row.appendChild(emojiEl);
            }

            var titleEl = document.createElement('span');
            titleEl.className = 'mj-member-events-calendar__day-note-title';
            titleEl.textContent = note.title || (note.content || '').slice(0, 40);
            row.appendChild(titleEl);

            if (note.start_time) {
                var timeLabel = note.start_time + (note.end_time ? ' - ' + note.end_time : '');
                var timeEl = document.createElement('time');
                timeEl.className = 'mj-member-events-calendar__day-note-time';
                timeEl.setAttribute('data-meta-text', timeLabel);
                var timeIconEl = document.createElement('span');
                timeIconEl.className = 'mj-member-events-calendar__day-note-time-icon';
                timeIconEl.setAttribute('aria-hidden', 'true');
                timeIconEl.textContent = '🕐';
                timeEl.appendChild(timeIconEl);
                var timeTextEl = document.createElement('span');
                timeTextEl.className = 'mj-member-events-calendar__day-note-time-text';
                timeTextEl.textContent = timeLabel;
                timeEl.appendChild(timeTextEl);
                row.appendChild(timeEl);
            }

            var thumbUrl = note.media && note.media[0] ? note.media[0].thumbUrl : '';
            if (thumbUrl) {
                var thumbEl = document.createElement('img');
                thumbEl.className = 'mj-member-events-calendar__day-note-thumb';
                thumbEl.src = thumbUrl;
                thumbEl.alt = '';
                row.appendChild(thumbEl);
            }

            if (note.can_edit) {
                var editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'mj-member-events-calendar__day-note-edit';
                editBtn.setAttribute('data-note-edit', '');
                editBtn.setAttribute('aria-label', 'Modifier la note');
                editBtn.setAttribute('title', 'Modifier la note');
                editBtn.textContent = '✎';
                row.appendChild(editBtn);
            }

            applyCurrentFilterToItem(row);
            return row;
        }

        function renderDayNotesPage(wrapper) {
            var dayNotesData;
            try {
                dayNotesData = JSON.parse(wrapper.getAttribute('data-day-notes') || '[]');
            } catch (e) {
                dayNotesData = [];
            }
            var page = parseInt(wrapper.getAttribute('data-note-page'), 10) || 0;
            var total = dayNotesData.length;
            var start = page * NOTE_PAGE_SIZE;
            var visible = dayNotesData.slice(start, start + NOTE_PAGE_SIZE);

            toArray(wrapper.querySelectorAll(':scope > .mj-member-events-calendar__day-note')).forEach(function (row) { row.remove(); });
            var navEl = wrapper.querySelector(':scope > .mj-member-events-calendar__day-note-nav');
            visible.forEach(function (note) {
                wrapper.insertBefore(buildDayNoteRow(note), navEl || null);
            });

            var countEl = wrapper.querySelector('.mj-member-events-calendar__day-note-nav-count');
            if (countEl) {
                countEl.textContent = (start + 1) + '-' + (start + visible.length) + '/' + total;
            }
        }

        function buildDayNotesWrapper(dayNotesData) {
            var wrapper = document.createElement('div');
            wrapper.className = 'mj-member-events-calendar__day-notes';
            wrapper.setAttribute('data-day-notes', JSON.stringify(dayNotesData));
            wrapper.setAttribute('data-note-page', '0');
            wrapper.setAttribute('data-page-size', String(NOTE_PAGE_SIZE));

            if (dayNotesData.length > NOTE_PAGE_SIZE) {
                var navEl = document.createElement('span');
                navEl.className = 'mj-member-events-calendar__day-note-nav';
                var prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.setAttribute('data-note-page-nav', 'prev');
                prevBtn.setAttribute('aria-label', 'Notes précédentes');
                prevBtn.setAttribute('title', 'Notes précédentes');
                prevBtn.textContent = '‹';
                var countEl = document.createElement('span');
                countEl.className = 'mj-member-events-calendar__day-note-nav-count';
                var nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.setAttribute('data-note-page-nav', 'next');
                nextBtn.setAttribute('aria-label', 'Notes suivantes');
                nextBtn.setAttribute('title', 'Notes suivantes');
                nextBtn.textContent = '›';
                navEl.appendChild(prevBtn);
                navEl.appendChild(countEl);
                navEl.appendChild(nextBtn);
                wrapper.appendChild(navEl);
            }

            renderDayNotesPage(wrapper);
            return wrapper;
        }

        function applyDayNotesToDayCell(dayCell, dayNotesData) {
            var existing = dayCell.querySelector(':scope > .mj-member-events-calendar__day-notes');
            if (existing) {
                existing.remove();
            }
            if (!dayNotesData || !dayNotesData.length) {
                return;
            }
            var header = dayCell.querySelector(':scope > .mj-member-events-calendar__day-header');
            var newEl = buildDayNotesWrapper(dayNotesData);
            if (header && header.nextSibling) {
                header.parentNode.insertBefore(newEl, header.nextSibling);
            } else if (header) {
                header.parentNode.appendChild(newEl);
            } else {
                dayCell.insertBefore(newEl, dayCell.firstChild);
            }
        }

        function refreshDayNotes() {
            if (!config || !config.noteNonce) return;
            var ajaxUrl = config.noteAjaxUrl || config.ajaxUrl;
            if (!ajaxUrl) return;

            var dayKeys = toArray(root.querySelectorAll('.mj-member-events-calendar__day[data-calendar-day]'))
                .map(function (el) { return el.getAttribute('data-calendar-day'); })
                .filter(Boolean)
                .sort();
            if (!dayKeys.length) return;

            var body = new URLSearchParams({
                action: 'mj_member_day_notes_list',
                nonce: config.noteNonce,
                date_from: dayKeys[0],
                date_to: dayKeys[dayKeys.length - 1],
            }).toString();

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body,
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) return;
                    var notes = (payload.data && payload.data.notes) || [];
                    var byDay = {};
                    notes.forEach(function (note) {
                        var day = note.note_date;
                        if (!day) return;
                        if (!byDay[day]) byDay[day] = [];
                        byDay[day].push(note);
                    });
                    Object.keys(byDay).forEach(function (day) {
                        byDay[day].sort(function (a, b) {
                            var aTime = a.start_time || '99:99';
                            var bTime = b.start_time || '99:99';
                            if (aTime !== bTime) {
                                return aTime < bTime ? -1 : 1;
                            }
                            if (a.created_at === b.created_at) return 0;
                            return a.created_at < b.created_at ? 1 : -1;
                        });
                    });

                    toArray(root.querySelectorAll('.mj-member-events-calendar__day[data-calendar-day]')).forEach(function (dayCell) {
                        var day = dayCell.getAttribute('data-calendar-day');
                        applyDayNotesToDayCell(dayCell, byDay[day] || null);
                    });
                })
                .catch(function () { /* keep whatever is currently shown */ });
        }

        function openNoteModal(dayKey, existingNote) {
            if (!config || !config.noteNonce) {
                return;
            }
            noteModalCurrent = existingNote || { note_date: dayKey };
            noteModalOpen = true;
            renderNoteModal();
        }

        function closeNoteModal() {
            noteModalOpen = false;
            renderNoteModal();
        }

        function getDayNoteById(wrapper, noteId) {
            if (!wrapper || !noteId) return null;
            try {
                var notes = JSON.parse(wrapper.getAttribute('data-day-notes') || '[]');
                return notes.filter(function (n) { return String(n.id) === String(noteId); })[0] || null;
            } catch (e) {
                return null;
            }
        }

        function pageDayNotes(wrapper, direction) {
            if (!wrapper) return;
            var total;
            try {
                total = JSON.parse(wrapper.getAttribute('data-day-notes') || '[]').length;
            } catch (e) {
                return;
            }
            var pageCount = Math.max(1, Math.ceil(total / NOTE_PAGE_SIZE));
            var page = parseInt(wrapper.getAttribute('data-note-page'), 10) || 0;
            page = direction === 'next' ? (page + 1) % pageCount : (page - 1 + pageCount) % pageCount;
            wrapper.setAttribute('data-note-page', String(page));
            renderDayNotesPage(wrapper);
        }

        // ---- Create task modal (inspired by the todo widget's create form) ----
        var taskModal = null;

        function createTaskModal() {
            if (taskModal) {
                return taskModal;
            }

            var modal = document.createElement('div');
            modal.className = 'mj-calendar-task-modal';
            modal.hidden = true;

            var projectOptions = '<option value="0">Aucun dossier</option>';
            (Array.isArray(config.todoProjects) ? config.todoProjects : []).forEach(function(project) {
                if (!project || !project.id) {
                    return;
                }
                projectOptions += '<option value="' + project.id + '">' + escapeHtml(project.title || '') + '</option>';
            });

            var assigneeOptions = '';
            (Array.isArray(config.todoAssignableMembers) ? config.todoAssignableMembers : []).forEach(function(member) {
                if (!member || !member.id) {
                    return;
                }
                var selected = member.isSelf ? ' selected' : '';
                assigneeOptions += '<option value="' + member.id + '"' + selected + '>' + escapeHtml(member.name || '') + '</option>';
            });

            modal.innerHTML = '<div class="mj-calendar-task-modal__backdrop" data-task-close></div>' +
                '<section class="mj-calendar-task-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mj-calendar-task-title">' +
                    '<header class="mj-calendar-task-modal__header"><h2 id="mj-calendar-task-title">Créer une tâche</h2><button type="button" data-task-close aria-label="Fermer">&times;</button></header>' +
                    '<div class="mj-calendar-task-modal__body">' +
                        '<label class="mj-calendar-task-modal__field">Titre<input type="text" data-task-title maxlength="190" placeholder="Ex : Préparer le matériel"></label>' +
                        '<label class="mj-calendar-task-modal__field">Description<textarea data-task-description rows="3" placeholder="Détails de la tâche (facultatif)"></textarea></label>' +
                        '<div class="mj-calendar-task-modal__row">' +
                            '<label class="mj-calendar-task-modal__field">Échéance<input type="date" data-task-due-date></label>' +
                            '<label class="mj-calendar-task-modal__field">Dossier<select data-task-project>' + projectOptions + '</select></label>' +
                        '</div>' +
                        '<label class="mj-calendar-task-modal__field">Assigné(s)<select data-task-assignees multiple size="4">' + assigneeOptions + '</select></label>' +
                        '<p class="mj-calendar-task-modal__feedback" data-task-feedback aria-live="polite"></p>' +
                    '</div>' +
                    '<footer class="mj-calendar-task-modal__footer"><button type="button" data-task-close>Annuler</button><button type="button" data-task-submit>Créer la tâche</button></footer>' +
                '</section>';
            document.body.appendChild(modal);

            function close() {
                modal.hidden = true;
            }

            toArray(modal.querySelectorAll('[data-task-close]')).forEach(function(button) {
                button.addEventListener('click', close);
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    close();
                }
            });
            modal._close = close;
            taskModal = modal;
            return modal;
        }

        function openTaskModal(day) {
            if (!config || !config.ajaxUrl || !config.todoNonce) {
                return;
            }
            var modal = createTaskModal();
            var titleInput = modal.querySelector('[data-task-title]');
            var descriptionInput = modal.querySelector('[data-task-description]');
            var dueDateInput = modal.querySelector('[data-task-due-date]');
            var projectSelect = modal.querySelector('[data-task-project]');
            var assigneesSelect = modal.querySelector('[data-task-assignees]');
            var feedback = modal.querySelector('[data-task-feedback]');
            var submit = modal.querySelector('[data-task-submit]');

            titleInput.value = '';
            descriptionInput.value = '';
            dueDateInput.value = day || '';
            projectSelect.value = '0';
            feedback.textContent = '';
            submit.disabled = false;
            modal.hidden = false;
            titleInput.focus();

            submit.onclick = function() {
                var title = titleInput.value.trim();
                if (!title) {
                    feedback.textContent = 'Merci de saisir un titre.';
                    titleInput.focus();
                    return;
                }

                var formData = new FormData();
                formData.append('action', config.todoCreateAction || 'mj_member_todos_create');
                formData.append('nonce', config.todoNonce);
                formData.append('title', title);
                formData.append('description', descriptionInput.value.trim());
                formData.append('due_date', dueDateInput.value || '');
                formData.append('project_id', projectSelect.value || '0');
                toArray(assigneesSelect.selectedOptions || []).forEach(function(option) {
                    formData.append('assigned_member_ids[]', option.value);
                });

                submit.disabled = true;
                feedback.textContent = 'Création en cours...';
                fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(response) { return response.json(); })
                    .then(function(response) {
                        if (!response || !response.success) {
                            throw new Error(response && response.data && response.data.message ? response.data.message : 'Impossible de créer la tâche.');
                        }
                        window.location.reload();
                    })
                    .catch(function(error) {
                        feedback.textContent = error.message;
                        submit.disabled = false;
                    });
            };
        }

        function openLeaveRequestModal(day) {
            if (!day || !window.MjLeaveRequestsWidget || typeof window.MjLeaveRequestsWidget.openCreateModal !== 'function') {
                window.alert('Impossible de charger le formulaire de congé.');
                return;
            }

            var opened = window.MjLeaveRequestsWidget.openCreateModal({
                initialDates: [day],
                onSuccess: function() {
                    window.location.reload();
                }
            });

            if (!opened) {
                window.alert('Vous n\'avez pas accès aux demandes de congé.');
            }
        }

        if (config && config.openEventPageModal) {
            root.addEventListener('click', function(e) {
                var eventLink = e.target.closest('a.mj-member-events-calendar__event-trigger, a.mj-member-events-calendar__mobile-link');
                if (!eventLink || !root.contains(eventLink)) {
                    return;
                }

                var href = (eventLink.getAttribute('href') || '').trim();
                if (!href || href.charAt(0) === '#') {
                    return;
                }

                if (/^(javascript:|mailto:|tel:)/i.test(href)) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                if (mobileModal && !mobileModal.hidden && eventLink.closest('[data-calendar-mobile-modal]')) {
                    closeMobileModal();
                }

                var titleNode = eventLink.querySelector('.mj-member-events-calendar__event-title-text, .mj-member-events-calendar__mobile-title-text');
                var titleText = titleNode && titleNode.textContent ? titleNode.textContent.trim() : '';
                openEventPageModal(href, titleText);
            });
        }

        // ---- Delete occurrence handler & add-event day buttons ----
        if (config && config.ajaxUrl && config.deleteNonce) {
            function closeDayActionMenus() {
                toArray(root.querySelectorAll('.mj-cal-mobile__day-actions.is-open')).forEach(function(actions) {
                    actions.classList.remove('is-open');
                });
                toArray(root.querySelectorAll('.mj-cal-mobile__day-menu')).forEach(function(menu) {
                    menu.hidden = true;
                });
                toArray(root.querySelectorAll('.mj-cal-mobile__day-menu-toggle[aria-expanded="true"]')).forEach(function(toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                });
            }

            root.addEventListener('click', function(e) {
                var dayMenuToggle = e.target.closest('.mj-cal-mobile__day-menu-toggle');
                if (dayMenuToggle) {
                    e.preventDefault();
                    e.stopPropagation();

                    var dayActions = dayMenuToggle.closest('.mj-cal-mobile__day-actions');
                    var dayMenu = dayActions ? dayActions.querySelector('.mj-cal-mobile__day-menu') : null;
                    var isDayMenuOpen = dayMenu && !dayMenu.hidden;
                    closeDayActionMenus();

                    if (dayMenu && !isDayMenuOpen) {
                        dayMenu.hidden = false;
                        dayActions.classList.add('is-open');
                        dayMenuToggle.setAttribute('aria-expanded', 'true');
                    }
                    return;
                }

                if (e.target.closest('.mj-cal-mobile__day-menu')) {
                    closeDayActionMenus();
                }

                var menuToggle = e.target.closest('.mj-member-events-calendar__event-menu-toggle');
                if (menuToggle) {
                    e.preventDefault();
                    e.stopPropagation();

                    var actions = menuToggle.closest('.mj-member-events-calendar__event-actions');
                    var menu = actions ? actions.querySelector('.mj-member-events-calendar__event-menu') : null;
                    var isOpen = menu && !menu.hidden;
                    toArray(root.querySelectorAll('.mj-member-events-calendar__event-actions.is-open')).forEach(function(openActions) {
                        openActions.classList.remove('is-open');
                    });
                    toArray(root.querySelectorAll('.mj-member-events-calendar__event-menu')).forEach(function(openMenu) {
                        openMenu.hidden = true;
                    });
                    toArray(root.querySelectorAll('.mj-member-events-calendar__event-menu-toggle[aria-expanded="true"]')).forEach(function(openToggle) {
                        openToggle.setAttribute('aria-expanded', 'false');
                    });

                    if (menu && !isOpen) {
                        menu.hidden = false;
                        if (actions) {
                            actions.classList.add('is-open');
                        }
                        menuToggle.setAttribute('aria-expanded', 'true');
                    }
                    return;
                }

                var addBtn = e.target.closest('[data-calendar-create-day]');
                if (addBtn && ccmInstance) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Close mobile modal if the button is inside it
                    if (mobileModal && !mobileModal.hidden && addBtn.closest('[data-calendar-mobile-modal]')) {
                        closeMobileModal();
                    }
                    ccmInstance.open(addBtn.getAttribute('data-calendar-create-day') || '', addBtn);
                    return;
                }

                var occurrenceBtn = e.target.closest('[data-calendar-create-occurrence-day]');
                if (occurrenceBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    openOccurrenceModal(occurrenceBtn.getAttribute('data-calendar-create-occurrence-day') || '');
                    return;
                }

                var taskBtn = e.target.closest('[data-calendar-create-task-day]');
                if (taskBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (mobileModal && !mobileModal.hidden && taskBtn.closest('[data-calendar-mobile-modal]')) {
                        closeMobileModal();
                    }
                    openTaskModal(taskBtn.getAttribute('data-calendar-create-task-day') || '');
                    return;
                }

                var leaveBtn = e.target.closest('[data-calendar-create-leave-day]');
                if (leaveBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (mobileModal && !mobileModal.hidden && leaveBtn.closest('[data-calendar-mobile-modal]')) {
                        closeMobileModal();
                    }
                    openLeaveRequestModal(leaveBtn.getAttribute('data-calendar-create-leave-day') || '');
                    return;
                }

                var noteBtn = e.target.closest('[data-calendar-create-note-day]');
                if (noteBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (mobileModal && !mobileModal.hidden && noteBtn.closest('[data-calendar-mobile-modal]')) {
                        closeMobileModal();
                    }
                    openNoteModal(noteBtn.getAttribute('data-calendar-create-note-day') || '', null);
                    return;
                }

                var notePageBtn = e.target.closest('[data-note-page-nav]');
                if (notePageBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    pageDayNotes(notePageBtn.closest('.mj-member-events-calendar__day-notes'), notePageBtn.getAttribute('data-note-page-nav'));
                    return;
                }

                var noteEditBtn = e.target.closest('[data-note-edit]');
                if (noteEditBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var noteRow = noteEditBtn.closest('.mj-member-events-calendar__day-note');
                    var notesWrapper = noteEditBtn.closest('.mj-member-events-calendar__day-notes');
                    var noteId = noteRow ? noteRow.getAttribute('data-note-id') : null;
                    var currentNote = getDayNoteById(notesWrapper, noteId);
                    if (currentNote) {
                        openNoteModal(currentNote.note_date, currentNote);
                    }
                    return;
                }

                var occurrenceEditBtn = e.target.closest('.mj-member-events-calendar__event-occurrence-edit');
                if (occurrenceEditBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    openExistingOccurrenceEditor(
                        parseInt(occurrenceEditBtn.getAttribute('data-edit-occurrence-event'), 10) || 0,
                        parseInt(occurrenceEditBtn.getAttribute('data-edit-occurrence-ts'), 10) || 0
                    );
                    return;
                }

                var btn = e.target.closest('.mj-member-events-calendar__event-delete');
                if (!btn) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();

                var eventId = parseInt(btn.getAttribute('data-delete-event'), 10);
                var startTs = parseInt(btn.getAttribute('data-delete-ts'), 10);
                if (!eventId || !startTs) {
                    return;
                }

                if (!confirm('Supprimer cette occurrence ? Cette action est irr\u00e9versible.')) {
                    return;
                }

                btn.classList.add('is-loading');
                btn.disabled = true;

                var formData = new FormData();
                formData.append('action', 'mj_calendar_delete_occurrence');
                formData.append('nonce', config.deleteNonce);
                formData.append('event_id', String(eventId));
                formData.append('start_ts', String(startTs));

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        var li = btn.closest('.mj-member-events-calendar__event') || btn.closest('li');
                        if (li) {
                            li.style.transition = 'opacity 0.3s ease';
                            li.style.opacity = '0';
                            setTimeout(function() {
                                li.remove();
                                updateDayStates();
                            }, 300);
                        }
                        var mobileItem = root.querySelector('.mj-member-events-calendar__event-delete[data-delete-event="' + eventId + '"][data-delete-ts="' + startTs + '"]');
                        if (mobileItem && mobileItem !== btn) {
                            var mobileLi = mobileItem.closest('li');
                            if (mobileLi) {
                                mobileLi.style.transition = 'opacity 0.3s ease';
                                mobileLi.style.opacity = '0';
                                setTimeout(function() { mobileLi.remove(); }, 300);
                            }
                        }
                        // Also remove corresponding chip from mobile grid
                        removeMobileChipForEvent(eventId, startTs);
                    } else {
                        var msg = result.data && result.data.message ? result.data.message : 'Erreur lors de la suppression.';
                        alert(msg);
                        btn.classList.remove('is-loading');
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Erreur r\u00e9seau. Veuillez r\u00e9essayer.');
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                });
            });

            document.addEventListener('click', function(e) {
                if (root.contains(e.target) && e.target.closest('.mj-member-events-calendar__event-actions, .mj-cal-mobile__day-actions')) {
                    return;
                }
                closeDayActionMenus();
                toArray(root.querySelectorAll('.mj-member-events-calendar__event-menu')).forEach(function(menu) {
                    menu.hidden = true;
                });
                toArray(root.querySelectorAll('.mj-member-events-calendar__event-actions.is-open')).forEach(function(actions) {
                    actions.classList.remove('is-open');
                });
                toArray(root.querySelectorAll('.mj-member-events-calendar__event-menu-toggle[aria-expanded="true"]')).forEach(function(toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                });
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDayActionMenus();
                }
            });
        }

        // ---- Mobile compact calendar grid: day tap → modal ----
        var mobileModal = root.querySelector('[data-calendar-mobile-modal]');
        var mobileModalDate = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-date') : null;
        var mobileModalBody = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-body') : null;
        var mobileModalClose = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-close') : null;
        var mobileModalBackdrop = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-backdrop') : null;
        var mobileLists = toArray(root.querySelectorAll('[data-calendar-mobile-list]'));

        function formatMobileDayLabel(dayKey) {
            var dateParts = dayKey.split('-');
            var dateObj = new Date(parseInt(dateParts[0], 10), parseInt(dateParts[1], 10) - 1, parseInt(dateParts[2], 10));
            var dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            return dayNames[dateObj.getDay()] + ' ' + dateObj.getDate();
        }

        function setActiveMobileDay(mobileList, dayKey) {
            var mobileCalendar = mobileList.closest('.mj-cal-mobile');
            if (!mobileCalendar) {
                return;
            }
            toArray(mobileCalendar.querySelectorAll('.mj-cal-mobile__day')).forEach(function(dayCell) {
                dayCell.classList.toggle('is-list-active', dayCell.getAttribute('data-calendar-day') === dayKey);
            });
        }

        function appendMobileDayAction(menu, attributeName, dayKey, icon, label, modifierClass) {
            var action = document.createElement('button');
            action.type = 'button';
            action.className = 'mj-cal-mobile__day-menu-action' + (modifierClass ? ' ' + modifierClass : '');
            action.setAttribute('role', 'menuitem');
            action.setAttribute(attributeName, dayKey);
            var iconNode = document.createElement('span');
            iconNode.className = 'mj-cal-mobile__day-menu-icon';
            iconNode.setAttribute('aria-hidden', 'true');
            iconNode.textContent = icon;
            var labelNode = document.createElement('span');
            labelNode.textContent = label;
            action.appendChild(iconNode);
            action.appendChild(labelNode);
            menu.appendChild(action);
        }

        function buildMobileList(mobileList) {
            var mobileCalendar = mobileList.closest('.mj-cal-mobile');
            if (!mobileCalendar) {
                return;
            }
            mobileList.innerHTML = '';
            toArray(mobileCalendar.querySelectorAll('template[data-mobile-day-events]')).forEach(function(tpl) {
                var dayKey = tpl.getAttribute('data-mobile-day-events') || '';
                if (!dayKey) {
                    return;
                }
                var section = document.createElement('section');
                section.className = 'mj-cal-mobile__event-day';
                section.setAttribute('data-calendar-mobile-list-day', dayKey);

                var heading = document.createElement('h4');
                heading.className = 'mj-cal-mobile__event-day-title';
                var headingLabel = document.createElement('span');
                headingLabel.textContent = formatMobileDayLabel(dayKey);
                heading.appendChild(headingLabel);

                var canCreateEvent = !!ccmInstance;
                var canCreateOccurrence = !!(config && config.ajaxUrl && config.deleteNonce);
                var canCreateTask = !!(config && config.todoNonce);
                var canCreateLeave = !!(config && config.canCreateLeaveRequest && window.MjLeaveRequestsWidget && typeof window.MjLeaveRequestsWidget.openCreateModal === 'function');
                var canCreateNote = !!(config && config.noteNonce);

                if (canCreateEvent || canCreateOccurrence || canCreateTask || canCreateLeave || canCreateNote) {
                    var actions = document.createElement('div');
                    actions.className = 'mj-cal-mobile__day-actions';

                    var menuToggle = document.createElement('button');
                    menuToggle.type = 'button';
                    menuToggle.className = 'mj-cal-mobile__day-menu-toggle';
                    menuToggle.setAttribute('aria-label', 'Actions du ' + formatMobileDayLabel(dayKey));
                    menuToggle.setAttribute('aria-expanded', 'false');
                    menuToggle.setAttribute('aria-haspopup', 'menu');
                    menuToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="5" cy="12" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle></svg>';

                    var menu = document.createElement('div');
                    menu.className = 'mj-cal-mobile__day-menu';
                    menu.setAttribute('role', 'menu');
                    menu.hidden = true;
                    if (canCreateEvent) {
                        appendMobileDayAction(menu, 'data-calendar-create-day', dayKey, '+', 'Créer un événement');
                    }
                    if (canCreateOccurrence) {
                        appendMobileDayAction(menu, 'data-calendar-create-occurrence-day', dayKey, '↻', 'Créer une occurrence');
                    }
                    if (canCreateTask) {
                        appendMobileDayAction(menu, 'data-calendar-create-task-day', dayKey, '✓', 'Créer une tâche');
                    }
                    if (canCreateLeave) {
                        appendMobileDayAction(menu, 'data-calendar-create-leave-day', dayKey, '🏖️', 'Créer un congé', 'mj-cal-mobile__day-menu-action--leave');
                    }
                    if (canCreateNote) {
                        appendMobileDayAction(menu, 'data-calendar-create-note-day', dayKey, '📝', 'Créer une note', 'mj-cal-mobile__day-menu-action--note');
                    }

                    actions.appendChild(menuToggle);
                    actions.appendChild(menu);
                    heading.appendChild(actions);
                }
                section.appendChild(heading);
                section.appendChild(document.importNode(tpl.content, true));
                mobileList.appendChild(section);
            });
        }

        function scrollMobileListToDay(mobileList, dayKey, smooth) {
            var section = mobileList.querySelector('[data-calendar-mobile-list-day="' + dayKey + '"]');
            if (!section) {
                return;
            }
            setActiveMobileDay(mobileList, dayKey);
            mobileList.scrollTo({
                top: section.offsetTop - mobileList.offsetTop,
                behavior: smooth === false ? 'auto' : 'smooth'
            });
        }

        function getTodayDayKey() {
            var today = new Date();
            var year = today.getFullYear();
            var month = String(today.getMonth() + 1).padStart(2, '0');
            var day = String(today.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function syncMobileListDay(mobileList) {
            var sections = toArray(mobileList.querySelectorAll('[data-calendar-mobile-list-day]'));
            var listTop = mobileList.getBoundingClientRect().top;
            var activeSection = sections.find(function(section) {
                return section.getBoundingClientRect().bottom > listTop + 40;
            });
            if (activeSection) {
                setActiveMobileDay(mobileList, activeSection.getAttribute('data-calendar-mobile-list-day'));
            }
        }

        mobileLists.forEach(function(mobileList) {
            buildMobileList(mobileList);
            scrollMobileListToDay(mobileList, getTodayDayKey(), false);
            mobileList.addEventListener('scroll', function() {
                syncMobileListDay(mobileList);
            }, { passive: true });
            syncMobileListDay(mobileList);
        });

        function openMobileModal(dayKey) {
            if (!mobileModal || !mobileModalBody) {
                return;
            }
            var tpl = root.querySelector('template[data-mobile-day-events="' + dayKey + '"]');
            mobileModalBody.innerHTML = '';
            if (tpl) {
                var clone = document.importNode(tpl.content, true);
                // Apply active filters to cloned content
                var filterMap = getActiveFilterMap();
                toArray(clone.querySelectorAll('[data-calendar-type-item]')).forEach(function(item) {
                    var typeKey = item.getAttribute('data-calendar-type') || '';
                    var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                    var filteredByType = !!filterMap && isKnown && !Object.prototype.hasOwnProperty.call(filterMap, typeKey);
                    if (filteredByType || !passesNoteTypeFilter(item)) {
                        item.classList.add('is-filtered-out');
                    }
                });
                mobileModalBody.appendChild(clone);
            } else {
                mobileModalBody.innerHTML = '<p class="mj-cal-mobile__modal-empty">Aucun \u00e9v\u00e9nement</p>';
            }
            // Append "Créer un event" button at the bottom when available
            if (ccmInstance) {
                var addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'mj-cal-mobile__modal-create-event';
                addBtn.setAttribute('data-calendar-create-day', dayKey);
                addBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                    '<span>Cr\u00e9er un event</span>';
                mobileModalBody.appendChild(addBtn);
            }
            if (config && config.ajaxUrl && config.deleteNonce) {
                var occurrenceBtn = document.createElement('button');
                occurrenceBtn.type = 'button';
                occurrenceBtn.className = 'mj-cal-mobile__modal-create-event';
                occurrenceBtn.setAttribute('data-calendar-create-occurrence-day', dayKey);
                occurrenceBtn.textContent = 'Créer une occurrence';
                mobileModalBody.appendChild(occurrenceBtn);
            }
            if (config && config.todoNonce) {
                var taskBtn = document.createElement('button');
                taskBtn.type = 'button';
                taskBtn.className = 'mj-cal-mobile__modal-create-event';
                taskBtn.setAttribute('data-calendar-create-task-day', dayKey);
                taskBtn.textContent = 'Créer une tâche';
                mobileModalBody.appendChild(taskBtn);
            }
            if (config && config.canCreateLeaveRequest && window.MjLeaveRequestsWidget && typeof window.MjLeaveRequestsWidget.openCreateModal === 'function') {
                var leaveBtn = document.createElement('button');
                leaveBtn.type = 'button';
                leaveBtn.className = 'mj-cal-mobile__modal-create-event mj-cal-mobile__modal-create-event--leave';
                leaveBtn.setAttribute('data-calendar-create-leave-day', dayKey);
                leaveBtn.textContent = '🏖️ Créer un congé';
                mobileModalBody.appendChild(leaveBtn);
            }
            if (config && config.noteNonce) {
                var noteBtnMobile = document.createElement('button');
                noteBtnMobile.type = 'button';
                noteBtnMobile.className = 'mj-cal-mobile__modal-create-event mj-cal-mobile__modal-create-event--note';
                noteBtnMobile.setAttribute('data-calendar-create-note-day', dayKey);
                noteBtnMobile.textContent = '📝 Créer une note';
                mobileModalBody.appendChild(noteBtnMobile);
            }
            if (mobileModalDate) {
                var dateParts = dayKey.split('-');
                var dateObj = new Date(parseInt(dateParts[0], 10), parseInt(dateParts[1], 10) - 1, parseInt(dateParts[2], 10));
                var dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                var monthNames = ['janvier', 'f\u00e9vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao\u00fbt', 'septembre', 'octobre', 'novembre', 'd\u00e9cembre'];
                mobileModalDate.textContent = dayNames[dateObj.getDay()] + ' ' + dateObj.getDate() + ' ' + monthNames[dateObj.getMonth()];
            }
            mobileModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeMobileModal() {
            if (!mobileModal) {
                return;
            }
            mobileModal.hidden = true;
            mobileModalBody.innerHTML = '';
            document.body.style.overflow = '';
        }

        function getActiveFilterMap() {
            if (!filterInputsRef || !filterInputsRef.length) {
                return null;
            }
            var map = {};
            var hasChecked = false;
            for (var i = 0; i < filterInputsRef.length; i++) {
                if (filterInputsRef[i].checked) {
                    map[filterInputsRef[i].value] = true;
                    hasChecked = true;
                }
            }
            return hasChecked ? map : null;
        }

        // Lazy ref to filterInputs (set after var declarations below)
        var filterInputsRef = null;
        // Lazy ref to the note-type sub-filter checkboxes (set after var declarations below)
        var noteTypeFilterInputsRef = null;

        function removeMobileChipForEvent(eventId, startTs) {
            // Find and remove the chip in the mobile grid that corresponds to the deleted event
            // Chips don't have direct event ID data, so we find the template, check its contents,
            // and remove matching chips by counting within the day
            var tpls = toArray(root.querySelectorAll('template[data-mobile-day-events]'));
            tpls.forEach(function(tpl) {
                var delBtns = tpl.content.querySelectorAll('.mj-member-events-calendar__event-delete[data-delete-event="' + eventId + '"][data-delete-ts="' + startTs + '"]');
                if (delBtns.length > 0) {
                    // Remove the <li> in the template
                    toArray(delBtns).forEach(function(delBtn) {
                        var li = delBtn.closest('li');
                        if (li) {
                            li.remove();
                        }
                    });
                    // Also remove the chip from the visible grid (by index - find index of removed event)
                    var dayKey = tpl.getAttribute('data-mobile-day-events');
                    var dayCell = root.querySelector('.mj-cal-mobile__day[data-calendar-day="' + dayKey + '"]');
                    if (dayCell) {
                        var remainingEvents = tpl.content.querySelectorAll('.mj-member-events-calendar__mobile-event');
                        var chips = toArray(dayCell.querySelectorAll('.mj-cal-mobile__chip'));
                        // Rebuild chips to match remaining events
                        if (chips.length > remainingEvents.length) {
                            // Remove last chip (simplistic approach - works when only one deleted at a time)
                            for (var c = chips.length - 1; c >= remainingEvents.length; c--) {
                                chips[c].remove();
                            }
                        }
                        if (!remainingEvents.length) {
                            dayCell.classList.remove('has-events');
                        }
                    }
                }
            });
        }

        if (mobileLists.length) {
            // Day cell click handler
            root.addEventListener('click', function(e) {
                var dayCell = e.target.closest('.mj-cal-mobile__day');
                if (!dayCell) {
                    return;
                }
                var dayKey = dayCell.getAttribute('data-calendar-day');
                if (!dayKey) {
                    return;
                }

                if (dayCell.classList.contains('is-padding')) {
                    return;
                }

                var mobileCalendar = dayCell.closest('.mj-cal-mobile');
                var mobileList = mobileCalendar ? mobileCalendar.querySelector('[data-calendar-mobile-list]') : null;
                if (mobileList && mobileCalendar.querySelector('template[data-mobile-day-events="' + dayKey + '"]')) {
                    scrollMobileListToDay(mobileList, dayKey);
                }
            });
        }

        var months = toArray(root.querySelectorAll('[data-calendar-month]'));
        if (!months.length) {
            return;
        }

        var monthIndexMap = {};
        months.forEach(function(month, idx) {
            var key = month.getAttribute('data-calendar-month');
            if (key) {
                monthIndexMap[key] = idx;
            }
        });

        var filterInputs = toArray(root.querySelectorAll('[data-calendar-filter]'));
        filterInputsRef = filterInputs;
        var noteTypeFilterInputs = toArray(root.querySelectorAll('[data-calendar-note-type-filter]'));
        noteTypeFilterInputsRef = noteTypeFilterInputs;
        var noteTypeFiltersWrap = root.querySelector('[data-calendar-note-type-filters]');
        var noteMainFilterInput = null;
        filterInputs.forEach(function (input) {
            if (input.value === 'note') {
                noteMainFilterInput = input;
            }
        });

        function syncNoteTypeFiltersVisibility() {
            if (!noteTypeFiltersWrap) {
                return;
            }
            noteTypeFiltersWrap.hidden = !(noteMainFilterInput && noteMainFilterInput.checked);
        }
        syncNoteTypeFiltersVisibility();

        var typeItems = toArray(root.querySelectorAll('[data-calendar-type-item]'));
        var dayNodes = toArray(root.querySelectorAll('[data-calendar-day]'));
        var countSingular = root.getAttribute('data-calendar-count-singular') || '%d';
        var countPlural = root.getAttribute('data-calendar-count-plural') || '%d';
        var countEmpty = root.getAttribute('data-calendar-count-empty') || '';

        var prev = root.querySelector('[data-calendar-nav="prev"]');
        var next = root.querySelector('[data-calendar-nav="next"]');
        var label = root.querySelector('[data-calendar-active-label]');
        var todayBtn = root.querySelector('[data-calendar-action="today"]');
        var filtersToggle = root.querySelector('[data-calendar-action="toggle-filters"]');
        var toolbar = root.querySelector('.mj-member-events-calendar__toolbar');
        var openPrintBtn = root.querySelector('[data-calendar-action="open-print"]');
        var printNowBtn = root.querySelector('[data-calendar-action="print-now"]');
        var saveImageBtn = root.querySelector('[data-calendar-action="save-image"]');
        var printModal = root.querySelector('[data-calendar-print-modal]');
        var printPreviewFrame = root.querySelector('[data-calendar-print-preview]');
        var printCloseBtns = toArray(root.querySelectorAll('[data-calendar-print-close]'));
        var printModeInput = root.querySelector('[data-print-option="mode"]');
        var printMonthInput = root.querySelector('[data-print-option="month"]');
        var printMonthYearInput = root.querySelector('[data-print-option="month-year"]');
        var printWeekInput = root.querySelector('[data-print-option="week"]');
        var printWeekYearInput = root.querySelector('[data-print-option="week-year"]');
        var printDayInput = root.querySelector('[data-print-option="day"]');
        var printMonthPickerWrap = root.querySelector('[data-print-month-picker]');
        var printMonthYearPickerWrap = root.querySelector('[data-print-month-year-picker]');
        var printWeekPickerWrap = root.querySelector('[data-print-week-picker]');
        var printWeekYearPickerWrap = root.querySelector('[data-print-week-year-picker]');
        var printDayPickerWrap = root.querySelector('[data-print-day-picker]');
        var printPadPageInput = root.querySelector('[data-print-option="pad-page"]');
        var printPadDayInput = root.querySelector('[data-print-option="pad-day"]');
        var printPadEventInput = root.querySelector('[data-print-option="pad-event"]');
        var printTextSizeInput = root.querySelector('[data-print-option="text-size"]');
        var printThemeInput = root.querySelector('[data-print-option="theme"]');
        var printSpanInput = root.querySelector('[data-print-option="span"]');
        var printDayColumnsInput = root.querySelector('[data-print-option="day-columns"]');
        var printDayColumnsPickerWrap = root.querySelector('[data-print-day-columns-picker]');
        var printDetailsInput = root.querySelector('[data-print-option="details"]');
        var printCoverInput = root.querySelector('[data-print-option="cover"]');
        var printTimeRangeInput = root.querySelector('[data-print-option="time-range"]');
        var printEventEmojiInput = root.querySelector('[data-print-option="event-emoji"]');
        var printEventColorInput = root.querySelector('[data-print-option="event-color"]');
        var printBadgesInput = root.querySelector('[data-print-option="badges"]');
        var printHeaderImageInput = root.querySelector('[data-print-option="header-image"]');
        var printFooterImageInput = root.querySelector('[data-print-option="footer-image"]');
        var printHeaderImageSource = root.querySelector('[data-print-image-source="header"]');
        var printFooterImageSource = root.querySelector('[data-print-image-source="footer"]');
        var printHeaderImageUrlInput = root.querySelector('[data-print-image-url="header"]');
        var printFooterImageUrlInput = root.querySelector('[data-print-image-url="footer"]');
        var printPageBreakInput = root.querySelector('[data-print-option="page-break"]');
        var printHideEmptyDaysInput = root.querySelector('[data-print-option="hide-empty-days"]');
        var printReduceEmptyDaysInput = root.querySelector('[data-print-option="reduce-empty-days"]');
        var printPeriodTitlesWrap = root.querySelector('[data-print-period-titles]');
        if (!printReduceEmptyDaysInput && printHideEmptyDaysInput && printHideEmptyDaysInput.parentElement) {
            var reduceEmptyDaysLabel = document.createElement('label');
            reduceEmptyDaysLabel.className = 'mj-cal-print__option';
            reduceEmptyDaysLabel.innerHTML = '<input type="checkbox" data-print-option="reduce-empty-days" /><span>Réduire la taille des jours vides</span>';
            printHideEmptyDaysInput.parentElement.insertAdjacentElement('afterend', reduceEmptyDaysLabel);
            printReduceEmptyDaysInput = reduceEmptyDaysLabel.querySelector('[data-print-option="reduce-empty-days"]');
        }
        var printPageBreakLabel = root.querySelector('[data-print-option-page-break-label]');
        var printTypeFiltersWrap = root.querySelector('[data-print-type-filters]');
        var printPresetSelect = root.querySelector('[data-print-preset-select]');
        var printPresetNameInput = root.querySelector('[data-print-preset-name]');
        var savePrintPresetBtn = root.querySelector('[data-calendar-action="save-print-preset"]');
        var deletePrintPresetBtn = root.querySelector('[data-calendar-action="delete-print-preset"]');
        var printTypeFilterInputs = [];
        var printSelectedMonthKey = '';
        var printSelectedWeekStartKey = '';
        var printSelectedDayKeys = [];
        var printMonthEntries = [];
        var printWeekEntries = [];
        var printDayEntries = [];
        var printConfig = (config && config.print) ? config.print : {};
        var printImageHistory = Array.isArray(printConfig.imageHistory) ? printConfig.imageHistory : [];
        var printPrefsEnabled = !!(printConfig && printConfig.userPrefsEnabled && printConfig.ajaxUrl && printConfig.prefsNonce);
        var printPrefsFromServer = (printConfig && printConfig.userPrefs && typeof printConfig.userPrefs === 'object') ? printConfig.userPrefs : null;
        var printPrefsSaveTimer = null;
        var printPresets = Array.isArray(printConfig.presets) ? printConfig.presets : [];
        var isApplyingPrintPrefs = false;
        var hasAppliedPrintPrefs = false;
        var lastPrintSnapshot = null;
        var printTitleOverrides = {};
        var printTitleSignature = '';
        var todayMonthKey = root.getAttribute('data-calendar-today') || '';
        if (config && config.todayMonth) {
            todayMonthKey = config.todayMonth;
        }
        var activeIndex = 0;
        var weekOnly = root.getAttribute('data-calendar-week-only') === '1';
        var weekDaysAttr = root.getAttribute('data-calendar-week-days') || '';
        var weekDays = weekOnly && weekDaysAttr ? weekDaysAttr.split(',').map(function(item) {
            return item.trim();
        }).filter(function(value) {
            return value.length > 0;
        }) : [];

        if (weekOnly && weekDays.length) {
            var weekDaySet = {};
            weekDays.forEach(function(dayKey) {
                weekDaySet[dayKey] = true;
            });

            var visibleMonths = [];
            months.forEach(function(month) {
                var keepMonth = false;
                var weeks = toArray(month.querySelectorAll('.mj-member-events-calendar__week'));
                weeks.forEach(function(week) {
                    var containsTargetDay = false;
                    var dayCells = toArray(week.querySelectorAll('[data-calendar-day]'));
                    dayCells.forEach(function(dayCell) {
                        var dayKey = dayCell.getAttribute('data-calendar-day');
                        if (dayKey && Object.prototype.hasOwnProperty.call(weekDaySet, dayKey)) {
                            containsTargetDay = true;
                        }
                    });

                    if (!containsTargetDay) {
                        week.style.display = 'none';
                    } else {
                        keepMonth = true;
                    }
                });

                if (keepMonth) {
                    visibleMonths.push(month);
                } else {
                    month.style.display = 'none';
                    month.classList.remove('is-active');
                }
            });

            if (visibleMonths.length) {
                months = visibleMonths;
                monthIndexMap = {};
                months.forEach(function(month, idx) {
                    var key = month.getAttribute('data-calendar-month');
                    if (key) {
                        monthIndexMap[key] = idx;
                    }
                });
            }

            activeIndex = 0;
            root.setAttribute('data-calendar-preferred', '0');
            if (prev) {
                prev.disabled = true;
                prev.style.display = 'none';
            }
            if (next) {
                next.disabled = true;
                next.style.display = 'none';
            }
            if (todayBtn) {
                todayBtn.disabled = true;
                todayBtn.style.display = 'none';
            }
        }

        var todayIndex = -1;
        if (todayMonthKey && Object.prototype.hasOwnProperty.call(monthIndexMap, todayMonthKey)) {
            todayIndex = monthIndexMap[todayMonthKey];
        }

        activeIndex = 0;
        var preferred = parseInt(root.getAttribute('data-calendar-preferred'), 10);
        if (!isNaN(preferred) && preferred >= 0 && preferred < months.length) {
            activeIndex = preferred;
        } else if (config && typeof config.preferredIndex === 'number' && config.preferredIndex >= 0 && config.preferredIndex < months.length) {
            activeIndex = config.preferredIndex;
        }

        function updateDayStates() {
            if (!dayNodes.length) {
                return;
            }
            dayNodes.forEach(function(dayNode) {
                var items = dayNode.querySelectorAll('[data-calendar-type-item]');
                var visibleCount = 0;
                toArray(items).forEach(function(item) {
                    if (item.hasAttribute('data-calendar-count-exclude')) {
                        return;
                    }
                    if (!item.classList.contains('is-filtered-out')) {
                        visibleCount += 1;
                    }
                });
                if (visibleCount === 0) {
                    dayNode.classList.add('is-filtered-empty');
                } else {
                    dayNode.classList.remove('is-filtered-empty');
                }
                var mobileCalendar = dayNode.classList.contains('mj-cal-mobile__day') ? dayNode.closest('.mj-cal-mobile') : null;
                var mobileList = mobileCalendar ? mobileCalendar.querySelector('[data-calendar-mobile-list]') : null;
                var mobileListDay = mobileList
                    ? mobileList.querySelector('[data-calendar-mobile-list-day="' + dayNode.getAttribute('data-calendar-day') + '"]')
                    : null;
                if (mobileListDay) {
                    mobileListDay.classList.toggle('is-filtered-empty', visibleCount === 0);
                }
                var countNode = dayNode.querySelector('[data-calendar-day-count]');
                if (countNode) {
                    var countLabel;
                    if (visibleCount === 0) {
                        countLabel = countEmpty || '';
                    } else if (visibleCount === 1) {
                        countLabel = countSingular.replace('%d', '1');
                    } else {
                        countLabel = countPlural.replace('%d', String(visibleCount));
                    }
                    countNode.textContent = countLabel;
                }
            });
        }

        function applyFilters() {
            if (!filterInputs.length) {
                updateDayStates();
                refreshPrintPreview();
                return;
            }

            var activeMap = {};
            var hasChecked = false;
            filterInputs.forEach(function(input) {
                if (input.checked) {
                    activeMap[input.value] = true;
                    hasChecked = true;
                }
            });

            typeItems.forEach(function(item) {
                var typeKey = item.getAttribute('data-calendar-type') || '';
                var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                var passesType = !hasChecked || !isKnown || Object.prototype.hasOwnProperty.call(activeMap, typeKey);
                if (passesType && passesNoteTypeFilter(item)) {
                    item.classList.remove('is-filtered-out');
                } else {
                    item.classList.add('is-filtered-out');
                }
            });

            updateDayStates();
            refreshPrintPreview();
        }

        function escapeHtml(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function startOfWeekMonday(date) {
            var start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            var day = start.getDay();
            var diff = (day + 6) % 7;
            start.setDate(start.getDate() - diff);
            start.setHours(0, 0, 0, 0);
            return start;
        }

        function addDays(date, days) {
            var nextDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            nextDate.setDate(nextDate.getDate() + days);
            return nextDate;
        }

        function formatDayKey(date) {
            var y = String(date.getFullYear());
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        function getPrintMode() {
            if (!printModeInput) {
                return 'week';
            }
            return printModeInput.value === 'month' || printModeInput.value === 'day' ? printModeInput.value : 'week';
        }

        function normalizePrintTheme(theme) {
            var value = String(theme || '').trim();
            if (
                value === 'light'
                || value === 'dark'
                || value === 'dark-light-days'
                || value === 'light-dark-days'
            ) {
                return value;
            }
            return 'light';
        }

        function getPrintTheme() {
            if (printThemeInput) {
                return normalizePrintTheme(printThemeInput.value);
            }
            return normalizePrintTheme(printConfig && printConfig.defaultTheme ? printConfig.defaultTheme : 'light');
        }

        function parseMonthKey(monthKey) {
            if (!/^\d{4}-\d{2}$/.test(monthKey || '')) {
                return null;
            }
            var parts = monthKey.split('-');
            var year = parseInt(parts[0], 10);
            var monthIndex = parseInt(parts[1], 10) - 1;
            if (isNaN(year) || isNaN(monthIndex) || monthIndex < 0 || monthIndex > 11) {
                return null;
            }
            return new Date(year, monthIndex, 1);
        }

        function parseDayKey(dayKey) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey || '')) {
                return null;
            }
            var parts = dayKey.split('-');
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) - 1;
            var d = parseInt(parts[2], 10);
            if (isNaN(y) || isNaN(m) || isNaN(d)) {
                return null;
            }
            return new Date(y, m, d);
        }

        function getIsoWeekInfo(dateObj) {
            var tmp = new Date(Date.UTC(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate()));
            var weekday = tmp.getUTCDay() || 7;
            tmp.setUTCDate(tmp.getUTCDate() + 4 - weekday);
            var yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
            var week = Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7);
            return {
                year: tmp.getUTCFullYear(),
                week: week
            };
        }

        function createMonthLabel(dateObj) {
            try {
                return dateObj.toLocaleDateString('fr-BE', { month: 'long' });
            } catch (error) {
                return String(dateObj.getMonth() + 1);
            }
        }

        function getDistinctYears(entries) {
            var out = [];
            var seen = {};
            entries.forEach(function(entry) {
                var key = String(entry.year);
                if (Object.prototype.hasOwnProperty.call(seen, key)) {
                    return;
                }
                seen[key] = true;
                out.push(entry.year);
            });
            out.sort(function(a, b) {
                return a - b;
            });
            return out;
        }

        function rebuildSelectOptions(selectEl, options, selectedValue) {
            if (!selectEl) {
                return;
            }
            selectEl.innerHTML = '';
            options.forEach(function(opt) {
                var optionEl = document.createElement('option');
                optionEl.value = String(opt.value);
                optionEl.textContent = String(opt.label);
                if (String(opt.value) === String(selectedValue)) {
                    optionEl.selected = true;
                }
                selectEl.appendChild(optionEl);
            });
        }

        function buildPrintPeriodEntries() {
            var monthSeen = {};
            var monthList = [];
            months.forEach(function(monthEl) {
                var monthKey = (monthEl.getAttribute('data-calendar-month') || '').trim();
                if (!monthKey || Object.prototype.hasOwnProperty.call(monthSeen, monthKey)) {
                    return;
                }
                var monthDate = parseMonthKey(monthKey);
                if (!monthDate) {
                    return;
                }
                monthSeen[monthKey] = true;
                monthList.push({
                    key: monthKey,
                    year: monthDate.getFullYear(),
                    month: monthDate.getMonth() + 1,
                    monthLabel: createMonthLabel(monthDate),
                    sortKey: monthKey
                });
            });
            monthList.sort(function(a, b) {
                return a.sortKey.localeCompare(b.sortKey);
            });
            printMonthEntries = monthList;

            var weekSeen = {};
            var weekList = [];
            dayNodes.forEach(function(dayNode) {
                var dayKey = (dayNode.getAttribute('data-calendar-day') || '').trim();
                var dayDate = parseDayKey(dayKey);
                if (!dayDate) {
                    return;
                }
                var weekStart = startOfWeekMonday(dayDate);
                var weekStartKey = formatDayKey(weekStart);
                if (Object.prototype.hasOwnProperty.call(weekSeen, weekStartKey)) {
                    return;
                }
                weekSeen[weekStartKey] = true;
                var iso = getIsoWeekInfo(weekStart);
                weekList.push({
                    key: weekStartKey,
                    year: iso.year,
                    week: iso.week,
                    weekLabel: 'S' + String(iso.week).padStart(2, '0'),
                    sortDate: weekStart.getTime()
                });
            });
            weekList.sort(function(a, b) {
                return a.sortDate - b.sortDate;
            });
            printWeekEntries = weekList;

            var daySeen = {};
            printDayEntries = dayNodes.map(function(dayNode) {
                var key = (dayNode.getAttribute('data-calendar-day') || '').trim();
                var date = parseDayKey(key);
                if (!key || !date || daySeen[key]) {
                    return null;
                }
                daySeen[key] = true;
                return { key: key, date: date, label: formatDateLabel(date, true) };
            }).filter(function(entry) { return !!entry; }).sort(function(a, b) { return a.key.localeCompare(b.key); });
        }

        function setDefaultPrintPeriodSelection() {
            var activeMonthEl = months[activeIndex] || months[0] || null;
            var activeMonthKey = activeMonthEl ? (activeMonthEl.getAttribute('data-calendar-month') || '') : '';
            if (!activeMonthKey && printMonthEntries.length) {
                activeMonthKey = printMonthEntries[0].key;
            }

            if (activeMonthKey) {
                printSelectedMonthKey = activeMonthKey;
                var monthDate = parseMonthKey(activeMonthKey);
                if (monthDate) {
                    var weekStart = startOfWeekMonday(monthDate);
                    var weekStartKey = formatDayKey(weekStart);
                    printSelectedWeekStartKey = weekStartKey;
                }
            }

            if (!printSelectedWeekStartKey && printWeekEntries.length) {
                printSelectedWeekStartKey = printWeekEntries[0].key;
            }
            if (!printSelectedDayKeys.length && printDayEntries.length) {
                printSelectedDayKeys = [printDayEntries[0].key];
            }
        }

        function renderPrintPeriodSelectors() {
            var mode = getPrintMode();

            if (printMonthPickerWrap) {
                printMonthPickerWrap.hidden = mode !== 'month';
            }
            if (printMonthYearPickerWrap) {
                printMonthYearPickerWrap.hidden = mode !== 'month';
            }
            if (printWeekPickerWrap) {
                printWeekPickerWrap.hidden = mode !== 'week';
            }
            if (printWeekYearPickerWrap) {
                printWeekYearPickerWrap.hidden = mode !== 'week';
            }
            if (printDayPickerWrap) {
                printDayPickerWrap.hidden = mode !== 'day';
            }
            if (printDayColumnsPickerWrap) {
                printDayColumnsPickerWrap.hidden = mode !== 'day';
            }
            if (printSpanInput && printSpanInput.parentElement) {
                printSpanInput.parentElement.hidden = false;
                var spanLabel = printSpanInput.parentElement.querySelector('span');
                if (spanLabel) {
                    spanLabel.textContent = mode === 'day' ? 'Nombre de jours' : 'Nombre d\'unités';
                }
            }

            if (printMonthYearInput && printMonthInput) {
                var monthYears = getDistinctYears(printMonthEntries);
                var selectedMonthEntry = null;
                if (printSelectedMonthKey) {
                    selectedMonthEntry = printMonthEntries.find(function(entry) {
                        return entry.key === printSelectedMonthKey;
                    }) || null;
                }
                var selectedMonthYear = '';
                if (printMonthYearInput.value) {
                    selectedMonthYear = printMonthYearInput.value;
                } else if (selectedMonthEntry) {
                    selectedMonthYear = selectedMonthEntry.year;
                } else {
                    selectedMonthYear = monthYears.length ? monthYears[0] : '';
                }

                rebuildSelectOptions(printMonthYearInput, monthYears.map(function(year) {
                    return { value: String(year), label: String(year) };
                }), String(selectedMonthYear));

                var monthEntriesForYear = printMonthEntries.filter(function(entry) {
                    return String(entry.year) === String(selectedMonthYear);
                });
                rebuildSelectOptions(printMonthInput, monthEntriesForYear.map(function(entry) {
                    return {
                        value: entry.key,
                        label: entry.monthLabel
                    };
                }), printSelectedMonthKey);

                if (monthEntriesForYear.length) {
                    var hasSelectedMonth = monthEntriesForYear.some(function(entry) {
                        return entry.key === printSelectedMonthKey;
                    });
                    if (!hasSelectedMonth) {
                        printSelectedMonthKey = monthEntriesForYear[0].key;
                        printMonthInput.value = printSelectedMonthKey;
                    }
                }
            }

            if (printWeekYearInput && printWeekInput) {
                var weekYears = getDistinctYears(printWeekEntries);
                var selectedWeekEntry = null;
                if (printSelectedWeekStartKey) {
                    selectedWeekEntry = printWeekEntries.find(function(entry) {
                        return entry.key === printSelectedWeekStartKey;
                    }) || null;
                }
                var selectedWeekYear = '';
                if (printWeekYearInput.value) {
                    selectedWeekYear = printWeekYearInput.value;
                } else if (selectedWeekEntry) {
                    selectedWeekYear = selectedWeekEntry.year;
                } else {
                    selectedWeekYear = weekYears.length ? weekYears[0] : '';
                }

                rebuildSelectOptions(printWeekYearInput, weekYears.map(function(year) {
                    return { value: String(year), label: String(year) };
                }), String(selectedWeekYear));

                var weekEntriesForYear = printWeekEntries.filter(function(entry) {
                    return String(entry.year) === String(selectedWeekYear);
                });
                rebuildSelectOptions(printWeekInput, weekEntriesForYear.map(function(entry) {
                    return {
                        value: entry.key,
                        label: entry.weekLabel
                    };
                }), printSelectedWeekStartKey);

                if (weekEntriesForYear.length) {
                    var hasSelectedWeek = weekEntriesForYear.some(function(entry) {
                        return entry.key === printSelectedWeekStartKey;
                    });
                    if (!hasSelectedWeek) {
                        printSelectedWeekStartKey = weekEntriesForYear[0].key;
                        printWeekInput.value = printSelectedWeekStartKey;
                    }
                }
            }

            if (printDayInput) {
                printDayInput.innerHTML = '';
                printDayEntries.forEach(function(entry) {
                    var option = document.createElement('option');
                    option.value = entry.key;
                    option.textContent = entry.label;
                    option.selected = printSelectedDayKeys.indexOf(entry.key) !== -1;
                    printDayInput.appendChild(option);
                });
            }
        }

        function getSelectedDayKeys() {
            if (!printDayInput) {
                return printSelectedDayKeys;
            }
            var value = printDayInput.value;
            return value ? [value] : printSelectedDayKeys;
        }
        function getPrintDayColumns() {
            var value = printDayColumnsInput ? parseInt(printDayColumnsInput.value, 10) : 5;
            return Math.max(1, Math.min(5, Number.isFinite(value) ? value : 5));
        }

        function collectDayPeriods(withDetails, withCover, selectedTypesMap, selectedDayKeys, removeEmptyDays) {
            var cells = [];
            var firstDay = selectedDayKeys.length ? parseDayKey(selectedDayKeys[0]) : null;
            var dayCount = getPrintSpan();
            for (var dayIndex = 0; firstDay && dayIndex < dayCount; dayIndex += 1) {
                var dayDate = addDays(firstDay, dayIndex);
                var dayKey = formatDayKey(dayDate);
                var dayNode = getDayNodeByKey(dayKey);
                if (!dayDate || !dayNode) {
                    continue;
                }
                var events = collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap);
                if (removeEmptyDays && !events.length) {
                    continue;
                }
                cells.push({
                    isPadding: false,
                    dayNumber: String(dayDate.getDate()),
                    dayHeading: formatDayHeading(dayDate),
                    label: formatDateLabel(dayDate, true),
                    events: events
                });
            }
            return cells.length ? [{
                title: uppercaseFirstLetter(createMonthLabel(firstDay)),
                weeks: [{ cells: cells }]
            }] : [];
        }

        function getSelectedMonthStartIndex() {
            if (printSelectedMonthKey && Object.prototype.hasOwnProperty.call(monthIndexMap, printSelectedMonthKey)) {
                return monthIndexMap[printSelectedMonthKey];
            }
            return activeIndex;
        }

        function getSelectedWeekAnchorDate() {
            if (printSelectedWeekStartKey) {
                var selected = parseDayKey(printSelectedWeekStartKey);
                if (selected) {
                    return startOfWeekMonday(selected);
                }
            }

            var monthEl = months[activeIndex] || months[0];
            var monthKey = monthEl ? (monthEl.getAttribute('data-calendar-month') || '') : '';
            var monthDate = parseMonthKey(monthKey);
            if (!monthDate) {
                return null;
            }
            return startOfWeekMonday(monthDate);
        }

        function getPrintSpan() {
            var defaultSpan = (printConfig && typeof printConfig.defaultSpan === 'number') ? printConfig.defaultSpan : 1;
            var span = printSpanInput ? parseInt(printSpanInput.value, 10) : defaultSpan;
            if (isNaN(span)) {
                span = defaultSpan;
            }
            if (span < 1) {
                span = 1;
            }
            var maxSpan = getPrintMode() === 'day' ? 31 : 12;
            if (span > maxSpan) {
                span = maxSpan;
            }
            if (printSpanInput) {
                printSpanInput.value = String(span);
            }
            return span;
        }

        function getPrintPaddingValue(input, min, max, fallback) {
            var value = input ? parseInt(input.value, 10) : fallback;
            if (isNaN(value)) {
                value = fallback;
            }
            if (value < min) {
                value = min;
            }
            if (value > max) {
                value = max;
            }
            if (input) {
                input.value = String(value);
            }
            return value;
        }

        function isDetailsEnabled() {
            return !!(printDetailsInput && printDetailsInput.checked);
        }

        function isCoverEnabled() {
            return !!(printCoverInput && printCoverInput.checked);
        }

        function isTimeRangeEnabled() {
            if (printTimeRangeInput) {
                return !!printTimeRangeInput.checked;
            }
            return !printConfig || typeof printConfig.defaultTimeRange === 'undefined' ? true : !!printConfig.defaultTimeRange;
        }

        function isPageBreakEnabled() {
            return !!(printPageBreakInput && printPageBreakInput.checked);
        }

        function isHideEmptyDaysEnabled() {
            if (printHideEmptyDaysInput) {
                return !!printHideEmptyDaysInput.checked;
            }
            return !!(printConfig && printConfig.defaultHideEmptyDays);
        }

        function isReduceEmptyDaysEnabled() {
            return !!(printReduceEmptyDaysInput && printReduceEmptyDaysInput.checked && !isHideEmptyDaysEnabled());
        }

        function syncReduceEmptyDaysOption() {
            if (printReduceEmptyDaysInput) {
                printReduceEmptyDaysInput.disabled = isHideEmptyDaysEnabled();
            }
        }

        function isEventEmojiEnabled() {
            if (printEventEmojiInput) {
                return !!printEventEmojiInput.checked;
            }
            if (!printConfig) {
                return true;
            }
            if (typeof printConfig.defaultEventEmoji !== 'undefined') {
                return !!printConfig.defaultEventEmoji;
            }
            return typeof printConfig.defaultEventStyle === 'undefined' ? true : !!printConfig.defaultEventStyle;
        }

        function isEventColorEnabled() {
            if (printEventColorInput) {
                return !!printEventColorInput.checked;
            }
            if (!printConfig) {
                return true;
            }
            if (typeof printConfig.defaultEventColor !== 'undefined') {
                return !!printConfig.defaultEventColor;
            }
            return typeof printConfig.defaultEventStyle === 'undefined' ? true : !!printConfig.defaultEventStyle;
        }

        function isBadgesEnabled() {
            if (printBadgesInput) {
                return !!printBadgesInput.checked;
            }
            return !printConfig || typeof printConfig.defaultBadges === 'undefined' ? true : !!printConfig.defaultBadges;
        }

        function isHeaderImageEnabled() {
            if (printHeaderImageInput) {
                return !!printHeaderImageInput.checked;
            }
            return !!(printConfig && printConfig.defaultHeaderImage && printConfig.headerImageUrl);
        }

        function isFooterImageEnabled() {
            if (printFooterImageInput) {
                return !!printFooterImageInput.checked;
            }
            return !!(printConfig && printConfig.defaultFooterImage && printConfig.footerImageUrl);
        }

        function getPrintImageUrl(slot) {
            var source = slot === 'header' ? printHeaderImageSource : printFooterImageSource;
            var fallback = slot === 'header' ? printConfig.headerImageUrl : printConfig.footerImageUrl;
            var selectedUrl = source ? (source.getAttribute('data-selected-url') || '') : '';
            return selectedUrl || (fallback || '');
        }

        function renderPrintImageHistory() {
            [
                { picker: printHeaderImageSource, url: printConfig.headerImageUrl, label: 'Image d’en-tête' },
                { picker: printFooterImageSource, url: printConfig.footerImageUrl, label: 'Image de pied de page' }
            ].forEach(function(item) {
                if (!item.picker) {
                    return;
                }
                var selected = item.picker.getAttribute('data-selected-url') || '';
                var selectedKey = selected || item.url || '';
                item.picker.innerHTML = '';
                var images = [];
                if (item.url) {
                    images.push({ url: item.url, label: item.label + ' par défaut' });
                }
                printImageHistory.forEach(function(image) {
                    if (!image || !image.url || image.url === item.url) {
                        return;
                    }
                    images.push({ url: String(image.url), label: String(image.label || image.url) });
                });
                images.forEach(function(image) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'mj-cal-print__image-choice' + (selectedKey === image.url ? ' is-selected' : '');
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', selectedKey === image.url ? 'true' : 'false');
                    button.setAttribute('data-image-url', image.url);
                    button.title = image.label;
                    button.innerHTML = '<img src="' + escapeHtml(image.url) + '" alt="' + escapeHtml(image.label) + '" loading="lazy" />';
                    button.addEventListener('click', function() {
                        item.picker.setAttribute('data-selected-url', image.url);
                        renderPrintImageHistory();
                        refreshPrintPreview();
                        queueSavePrintPrefs();
                    });
                    item.picker.appendChild(button);
                });
            });
        }

        function addPrintImage(slot) {
            var input = slot === 'header' ? printHeaderImageUrlInput : printFooterImageUrlInput;
            var picker = slot === 'header' ? printHeaderImageSource : printFooterImageSource;
            var url = input ? input.value.trim() : '';
            if (!/^https?:\/\//i.test(url)) {
                return;
            }
            printImageHistory = printImageHistory.filter(function(image) { return image && image.url !== url; });
            printImageHistory.push({ url: url, label: url.split('/').pop() || url });
            if (printImageHistory.length > 20) {
                printImageHistory = printImageHistory.slice(-20);
            }
            renderPrintImageHistory();
            if (picker) {
                picker.setAttribute('data-selected-url', url);
            }
            refreshPrintPreview();
            queueSavePrintPrefs();
        }

        function uploadPrintImage(slot) {
            if (!window.wp || typeof window.wp.media !== 'function') {
                return;
            }

            var picker = slot === 'header' ? printHeaderImageSource : printFooterImageSource;
            var input = slot === 'header' ? printHeaderImageUrlInput : printFooterImageUrlInput;
            var frame = window.wp.media({
                title: slot === 'header' ? 'Choisir une image d’en-tête' : 'Choisir une image de pied de page',
                button: { text: 'Utiliser cette image' },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment && (attachment.url || (attachment.sizes && attachment.sizes.large && attachment.sizes.large.url));
                if (!url || !picker) {
                    return;
                }
                printImageHistory = printImageHistory.filter(function(image) { return image && image.url !== url; });
                printImageHistory.push({ url: url, label: attachment.filename || attachment.title || url.split('/').pop() || url });
                if (printImageHistory.length > 20) {
                    printImageHistory = printImageHistory.slice(-20);
                }
                picker.setAttribute('data-selected-url', url);
                if (input) {
                    input.value = '';
                }
                renderPrintImageHistory();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            frame.open();
        }

        function formatTypeLabel(typeKey) {
            if (!typeKey) {
                return 'Type';
            }
            return String(typeKey)
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/(^|\s)\S/g, function(c) { return c.toUpperCase(); });
        }

        function getAvailablePrintTypes() {
            var types = [];
            var seen = {};

            if (filterInputs && filterInputs.length) {
                filterInputs.forEach(function(input) {
                    var key = (input.value || '').trim();
                    if (!key || Object.prototype.hasOwnProperty.call(seen, key)) {
                        return;
                    }
                    seen[key] = true;
                    var labelNode = input.parentElement ? input.parentElement.querySelector('span') : null;
                    types.push({
                        key: key,
                        label: labelNode ? (labelNode.textContent || '').trim() : formatTypeLabel(key),
                        checked: !!input.checked
                    });
                });
                if (types.length) {
                    return types;
                }
            }

            typeItems.forEach(function(item) {
                var key = (item.getAttribute('data-calendar-type') || '').trim();
                var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                if (!isKnown || !key || Object.prototype.hasOwnProperty.call(seen, key)) {
                    return;
                }
                seen[key] = true;
                types.push({ key: key, label: formatTypeLabel(key), checked: true });
            });

            return types;
        }

        function renderPrintTypeFilters() {
            if (!printTypeFiltersWrap) {
                return;
            }

            var types = getAvailablePrintTypes();
            var labels = (printConfig && printConfig.labels) ? printConfig.labels : {};
            if (!types.length) {
                printTypeFiltersWrap.hidden = true;
                return;
            }

            printTypeFiltersWrap.hidden = false;
            printTypeFiltersWrap.innerHTML = '<legend>' + escapeHtml(labels.typesLegend || 'Types d\'événement') + '</legend>';
            printTypeFilterInputs = [];

            types.forEach(function(typeDef) {
                var labelEl = document.createElement('label');
                labelEl.className = 'mj-cal-print__option';

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = !!typeDef.checked;
                checkbox.setAttribute('data-print-type-filter', typeDef.key);

                var textSpan = document.createElement('span');
                textSpan.textContent = typeDef.label || formatTypeLabel(typeDef.key);

                labelEl.appendChild(checkbox);
                labelEl.appendChild(textSpan);
                printTypeFiltersWrap.appendChild(labelEl);
                printTypeFilterInputs.push(checkbox);

                checkbox.addEventListener('change', function() {
                    refreshPrintPreview();
                    queueSavePrintPrefs();
                });
            });
        }

        function getSelectedPrintTypesMap() {
            if (!printTypeFilterInputs || !printTypeFilterInputs.length) {
                return null;
            }
            var selected = {};
            var hasSelected = false;
            printTypeFilterInputs.forEach(function(input) {
                if (!input || !input.checked) {
                    return;
                }
                var key = (input.getAttribute('data-print-type-filter') || '').trim();
                if (!key) {
                    return;
                }
                selected[key] = true;
                hasSelected = true;
            });

            if (!hasSelected) {
                return {};
            }

            return selected;
        }

        function applyPrintPrefsToInputs(prefs) {
            if (!prefs || typeof prefs !== 'object') {
                return;
            }

            isApplyingPrintPrefs = true;

            if (printModeInput && (prefs.mode === 'week' || prefs.mode === 'month' || prefs.mode === 'day')) {
                printModeInput.value = prefs.mode;
            }
            if (printThemeInput && typeof prefs.theme !== 'undefined') {
                printThemeInput.value = normalizePrintTheme(prefs.theme);
            }
            if (printSpanInput && typeof prefs.span !== 'undefined') {
                printSpanInput.value = String(prefs.span);
            }
            if (printDayColumnsInput && typeof prefs.dayColumns !== 'undefined') {
                printDayColumnsInput.value = String(prefs.dayColumns);
            }
            if (printDetailsInput && typeof prefs.details !== 'undefined') {
                printDetailsInput.checked = !!prefs.details;
            }
            if (printCoverInput && typeof prefs.cover !== 'undefined') {
                printCoverInput.checked = !!prefs.cover;
            }
            if (printTimeRangeInput && typeof prefs.timeRange !== 'undefined') {
                printTimeRangeInput.checked = !!prefs.timeRange;
            }
            if (printEventEmojiInput && typeof prefs.eventEmoji !== 'undefined') {
                printEventEmojiInput.checked = !!prefs.eventEmoji;
            }
            if (printEventColorInput && typeof prefs.eventColor !== 'undefined') {
                printEventColorInput.checked = !!prefs.eventColor;
            }
            if (printBadgesInput && typeof prefs.badges !== 'undefined') {
                printBadgesInput.checked = !!prefs.badges;
            }
            if (printHeaderImageInput && typeof prefs.headerImage !== 'undefined') {
                printHeaderImageInput.checked = !!prefs.headerImage;
            }
            if (printFooterImageInput && typeof prefs.footerImage !== 'undefined') {
                printFooterImageInput.checked = !!prefs.footerImage;
            }
            if (printHeaderImageSource && typeof prefs.headerImageUrl === 'string') {
                printHeaderImageSource.setAttribute('data-selected-url', prefs.headerImageUrl);
            }
            if (printFooterImageSource && typeof prefs.footerImageUrl === 'string') {
                printFooterImageSource.setAttribute('data-selected-url', prefs.footerImageUrl);
            }
            renderPrintImageHistory();
            if (printPageBreakInput && typeof prefs.pageBreak !== 'undefined') {
                printPageBreakInput.checked = !!prefs.pageBreak;
            }
            if (printHideEmptyDaysInput && typeof prefs.hideEmptyDays !== 'undefined') {
                printHideEmptyDaysInput.checked = !!prefs.hideEmptyDays;
            }
            if (printReduceEmptyDaysInput && typeof prefs.reduceEmptyDays !== 'undefined') {
                printReduceEmptyDaysInput.checked = !!prefs.reduceEmptyDays;
            }
            if (printPadPageInput && typeof prefs.padPage !== 'undefined') {
                printPadPageInput.value = String(prefs.padPage);
            }
            if (printPadDayInput && typeof prefs.padDay !== 'undefined') {
                printPadDayInput.value = String(prefs.padDay);
            }
            if (printPadEventInput && typeof prefs.padEvent !== 'undefined') {
                printPadEventInput.value = String(prefs.padEvent);
            }
            if (printTextSizeInput && typeof prefs.textSize !== 'undefined') {
                printTextSizeInput.value = String(prefs.textSize);
            }

            if (typeof prefs.monthKey === 'string' && /^\d{4}-\d{2}$/.test(prefs.monthKey)) {
                printSelectedMonthKey = prefs.monthKey;
            }
            if (typeof prefs.weekStartKey === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(prefs.weekStartKey)) {
                printSelectedWeekStartKey = prefs.weekStartKey;
            }

            renderPrintPeriodSelectors();

            if (Array.isArray(prefs.selectedTypes) && printTypeFilterInputs && printTypeFilterInputs.length) {
                var selectedMap = {};
                prefs.selectedTypes.forEach(function(typeKey) {
                    var clean = String(typeKey || '').trim();
                    if (clean) {
                        selectedMap[clean] = true;
                    }
                });
                printTypeFilterInputs.forEach(function(input) {
                    var key = (input.getAttribute('data-print-type-filter') || '').trim();
                    input.checked = Object.prototype.hasOwnProperty.call(selectedMap, key);
                });
            }

            isApplyingPrintPrefs = false;
            syncReduceEmptyDaysOption();
        }

        function collectPrintPrefsFromInputs() {
            var selectedTypes = [];
            if (printTypeFilterInputs && printTypeFilterInputs.length) {
                printTypeFilterInputs.forEach(function(input) {
                    if (!input || !input.checked) {
                        return;
                    }
                    var typeKey = (input.getAttribute('data-print-type-filter') || '').trim();
                    if (typeKey) {
                        selectedTypes.push(typeKey);
                    }
                });
            }

            return {
                mode: getPrintMode(),
                theme: getPrintTheme(),
                span: getPrintSpan(),
                details: isDetailsEnabled(),
                cover: isCoverEnabled(),
                timeRange: isTimeRangeEnabled(),
                eventEmoji: isEventEmojiEnabled(),
                eventColor: isEventColorEnabled(),
                badges: isBadgesEnabled(),
                headerImage: isHeaderImageEnabled(),
                footerImage: isFooterImageEnabled(),
                headerImageUrl: getPrintImageUrl('header'),
                footerImageUrl: getPrintImageUrl('footer'),
                pageBreak: isPageBreakEnabled(),
                hideEmptyDays: isHideEmptyDaysEnabled(),
                reduceEmptyDays: isReduceEmptyDaysEnabled(),
                dayColumns: getPrintDayColumns(),
                padPage: getPrintPaddingValue(printPadPageInput, 0, 24, 10),
                padDay: getPrintPaddingValue(printPadDayInput, 0, 16, 6),
                padEvent: getPrintPaddingValue(printPadEventInput, 0, 16, 6),
                textSize: getPrintPaddingValue(printTextSizeInput, 8, 20, 12),
                monthKey: printSelectedMonthKey || '',
                weekStartKey: printSelectedWeekStartKey || '',
                selectedTypes: selectedTypes
            };
        }

        function renderPrintPresets(selectedId) {
            if (!printPresetSelect) {
                return;
            }
            printPresetSelect.innerHTML = '<option value="">Choisir un preset</option>';
            printPresets.forEach(function(preset) {
                if (!preset || !preset.id || !preset.name) {
                    return;
                }
                var option = document.createElement('option');
                option.value = String(preset.id);
                option.textContent = String(preset.name);
                option.selected = option.value === String(selectedId || '');
                printPresetSelect.appendChild(option);
            });
        }

        function updatePrintPresets(presets, selectedId) {
            printPresets = Array.isArray(presets) ? presets : [];
            renderPrintPresets(selectedId);
        }

        function savePrintPreset() {
            if (!printConfig.canManagePresets || !printConfig.ajaxUrl || !printConfig.presetsNonce || !printPresetNameInput) {
                return;
            }
            var name = (printPresetNameInput.value || '').trim();
            if (!name) {
                printPresetNameInput.focus();
                return;
            }
            var formData = new FormData();
            formData.append('action', 'mj_member_calendar_print_preset_save');
            formData.append('nonce', String(printConfig.presetsNonce));
            formData.append('presetId', printPresetSelect ? String(printPresetSelect.value || '') : '');
            formData.append('name', name);
            formData.append('prefs', JSON.stringify(collectPrintPrefsFromInputs()));
            fetch(printConfig.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function(response) { return response.json(); })
                .then(function(payload) {
                    if (payload && payload.success && payload.data) {
                        updatePrintPresets(payload.data.presets, payload.data.presetId);
                    }
                }).catch(function() {});
        }

        function deletePrintPreset() {
            if (!printConfig.canManagePresets || !printConfig.ajaxUrl || !printConfig.presetsNonce || !printPresetSelect || !printPresetSelect.value) {
                return;
            }
            var formData = new FormData();
            formData.append('action', 'mj_member_calendar_print_preset_delete');
            formData.append('nonce', String(printConfig.presetsNonce));
            formData.append('presetId', String(printPresetSelect.value));
            fetch(printConfig.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function(response) { return response.json(); })
                .then(function(payload) {
                    if (payload && payload.success && payload.data) {
                        updatePrintPresets(payload.data.presets, '');
                        if (printPresetNameInput) {
                            printPresetNameInput.value = '';
                        }
                    }
                }).catch(function() {});
        }

        function savePrintPrefsNow() {
            if (!printPrefsEnabled || isApplyingPrintPrefs || !printConfig || !printConfig.ajaxUrl || !printConfig.prefsNonce) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'mj_member_calendar_print_prefs_save');
            formData.append('nonce', String(printConfig.prefsNonce));
            formData.append('prefs', JSON.stringify(collectPrintPrefsFromInputs()));

            fetch(printConfig.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).catch(function() {
                // Silent fail: preview remains functional even if persistence fails.
            });
        }

        function queueSavePrintPrefs() {
            if (!printPrefsEnabled || isApplyingPrintPrefs) {
                return;
            }
            if (printPrefsSaveTimer) {
                clearTimeout(printPrefsSaveTimer);
            }
            printPrefsSaveTimer = setTimeout(function() {
                printPrefsSaveTimer = null;
                savePrintPrefsNow();
            }, 350);
        }

        function getDayNodeByKey(dayKey) {
            if (!dayKey) {
                return null;
            }
            return root.querySelector('.mj-member-events-calendar__month [data-calendar-day="' + dayKey + '"]');
        }

        function normalizeHexColor(value) {
            if (!value) {
                return '';
            }
            var str = String(value).trim();
            if (/^#[0-9a-fA-F]{3}$/.test(str) || /^#[0-9a-fA-F]{6}$/.test(str)) {
                return str;
            }
            return '';
        }

        function hexToRgba(value, alpha) {
            var hex = normalizeHexColor(value);
            if (!hex) {
                return '';
            }

            var normalized = hex;
            if (normalized.length === 4) {
                normalized = '#' + normalized.charAt(1) + normalized.charAt(1) + normalized.charAt(2) + normalized.charAt(2) + normalized.charAt(3) + normalized.charAt(3);
            }

            var r = parseInt(normalized.slice(1, 3), 16);
            var g = parseInt(normalized.slice(3, 5), 16);
            var b = parseInt(normalized.slice(5, 7), 16);
            if (isNaN(r) || isNaN(g) || isNaN(b)) {
                return '';
            }

            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
        }

        function collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap) {
            if (!dayNode) {
                return [];
            }

            var list = [];
            var notesWrapper = dayNode.querySelector('.mj-member-events-calendar__day-notes[data-day-notes]');
            if (notesWrapper) {
                var notes = [];
                try {
                    notes = JSON.parse(notesWrapper.getAttribute('data-day-notes') || '[]');
                } catch (error) {
                    notes = [];
                }
                notes.forEach(function(note) {
                    if (!note || (selectedTypesMap && !Object.prototype.hasOwnProperty.call(selectedTypesMap, 'note'))) {
                        return;
                    }
                    list.push({
                        title: note.title || (note.content || '').slice(0, 60) || 'Note',
                        meta: note.note_type_label || '',
                        type: 'Note',
                        details: withDetails ? (note.content || '') : '',
                        cover: withCover && note.media && note.media[0] ? (note.media[0].url || '') : '',
                        emoji: note.emoji || '📝',
                        accentColor: normalizeHexColor(note.color || ''),
                        isNote: true
                    });
                });
            }

            var items = toArray(dayNode.querySelectorAll('.mj-member-events-calendar__event[data-calendar-type-item]'));
            items.forEach(function(item) {
                if (item.classList.contains('is-filtered-out')) {
                    return;
                }

                if (selectedTypesMap) {
                    var itemType = (item.getAttribute('data-calendar-type') || '').trim();
                    if (!itemType || !Object.prototype.hasOwnProperty.call(selectedTypesMap, itemType)) {
                        return;
                    }
                }

                var titleNode = item.querySelector('.mj-member-events-calendar__event-title-text');
                var metaNode = item.querySelector('.mj-member-events-calendar__event-meta');
                var typeNode = item.querySelector('.mj-member-events-calendar__event-type');
                var detailsNode = item.querySelector('.mj-member-events-calendar__event-preview-description');
                var coverNode = item.querySelector('.mj-member-events-calendar__event-preview-cover img, .mj-member-events-calendar__event-thumb img');
                var emojiNode = item.querySelector('[data-calendar-emoji]');

                var title = titleNode ? (titleNode.textContent || '').trim() : '';
                if (!title) {
                    return;
                }

                var entry = {
                    title: title,
                    meta: metaNode ? (metaNode.getAttribute('data-meta-text') || metaNode.textContent || '').trim() : '',
                    type: typeNode ? (typeNode.textContent || '').trim() : '',
                    details: withDetails && detailsNode ? (detailsNode.textContent || '').trim() : '',
                    cover: withCover && coverNode ? (coverNode.getAttribute('src') || '').trim() : '',
                    emoji: emojiNode ? (emojiNode.getAttribute('data-calendar-emoji') || '').trim() : '',
                    accentColor: normalizeHexColor(item.getAttribute('data-calendar-accent-color') || '')
                };
                list.push(entry);
            });

            return list;
        }

        function formatDateLabel(dateObj, withWeekday) {
            var options = withWeekday
                ? { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }
                : { day: '2-digit', month: 'long', year: 'numeric' };
            try {
                return dateObj.toLocaleDateString('fr-BE', options);
            } catch (error) {
                return dateObj.toLocaleDateString(undefined, options);
            }
        }

        function formatDayHeading(dateObj) {
            var weekday = '';
            try {
                weekday = dateObj.toLocaleDateString('fr-BE', { weekday: 'long' });
            } catch (error) {
                weekday = dateObj.toLocaleDateString(undefined, { weekday: 'long' });
            }

            weekday = String(weekday || '').trim();
            if (weekday) {
                weekday = weekday.charAt(0).toUpperCase() + weekday.slice(1);
            }

            return (weekday ? weekday + ' ' : '') + String(dateObj.getDate());
        }

        function uppercaseFirstLetter(text) {
            var value = String(text || '').trim();
            if (!value) {
                return '';
            }
            return value.charAt(0).toUpperCase() + value.slice(1);
        }

        function formatWeekPeriodTitle(weekStart, weekEnd) {
            var startDay = weekStart.getDate() === 1 ? '1er' : String(weekStart.getDate());
            var endDay = String(weekEnd.getDate()).padStart(2, '0');
            var endLabel = formatDateLabel(weekEnd, false);
            var startMonth = '';

            try {
                startMonth = weekStart.toLocaleDateString('fr-BE', { month: 'long' });
            } catch (error) {
                startMonth = weekStart.toLocaleDateString(undefined, { month: 'long' });
            }

            if (weekStart.getMonth() === weekEnd.getMonth() && weekStart.getFullYear() === weekEnd.getFullYear()) {
                return startDay + ' au ' + endLabel;
            }

            return startDay + ' ' + startMonth + ' au ' + endLabel;
        }

        function collectWeekPeriods(span, withDetails, withCover, selectedTypesMap, weekAnchorDate, removeEmptyDays) {
            var periods = [];
            if (!months[activeIndex] && !weekAnchorDate) {
                return periods;
            }

            var anchor = weekAnchorDate;
            if (!anchor) {
                var monthEl = months[activeIndex];
                var monthKey = monthEl ? (monthEl.getAttribute('data-calendar-month') || '') : '';
                if (!/^\d{4}-\d{2}$/.test(monthKey)) {
                    return periods;
                }
                var firstOfMonth = new Date(monthKey + '-01T00:00:00');
                anchor = startOfWeekMonday(firstOfMonth);
            }

            if (!anchor) {
                return periods;
            }

            for (var i = 0; i < span; i += 1) {
                var weekStart = addDays(anchor, i * 7);
                var weekEnd = addDays(weekStart, 6);
                var cells = [];

                for (var d = 0; d < 7; d += 1) {
                    var dayDate = addDays(weekStart, d);
                    var dayKey = formatDayKey(dayDate);
                    var dayNode = getDayNodeByKey(dayKey);
                    var events = collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap);
                    if (removeEmptyDays && (!events || !events.length)) {
                        continue;
                    }
                    cells.push({
                        isPadding: false,
                        dayNumber: String(dayDate.getDate()),
                        dayHeading: formatDayHeading(dayDate),
                        label: formatDateLabel(dayDate, true),
                        events: events
                    });
                }

                if (removeEmptyDays && !cells.length) {
                    continue;
                }

                periods.push({
                    title: formatWeekPeriodTitle(weekStart, weekEnd),
                    weeks: [{ cells: cells }]
                });
            }

            return periods;
        }

        function collectMonthPeriods(span, withDetails, withCover, selectedTypesMap, monthStartIndex, removeEmptyDays) {
            var periods = [];
            var startIndex = typeof monthStartIndex === 'number' ? monthStartIndex : activeIndex;
            for (var i = 0; i < span; i += 1) {
                var monthEl = months[startIndex + i];
                if (!monthEl) {
                    break;
                }

                var period = {
                    title: uppercaseFirstLetter(monthEl.getAttribute('data-calendar-label') || ('Mois ' + (i + 1))),
                    weeks: []
                };

                var weekNodes = toArray(monthEl.querySelectorAll('.mj-member-events-calendar__week'));
                weekNodes.forEach(function(weekNode) {
                    var weekData = { cells: [] };
                    var dayCells = toArray(weekNode.querySelectorAll('.mj-member-events-calendar__day-cell'));
                    dayCells.forEach(function(dayCell) {
                        var dayNode = dayCell.querySelector('.mj-member-events-calendar__day[data-calendar-day]');
                        if (!dayNode) {
                            if (!removeEmptyDays) {
                                weekData.cells.push({
                                    isPadding: true,
                                    dayNumber: '',
                                    label: '',
                                    events: []
                                });
                            }
                            return;
                        }

                        var dayKey = dayNode.getAttribute('data-calendar-day') || '';
                        var dayNumberNode = dayNode.querySelector('.mj-member-events-calendar__day-number');
                        var parts = dayKey.split('-');
                        var dayDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                        var events = collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap);
                        if (removeEmptyDays && (!events || !events.length)) {
                            return;
                        }
                        weekData.cells.push({
                            isPadding: false,
                            dayNumber: dayNumberNode ? (dayNumberNode.textContent || '').trim() : String(dayDate.getDate()),
                            dayHeading: formatDayHeading(dayDate),
                            label: formatDateLabel(dayDate, true),
                            events: events
                        });
                    });

                    if (!removeEmptyDays || weekData.cells.length) {
                        period.weeks.push(weekData);
                    }
                });

                periods.push(period);
            }

            return periods;
        }

        function hasEventsInPeriod(period) {
            if (!period || !period.weeks || !period.weeks.length) {
                return false;
            }

            for (var wi = 0; wi < period.weeks.length; wi += 1) {
                var week = period.weeks[wi];
                if (!week || !week.cells) {
                    continue;
                }
                for (var ci = 0; ci < week.cells.length; ci += 1) {
                    var cell = week.cells[ci];
                    if (cell && cell.events && cell.events.length) {
                        return true;
                    }
                }
            }

            return false;
        }

        function collectPeriodCompactDays(period) {
            var compactDays = [];
            if (!period || !period.weeks || !period.weeks.length) {
                return compactDays;
            }

            period.weeks.forEach(function(week) {
                (week && week.cells ? week.cells : []).forEach(function(cell) {
                    if (!cell || cell.isPadding || !cell.events || !cell.events.length) {
                        return;
                    }
                    compactDays.push(cell);
                });
            });

            return compactDays;
        }

        function getPeriodEmptyWeekdayIndexes(period) {
            var emptyIndexes = {};
            for (var index = 0; index < 7; index += 1) {
                var hasDay = false;
                var hasEvent = false;
                (period.weeks || []).forEach(function(week) {
                    var cell = week && week.cells ? week.cells[index] : null;
                    if (!cell || cell.isPadding) {
                        return;
                    }
                    hasDay = true;
                    hasEvent = hasEvent || !!(cell.events && cell.events.length);
                });
                if (hasDay && !hasEvent) {
                    emptyIndexes[index] = true;
                }
            }
            return emptyIndexes;
        }

        function buildPrintDocumentHtml(periods, options) {
            var blocks = [];
            var labels = (printConfig && printConfig.labels) ? printConfig.labels : {};
            var weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            var headerImageUrl = options.headerImage && options.headerImageUrl ? String(options.headerImageUrl) : '';
            var footerImageUrl = options.footerImage && options.footerImageUrl ? String(options.footerImageUrl) : '';
            var removeEmptyDays = !!options.removeEmptyDays;
            var reduceEmptyDays = !!options.reduceEmptyDays && !removeEmptyDays;
            var theme = normalizePrintTheme(options.theme);
            var hasDarkBackground = theme === 'dark' || theme === 'dark-light-days';
            var hasDarkDayCards = theme === 'dark' || theme === 'light-dark-days';
            var pageBg = hasDarkBackground ? '#000000' : '#ffffff';
            var pageText = hasDarkBackground ? '#f5f7fa' : '#111111';
            var titleColor = hasDarkBackground ? '#d5024b' : pageText;
            var titleBorder = hasDarkBackground ? '#2a2f36' : '#dddddd';
            var weekdayText = hasDarkBackground ? '#aeb6c2' : '#666666';
            var dayBorder = hasDarkDayCards ? '#2f3742' : '#e4e4e4';
            var dayBg = hasDarkDayCards ? '#171c23' : '#ffffff';
            var dayPaddingBg = hasDarkDayCards ? '#141920' : '#fafafa';
            var dayHead = hasDarkDayCards ? '#d6dde8' : '#444444';
            var eventBorder = hasDarkDayCards ? '#323a46' : '#efefef';
            var eventBg = hasDarkDayCards ? '#202733' : '#ffffff';
            var eventTitle = hasDarkDayCards ? '#f6f8fc' : '#111111';
            var eventMeta = hasDarkDayCards ? '#c2cad7' : '#444444';
            var typeLabelBorder = hasDarkDayCards ? '#3c4552' : '#d7d7d7';
            var typeLabelBg = hasDarkDayCards ? '#2a3240' : '#f6f6f6';
            var typeLabelColor = hasDarkDayCards ? '#e2e7f0' : '#444444';
            var emptyText = hasDarkBackground ? '#aeb6c2' : '#666666';

            if (periods.length) {
                var isDayMode = options.mode === 'day';
                periods.forEach(function(period, idx) {
                    var periodHtml = [];
                    var emptyWeekdayIndexes = reduceEmptyDays ? getPeriodEmptyWeekdayIndexes(period) : {};
                    periodHtml.push('<section class="mj-print-period' + (options.pageBreak && !isDayMode && idx < periods.length - 1 ? ' has-break' : '') + '">');
                    periodHtml.push('<h2>' + escapeHtml(period.title) + '</h2>');

                    periodHtml.push('<div class="mj-print-cal' + (removeEmptyDays ? ' mj-print-cal--compact' : '') + (reduceEmptyDays ? ' mj-print-cal--reduce-empty-days' : '') + (isDayMode ? ' mj-print-cal--day-mode' : '') + '">');
                    if (!removeEmptyDays && !isDayMode) {
                        periodHtml.push('<div class="mj-print-cal__weekdays">');
                        weekdayLabels.forEach(function(wd, weekdayIndex) {
                            periodHtml.push('<span' + (emptyWeekdayIndexes[weekdayIndex] ? ' class="is-empty-column"' : '') + '>' + escapeHtml(wd) + '</span>');
                        });
                        periodHtml.push('</div>');
                    }

                    if (removeEmptyDays || isDayMode) {
                        var compactDays = collectPeriodCompactDays(period);
                        periodHtml.push('<div class="mj-print-cal__days">');
                        compactDays.forEach(function(cell) {
                            periodHtml.push('<div class="mj-print-cal__day">');
                            periodHtml.push('<div class="mj-print-cal__day-head" title="' + escapeHtml(cell.label || '') + '">' + escapeHtml(cell.dayHeading || cell.dayNumber || '') + '</div>');
                            periodHtml.push('<div class="mj-print-cal__events">');
                            (cell.events || []).forEach(function(eventItem) {
                                var eventStyleAttr = '';
                                if (options.eventColor && eventItem.accentColor) {
                                    var bgColor = hexToRgba(eventItem.accentColor, 0.16);
                                    var borderColor = hexToRgba(eventItem.accentColor, 0.45);
                                    if (bgColor && borderColor) {
                                        eventStyleAttr = ' style="background:' + escapeHtml(bgColor) + ';border-color:' + escapeHtml(borderColor) + ';"';
                                    }
                                }
                                periodHtml.push('<article class="mj-print-event' + (eventItem.isNote ? ' mj-print-event--note' : '') + '"' + eventStyleAttr + '>');
                                if (options.cover && eventItem.cover) {
                                    periodHtml.push('<img class="mj-print-event-cover" src="' + escapeHtml(eventItem.cover) + '" alt="' + escapeHtml(eventItem.title || '') + '" />');
                                }
                                periodHtml.push('<div class="mj-print-event-title">');
                                if (options.eventEmoji && eventItem.emoji) {
                                    periodHtml.push('<span class="mj-print-event-emoji">' + escapeHtml(eventItem.emoji) + '</span>');
                                }
                                periodHtml.push('<span class="mj-print-event-title-text">' + escapeHtml(eventItem.title) + '</span>');
                                periodHtml.push('</div>');
                                if (options.timeRange && eventItem.meta) {
                                    periodHtml.push('<div class="mj-print-event-meta">' + escapeHtml(eventItem.meta) + '</div>');
                                }
                                if (options.badges && eventItem.type) {
                                    var compactTypeLabelStyle = '';
                                    if (eventItem.accentColor) {
                                        var compactTypeBg = hexToRgba(eventItem.accentColor, 0.18);
                                        var compactTypeBorder = hexToRgba(eventItem.accentColor, 0.42);
                                        if (compactTypeBg && compactTypeBorder) {
                                            compactTypeLabelStyle = ' style="background:' + escapeHtml(compactTypeBg) + ';border-color:' + escapeHtml(compactTypeBorder) + ';color:' + escapeHtml(eventItem.accentColor) + ';"';
                                        }
                                    }
                                    periodHtml.push('<div class="mj-print-event-type-label"' + compactTypeLabelStyle + '>' + escapeHtml(eventItem.type) + '</div>');
                                }
                                if (options.details && eventItem.details) {
                                    periodHtml.push('<div class="mj-print-event-details">' + escapeHtml(eventItem.details) + '</div>');
                                }
                                periodHtml.push('</article>');
                            });
                            periodHtml.push('</div>');
                            periodHtml.push('</div>');
                        });
                        periodHtml.push('</div>');
                    } else {
                        period.weeks.forEach(function(week) {
                            periodHtml.push('<div class="mj-print-cal__week">');
                            (week.cells || []).forEach(function(cell, cellIndex) {
                                if (!cell || cell.isPadding) {
                                    periodHtml.push('<div class="mj-print-cal__day is-padding"></div>');
                                    return;
                                }

                                periodHtml.push('<div class="mj-print-cal__day' + (emptyWeekdayIndexes[cellIndex] ? ' is-empty-column' : '') + '">');
                                periodHtml.push('<div class="mj-print-cal__day-head" title="' + escapeHtml(cell.label || '') + '">' + escapeHtml(cell.dayNumber || '') + '</div>');
                                periodHtml.push('<div class="mj-print-cal__events">');
                                (cell.events || []).forEach(function(eventItem) {
                                    var eventStyleAttr = '';
                                    if (options.eventColor && eventItem.accentColor) {
                                        var bgColor = hexToRgba(eventItem.accentColor, 0.16);
                                        var borderColor = hexToRgba(eventItem.accentColor, 0.45);
                                        if (bgColor && borderColor) {
                                            eventStyleAttr = ' style="background:' + escapeHtml(bgColor) + ';border-color:' + escapeHtml(borderColor) + ';"';
                                        }
                                    }
                                    periodHtml.push('<article class="mj-print-event' + (eventItem.isNote ? ' mj-print-event--note' : '') + '"' + eventStyleAttr + '>');
                                    if (options.cover && eventItem.cover) {
                                        periodHtml.push('<img class="mj-print-event-cover" src="' + escapeHtml(eventItem.cover) + '" alt="' + escapeHtml(eventItem.title || '') + '" />');
                                    }
                                    periodHtml.push('<div class="mj-print-event-title">');
                                    if (options.eventEmoji && eventItem.emoji) {
                                        periodHtml.push('<span class="mj-print-event-emoji">' + escapeHtml(eventItem.emoji) + '</span>');
                                    }
                                    periodHtml.push('<span class="mj-print-event-title-text">' + escapeHtml(eventItem.title) + '</span>');
                                    periodHtml.push('</div>');
                                    if (options.timeRange && eventItem.meta) {
                                        periodHtml.push('<div class="mj-print-event-meta">' + escapeHtml(eventItem.meta) + '</div>');
                                    }
                                    if (options.badges && eventItem.type) {
                                        var typeLabelStyle = '';
                                        if (eventItem.accentColor) {
                                            var typeBg = hexToRgba(eventItem.accentColor, 0.18);
                                            var typeBorder = hexToRgba(eventItem.accentColor, 0.42);
                                            if (typeBg && typeBorder) {
                                                typeLabelStyle = ' style="background:' + escapeHtml(typeBg) + ';border-color:' + escapeHtml(typeBorder) + ';color:' + escapeHtml(eventItem.accentColor) + ';"';
                                            }
                                        }
                                        periodHtml.push('<div class="mj-print-event-type-label"' + typeLabelStyle + '>' + escapeHtml(eventItem.type) + '</div>');
                                    }
                                    if (options.details && eventItem.details) {
                                        periodHtml.push('<div class="mj-print-event-details">' + escapeHtml(eventItem.details) + '</div>');
                                    }
                                    periodHtml.push('</article>');
                                });
                                periodHtml.push('</div>');
                                periodHtml.push('</div>');
                            });
                            periodHtml.push('</div>');
                        });
                    }

                    periodHtml.push('</div>');

                    periodHtml.push('</section>');
                    blocks.push(periodHtml.join(''));
                });
            }

            var title = (labels && labels.title) ? labels.title : 'Calendrier - impression';
            var pagePaddingCss = typeof options.pagePadding === 'number' ? options.pagePadding : 10;
            var dayPaddingCss = typeof options.dayPadding === 'number' ? options.dayPadding : 6;
            var eventPaddingCss = typeof options.eventPadding === 'number' ? options.eventPadding : 6;
            var textSizeCss = typeof options.textSize === 'number' ? options.textSize : 12;
            var textScale = textSizeCss / 12;
            function scaledTextSize(size) {
                return Math.round(size * textScale * 10) / 10;
            }
            return [
                '<!doctype html>',
                '<html lang="fr">',
                '<head>',
                '<meta charset="utf-8" />',
                '<meta name="viewport" content="width=device-width, initial-scale=1" />',
                '<title>' + escapeHtml(title) + '</title>',
                '<style>',
                'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:' + pagePaddingCss + 'px;color:' + pageText + ';background:' + pageBg + ';}',
                'h2{font-size:' + scaledTextSize(26) + 'px;margin:0 0 14px;padding-bottom:6px;border-bottom:1px solid ' + titleBorder + ';color:' + titleColor + ';text-align:center;}',
                '.mj-print-cal{display:grid;gap:8px;}',
                '.mj-print-cal__weekdays,.mj-print-cal__week{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;}',
                '.mj-print-cal--compact .mj-print-cal__weekdays{display:none;}',
                '.mj-print-cal__days{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;}',
                '.mj-print-cal--day-mode .mj-print-cal__days{grid-template-columns:repeat(' + (options.dayColumns || 5) + ',minmax(0,1fr));}',
                '.mj-print-cal__weekdays span{font-size:' + scaledTextSize(11) + 'px;font-weight:700;text-transform:uppercase;color:' + weekdayText + ';padding:2px 4px;}',
                '.mj-print-cal__day{border:1px solid ' + dayBorder + ';border-radius:8px;min-height:80px;padding:' + dayPaddingCss + 'px;display:flex;flex-direction:column;gap:6px;background:' + dayBg + ';}',
                '.mj-print-cal--reduce-empty-days .mj-print-cal__weekdays,.mj-print-cal--reduce-empty-days .mj-print-cal__week{display:flex;gap:6px;}',
                '.mj-print-cal--reduce-empty-days .mj-print-cal__weekdays span{flex:1 1 0;min-width:0;}',
                '.mj-print-cal--reduce-empty-days .mj-print-cal__day{flex:1 1 0;min-width:0;}',
                '.mj-print-cal--reduce-empty-days .mj-print-cal__weekdays span.is-empty-column,.mj-print-cal--reduce-empty-days .mj-print-cal__day.is-empty-column{flex:0 0 48px;}',
                '.mj-print-cal__day.is-padding{background:' + dayPaddingBg + ';border-style:dashed;}',
                '.mj-print-cal__day-head{font-size:' + scaledTextSize(13) + 'px;font-weight:700;color:' + dayHead + ';}',
                '.mj-print-cal__events{display:grid;gap:6px;}',
                '.mj-print-event{border:1px solid ' + eventBorder + ';border-radius:6px;padding:' + eventPaddingCss + 'px;background:' + eventBg + ';display:grid;gap:4px;}',
                '.mj-print-event--note{border-left:4px solid #d89b00;background:' + (hasDarkDayCards ? '#2a2518' : '#fffaf0') + ';}',
                '.mj-print-event-cover{width:100%;aspect-ratio:1 / 1;object-fit:cover;border-radius:4px;display:block;}',
                '.mj-print-event-title{font-size:' + scaledTextSize(12) + 'px;font-weight:700;line-height:1.2;display:flex;align-items:center;gap:6px;color:' + eventTitle + ';}',
                '.mj-print-event-emoji{font-size:' + scaledTextSize(13) + 'px;line-height:1;}',
                '.mj-print-event-title-text{display:inline;}',
                '.mj-print-event-meta,.mj-print-event-details{font-size:' + scaledTextSize(10) + 'px;color:' + eventMeta + ';line-height:1.3;}',
                '.mj-print-event-type-label{display:inline-flex;align-items:center;align-self:flex-start;border:1px solid ' + typeLabelBorder + ';border-radius:999px;padding:2px 8px;font-size:' + scaledTextSize(10) + 'px;font-weight:600;line-height:1.2;background:' + typeLabelBg + ';color:' + typeLabelColor + ';}',
                '.mj-print-empty{font-size:' + scaledTextSize(13) + 'px;color:' + emptyText + ';}',
                '.mj-print-period + .mj-print-period{margin-top:14px;}',
                '.mj-print-doc-image{margin:0 0 12px;overflow:hidden;}',
                '.mj-print-doc-image img{display:block;width:calc(100% + ' + (pagePaddingCss * 2) + 'px);max-width:none;height:auto;margin-left:-' + pagePaddingCss + 'px;}',
                '.mj-print-doc-image--footer{margin:14px 0 0;}',
                '@media print{body{padding:' + pagePaddingCss + 'px;} .mj-print-period.has-break{page-break-after:always;break-after:page;}}',
                '</style>',
                '</head>',
                '<body>',
                (headerImageUrl ? '<div class="mj-print-doc-image mj-print-doc-image--header"><img src="' + escapeHtml(headerImageUrl) + '" alt="" /></div>' : ''),
                blocks.join(''),
                (footerImageUrl ? '<div class="mj-print-doc-image mj-print-doc-image--footer"><img src="' + escapeHtml(footerImageUrl) + '" alt="" /></div>' : ''),
                '</body>',
                '</html>'
            ].join('');
        }

        function updatePrintPageBreakLabel() {
            if (!printPageBreakLabel) {
                return;
            }
            var labels = (printConfig && printConfig.labels) ? printConfig.labels : {};
            var mode = getPrintMode();
            printPageBreakLabel.textContent = mode === 'month'
                ? (labels.pagePerMonth || 'Une page par mois')
                : mode === 'day'
                    ? 'Une page par jour'
                    : (labels.pagePerWeek || 'Une page par semaine');
        }

        function waitForPreviewImages(doc) {
            if (!doc || !doc.images || !doc.images.length) {
                return Promise.resolve();
            }

            var pending = toArray(doc.images).filter(function(img) {
                return !img.complete;
            });

            if (!pending.length) {
                return Promise.resolve();
            }

            return Promise.all(pending.map(function(img) {
                return new Promise(function(resolve) {
                    var done = false;
                    function finish() {
                        if (done) {
                            return;
                        }
                        done = true;
                        resolve();
                    }
                    img.addEventListener('load', finish, { once: true });
                    img.addEventListener('error', finish, { once: true });
                    setTimeout(finish, 2500);
                });
            })).then(function() {
                return undefined;
            });
        }

        function buildImageFileName() {
            var mode = getPrintMode();
            var suffix = mode === 'month' ? 'mois' : mode === 'day' ? 'jour' : 'semaine';
            var periodKey = mode === 'month'
                ? (printSelectedMonthKey || 'calendrier')
                : (printSelectedWeekStartKey || 'horaire');
            return 'horaire-' + suffix + '-' + periodKey + '.jpg';
        }

        function clonePrintSnapshot(periods, options) {
            return {
                periods: JSON.parse(JSON.stringify(periods || [])),
                options: JSON.parse(JSON.stringify(options || {}))
            };
        }

        function getPrintParameterSignature() {
            var preferences = collectPrintPrefsFromInputs();
            preferences.dayKeys = getSelectedDayKeys();
            return JSON.stringify(preferences);
        }

        function renderPrintPeriodTitleInputs(periods) {
            if (!printPeriodTitlesWrap) {
                return;
            }
            printPeriodTitlesWrap.innerHTML = '';
            periods.forEach(function(period, index) {
                var label = document.createElement('label');
                label.className = 'mj-cal-print__option';
                var labelText = document.createElement('span');
                labelText.textContent = 'Titre ' + (index + 1);
                var input = document.createElement('input');
                input.type = 'text';
                input.maxLength = 120;
                input.value = Object.prototype.hasOwnProperty.call(printTitleOverrides, index)
                    ? printTitleOverrides[index]
                    : period.title;
                input.setAttribute('data-print-period-title-index', String(index));
                label.appendChild(labelText);
                label.appendChild(input);
                printPeriodTitlesWrap.appendChild(label);
            });
        }

        function applyPrintPeriodTitleOverrides(periods) {
            periods.forEach(function(period, index) {
                if (Object.prototype.hasOwnProperty.call(printTitleOverrides, index)) {
                    period.title = printTitleOverrides[index];
                }
            });
        }

        function drawRoundedRect(ctx, x, y, width, height, radius) {
            var r = Math.max(0, Math.min(radius, width / 2, height / 2));
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + width, y, x + width, y + height, r);
            ctx.arcTo(x + width, y + height, x, y + height, r);
            ctx.arcTo(x, y + height, x, y, r);
            ctx.arcTo(x, y, x + width, y, r);
            ctx.closePath();
        }

        function drawImageCover(ctx, image, x, y, width, height, radius) {
            if (!image || !image.naturalWidth || !image.naturalHeight || width <= 0 || height <= 0) {
                return;
            }

            var sourceWidth = image.naturalWidth;
            var sourceHeight = image.naturalHeight;
            var sourceRatio = sourceWidth / sourceHeight;
            var targetRatio = width / height;
            var cropWidth = sourceWidth;
            var cropHeight = sourceHeight;
            var cropX = 0;
            var cropY = 0;

            if (sourceRatio > targetRatio) {
                cropWidth = sourceHeight * targetRatio;
                cropX = (sourceWidth - cropWidth) / 2;
            } else if (sourceRatio < targetRatio) {
                cropHeight = sourceWidth / targetRatio;
                cropY = (sourceHeight - cropHeight) / 2;
            }

            ctx.save();
            drawRoundedRect(ctx, x, y, width, height, radius);
            ctx.clip();
            ctx.drawImage(
                image,
                cropX,
                cropY,
                cropWidth,
                cropHeight,
                x,
                y,
                width,
                height
            );
            ctx.restore();
        }

        function wrapCanvasText(ctx, text, maxWidth) {
            var content = String(text || '').trim();
            if (!content) {
                return [];
            }

            var words = content.split(/\s+/);
            var lines = [];
            var current = '';

            words.forEach(function(word) {
                var candidate = current ? current + ' ' + word : word;
                if (!current || ctx.measureText(candidate).width <= maxWidth) {
                    current = candidate;
                } else {
                    lines.push(current);
                    current = word;
                }
            });

            if (current) {
                lines.push(current);
            }

            return lines;
        }

        function drawCanvasTextBlock(ctx, lines, x, y, lineHeight, maxLines) {
            var rendered = 0;
            lines.slice(0, maxLines).forEach(function(line, index) {
                var output = line;
                if (index === maxLines - 1 && lines.length > maxLines) {
                    output = line.replace(/[\s.,;:!?-]*$/, '') + '...';
                }
                ctx.fillText(output, x, y + (rendered * lineHeight));
                rendered += 1;
            });
            return rendered * lineHeight;
        }

        function loadImageForCanvas(url) {
            return new Promise(function(resolve) {
                if (!url) {
                    resolve(null);
                    return;
                }

                var img = new Image();
                var done = false;
                function finish(result) {
                    if (done) {
                        return;
                    }
                    done = true;
                    resolve(result);
                }

                img.crossOrigin = 'anonymous';
                img.onload = function() { finish(img); };
                img.onerror = function() { finish(null); };
                setTimeout(function() { finish(null); }, 3000);
                img.src = url;
            });
        }

        async function savePrintPreviewFrameAsJpeg() {
            if (!printPreviewFrame || !printPreviewFrame.contentDocument) {
                throw new Error('preview-unavailable');
            }

            var previewDocument = printPreviewFrame.contentDocument;
            await waitForPreviewImages(previewDocument);

            var width = Math.max(1, previewDocument.documentElement.scrollWidth, previewDocument.body ? previewDocument.body.scrollWidth : 0);
            var height = Math.max(1, previewDocument.documentElement.scrollHeight, previewDocument.body ? previewDocument.body.scrollHeight : 0);
            var documentClone = previewDocument.documentElement.cloneNode(true);
            documentClone.setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
            var imageNodes = toArray(documentClone.querySelectorAll('img'));
            await Promise.all(imageNodes.map(function(imageNode) {
                var source = imageNode.getAttribute('src') || '';
                if (!source || source.indexOf('data:') === 0) {
                    return Promise.resolve();
                }
                return fetch(source, { credentials: 'same-origin' })
                    .then(function(response) { return response.ok ? response.blob() : null; })
                    .then(function(blob) {
                        if (!blob) {
                            imageNode.remove();
                            return;
                        }
                        return new Promise(function(resolve) {
                            var reader = new FileReader();
                            reader.onload = function() {
                                imageNode.setAttribute('src', String(reader.result || ''));
                                resolve();
                            };
                            reader.onerror = function() {
                                imageNode.remove();
                                resolve();
                            };
                            reader.readAsDataURL(blob);
                        });
                    }).catch(function() {
                        imageNode.remove();
                    });
            }));
            var serializedDocument = new XMLSerializer().serializeToString(documentClone);
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '"><foreignObject width="100%" height="100%">' + serializedDocument + '</foreignObject></svg>';
            var svgUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));

            try {
                var image = await new Promise(function(resolve) {
                    var svgImage = new Image();
                    svgImage.onload = function() { resolve(svgImage); };
                    svgImage.onerror = function() { resolve(null); };
                    svgImage.src = svgUrl;
                });
                if (!image) {
                    throw new Error('preview-rasterization-failed');
                }

                var canvas = document.createElement('canvas');
                canvas.width = width * 2;
                canvas.height = height * 2;
                var context = canvas.getContext('2d');
                if (!context) {
                    throw new Error('canvas-unavailable');
                }
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0, canvas.width, canvas.height);

                await new Promise(function(resolve, reject) {
                    canvas.toBlob(function(jpegBlob) {
                        if (!jpegBlob) {
                            reject(new Error('blob-unavailable'));
                            return;
                        }
                        var downloadUrl = URL.createObjectURL(jpegBlob);
                        var anchor = document.createElement('a');
                        anchor.href = downloadUrl;
                        anchor.download = buildImageFileName();
                        document.body.appendChild(anchor);
                        anchor.click();
                        anchor.remove();
                        setTimeout(function() {
                            URL.revokeObjectURL(downloadUrl);
                        }, 1000);
                        resolve();
                    }, 'image/jpeg', 0.92);
                });
            } finally {
                URL.revokeObjectURL(svgUrl);
            }
        }

        async function savePreviewAsJpeg() {
            if (!lastPrintSnapshot || !lastPrintSnapshot.periods) {
                throw new Error('snapshot-unavailable');
            }

            var snapshot = lastPrintSnapshot;
            var periods = snapshot.periods || [];
            var options = snapshot.options || {};
            var pagePadding = typeof options.pagePadding === 'number' ? options.pagePadding : 10;
            var dayPadding = typeof options.dayPadding === 'number' ? options.dayPadding : 6;
            var eventPadding = typeof options.eventPadding === 'number' ? options.eventPadding : 6;
            var textSize = typeof options.textSize === 'number' ? options.textSize : 12;
            var textScale = textSize / 12;
            function scaledCanvasSize(size) {
                return Math.max(1, Math.round(size * textScale * 10) / 10);
            }
            var theme = normalizePrintTheme(options.theme);
            var hasDarkBackground = theme === 'dark' || theme === 'dark-light-days';
            var hasDarkDayCards = theme === 'dark' || theme === 'light-dark-days';
            var palette = {
                pageBg: hasDarkBackground ? '#000000' : '#ffffff',
                heading: hasDarkBackground ? '#d5024b' : '#111111',
                weekday: hasDarkBackground ? '#aeb6c2' : '#666666',
                dayBg: hasDarkDayCards ? '#171c23' : '#ffffff',
                dayPaddingBg: hasDarkDayCards ? '#141920' : '#fafafa',
                dayBorder: hasDarkDayCards ? '#2f3742' : '#e4e4e4',
                dayPaddingBorder: hasDarkDayCards ? '#3d4653' : '#d8d8d8',
                dayHead: hasDarkDayCards ? '#d6dde8' : '#444444',
                eventBg: hasDarkDayCards ? '#202733' : '#ffffff',
                eventBorder: hasDarkDayCards ? '#323a46' : '#efefef',
                eventTitle: hasDarkDayCards ? '#f6f8fc' : '#111111',
                eventMeta: hasDarkDayCards ? '#c2cad7' : '#444444',
                pillBg: hasDarkDayCards ? '#2a3240' : '#f6f6f6',
                pillBorder: hasDarkDayCards ? '#3c4552' : '#d7d7d7',
                pillText: hasDarkDayCards ? '#e2e7f0' : '#444444'
            };
            var headerImageUrl = options.headerImage && options.headerImageUrl ? String(options.headerImageUrl) : '';
            var footerImageUrl = options.footerImage && options.footerImageUrl ? String(options.footerImageUrl) : '';
            var canvasWidth = 900;
            var weekdayGap = 6;
            var blockGap = 8;
            var periodGap = 18;
            var weekdayHeight = 20;
            var dayWidth = Math.floor((canvasWidth - (pagePadding * 2) - (weekdayGap * 6)) / 7);
            var measureCanvas = document.createElement('canvas');
            var measureCtx = measureCanvas.getContext('2d');
            if (!measureCtx) {
                throw new Error('canvas-unavailable');
            }

            var imageCache = {};
            async function getImage(url) {
                if (!url) {
                    return null;
                }
                if (!Object.prototype.hasOwnProperty.call(imageCache, url)) {
                    imageCache[url] = loadImageForCanvas(url);
                }
                return imageCache[url];
            }

            function getImageScaledHeight(image, width, fallbackHeight) {
                if (!image || !image.naturalWidth || !image.naturalHeight || width <= 0) {
                    return fallbackHeight;
                }
                var raw = width * (image.naturalHeight / image.naturalWidth);
                if (!isFinite(raw) || raw <= 0) {
                    return fallbackHeight;
                }
                return Math.max(30, raw);
            }

            var headerImage = headerImageUrl ? await getImage(headerImageUrl) : null;
            var footerImage = footerImageUrl ? await getImage(footerImageUrl) : null;
            var headerDrawHeight = headerImage ? getImageScaledHeight(headerImage, canvasWidth, 56) : 0;
            var footerDrawHeight = footerImage ? getImageScaledHeight(footerImage, canvasWidth, 56) : 0;

            function measureEventHeight(eventItem, cellWidth) {
                var effectiveCellWidth = typeof cellWidth === 'number' ? cellWidth : dayWidth;
                var innerWidth = effectiveCellWidth - (dayPadding * 2) - (eventPadding * 2);
                var height = eventPadding * 2 + 4;

                if (options.cover && eventItem.cover) {
                    height += innerWidth + 4;
                }

                measureCtx.font = '700 ' + scaledCanvasSize(12) + 'px Arial, sans-serif';
                var titleLines = wrapCanvasText(measureCtx, eventItem.title || '', Math.max(60, innerWidth - 20));
                height += Math.max(scaledCanvasSize(14), Math.min(titleLines.length, 3) * scaledCanvasSize(14));

                if (options.timeRange && eventItem.meta) {
                    height += scaledCanvasSize(14);
                }
                if (options.badges && eventItem.type) {
                    height += scaledCanvasSize(18);
                }
                if (options.details && eventItem.details) {
                    measureCtx.font = '400 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                    var detailLines = wrapCanvasText(measureCtx, eventItem.details, Math.max(60, innerWidth));
                    height += Math.min(detailLines.length, 4) * scaledCanvasSize(12);
                }

                return Math.max(42, height);
            }

            var totalHeight = pagePadding;
            if (headerImage) {
                totalHeight += headerDrawHeight + 12;
            }
            var removeEmptyDays = !!options.removeEmptyDays;
            var reduceEmptyDays = !!options.reduceEmptyDays && !removeEmptyDays;

            periods.forEach(function(period) {
                totalHeight += 44;
                if (!removeEmptyDays) {
                    totalHeight += weekdayHeight + blockGap;
                    (period.weeks || []).forEach(function(week) {
                        var weekHeight = 80;
                        (week.cells || []).forEach(function(cell) {
                            if (!cell || cell.isPadding) {
                                return;
                            }
                            var cellHeight = Math.max(80, dayPadding * 2 + 18);
                            (cell.events || []).forEach(function(eventItem, eventIndex) {
                                cellHeight += measureEventHeight(eventItem, dayWidth);
                                if (eventIndex < cell.events.length - 1) {
                                    cellHeight += 6;
                                }
                            });
                            weekHeight = Math.max(weekHeight, cellHeight);
                        });
                        totalHeight += weekHeight + blockGap;
                    });
                } else {
                    var compactDays = collectPeriodCompactDays(period);
                    if (compactDays.length) {
                        var compactColumns = Math.min(4, Math.max(1, compactDays.length));
                        var compactGap = 8;
                        var compactDayWidth = Math.floor((canvasWidth - (pagePadding * 2) - (compactGap * (compactColumns - 1))) / compactColumns);
                        for (var compactIndex = 0; compactIndex < compactDays.length; compactIndex += compactColumns) {
                            var rowCells = compactDays.slice(compactIndex, compactIndex + compactColumns);
                            var rowHeight = 80;
                            rowCells.forEach(function(cell) {
                                var cellHeight = Math.max(80, dayPadding * 2 + 18);
                                (cell.events || []).forEach(function(eventItem, eventIndex) {
                                    cellHeight += measureEventHeight(eventItem, compactDayWidth);
                                    if (eventIndex < cell.events.length - 1) {
                                        cellHeight += 6;
                                    }
                                });
                                rowHeight = Math.max(rowHeight, cellHeight);
                            });
                            totalHeight += rowHeight + blockGap;
                        }
                    }
                }
                totalHeight += periodGap;
            });
            if (footerImage) {
                totalHeight += 14 + footerDrawHeight;
            }
            totalHeight += pagePadding;

            var canvas = document.createElement('canvas');
            canvas.width = canvasWidth;
            canvas.height = Math.max(600, totalHeight);
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('canvas-unavailable');
            }

            ctx.fillStyle = palette.pageBg;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            var weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            var cursorY = pagePadding;

            if (headerImage) {
                try {
                    ctx.drawImage(headerImage, 0, cursorY, canvasWidth, headerDrawHeight);
                    cursorY += headerDrawHeight + 12;
                } catch (error) {
                    // Ignore header drawing errors.
                }
            }

            for (var pi = 0; pi < periods.length; pi += 1) {
                var period = periods[pi];
                var emptyWeekdayIndexes = reduceEmptyDays ? getPeriodEmptyWeekdayIndexes(period) : {};
                ctx.font = '700 ' + scaledCanvasSize(26) + 'px Arial, sans-serif';
                ctx.fillStyle = palette.heading;
                ctx.textAlign = 'center';
                ctx.fillText(String(period.title || ''), canvasWidth / 2, cursorY + scaledCanvasSize(26));
                ctx.textAlign = 'left';
                cursorY += scaledCanvasSize(40);

                ctx.font = '700 ' + scaledCanvasSize(11) + 'px Arial, sans-serif';
                ctx.fillStyle = palette.weekday;
                if (!removeEmptyDays) {
                    var sparseWeekdayCount = Object.keys(emptyWeekdayIndexes).length;
                    var fullWeekdayCount = weekdayLabels.length - sparseWeekdayCount;
                    var reducedEmptyDayWidth = 48;
                    var expandedWeekdayWidth = fullWeekdayCount > 0
                        ? Math.floor((canvasWidth - (pagePadding * 2) - (weekdayGap * 6) - (sparseWeekdayCount * reducedEmptyDayWidth)) / fullWeekdayCount)
                        : dayWidth;
                    var weekdayX = pagePadding;
                    for (var wdi = 0; wdi < weekdayLabels.length; wdi += 1) {
                        ctx.fillText(weekdayLabels[wdi], weekdayX + 4, cursorY + scaledCanvasSize(11));
                        weekdayX += (reduceEmptyDays && emptyWeekdayIndexes[wdi] ? reducedEmptyDayWidth : expandedWeekdayWidth) + weekdayGap;
                    }
                    cursorY += weekdayHeight + blockGap;
                }

                if (removeEmptyDays) {
                    var compactDaysToDraw = collectPeriodCompactDays(period);
                    if (compactDaysToDraw.length) {
                        var compactColumnsToDraw = Math.min(4, Math.max(1, compactDaysToDraw.length));
                        var compactGapToDraw = 8;
                        var compactDayWidthToDraw = Math.floor((canvasWidth - (pagePadding * 2) - (compactGapToDraw * (compactColumnsToDraw - 1))) / compactColumnsToDraw);

                        for (var compactStart = 0; compactStart < compactDaysToDraw.length; compactStart += compactColumnsToDraw) {
                            var rowToDraw = compactDaysToDraw.slice(compactStart, compactStart + compactColumnsToDraw);
                            var rowHeightToDraw = 80;

                            rowToDraw.forEach(function(cell) {
                                var cellHeight = Math.max(80, dayPadding * 2 + 18);
                                (cell.events || []).forEach(function(eventItem, eventIndex) {
                                    cellHeight += measureEventHeight(eventItem, compactDayWidthToDraw);
                                    if (eventIndex < cell.events.length - 1) {
                                        cellHeight += 6;
                                    }
                                });
                                rowHeightToDraw = Math.max(rowHeightToDraw, cellHeight);
                            });

                            for (var compactCellIndex = 0; compactCellIndex < rowToDraw.length; compactCellIndex += 1) {
                                var compactCell = rowToDraw[compactCellIndex];
                                var compactCellX = pagePadding + (compactCellIndex * (compactDayWidthToDraw + compactGapToDraw));
                                var compactCellY = cursorY;

                                ctx.save();
                                drawRoundedRect(ctx, compactCellX, compactCellY, compactDayWidthToDraw, rowHeightToDraw, 8);
                                ctx.fillStyle = palette.dayBg;
                                ctx.fill();
                                ctx.lineWidth = 1;
                                ctx.strokeStyle = palette.dayBorder;
                                ctx.stroke();
                                ctx.restore();

                                var compactInnerX = compactCellX + dayPadding;
                                var compactInnerY = compactCellY + dayPadding;
                                var compactInnerWidth = compactDayWidthToDraw - (dayPadding * 2);

                                ctx.font = '700 ' + scaledCanvasSize(13) + 'px Arial, sans-serif';
                                ctx.fillStyle = palette.dayHead;
                                ctx.fillText(String(compactCell.dayHeading || compactCell.dayNumber || ''), compactInnerX, compactInnerY + scaledCanvasSize(13));
                                compactInnerY += scaledCanvasSize(22);

                                for (var compactEventIndex = 0; compactEventIndex < (compactCell.events || []).length; compactEventIndex += 1) {
                                    var compactEventItem = compactCell.events[compactEventIndex];
                                    var compactEventHeight = measureEventHeight(compactEventItem, compactDayWidthToDraw);
                                    var compactEventX = compactInnerX;
                                    var compactEventY = compactInnerY;
                                    var compactEventWidth = compactInnerWidth;

                                    ctx.save();
                                    drawRoundedRect(ctx, compactEventX, compactEventY, compactEventWidth, compactEventHeight, 6);
                                    ctx.fillStyle = (options.eventColor && compactEventItem.accentColor)
                                        ? (hexToRgba(compactEventItem.accentColor, hasDarkDayCards ? 0.2 : 0.16) || palette.eventBg)
                                        : palette.eventBg;
                                    ctx.fill();
                                    ctx.lineWidth = 1;
                                    ctx.strokeStyle = (options.eventColor && compactEventItem.accentColor)
                                        ? (hexToRgba(compactEventItem.accentColor, hasDarkDayCards ? 0.5 : 0.45) || palette.eventBorder)
                                        : palette.eventBorder;
                                    ctx.stroke();
                                    ctx.restore();

                                    var compactContentX = compactEventX + eventPadding;
                                    var compactContentY = compactEventY + eventPadding;
                                    var compactContentWidth = compactEventWidth - (eventPadding * 2);

                                    if (options.cover && compactEventItem.cover) {
                                        var compactCoverImage = await getImage(compactEventItem.cover);
                                        if (compactCoverImage) {
                                            try {
                                                drawImageCover(ctx, compactCoverImage, compactContentX, compactContentY, compactContentWidth, compactContentWidth, 4);
                                                compactContentY += compactContentWidth + 4;
                                            } catch (error) {
                                                // Ignore cover drawing failures, keep export working.
                                            }
                                        }
                                    }

                                    var compactTitleOffsetX = compactContentX;
                                    ctx.font = '700 ' + scaledCanvasSize(12) + 'px Arial, sans-serif';
                                    ctx.fillStyle = palette.eventTitle;
                                    if (options.eventEmoji && compactEventItem.emoji) {
                                        ctx.fillText(String(compactEventItem.emoji), compactContentX, compactContentY + scaledCanvasSize(12));
                                        compactTitleOffsetX += scaledCanvasSize(18);
                                    }
                                    var compactTitleLines = wrapCanvasText(ctx, compactEventItem.title || '', Math.max(60, compactContentWidth - (compactTitleOffsetX - compactContentX)));
                                    drawCanvasTextBlock(ctx, compactTitleLines, compactTitleOffsetX, compactContentY + scaledCanvasSize(11), scaledCanvasSize(14), 3);
                                    compactContentY += Math.max(scaledCanvasSize(16), Math.min(compactTitleLines.length, 3) * scaledCanvasSize(14));

                                    ctx.font = '400 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                                    ctx.fillStyle = palette.eventMeta;
                                    if (options.timeRange && compactEventItem.meta) {
                                        ctx.fillText(String(compactEventItem.meta), compactContentX, compactContentY + scaledCanvasSize(10));
                                        compactContentY += scaledCanvasSize(14);
                                    }

                                    if (compactEventItem.type) {
                                        var compactPillText = String(compactEventItem.type);
                                        ctx.font = '600 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                                        var compactPillWidth = Math.min(compactContentWidth, ctx.measureText(compactPillText).width + 16);
                                        ctx.save();
                                        drawRoundedRect(ctx, compactContentX, compactContentY, compactPillWidth, 16, 999);
                                        ctx.fillStyle = compactEventItem.accentColor
                                            ? (hexToRgba(compactEventItem.accentColor, hasDarkDayCards ? 0.22 : 0.18) || palette.pillBg)
                                            : palette.pillBg;
                                        ctx.fill();
                                        ctx.lineWidth = 1;
                                        ctx.strokeStyle = compactEventItem.accentColor
                                            ? (hexToRgba(compactEventItem.accentColor, hasDarkDayCards ? 0.5 : 0.42) || palette.pillBorder)
                                            : palette.pillBorder;
                                        ctx.stroke();
                                        ctx.restore();
                                        ctx.fillStyle = compactEventItem.accentColor || palette.pillText;
                                        ctx.fillText(compactPillText, compactContentX + 8, compactContentY + scaledCanvasSize(11));
                                        compactContentY += scaledCanvasSize(20);
                                    }

                                    if (options.details && compactEventItem.details) {
                                        ctx.font = '400 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                                        ctx.fillStyle = palette.eventMeta;
                                        var compactDetailLines = wrapCanvasText(ctx, compactEventItem.details, Math.max(60, compactContentWidth));
                                        drawCanvasTextBlock(ctx, compactDetailLines, compactContentX, compactContentY + scaledCanvasSize(10), scaledCanvasSize(12), 4);
                                    }

                                    compactInnerY += compactEventHeight + 6;
                                }
                            }

                            cursorY += rowHeightToDraw + blockGap;
                        }
                    }
                } else {
                    for (var wi = 0; wi < (period.weeks || []).length; wi += 1) {
                        var week = period.weeks[wi];
                        var weekCells = week.cells || [];
                        var sparseCellCount = Object.keys(emptyWeekdayIndexes).length;
                        var fullCellCount = weekCells.length - sparseCellCount;
                        var reducedEmptyDayWidth = 48;
                        var expandedDayWidth = reduceEmptyDays && fullCellCount > 0
                            ? Math.floor((canvasWidth - (pagePadding * 2) - (weekdayGap * (weekCells.length - 1)) - (sparseCellCount * reducedEmptyDayWidth)) / fullCellCount)
                            : dayWidth;

                        var computedWeekHeight = 80;
                        weekCells.forEach(function(cell, cellIndex) {
                            if (!cell || cell.isPadding) {
                                return;
                            }
                            var cellWidth = reduceEmptyDays && !emptyWeekdayIndexes[cellIndex] ? expandedDayWidth : dayWidth;
                            var cellHeight = Math.max(80, dayPadding * 2 + 18);
                            (cell.events || []).forEach(function(eventItem, eventIndex) {
                                cellHeight += measureEventHeight(eventItem, cellWidth);
                                if (eventIndex < cell.events.length - 1) {
                                    cellHeight += 6;
                                }
                            });
                            computedWeekHeight = Math.max(computedWeekHeight, cellHeight);
                        });

                        var cellX = pagePadding;
                        for (var ci = 0; ci < weekCells.length; ci += 1) {
                            var cell = weekCells[ci];
                            var cellWidth = reduceEmptyDays && emptyWeekdayIndexes[ci] ? reducedEmptyDayWidth : expandedDayWidth;
                            var cellY = cursorY;

                            ctx.save();
                            drawRoundedRect(ctx, cellX, cellY, cellWidth, computedWeekHeight, 8);
                            ctx.fillStyle = cell && cell.isPadding ? palette.dayPaddingBg : palette.dayBg;
                            ctx.fill();
                            ctx.lineWidth = 1;
                            ctx.strokeStyle = cell && cell.isPadding ? palette.dayPaddingBorder : palette.dayBorder;
                            ctx.stroke();
                            ctx.restore();

                            if (!cell || cell.isPadding) {
                                cellX += cellWidth + weekdayGap;
                                continue;
                            }

                            var innerX = cellX + dayPadding;
                            var innerY = cellY + dayPadding;
                            var innerWidth = cellWidth - (dayPadding * 2);

                            ctx.font = '700 ' + scaledCanvasSize(13) + 'px Arial, sans-serif';
                            ctx.fillStyle = palette.dayHead;
                            ctx.fillText(String(cell.dayNumber || ''), innerX, innerY + scaledCanvasSize(13));
                            innerY += scaledCanvasSize(22);

                            for (var ei = 0; ei < (cell.events || []).length; ei += 1) {
                                var eventItem = cell.events[ei];
                                var eventHeight = measureEventHeight(eventItem, cellWidth);
                                var eventX = innerX;
                                var eventY = innerY;
                                var eventWidth = innerWidth;

                                ctx.save();
                                drawRoundedRect(ctx, eventX, eventY, eventWidth, eventHeight, 6);
                                ctx.fillStyle = (options.eventColor && eventItem.accentColor)
                                    ? (hexToRgba(eventItem.accentColor, hasDarkDayCards ? 0.2 : 0.16) || palette.eventBg)
                                    : palette.eventBg;
                                ctx.fill();
                                ctx.lineWidth = 1;
                                ctx.strokeStyle = (options.eventColor && eventItem.accentColor)
                                    ? (hexToRgba(eventItem.accentColor, hasDarkDayCards ? 0.5 : 0.45) || palette.eventBorder)
                                    : palette.eventBorder;
                                ctx.stroke();
                                ctx.restore();

                                var contentX = eventX + eventPadding;
                                var contentY = eventY + eventPadding;
                                var contentWidth = eventWidth - (eventPadding * 2);

                                if (options.cover && eventItem.cover) {
                                    var coverImage = await getImage(eventItem.cover);
                                    if (coverImage) {
                                        try {
                                            drawImageCover(ctx, coverImage, contentX, contentY, contentWidth, contentWidth, 4);
                                            contentY += contentWidth + 4;
                                        } catch (error) {
                                            // Ignore cover drawing failures, keep export working.
                                        }
                                    }
                                }

                                var titleOffsetX = contentX;
                                ctx.font = '700 ' + scaledCanvasSize(12) + 'px Arial, sans-serif';
                                ctx.fillStyle = palette.eventTitle;
                                if (options.eventEmoji && eventItem.emoji) {
                                    ctx.fillText(String(eventItem.emoji), contentX, contentY + scaledCanvasSize(12));
                                    titleOffsetX += scaledCanvasSize(18);
                                }
                                var titleLines = wrapCanvasText(ctx, eventItem.title || '', Math.max(60, contentWidth - (titleOffsetX - contentX)));
                                drawCanvasTextBlock(ctx, titleLines, titleOffsetX, contentY + scaledCanvasSize(11), scaledCanvasSize(14), 3);
                                contentY += Math.max(scaledCanvasSize(16), Math.min(titleLines.length, 3) * scaledCanvasSize(14));

                                ctx.font = '400 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                                ctx.fillStyle = palette.eventMeta;
                                if (options.timeRange && eventItem.meta) {
                                    ctx.fillText(String(eventItem.meta), contentX, contentY + scaledCanvasSize(10));
                                    contentY += scaledCanvasSize(14);
                                }

                                if (options.badges && eventItem.type) {
                                    var pillText = String(eventItem.type);
                                    ctx.font = '600 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                                    var pillWidth = Math.min(contentWidth, ctx.measureText(pillText).width + 16);
                                    ctx.save();
                                    drawRoundedRect(ctx, contentX, contentY, pillWidth, 16, 999);
                                    ctx.fillStyle = eventItem.accentColor
                                        ? (hexToRgba(eventItem.accentColor, hasDarkDayCards ? 0.22 : 0.18) || palette.pillBg)
                                        : palette.pillBg;
                                    ctx.fill();
                                    ctx.lineWidth = 1;
                                    ctx.strokeStyle = eventItem.accentColor
                                        ? (hexToRgba(eventItem.accentColor, hasDarkDayCards ? 0.5 : 0.42) || palette.pillBorder)
                                        : palette.pillBorder;
                                    ctx.stroke();
                                    ctx.restore();
                                    ctx.fillStyle = eventItem.accentColor || palette.pillText;
                                    ctx.fillText(pillText, contentX + 8, contentY + scaledCanvasSize(11));
                                    contentY += scaledCanvasSize(20);
                                }

                                if (options.details && eventItem.details) {
                                    ctx.font = '400 ' + scaledCanvasSize(10) + 'px Arial, sans-serif';
                                    ctx.fillStyle = palette.eventMeta;
                                    var detailLines = wrapCanvasText(ctx, eventItem.details, Math.max(60, contentWidth));
                                    drawCanvasTextBlock(ctx, detailLines, contentX, contentY + scaledCanvasSize(10), scaledCanvasSize(12), 4);
                                }

                                innerY += eventHeight + 6;
                            }

                            cellX += cellWidth + weekdayGap;
                        }

                        cursorY += computedWeekHeight + blockGap;
                    }
                }

                cursorY += periodGap;
            }

            if (footerImage) {
                cursorY += 14;
                try {
                    ctx.drawImage(footerImage, 0, cursorY, canvasWidth, footerDrawHeight);
                } catch (error) {
                    // Ignore footer drawing errors.
                }
            }

            return new Promise(function(resolve, reject) {
                canvas.toBlob(function(jpegBlob) {
                    if (!jpegBlob) {
                        reject(new Error('blob-unavailable'));
                        return;
                    }
                    var downloadUrl = URL.createObjectURL(jpegBlob);
                    var anchor = document.createElement('a');
                    anchor.href = downloadUrl;
                    anchor.download = buildImageFileName();
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    setTimeout(function() {
                        URL.revokeObjectURL(downloadUrl);
                    }, 1000);
                    resolve();
                }, 'image/jpeg', 0.92);
            });
        }

        function refreshPrintPreview() {
            if (!printConfig || !printConfig.enabled || !printPreviewFrame) {
                return;
            }

            var mode = getPrintMode();
            var theme = getPrintTheme();
            var span = getPrintSpan();
            var details = isDetailsEnabled();
            var cover = isCoverEnabled();
            var timeRange = isTimeRangeEnabled();
            var eventEmoji = isEventEmojiEnabled();
            var eventColor = isEventColorEnabled();
            var badges = isBadgesEnabled();
            var headerImage = isHeaderImageEnabled();
            var footerImage = isFooterImageEnabled();
            var hideEmptyDays = isHideEmptyDaysEnabled();
            var reduceEmptyDays = isReduceEmptyDaysEnabled();
            var pagePadding = getPrintPaddingValue(printPadPageInput, 0, 24, 10);
            var dayPadding = getPrintPaddingValue(printPadDayInput, 0, 16, 6);
            var eventPadding = getPrintPaddingValue(printPadEventInput, 0, 16, 6);
            var textSize = getPrintPaddingValue(printTextSizeInput, 8, 20, 12);
            var pageBreak = isPageBreakEnabled();
            var selectedTypesMap = getSelectedPrintTypesMap();
            var selectedMonthStartIndex = getSelectedMonthStartIndex();
            var selectedWeekAnchorDate = getSelectedWeekAnchorDate();
            var periods = mode === 'month'
                ? collectMonthPeriods(span, details, cover, selectedTypesMap, selectedMonthStartIndex, hideEmptyDays)
                : mode === 'day'
                    ? collectDayPeriods(details, cover, selectedTypesMap, getSelectedDayKeys(), hideEmptyDays)
                    : collectWeekPeriods(span, details, cover, selectedTypesMap, selectedWeekAnchorDate, hideEmptyDays);
            var currentPrintTitleSignature = getPrintParameterSignature();
            if (currentPrintTitleSignature !== printTitleSignature) {
                printTitleOverrides = {};
                printTitleSignature = currentPrintTitleSignature;
                renderPrintPeriodTitleInputs(periods);
            }
            applyPrintPeriodTitleOverrides(periods);

            var printRenderOptions = {
                mode: mode,
                theme: theme,
                span: span,
                details: details,
                cover: cover,
                timeRange: timeRange,
                eventEmoji: eventEmoji,
                eventColor: eventColor,
                badges: badges,
                headerImage: headerImage,
                footerImage: footerImage,
                headerImageUrl: getPrintImageUrl('header'),
                footerImageUrl: getPrintImageUrl('footer'),
                removeEmptyDays: hideEmptyDays,
                reduceEmptyDays: reduceEmptyDays,
                pagePadding: pagePadding,
                dayPadding: dayPadding,
                eventPadding: eventPadding,
                textSize: textSize,
                pageBreak: pageBreak,
                dayColumns: getPrintDayColumns()
            };

            printPreviewFrame.srcdoc = buildPrintDocumentHtml(periods, printRenderOptions);
            lastPrintSnapshot = clonePrintSnapshot(periods, printRenderOptions);
            updatePrintPageBreakLabel();
        }

        function openPrintModal() {
            if (!printModal) {
                return;
            }
            buildPrintPeriodEntries();
            setDefaultPrintPeriodSelection();
            renderPrintPeriodSelectors();
            renderPrintImageHistory();
            if (!hasAppliedPrintPrefs && printPrefsFromServer) {
                applyPrintPrefsToInputs(printPrefsFromServer);
                hasAppliedPrintPrefs = true;
            }
            refreshPrintPreview();
            printModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closePrintModal() {
            if (!printModal) {
                return;
            }
            printModal.hidden = true;
            document.body.style.overflow = '';
        }

        function getVisibleMonthCount() {
            return 1;
        }

        function updateMobilePanelHeights() {
            var mobilePanels = toArray(root.querySelectorAll('.mj-member-events-calendar__month.is-active .mj-cal-mobile'));
            var multiMonthLayout = getVisibleMonthCount() > 1 && mobilePanels.length > 0;
            var lockPageScroll = !!(config && config.lockPageScroll && multiMonthLayout);
            document.documentElement.classList.toggle('mj-calendar-mobile-layout', lockPageScroll);
            document.body.classList.toggle('mj-calendar-mobile-layout', lockPageScroll);
            toArray(document.querySelectorAll('.mj-header--sticky')).forEach(function(header) {
                var forceCompactHeader = !!(config && config.forceCompactHeader && multiMonthLayout && !header.contains(root));
                var headerInstance = header._mjHeader;
                if (!headerInstance || typeof headerInstance.setCompact !== 'function') {
                    return;
                }
                if (forceCompactHeader || header.getAttribute('data-mj-calendar-forced-stuck') === '1') {
                    headerInstance.setCompact(forceCompactHeader);
                    header.toggleAttribute('data-mj-calendar-forced-stuck', forceCompactHeader);
                }
            });
            if (!multiMonthLayout) {
                mobilePanels.forEach(function(panel) {
                    panel.style.removeProperty('height');
                });
                return;
            }

            var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            var panelTop = Math.min.apply(null, mobilePanels.map(function(panel) {
                return panel.getBoundingClientRect().top;
            }));
            var stickyHeaderHeight = 0;
            toArray(document.querySelectorAll('.mj-header--sticky')).forEach(function(header) {
                if (header.contains(root)) {
                    return;
                }
                var headerRect = header.getBoundingClientRect();
                if (headerRect.bottom > 0 && headerRect.top < viewportHeight) {
                    stickyHeaderHeight = Math.max(stickyHeaderHeight, headerRect.height);
                }
            });
            var adminBar = document.getElementById('wpadminbar');
            var adminBarHeight = 0;
            if (adminBar) {
                var adminBarRect = adminBar.getBoundingClientRect();
                if (adminBarRect.bottom > 0 && adminBarRect.top < viewportHeight) {
                    adminBarHeight = adminBarRect.height;
                }
            }
            var topOffset = Math.max(0, panelTop, stickyHeaderHeight + adminBarHeight);
            var availableHeight = Math.max(0, Math.floor(viewportHeight - topOffset - 16));

            mobilePanels.forEach(function(panel) {
                panel.style.height = availableHeight + 'px';
            });
        }

        var mobileNavMediaQuery = window.matchMedia('(max-width: 767px)');

        function updateMobileNavPosition() {
            if (!toolbar) {
                return;
            }
            var mobileCalendar = root.querySelector('.mj-member-events-calendar__month.is-active .mj-cal-mobile__calendar');
            if (!mobileNavMediaQuery.matches || !mobileCalendar) {
                root.style.removeProperty('--mj-cal-mobile-nav-top');
                return;
            }
            var toolbarRect = toolbar.getBoundingClientRect();
            var calendarRect = mobileCalendar.getBoundingClientRect();
            if (!calendarRect.height) {
                return;
            }
            var top = (calendarRect.top - toolbarRect.top) + (calendarRect.height / 2);
            root.style.setProperty('--mj-cal-mobile-nav-top', top + 'px');
        }

        function sync() {
            var visibleMonthCount = getVisibleMonthCount();
            months.forEach(function(month, idx) {
                if (idx >= activeIndex && idx < activeIndex + visibleMonthCount) {
                    month.classList.add('is-active');
                } else {
                    month.classList.remove('is-active');
                }
            });
            if (label && months[activeIndex]) {
                label.textContent = months[activeIndex].getAttribute('data-calendar-label') || '';
            }
            if (prev) {
                prev.disabled = activeIndex === 0;
            }
            if (next) {
                next.disabled = activeIndex >= months.length - visibleMonthCount;
            }
            if (todayBtn) {
                todayBtn.disabled = todayIndex === -1 || (todayIndex >= activeIndex && todayIndex < activeIndex + visibleMonthCount);
            }
            applyFilters();
            refreshPrintPreview();
            window.requestAnimationFrame(updateMobilePanelHeights);
            window.requestAnimationFrame(updateMobileNavPosition);
        }

        if (prev) {
            prev.addEventListener('click', function() {
                if (activeIndex > 0) {
                    activeIndex -= 1;
                    sync();
                }
            });
        }

        if (next) {
            next.addEventListener('click', function() {
                if (activeIndex < months.length - getVisibleMonthCount()) {
                    activeIndex += 1;
                    sync();
                }
            });
        }

        if (todayBtn) {
            todayBtn.addEventListener('click', function() {
                if (todayIndex >= 0 && activeIndex !== todayIndex) {
                    activeIndex = todayIndex;
                    sync();
                }
            });
        }

        if (filtersToggle && toolbar) {
            filtersToggle.addEventListener('click', function() {
                var isExpanded = toolbar.classList.toggle('is-filters-expanded');
                filtersToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                filtersToggle.querySelector('.screen-reader-text').textContent = isExpanded
                    ? 'Masquer les filtres'
                    : 'Afficher les filtres';
            });
        }

        if (filtersToggle && filtersToggle.parentNode) {
            var filtersToggleParent = filtersToggle.parentNode;
            var filtersToggleAnchor = filtersToggle.nextSibling;
            var filtersToggleMediaQuery = window.matchMedia('(max-width: 767px)');
            var syncFiltersToggleDom = function() {
                if (filtersToggleMediaQuery.matches) {
                    if (!filtersToggle.isConnected) {
                        filtersToggleParent.insertBefore(filtersToggle, filtersToggleAnchor);
                    }
                } else if (filtersToggle.isConnected) {
                    filtersToggleParent.removeChild(filtersToggle);
                }
            };
            syncFiltersToggleDom();
            filtersToggleMediaQuery.addEventListener('change', syncFiltersToggleDom);
        }

        window.addEventListener('resize', updateMobilePanelHeights, { passive: true });
        window.addEventListener('resize', updateMobileNavPosition, { passive: true });
        mobileNavMediaQuery.addEventListener('change', updateMobileNavPosition);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', updateMobilePanelHeights, { passive: true });
            window.visualViewport.addEventListener('resize', updateMobileNavPosition, { passive: true });
        }

        if (filterInputs.length) {
            filterInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    syncNoteTypeFiltersVisibility();
                    applyFilters();
                });
            });
        }

        if (noteTypeFilterInputs.length) {
            noteTypeFilterInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    applyFilters();
                });
            });
        }

        if (openPrintBtn && printConfig && printConfig.enabled) {
            openPrintBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openPrintModal();
            });
        }

        if (printCloseBtns.length) {
            printCloseBtns.forEach(function(closeBtn) {
                closeBtn.addEventListener('click', function() {
                    closePrintModal();
                });
            });
        }

        if (printNowBtn && printConfig && printConfig.enabled) {
            printNowBtn.addEventListener('click', function(e) {
                e.preventDefault();
                refreshPrintPreview();

                if (!printPreviewFrame || !printPreviewFrame.contentWindow) {
                    return;
                }

                try {
                    printPreviewFrame.contentWindow.focus();
                    printPreviewFrame.contentWindow.print();
                } catch (error) {
                    // Browser-specific restriction fallback.
                    window.print();
                }
            });
        }

        if (saveImageBtn && printConfig && printConfig.enabled) {
            saveImageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (saveImageBtn.disabled) {
                    return;
                }

                refreshPrintPreview();
                saveImageBtn.disabled = true;

                savePreviewAsJpeg()
                    .catch(function() {
                        alert('Impossible de générer l\'image JPEG.');
                    })
                    .finally(function() {
                        saveImageBtn.disabled = false;
                    });
            });
        }

        if (printModeInput) {
            printModeInput.addEventListener('change', function() {
                updatePrintPageBreakLabel();
                renderPrintPeriodSelectors();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printDayInput) {
            printDayInput.addEventListener('change', function() {
                printSelectedDayKeys = getSelectedDayKeys();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printThemeInput) {
            printThemeInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printMonthYearInput) {
            printMonthYearInput.addEventListener('change', function() {
                renderPrintPeriodSelectors();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printMonthInput) {
            printMonthInput.addEventListener('change', function() {
                printSelectedMonthKey = (printMonthInput.value || '').trim();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printWeekYearInput) {
            printWeekYearInput.addEventListener('change', function() {
                renderPrintPeriodSelectors();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printWeekInput) {
            printWeekInput.addEventListener('change', function() {
                printSelectedWeekStartKey = (printWeekInput.value || '').trim();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printSpanInput) {
            printSpanInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printSpanInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printDayColumnsInput) {
            printDayColumnsInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printDayColumnsInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPadPageInput) {
            printPadPageInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printPadPageInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPadDayInput) {
            printPadDayInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printPadDayInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPadEventInput) {
            printPadEventInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printPadEventInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printTextSizeInput) {
            printTextSizeInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printTextSizeInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printDetailsInput) {
            printDetailsInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printCoverInput) {
            printCoverInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printTimeRangeInput) {
            printTimeRangeInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printEventEmojiInput) {
            printEventEmojiInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printEventColorInput) {
            printEventColorInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printBadgesInput) {
            printBadgesInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printHeaderImageInput) {
            printHeaderImageInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        toArray(root.querySelectorAll('[data-calendar-action="add-print-image"]')).forEach(function(button) {
            button.addEventListener('click', function() {
                addPrintImage(button.getAttribute('data-print-image-slot') || 'header');
            });
        });

        toArray(root.querySelectorAll('[data-calendar-action="upload-print-image"]')).forEach(function(button) {
            button.addEventListener('click', function() {
                uploadPrintImage(button.getAttribute('data-print-image-slot') || 'header');
            });
        });

        if (printFooterImageInput) {
            printFooterImageInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPageBreakInput) {
            printPageBreakInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printHideEmptyDaysInput) {
            printHideEmptyDaysInput.addEventListener('change', function() {
                syncReduceEmptyDaysOption();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printReduceEmptyDaysInput) {
            printReduceEmptyDaysInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPeriodTitlesWrap) {
            printPeriodTitlesWrap.addEventListener('input', function(event) {
                var input = event.target.closest('[data-print-period-title-index]');
                if (!input) {
                    return;
                }
                printTitleOverrides[input.getAttribute('data-print-period-title-index')] = input.value;
                refreshPrintPreview();
            });
        }

        renderPrintTypeFilters();
        syncReduceEmptyDaysOption();
        renderPrintPresets();
        buildPrintPeriodEntries();
        setDefaultPrintPeriodSelection();
        renderPrintPeriodSelectors();
        if (!hasAppliedPrintPrefs && printPrefsFromServer) {
            applyPrintPrefsToInputs(printPrefsFromServer);
            hasAppliedPrintPrefs = true;
        }

        if (printPresetSelect) {
            printPresetSelect.addEventListener('change', function() {
                var selected = printPresets.find(function(preset) {
                    return preset && String(preset.id) === String(printPresetSelect.value);
                });
                if (!selected) {
                    if (printPresetNameInput) {
                        printPresetNameInput.value = '';
                    }
                    return;
                }
                if (printPresetNameInput) {
                    printPresetNameInput.value = selected.name || '';
                }
                applyPrintPrefsToInputs(selected.prefs || {});
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }
        if (savePrintPresetBtn) {
            savePrintPresetBtn.addEventListener('click', savePrintPreset);
        }
        if (deletePrintPresetBtn) {
            deletePrintPresetBtn.addEventListener('click', deletePrintPreset);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && printModal && !printModal.hidden) {
                closePrintModal();
            }
        });

        sync();

        if (!filterInputs.length) {
            updateDayStates();
        }
    }

    function drainQueue() {
        if (!window.mjMemberEventsCalendarQueue) {
            return;
        }
        while (window.mjMemberEventsCalendarQueue.length) {
            var item = window.mjMemberEventsCalendarQueue.shift();
            if (!item || !item.id) {
                continue;
            }
            var root = document.getElementById(item.id);
            if (root && item.config && typeof item.config.preferredIndex === 'number') {
                root.setAttribute('data-calendar-preferred', String(item.config.preferredIndex));
            }
            if (root && item.config && item.config.todayMonth) {
                root.setAttribute('data-calendar-today', String(item.config.todayMonth));
            }
            initCalendar(root, item.config || {});
        }
    }

    domReady(drainQueue);
})();