/**
 * MjCreateEventModal – shared create-event stepper modal.
 *
 * Usage:
 *   var modal = MjCreateEventModal.init(rootElement, config);
 *   modal.open('2026-03-10', triggerButton);   // open for a given day
 *   modal.close();
 *
 * config keys:
 *   ajaxUrl, createNonce, createUrl,
 *   createTypes        – { key: label }
 *   createTypeColors   – { key: color }
 *   createLocations    – { id: label }
 *   createAnimateurs   – { id: displayName }
 *
 * The rootElement must contain the HTML produced by
 * CreateEventModalRenderer::render().
 */
(function () {
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var toArray = typeof Utils.toArray === 'function'
        ? Utils.toArray
        : function (collection) {
            if (!collection) return [];
            if (Array.isArray(collection)) return collection.slice();
            try { return Array.prototype.slice.call(collection); }
            catch (e) {
                var arr = [];
                for (var i = 0; i < collection.length; i++) arr.push(collection[i]);
                return arr;
            }
        };

    /**
     * Initialise the create-event modal inside `root`.
     *
     * @param {HTMLElement} root  Container that holds the .ccm markup.
     * @param {Object}      config
     * @returns {{ open: Function, close: Function }}
     */
    function init(root, config) {
        if (!root || !config) return null;

        // ---- DOM refs ----
        // Try scoped search first, then fall back to document-level lookup.
        var modal = root.querySelector
            ? root.querySelector('[data-ccm-modal]')
            : null;
        if (!modal) {
            modal = document.querySelector('[data-ccm-modal]');
        }
        if (!modal) {
            console.warn('[CCM] Modal element [data-ccm-modal] not found');
            return null;
        }

        // All CCM elements live *inside* the modal – scope every query to it.
        var closeButtons    = toArray(modal.querySelectorAll('[data-ccm-close]'));
        var panels          = toArray(modal.querySelectorAll('[data-ccm-panel]'));
        var feedback        = modal.querySelector('[data-ccm-feedback]');
        var titleInput      = modal.querySelector('[data-ccm-title]');
        var typeHidden      = modal.querySelector('[data-ccm-type]');
        var typeGrid        = modal.querySelector('[data-ccm-type-grid]');
        var dateInput       = modal.querySelector('[data-ccm-date]');
        var dateDisplay     = modal.querySelector('[data-ccm-date-display]');
        var startInput      = modal.querySelector('[data-ccm-start]');
        var endInput        = modal.querySelector('[data-ccm-end]');
        var summary         = modal.querySelector('[data-ccm-summary]');
        var prevButton      = modal.querySelector('[data-ccm-prev]');
        var nextButton      = modal.querySelector('[data-ccm-next]');
        var submitButton    = modal.querySelector('[data-ccm-submit]');
        var onlyButton      = modal.querySelector('[data-ccm-only]');
        var emojiMount      = modal.querySelector('[data-ccm-emoji-mount]');
        var freeParticipation = modal.querySelector('[data-ccm-free-participation]');
        var showAllMembers  = modal.querySelector('[data-ccm-show-all-members]');
        var capacity        = modal.querySelector('[data-ccm-capacity]');
        var price           = modal.querySelector('[data-ccm-price]');
        var ageMin          = modal.querySelector('[data-ccm-age-min]');
        var ageMax          = modal.querySelector('[data-ccm-age-max]');
        var locationSelect  = modal.querySelector('[data-ccm-location]');
        var teamGrid        = modal.querySelector('[data-ccm-team-grid]');
        var statusSelect    = modal.querySelector('[data-ccm-status]');
        var occurrenceChoice = modal.querySelector('[data-ccm-occurrence-choice]');
        var requireValidation = modal.querySelector('[data-ccm-require-validation]');
        var coverZone       = modal.querySelector('[data-ccm-cover-zone]');
        var coverInput      = modal.querySelector('[data-ccm-cover-input]');
        var coverPlaceholder = modal.querySelector('[data-ccm-cover-placeholder]');
        var coverPreview    = modal.querySelector('[data-ccm-cover-preview]');
        var coverImg        = modal.querySelector('[data-ccm-cover-img]');
        var coverRemove     = modal.querySelector('[data-ccm-cover-remove]');
        var occurrenceMount = modal.querySelector('[data-ccm-occurrence-encoder]');
        var legacyScheduleWrap = modal.querySelector('[data-ccm-legacy-schedule]');
        var stepperDots     = toArray(modal.querySelectorAll('[data-ccm-step-dot]'));

        var descriptionInput = modal.querySelector('[data-ccm-description]');
        var coverFile       = null;
        var stepValidated   = [false, false, false, false, false];
        var occurrenceEditorEnabled = false;
        var occurrenceEditorSession = 0;
        var occurrenceState = {
            mode: 'fixed',
            occurrences: [],
            summary: '',
            generator: null,
            options: null,
            batches: []
        };

        function normalizeLegacyMode(mode) {
            var m = typeof mode === 'string' ? mode : '';
            if (m === 'fixed' || m === 'range' || m === 'recurring' || m === 'series') {
                return m;
            }
            return 'series';
        }

        function normalizeOccurrenceDateTime(value) {
            if (!value) return '';
            var raw = String(value).trim();
            if (!raw) return '';
            return raw.replace('T', ' ');
        }

        function normalizeOccurrenceList(list) {
            if (!Array.isArray(list)) return [];
            return list.map(function (item) {
                var occ = item && typeof item === 'object' ? item : {};
                var start = normalizeOccurrenceDateTime(occ.start);
                var end = normalizeOccurrenceDateTime(occ.end);
                if (!start && occ.date && occ.startTime) {
                    start = normalizeOccurrenceDateTime(String(occ.date) + ' ' + String(occ.startTime));
                }
                if (!end && occ.date && occ.endTime) {
                    end = normalizeOccurrenceDateTime(String(occ.date) + ' ' + String(occ.endTime));
                }
                var generationBatchId = '';
                if (occ.generationBatchId) generationBatchId = String(occ.generationBatchId);
                else if (occ.generation_batch_id) generationBatchId = String(occ.generation_batch_id);
                else if (occ.batch_id) generationBatchId = String(occ.batch_id);
                var manualLotGroup = occ.createAsManualLotGroup ? String(occ.createAsManualLotGroup) : '';
                var manualLotMode = occ.createAsManualLotMode ? String(occ.createAsManualLotMode) : '';
                return {
                    id: occ.id ? String(occ.id) : '',
                    date: occ.date ? String(occ.date) : (start ? String(start).slice(0, 10) : ''),
                    start: start,
                    end: end,
                    startTime: occ.startTime ? String(occ.startTime) : (start ? String(start).slice(11, 16) : '09:00'),
                    endTime: occ.endTime ? String(occ.endTime) : (end ? String(end).slice(11, 16) : '10:00'),
                    isAllDay: !!occ.isAllDay,
                    status: occ.status ? String(occ.status) : 'planned',
                    title: occ.title ? String(occ.title) : '',
                    noteCalendar: occ.noteCalendar ? String(occ.noteCalendar) : '',
                    generationBatchId: generationBatchId,
                    createAsManualLot: !!occ.createAsManualLot,
                    createAsManualLotGroup: manualLotGroup,
                    createAsManualLotMode: manualLotMode
                };
            }).filter(function (occ) {
                return !!(occ.start && occ.end);
            });
        }

        function parseDateTimeParts(value) {
            var raw = normalizeOccurrenceDateTime(value);
            if (!raw) {
                return { date: '', time: '' };
            }
            var parts = raw.split(' ');
            return {
                date: parts[0] || '',
                time: parts[1] ? String(parts[1]).slice(0, 5) : ''
            };
        }

        function buildIsoDateRange(startIso, endIso) {
            if (!startIso || !endIso) {
                return [];
            }
            var startDate = new Date(startIso + 'T00:00:00');
            var endDate = new Date(endIso + 'T00:00:00');
            if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
                return [];
            }
            if (endDate < startDate) {
                var tmp = startDate;
                startDate = endDate;
                endDate = tmp;
            }
            var list = [];
            var cursor = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
            var guard = 0;
            while (cursor <= endDate && guard < 731) {
                var yyyy = String(cursor.getFullYear());
                var mm = String(cursor.getMonth() + 1).padStart(2, '0');
                var dd = String(cursor.getDate()).padStart(2, '0');
                list.push(yyyy + '-' + mm + '-' + dd);
                cursor.setDate(cursor.getDate() + 1);
                guard += 1;
            }
            return list;
        }

        function normalizeBatchList(list) {
            if (!Array.isArray(list)) return [];
            return list.filter(function (batch) {
                return !!(batch && typeof batch === 'object' && batch.batchId);
            });
        }

        function hydrateBatchesFromOccurrences(normalizedList) {
            var list = Array.isArray(normalizedList) ? normalizedList.slice() : [];
            var previousById = {};
            normalizeBatchList(occurrenceState.batches).forEach(function (batch) {
                previousById[String(batch.batchId)] = batch;
            });

            var grouped = {};
            list.forEach(function (occ) {
                if (!occ || typeof occ !== 'object') return;
                var batchId = occ.generationBatchId ? String(occ.generationBatchId) : '';
                if (!batchId && occ.createAsManualLotGroup) {
                    batchId = 'manual-' + String(occ.createAsManualLotGroup);
                    occ.generationBatchId = batchId;
                }
                if (!batchId) return;
                if (!grouped[batchId]) grouped[batchId] = [];
                grouped[batchId].push(occ);
            });

            var batches = Object.keys(grouped).map(function (batchId) {
                var group = grouped[batchId] || [];
                var first = group[0] || {};
                var prev = previousById[batchId] || {};
                var prevSummary = prev.summary && typeof prev.summary === 'object' ? prev.summary : {};
                var prevConfig = prev.configSnapshot && typeof prev.configSnapshot === 'object' ? prev.configSnapshot : {};
                var firstStart = parseDateTimeParts(first.start);
                var firstEnd = parseDateTimeParts(first.end);
                var title = typeof prevSummary.batch_title === 'string' ? prevSummary.batch_title : '';
                var manualMode = first.createAsManualLotMode ? String(first.createAsManualLotMode) : '';
                var resolvedMode = prevConfig.mode || manualMode || occurrenceState.mode || (group.length > 1 ? 'range' : 'custom');

                return {
                    batchId: batchId,
                    status: 'active',
                    createdAt: prev.createdAt || new Date().toISOString(),
                    occurrencesCount: group.length,
                    configSnapshot: {
                        mode: resolvedMode,
                        frequency: prevConfig.frequency || 'every_week',
                        startDate: prevConfig.startDate || first.date || firstStart.date || '',
                        endDate: prevConfig.endDate || first.date || firstEnd.date || '',
                        startTime: prevConfig.startTime || first.startTime || firstStart.time || '09:00',
                        endTime: prevConfig.endTime || first.endTime || firstEnd.time || '10:00',
                        days: prevConfig.days || {},
                        overrides: prevConfig.overrides || {},
                        includeLocationInSchedulePreview: Object.prototype.hasOwnProperty.call(prevConfig, 'includeLocationInSchedulePreview')
                            ? !!prevConfig.includeLocationInSchedulePreview
                            : true,
                        includeMembersInSchedulePreview: Object.prototype.hasOwnProperty.call(prevConfig, 'includeMembersInSchedulePreview')
                            ? !!prevConfig.includeMembersInSchedulePreview
                            : true
                    },
                    summary: {
                        batch_title: title,
                        schedule_summary: prevSummary.schedule_summary || (occurrenceState.summary || ''),
                        include_in_global_schedule: Object.prototype.hasOwnProperty.call(prevSummary, 'include_in_global_schedule')
                            ? !!prevSummary.include_in_global_schedule
                            : true,
                        include_dates_in_schedule_preview: Object.prototype.hasOwnProperty.call(prevSummary, 'include_dates_in_schedule_preview')
                            ? !!prevSummary.include_dates_in_schedule_preview
                            : true,
                        include_location_in_schedule_preview: Object.prototype.hasOwnProperty.call(prevSummary, 'include_location_in_schedule_preview')
                            ? !!prevSummary.include_location_in_schedule_preview
                            : true,
                        include_members_in_schedule_preview: Object.prototype.hasOwnProperty.call(prevSummary, 'include_members_in_schedule_preview')
                            ? !!prevSummary.include_members_in_schedule_preview
                            : true,
                        assigned_location_id: prevSummary.assigned_location_id || 0,
                        assigned_member_ids: Array.isArray(prevSummary.assigned_member_ids) ? prevSummary.assigned_member_ids.slice() : [],
                        generated_occurrence_status: prevSummary.generated_occurrence_status || first.status || 'planned'
                    }
                };
            });

            return {
                occurrences: list,
                batches: batches
            };
        }

        function applyBatchSummaryPatch(batchId, patch) {
            var target = String(batchId || '');
            occurrenceState.batches = normalizeBatchList(occurrenceState.batches).map(function (batch) {
                if (!batch || String(batch.batchId) !== target) return batch;
                var nextSummary = Object.assign({}, batch.summary || {}, patch || {});
                return Object.assign({}, batch, { summary: nextSummary });
            });
            return occurrenceState.batches;
        }

        function applyBatchConfigPatch(batchId, patch) {
            var target = String(batchId || '');
            occurrenceState.batches = normalizeBatchList(occurrenceState.batches).map(function (batch) {
                if (!batch || String(batch.batchId) !== target) return batch;
                var nextConfig = Object.assign({}, batch.configSnapshot || {}, patch || {});
                return Object.assign({}, batch, { configSnapshot: nextConfig });
            });
            return occurrenceState.batches;
        }

        function localOccurrenceApiPost(action, payload) {
            var data = payload && typeof payload === 'object' ? payload : {};
            var batchId = data.batchId ? String(data.batchId) : '';

            if (action === 'mj_regmgr_get_event_editor') {
                return Promise.resolve({
                    form: {
                        options: {
                            locations: mapLocationsForOccurrenceEditor(config.createLocations || {}),
                            animateurs: mapMembersForOccurrenceEditor(config.createAnimateurs || {})
                        }
                    }
                });
            }

            if (action === 'mj_regmgr_save_event_schedule_preview') {
                return Promise.resolve({ success: true });
            }

            if (action === 'mj_regmgr_delete_occurrence_batch') {
                occurrenceState.occurrences = normalizeOccurrenceList(occurrenceState.occurrences).filter(function (occ) {
                    return String(occ.generationBatchId || '') !== batchId;
                });
                occurrenceState.batches = normalizeBatchList(occurrenceState.batches).filter(function (batch) {
                    return String(batch.batchId || '') !== batchId;
                });
                return Promise.resolve({
                    occurrenceGenerationBatches: normalizeBatchList(occurrenceState.batches),
                    occurrences: normalizeOccurrenceList(occurrenceState.occurrences)
                });
            }

            if (action === 'mj_regmgr_update_batch_schedule_flag') {
                var includeInSchedule = !!data.includeInGlobalSchedule;
                return Promise.resolve({
                    occurrenceGenerationBatches: applyBatchSummaryPatch(batchId, { include_in_global_schedule: includeInSchedule })
                });
            }

            if (action === 'mj_regmgr_update_batch_preview_dates_flag') {
                var includeDates = !!data.includeDatesInSchedulePreview;
                return Promise.resolve({
                    occurrenceGenerationBatches: applyBatchSummaryPatch(batchId, { include_dates_in_schedule_preview: includeDates })
                });
            }

            if (action === 'mj_regmgr_update_batch_title') {
                return Promise.resolve({
                    occurrenceGenerationBatches: applyBatchSummaryPatch(batchId, { batch_title: String(data.title || '').trim() })
                });
            }

            if (action === 'mj_regmgr_update_batch_assignment') {
                var locationId = parseInt(data.locationId, 10);
                var memberIds = Array.isArray(data.memberIds)
                    ? data.memberIds.map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; })
                    : [];
                var occurrenceStatus = data.occurrenceStatus ? String(data.occurrenceStatus) : 'planned';
                occurrenceState.occurrences = normalizeOccurrenceList(occurrenceState.occurrences).map(function (occ) {
                    if (!occ || String(occ.generationBatchId || '') !== batchId) return occ;
                    return Object.assign({}, occ, { status: occurrenceStatus });
                });
                return Promise.resolve({
                    occurrenceGenerationBatches: applyBatchSummaryPatch(batchId, {
                        assigned_location_id: locationId > 0 ? locationId : 0,
                        assigned_member_ids: memberIds,
                        generated_occurrence_status: occurrenceStatus
                    }),
                    occurrences: normalizeOccurrenceList(occurrenceState.occurrences)
                });
            }

            if (action === 'mj_regmgr_update_occurrence_batch_config') {
                var configPatch = data.config && typeof data.config === 'object' ? data.config : {};
                var nextBatches = applyBatchConfigPatch(batchId, configPatch);
                var targetBatch = nextBatches.filter(function (batch) {
                    return batch && String(batch.batchId || '') === batchId;
                })[0] || null;
                if (targetBatch && targetBatch.configSnapshot) {
                    var cfg = targetBatch.configSnapshot;
                    var mode = cfg.mode ? String(cfg.mode) : 'custom';
                    var startTime = cfg.startTime ? String(cfg.startTime) : '09:00';
                    var endTime = cfg.endTime ? String(cfg.endTime) : '10:00';
                    var startDate = cfg.startDate ? String(cfg.startDate) : '';
                    var endDate = cfg.endDate ? String(cfg.endDate) : startDate;
                    var currentOccurrences = normalizeOccurrenceList(occurrenceState.occurrences);
                    var batchOccurrences = currentOccurrences.filter(function (occ) {
                        return occ && String(occ.generationBatchId || '') === batchId;
                    });
                    var otherOccurrences = currentOccurrences.filter(function (occ) {
                        return !occ || String(occ.generationBatchId || '') !== batchId;
                    });
                    var template = batchOccurrences[0] || null;
                    var updatedBatchOccurrences = [];

                    if (template) {
                        if (mode === 'range' && startDate && endDate) {
                            var rangeDates = buildIsoDateRange(startDate, endDate);
                            updatedBatchOccurrences = rangeDates.map(function (dateIso, index) {
                                var source = batchOccurrences[index] || template;
                                return Object.assign({}, source, {
                                    id: source.id || ('ccm-occ-' + batchId + '-' + index + '-' + Date.now()),
                                    date: dateIso,
                                    startTime: startTime,
                                    endTime: endTime,
                                    start: dateIso + ' ' + startTime,
                                    end: dateIso + ' ' + endTime,
                                    generationBatchId: String(batchId),
                                    createAsManualLotMode: 'range',
                                });
                            });
                        } else {
                            var targetDate = startDate || template.date || '';
                            updatedBatchOccurrences = [Object.assign({}, template, {
                                date: targetDate,
                                startTime: startTime,
                                endTime: endTime,
                                start: targetDate ? (targetDate + ' ' + startTime) : template.start,
                                end: targetDate ? (targetDate + ' ' + endTime) : template.end,
                                generationBatchId: String(batchId),
                                createAsManualLotMode: mode === 'custom' ? 'custom' : mode,
                            })];
                        }
                    }

                    occurrenceState.occurrences = normalizeOccurrenceList(otherOccurrences.concat(updatedBatchOccurrences));

                    var hydration = hydrateBatchesFromOccurrences(occurrenceState.occurrences);
                    occurrenceState.occurrences = hydration.occurrences;
                    occurrenceState.batches = hydration.batches;
                    applyBatchConfigPatch(batchId, configPatch);
                }
                return Promise.resolve({
                    occurrenceGenerationBatches: normalizeBatchList(occurrenceState.batches),
                    occurrences: normalizeOccurrenceList(occurrenceState.occurrences)
                });
            }

            return Promise.reject(new Error('Action locale non supportée: ' + String(action || '')));
        }

        function mapLocationsForOccurrenceEditor(raw) {
            var mapped = [];
            var src = raw && typeof raw === 'object' ? raw : {};
            Object.keys(src).forEach(function (id) {
                var num = parseInt(id, 10);
                if (!num) return;
                mapped.push({ id: num, name: String(src[id] || ('Lieu #' + id)) });
            });
            return mapped;
        }

        function mapMembersForOccurrenceEditor(raw) {
            var mapped = [];
            var src = raw && typeof raw === 'object' ? raw : {};
            Object.keys(src).forEach(function (id) {
                var num = parseInt(id, 10);
                if (!num) return;
                mapped.push({ id: num, firstName: '', lastName: String(src[id] || ('Membre #' + id)) });
            });
            return mapped;
        }

        function updateScheduleViewMode() {
            if (legacyScheduleWrap) {
                legacyScheduleWrap.hidden = !!occurrenceEditorEnabled;
            }
            if (occurrenceMount) {
                occurrenceMount.hidden = !occurrenceEditorEnabled;
            }
        }

        function mountOccurrenceEncoder() {
            var app = window.MjRegMgrApp || {};
            var occurrenceEditorModule = window.MjRegMgrOccurrenceEditor || {};
            var OccurrenceEncoderPanel = app.OccurrenceEncoderPanel || occurrenceEditorModule.OccurrenceEncoderPanel;
            var preact = window.preact;
            if (!occurrenceMount || !OccurrenceEncoderPanel || !preact || typeof preact.h !== 'function' || typeof preact.render !== 'function') {
                occurrenceEditorEnabled = false;
                updateScheduleViewMode();
                return;
            }

            occurrenceEditorEnabled = true;
            updateScheduleViewMode();

            var strings = (config && config.strings && typeof config.strings === 'object') ? config.strings : {};
            var eventSkeleton = {
                id: 0,
                scheduleMode: occurrenceState.mode || 'series',
                schedule_mode: occurrenceState.mode || 'series',
                occurrenceScheduleSummary: occurrenceState.summary || '',
                inlineSchedulePreview: null,
                occurrenceGenerator: occurrenceState.generator || null,
                occurrenceGenerationBatches: normalizeBatchList(occurrenceState.batches)
            };

            preact.render(
                preact.h(OccurrenceEncoderPanel, {
                    key: 'ccm-occurrence-' + occurrenceEditorSession,
                    event: eventSkeleton,
                    occurrences: normalizeOccurrenceList(occurrenceState.occurrences),
                    strings: strings,
                    locale: (config && config.locale) ? config.locale : 'fr',
                    initialViewMode: 'month',
                    apiPost: localOccurrenceApiPost,
                    globalLocationOptions: mapLocationsForOccurrenceEditor(config.createLocations || {}),
                    globalMemberOptions: mapMembersForOccurrenceEditor(config.createAnimateurs || {}),
                    onPersistOccurrences: function (nextList, summaryPayload, generatorPayload, optionsPayload) {
                        var rawList = Array.isArray(nextList)
                            ? nextList.map(function (item) {
                                return item && typeof item === 'object' ? Object.assign({}, item) : item;
                            })
                            : [];
                        var hasGeneratedWithoutBatch = rawList.some(function (item) {
                            if (!item || typeof item !== 'object') {
                                return false;
                            }
                            var source = typeof item.source === 'string' ? item.source : '';
                            var hasBatch = !!(item.generationBatchId || item.generation_batch_id || item.batch_id);
                            return source === 'generated' && !hasBatch;
                        });
                        if (hasGeneratedWithoutBatch) {
                            var generatedMode = (generatorPayload && typeof generatorPayload.mode === 'string' && generatorPayload.mode)
                                ? String(generatorPayload.mode)
                                : 'weekly';
                            if (generatedMode !== 'weekly' && generatedMode !== 'monthly' && generatedMode !== 'range' && generatedMode !== 'custom') {
                                generatedMode = 'weekly';
                            }
                            var generatedGroup = 'generated-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
                            var generatedBatchId = 'tmp-' + generatedGroup;
                            rawList = rawList.map(function (item) {
                                if (!item || typeof item !== 'object') {
                                    return item;
                                }
                                var source = typeof item.source === 'string' ? item.source : '';
                                var hasBatch = !!(item.generationBatchId || item.generation_batch_id || item.batch_id);
                                if (source !== 'generated' || hasBatch) {
                                    return item;
                                }
                                return Object.assign({}, item, {
                                    generationBatchId: generatedBatchId,
                                    createAsManualLotMode: generatedMode,
                                });
                            });
                        }

                        var normalized = normalizeOccurrenceList(rawList);
                        var hydration = hydrateBatchesFromOccurrences(normalized);
                        occurrenceState.occurrences = hydration.occurrences;
                        occurrenceState.batches = hydration.batches;
                        occurrenceState.summary = typeof summaryPayload === 'string'
                            ? String(summaryPayload)
                            : (summaryPayload && summaryPayload.value ? String(summaryPayload.value) : '');
                        occurrenceState.generator = generatorPayload || null;
                        occurrenceState.options = optionsPayload || null;
                        if (generatorPayload && generatorPayload.mode) {
                            occurrenceState.mode = normalizeLegacyMode(generatorPayload.mode);
                        } else if (occurrenceState.occurrences.length > 1) {
                            occurrenceState.mode = 'series';
                        } else if (occurrenceState.occurrences.length === 1) {
                            occurrenceState.mode = 'fixed';
                        }

                        occurrenceEditorSession += 1;
                        mountOccurrenceEncoder();
                        return Promise.resolve();
                    },
                    onBatchesUpdate: function (nextBatches) {
                        occurrenceState.batches = normalizeBatchList(nextBatches);
                    }
                }),
                occurrenceMount
            );
        }

        // ---- Date picker sync ----
        if (dateInput && dateInput.type === 'date') {
            dateInput.addEventListener('change', function () {
                selectedDay = String(dateInput.value || '').trim();
                if (dateDisplay) {
                    if (selectedDay) {
                        dateDisplay.textContent = formatDate(selectedDay);
                        dateDisplay.hidden = false;
                    } else {
                        dateDisplay.textContent = '';
                        dateDisplay.hidden = true;
                    }
                }
            });
        }
        var currentStep     = 1;
        var selectedDay     = '';
        var activeTrigger   = null;
        var isSubmitting    = false;
        var selectedEmoji   = '';
        var totalSteps      = 5;
        var emojiRendered   = false;

        // ---- Emoji picker ----
        function mountEmoji() {
            if (emojiRendered || !emojiMount) return;
            var EmojiPicker = window.MjRegMgrEmojiPicker && window.MjRegMgrEmojiPicker.EmojiPickerField;
            var preact = window.preact;
            if (!EmojiPicker || !preact || !preact.render || !preact.h) return;
            var h = preact.h;
            function handleChange(val) { selectedEmoji = String(val || ''); }
            function Wrapper() {
                var hooks = window.preactHooks || {};
                var useState = hooks.useState;
                if (!useState) return h('span', null, '');
                var s = useState(selectedEmoji);
                return h(EmojiPicker, {
                    value: s[0],
                    onChange: function (v) { s[1](v); handleChange(v); },
                    fallbackPlaceholder: '\xF0\x9F\x8E\xB2'
                });
            }
            preact.render(h(Wrapper, null), emojiMount);
            emojiRendered = true;
        }

        // ---- Cover upload ----
        function setCover(file) {
            if (!file || !file.type.startsWith('image/')) { coverFile = null; return; }
            if (file.size > 5 * 1024 * 1024) {
                setFeedback('L\u2019image est trop volumineuse (max 5 Mo).', 'error');
                return;
            }
            coverFile = file;
            var reader = new FileReader();
            reader.onload = function (e) {
                if (coverImg) coverImg.src = e.target.result;
                if (coverPlaceholder) coverPlaceholder.hidden = true;
                if (coverPreview) coverPreview.hidden = false;
            };
            reader.readAsDataURL(file);
        }

        function clearCover() {
            coverFile = null;
            if (coverInput) coverInput.value = '';
            if (coverImg) coverImg.src = '';
            if (coverPlaceholder) coverPlaceholder.hidden = false;
            if (coverPreview) coverPreview.hidden = true;
        }

        if (coverZone) {
            coverZone.addEventListener('click', function (e) {
                if (e.target.closest('[data-ccm-cover-remove]')) return;
                if (coverInput) coverInput.click();
            });
            coverZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                coverZone.classList.add('is-dragover');
            });
            coverZone.addEventListener('dragleave', function () {
                coverZone.classList.remove('is-dragover');
            });
            coverZone.addEventListener('drop', function (e) {
                e.preventDefault();
                coverZone.classList.remove('is-dragover');
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) setCover(files[0]);
            });
        }
        if (coverInput) {
            coverInput.addEventListener('change', function () {
                if (coverInput.files && coverInput.files.length) setCover(coverInput.files[0]);
            });
        }
        if (coverRemove) {
            coverRemove.addEventListener('click', function (e) {
                e.stopPropagation();
                clearCover();
            });
        }

        // ---- Type grid ----
        function populateTypeGrid() {
            if (!typeGrid) return;
            var types = config.createTypes || {};
            var keys = Object.keys(types);
            typeGrid.innerHTML = '';
            if (!keys.length) {
                typeGrid.innerHTML = '<span class="ccm__type-empty">Aucun type disponible</span>';
                return;
            }
            var colors = config.createTypeColors || {};
            keys.forEach(function (typeKey, index) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'ccm__type-chip' + (index === 0 ? ' is-selected' : '');
                chip.setAttribute('data-type-value', typeKey);
                chip.textContent = String(types[typeKey] || typeKey);
                var c = colors[typeKey] || '';
                if (c) chip.style.setProperty('--chip-color', c);
                if (index === 0 && typeHidden) typeHidden.value = typeKey;
                chip.addEventListener('click', function () {
                    toArray(typeGrid.querySelectorAll('.ccm__type-chip')).forEach(function (ch) { ch.classList.remove('is-selected'); });
                    chip.classList.add('is-selected');
                    if (typeHidden) typeHidden.value = typeKey;
                });
                typeGrid.appendChild(chip);
            });
        }

        // ---- Location select ----
        function populateLocations() {
            if (!locationSelect) return;
            var locs = config.createLocations || {};
            var keys = Object.keys(locs);
            while (locationSelect.options.length > 1) locationSelect.remove(1);
            keys.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = id;
                opt.textContent = String(locs[id] || id);
                locationSelect.appendChild(opt);
            });
        }

        // ---- Team grid ----
        function populateTeam() {
            if (!teamGrid) return;
            var anims = config.createAnimateurs || {};
            var keys = Object.keys(anims);
            teamGrid.innerHTML = '';
            if (!keys.length) {
                teamGrid.innerHTML = '<span class="ccm__team-empty">Aucun animateur disponible</span>';
                return;
            }
            keys.forEach(function (id) {
                var label = document.createElement('label');
                label.className = 'ccm__team-check';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = id;
                cb.setAttribute('data-ccm-animateur', '');
                var span = document.createElement('span');
                span.className = 'ccm__team-name';
                span.textContent = String(anims[id] || id);
                label.appendChild(cb);
                label.appendChild(span);
                teamGrid.appendChild(label);
            });
        }

        // ---- Helpers ----
        function formatDate(str) {
            if (!str) return '';
            try {
                var p = str.split('-');
                var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
                var days = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                var months = ['janvier','f\u00e9vrier','mars','avril','mai','juin','juillet','ao\u00fbt','septembre','octobre','novembre','d\u00e9cembre'];
                return days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            } catch (e) { return str; }
        }

        function buildDateTime(day, time) {
            if (!day || !time) return '';
            return String(day) + ' ' + String(time);
        }

        function setFeedback(msg, type) {
            if (!feedback) return;
            if (!msg) {
                feedback.textContent = '';
                feedback.hidden = true;
                feedback.classList.remove('is-error', 'is-success');
                return;
            }
            feedback.textContent = msg;
            feedback.hidden = false;
            feedback.classList.toggle('is-error', type === 'error');
            feedback.classList.toggle('is-success', type === 'success');
        }

        function validate(step) {
            if (step === 1) {
                var t = titleInput ? String(titleInput.value || '').trim() : '';
                var ty = typeHidden ? String(typeHidden.value || '').trim() : '';
                if (!t) { setFeedback('Le titre est requis.', 'error'); if (titleInput) titleInput.focus(); return false; }
                if (!ty) { setFeedback('S\u00e9lectionnez un type.', 'error'); return false; }
            }
            // Step 2 (Description) – no mandatory validation
            if (step === 3) {
                if (occurrenceEditorEnabled) {
                    if (!Array.isArray(occurrenceState.occurrences) || occurrenceState.occurrences.length === 0) {
                        setFeedback('Ajoutez au moins une occurrence.', 'error');
                        return false;
                    }
                    setFeedback('', '');
                    return true;
                }
                var sv = startInput ? String(startInput.value || '').trim() : '';
                var ev = endInput ? String(endInput.value || '').trim() : '';
                var needDate = config.dateRequired !== false;
                if (needDate && (!selectedDay || !sv || !ev)) { setFeedback('Date et horaires obligatoires.', 'error'); return false; }
                if (!needDate && selectedDay && (!sv || !ev)) { setFeedback('Si vous renseignez une date, les horaires sont aussi requis.', 'error'); return false; }
                if (selectedDay && sv && ev && ev <= sv) { setFeedback("L'heure de fin doit \u00eatre apr\u00e8s l'heure de d\u00e9but.", 'error'); if (endInput) endInput.focus(); return false; }
            }
            setFeedback('', '');
            return true;
        }

        function updateSummary() {
            if (!summary) return;
            var t = titleInput ? String(titleInput.value || '').trim() : '';
            var typeLabel = '';
            var sel = typeGrid ? typeGrid.querySelector('.is-selected') : null;
            if (sel) typeLabel = sel.textContent || '';
            var sv = startInput ? String(startInput.value || '').trim() : '';
            var ev = endInput ? String(endInput.value || '').trim() : '';
            var emoji = selectedEmoji || '';
            var dl = formatDate(selectedDay);
            var esc = Utils.escapeHtml || function (s) { return String(s); };
            var scheduleLine = esc(dl) + ' &middot; ' + esc(sv) + ' \u2192 ' + esc(ev);
            if (occurrenceEditorEnabled) {
                var occCount = Array.isArray(occurrenceState.occurrences) ? occurrenceState.occurrences.length : 0;
                var summaryText = occurrenceState.summary ? String(occurrenceState.summary) : '';
                scheduleLine = summaryText
                    ? esc(summaryText)
                    : (occCount > 0 ? (occCount + ' occurrence' + (occCount > 1 ? 's' : '')) : esc('Aucune occurrence'));
            }
            summary.innerHTML = '<div class="ccm__summary-emoji">' + (emoji || '\uD83D\uDCC5') + '</div>'
                + '<div class="ccm__summary-info">'
                + '<strong>' + esc(t) + '</strong>'
                + '<span>' + esc(typeLabel) + '</span>'
                + '<span>' + scheduleLine + '</span>'
                + '</div>';
        }

        function ensureDefaultOccurrenceLotForSelectedDay() {
            if (!occurrenceEditorEnabled) {
                return;
            }
            if (!selectedDay) {
                return;
            }
            if (Array.isArray(occurrenceState.occurrences) && occurrenceState.occurrences.length > 0) {
                return;
            }

            var startTime = startInput ? String(startInput.value || '').trim() : '';
            var endTime = endInput ? String(endInput.value || '').trim() : '';
            if (!/^\d{2}:\d{2}$/.test(startTime)) {
                startTime = '14:00';
            }
            if (!/^\d{2}:\d{2}$/.test(endTime) || endTime <= startTime) {
                endTime = '17:00';
            }

            var nowTs = Date.now();
            var lotGroup = 'manual-custom-' + nowTs + '-' + Math.floor(Math.random() * 100000);
            var batchId = 'manual-' + lotGroup;
            var occurrenceId = 'ccm-occ-' + selectedDay + '-' + startTime.replace(':', '') + '-' + nowTs;
            occurrenceState.occurrences = normalizeOccurrenceList([
                {
                    id: occurrenceId,
                    date: selectedDay,
                    startTime: startTime,
                    endTime: endTime,
                    start: selectedDay + ' ' + startTime,
                    end: selectedDay + ' ' + endTime,
                    isAllDay: false,
                    status: 'planned',
                    source: 'manual',
                    generationBatchId: batchId,
                    createAsManualLot: true,
                    createAsManualLotGroup: lotGroup,
                    createAsManualLotMode: 'custom'
                }
            ]);
            occurrenceState.mode = 'fixed';

            var hydration = hydrateBatchesFromOccurrences(occurrenceState.occurrences);
            occurrenceState.occurrences = hydration.occurrences;
            occurrenceState.batches = hydration.batches;

            occurrenceEditorSession += 1;
            mountOccurrenceEncoder();
        }

        function syncStep() {
            panels.forEach(function (panel) {
                var ps = parseInt(panel.getAttribute('data-ccm-panel') || '0', 10);
                var active = ps === currentStep;
                panel.classList.toggle('is-active', active);
                if (active) panel.removeAttribute('hidden');
                else panel.setAttribute('hidden', 'hidden');
            });
            if (modal && modal.classList) {
                modal.classList.toggle('ccm--wide', currentStep === 3);
            }
            stepperDots.forEach(function (dot) {
                var ds = parseInt(dot.getAttribute('data-ccm-step-dot') || '0', 10);
                dot.classList.toggle('is-active', ds === currentStep);
                dot.classList.toggle('is-done', ds < currentStep);
            });
            if (prevButton) { prevButton.hidden = currentStep <= 1; prevButton.disabled = isSubmitting; }
            if (nextButton) { nextButton.hidden = currentStep >= totalSteps; nextButton.disabled = isSubmitting; }
            if (submitButton) { submitButton.hidden = currentStep < totalSteps || config.showEditButton === false; submitButton.disabled = isSubmitting; }
            if (onlyButton) { onlyButton.hidden = currentStep < totalSteps; onlyButton.disabled = isSubmitting; }
            if (currentStep === 3) ensureDefaultOccurrenceLotForSelectedDay();
            if (currentStep === 4) updateSummary();
        }

        // ---- Open / Close ----
        function open(dayValue, trigger) {
            if (!modal) return;
            selectedDay = String(dayValue || '').trim();
            activeTrigger = trigger || null;
            currentStep = 1;
            isSubmitting = false;
            selectedEmoji = '';
            setFeedback('', '');

            if (dateInput) dateInput.value = selectedDay;
            if (dateDisplay) {
                if (selectedDay) {
                    dateDisplay.textContent = formatDate(selectedDay);
                    dateDisplay.hidden = false;
                } else {
                    dateDisplay.textContent = '';
                    dateDisplay.hidden = true;
                }
            }
            if (startInput) startInput.value = '14:00';
            if (endInput) endInput.value = '17:00';
            if (titleInput) titleInput.value = '';
            if (descriptionInput) descriptionInput.value = '';
            clearCover();
            if (occurrenceChoice) occurrenceChoice.checked = false;
            if (requireValidation) requireValidation.checked = false;
            if (freeParticipation) freeParticipation.checked = false;
            if (showAllMembers) showAllMembers.checked = false;
            if (capacity) capacity.value = '0';
            if (price) price.value = '0';
            if (ageMin) ageMin.value = '12';
            if (ageMax) ageMax.value = '26';
            if (statusSelect) statusSelect.value = 'brouillon';
            if (locationSelect) locationSelect.value = '0';
            if (teamGrid) {
                toArray(teamGrid.querySelectorAll('input[type="checkbox"]')).forEach(function (cb) { cb.checked = false; });
            }
            stepValidated = [false, false, false, false, false];
            occurrenceState = {
                mode: 'fixed',
                occurrences: [],
                summary: '',
                generator: null,
                options: null,
                batches: []
            };
            occurrenceEditorSession += 1;
            mountOccurrenceEncoder();

            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ccm-open');
            syncStep();
            mountEmoji();
            if (titleInput) titleInput.focus();
        }

        function close() {
            if (!modal) return;
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ccm-open');
            setFeedback('', '');
            isSubmitting = false;
            syncStep();
            if (activeTrigger && typeof activeTrigger.focus === 'function') activeTrigger.focus();
        }

        // ---- Form data ----
        function buildFormData() {
            var tv = titleInput ? String(titleInput.value || '').trim() : '';
            var tyv = typeHidden ? String(typeHidden.value || '').trim() : '';
            var sv = startInput ? String(startInput.value || '').trim() : '';
            var ev = endInput ? String(endInput.value || '').trim() : '';

            var fd = new FormData();
            fd.append('action', 'mj_events_manager_create');
            fd.append('nonce', String(config.createNonce));
            fd.append('title', tv);
            fd.append('type', tyv);
            fd.append('status', statusSelect ? String(statusSelect.value || 'brouillon') : 'brouillon');
            if (occurrenceEditorEnabled && Array.isArray(occurrenceState.occurrences) && occurrenceState.occurrences.length > 0) {
                var mode = normalizeLegacyMode(occurrenceState.mode || (occurrenceState.occurrences.length > 1 ? 'series' : 'fixed'));
                var payload = {
                    mode: mode,
                    occurrences: normalizeOccurrenceList(occurrenceState.occurrences),
                    occurrence_summary: occurrenceState.summary || ''
                };
                if (occurrenceState.generator && typeof occurrenceState.generator === 'object') {
                    payload.occurrence_generator = occurrenceState.generator;
                }
                if (occurrenceState.options && typeof occurrenceState.options === 'object') {
                    payload.occurrence_options = occurrenceState.options;
                }
                fd.append('schedule_mode', mode);
                fd.append('schedule_payload', JSON.stringify(payload));
                fd.append('event_occurrences_payload', JSON.stringify(payload.occurrences));

                var firstOccurrence = payload.occurrences[0];
                if (firstOccurrence && firstOccurrence.start && firstOccurrence.end) {
                    fd.append('start_date', String(firstOccurrence.start));
                    fd.append('end_date', String(firstOccurrence.end));
                }
            } else {
                if (selectedDay && sv) fd.append('start_date', buildDateTime(selectedDay, sv));
                if (selectedDay && ev) fd.append('end_date', buildDateTime(selectedDay, ev));
            }
            if (selectedEmoji) fd.append('emoji', selectedEmoji);
            if (coverFile) fd.append('cover_image', coverFile);
            var desc = descriptionInput ? String(descriptionInput.value || '').trim() : '';
            if (desc) fd.append('description', desc);
            if (occurrenceChoice && occurrenceChoice.checked) fd.append('occurrence_choice', '1');
            if (requireValidation && requireValidation.checked) fd.append('require_validation', '1');

            var cap = capacity ? parseInt(capacity.value, 10) || 0 : 0;
            var pr = price ? parseFloat(price.value) || 0 : 0;
            if (cap > 0) fd.append('capacity_total', String(cap));
            if (pr > 0) fd.append('price', String(pr));
            if (showAllMembers && showAllMembers.checked) fd.append('attendance_show_all_members', '1');

            var locId = locationSelect ? parseInt(locationSelect.value, 10) || 0 : 0;
            if (locId > 0) fd.append('location_id', String(locId));

            var checked = teamGrid ? toArray(teamGrid.querySelectorAll('input[data-ccm-animateur]:checked')) : [];
            checked.forEach(function (cb) { fd.append('animateur_ids[]', cb.value); });
            return fd;
        }

        // ---- Submit ----
        function submit(mode) {
            if (isSubmitting) return;
            if (!validate(1) || !validate(3)) return;
            if (!config.ajaxUrl || !config.createNonce) {
                setFeedback('Configuration incompl\u00e8te.', 'error');
                return;
            }

            isSubmitting = true;
            syncStep();
            setFeedback('Cr\u00e9ation en cours\u2026', 'success');

            var fd = buildFormData();

            fetch(config.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (!result || !result.success) {
                        var msg = result && result.data && result.data.message ? result.data.message : 'Impossible de cr\u00e9er l\'\u00e9v\u00e9nement.';
                        throw new Error(msg);
                    }
                    var evt = result.data && result.data.event ? result.data.event : null;
                    var id = evt && evt.id ? parseInt(evt.id, 10) : 0;

                    if (mode === 'edit' && id > 0) {
                        var base = config.createUrl || '/mon-compte/gestionnaire/';
                        var sep = base.indexOf('?') === -1 ? '?' : '&';
                        window.location.href = base + sep + 'event=' + String(id);
                        return;
                    }

                    var title = titleInput ? String(titleInput.value || '').trim() : 'L\'\u00e9v\u00e9nement';
                    setFeedback('\u2705 \u00ab ' + title + ' \u00bb cr\u00e9\u00e9 avec succ\u00e8s !', 'success');

                    // Notify listeners
                    if (typeof config.onCreated === 'function') {
                        config.onCreated(result.data);
                    }

                    setTimeout(function () {
                        close();
                        if (typeof config.onAfterCreate === 'function') {
                            config.onAfterCreate(result.data);
                        } else {
                            window.location.reload();
                        }
                    }, 1500);
                })
                .catch(function (error) {
                    isSubmitting = false;
                    syncStep();
                    setFeedback(error && error.message ? error.message : 'Erreur r\u00e9seau.', 'error');
                });
        }

        // ---- Populate dynamic content ----
        populateTypeGrid();
        populateLocations();
        populateTeam();
        mountOccurrenceEncoder();

        // ---- Event listeners ----
        if (prevButton) {
            prevButton.addEventListener('click', function () {
                if (currentStep > 1 && !isSubmitting) {
                    currentStep -= 1;
                    setFeedback('', '');
                    syncStep();
                }
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', function () {
                if (isSubmitting) return;
                if (!validate(currentStep)) return;
                stepValidated[currentStep - 1] = true;
                if (currentStep < totalSteps) { currentStep += 1; syncStep(); }
            });
        }
        if (submitButton) {
            submitButton.addEventListener('click', function () { submit('edit'); });
        }
        if (onlyButton) {
            onlyButton.addEventListener('click', function () { submit('create'); });
        }
        closeButtons.forEach(function (btn) {
            btn.addEventListener('click', function () { close(); });
        });
        stepperDots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                var target = parseInt(dot.getAttribute('data-ccm-step-dot') || '0', 10);
                if (isSubmitting || target === currentStep) return;
                if (target < currentStep || stepValidated[target - 1]) {
                    currentStep = target;
                    setFeedback('', '');
                    syncStep();
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hidden) close();
        });

        return { open: open, close: close };
    }

    // Expose globally
    window.MjCreateEventModal = { init: init };
})();
