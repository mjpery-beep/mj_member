/**
 * Registration Manager - Occurrence Editor Module
 * Encapsule le panneau d'édition des occurrences et ses helpers.
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var Modals = global.MjRegMgrModals || {};

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Occurrence editor dependencies are missing.');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;
    var useRef = hooks.useRef;

    var getString = Utils.getString;
    var classNames = Utils.classNames;
    var useModal = Utils.useModal;
    var ModalComponent = typeof Modals.Modal === 'function' ? Modals.Modal : null;


    function OccurrenceEncoderPanel(props) {
        var event = props.event || null;
        var occurrencesProp = Array.isArray(props.occurrences) ? props.occurrences : [];
        var strings = props.strings || {};
        var locale = props.locale || 'fr';
        var onPersistOccurrences = typeof props.onPersistOccurrences === 'function' ? props.onPersistOccurrences : null;
        var apiPost = typeof props.apiPost === 'function' ? props.apiPost : null;
        var onBatchesUpdate = typeof props.onBatchesUpdate === 'function' ? props.onBatchesUpdate : null;
        var globalLocationOptions = Array.isArray(props.globalLocationOptions) ? props.globalLocationOptions : [];
        var globalMemberOptions = Array.isArray(props.globalMemberOptions) ? props.globalMemberOptions : [];

        var _loadedGlobalOptions = useState({ locations: [], members: [] });
        var loadedGlobalOptions = _loadedGlobalOptions[0];
        var setLoadedGlobalOptions = _loadedGlobalOptions[1];

        var _isPersisting = useState(false);
        var isPersisting = _isPersisting[0];
        var setIsPersisting = _isPersisting[1];

        var resolvedLocale = useMemo(function () {
            return resolveLocaleTag(locale);
        }, [locale]);

        var normalizedOccurrences = useMemo(function () {
            return occurrencesProp.map(function (occ, index) {
                return normalizeOccurrence(occ, index);
            });
        }, [occurrencesProp]);

        var _localOccurrences = useState(normalizedOccurrences);
        var localOccurrences = _localOccurrences[0];
        var setLocalOccurrences = _localOccurrences[1];
        var localOccurrencesRef = useRef(localOccurrences);

        useEffect(function () {
            setLocalOccurrences(normalizedOccurrences);
        }, [normalizedOccurrences]);

        useEffect(function () {
            localOccurrencesRef.current = localOccurrences;
        }, [localOccurrences]);

        var _selectedId = useState(normalizedOccurrences.length > 0 ? normalizedOccurrences[0].id : null);
        var selectedOccurrenceId = _selectedId[0];
        var setSelectedOccurrenceId = _selectedId[1];

        var _hoveredBatchId = useState('');
        var hoveredBatchId = _hoveredBatchId[0];
        var setHoveredBatchId = _hoveredBatchId[1];

        var _activeBatchId = useState('');
        var activeBatchId = _activeBatchId[0];
        var setActiveBatchId = _activeBatchId[1];

        var _draggingOccurrenceId = useState('');
        var draggingOccurrenceId = _draggingOccurrenceId[0];
        var setDraggingOccurrenceId = _draggingOccurrenceId[1];

        var _weekInteraction = useState(null);
        var weekInteraction = _weekInteraction[0];
        var setWeekInteraction = _weekInteraction[1];

        var _rangeSelection = useState(null);
        var rangeSelection = _rangeSelection[0];
        var setRangeSelection = _rangeSelection[1];
        var isPointerDraggingRangeRef = useRef(false);
        var suppressDayClickUntilRef = useRef(0);
        var dragRangeStartIsoRef = useRef('');
        var dragRangeEndIsoRef = useRef('');

        useEffect(function () {
            setSelectedOccurrenceId(normalizedOccurrences.length > 0 ? normalizedOccurrences[0].id : null);
        }, [normalizedOccurrences]);

        var initialPivotDate = useMemo(function () {
            return deriveInitialPivotDate(normalizedOccurrences, event);
        }, [normalizedOccurrences, event]);

        var eventGeneratorPlan = useMemo(function () {
            if (!event || !event.occurrenceGenerator) {
                return null;
            }
            return normalizeGeneratorPlan(event.occurrenceGenerator);
        }, [event]);

        var eventGeneratorPlanSignature = useMemo(function () {
            if (!eventGeneratorPlan) {
                return 'none';
            }
            try {
                return JSON.stringify(eventGeneratorPlan);
            } catch (error) {
                return 'none';
            }
        }, [eventGeneratorPlan]);

        var _pivotDate = useState(initialPivotDate);
        var pivotDate = _pivotDate[0];
        var setPivotDate = _pivotDate[1];

        useEffect(function () {
            if (!(initialPivotDate instanceof Date) || Number.isNaN(initialPivotDate.getTime())) {
                return;
            }
            setPivotDate(function () {
                if (viewMode === 'week') {
                    return alignDateToWeekStart(initialPivotDate);
                }
                return new Date(initialPivotDate.getFullYear(), initialPivotDate.getMonth(), 1);
            });
        }, [initialPivotDate, viewMode]);

        var _viewMode = useState('quarter');
        var viewMode = _viewMode[0];
        var setViewMode = _viewMode[1];

        var occurrenceEditorModal = useModal();
        var occurrenceGeneratorModal = useModal();
        var modalReopenGuardUntilRef = useRef(0);

        var openOccurrenceEditor = useCallback(function (dateIso) {
            if (Date.now() < modalReopenGuardUntilRef.current) {
                return;
            }
            occurrenceEditorModal.open({ date: dateIso });
        }, [occurrenceEditorModal.open]);

        var occurrencesByDate = useMemo(function () {
            var map = {};
            localOccurrences.forEach(function (occ) {
                if (!map[occ.date]) {
                    map[occ.date] = [];
                }
                map[occ.date].push(occ);
            });
            return map;
        }, [localOccurrences]);

        var selectedOccurrence = useMemo(function () {
            if (!selectedOccurrenceId) {
                return null;
            }
            return localOccurrences.find(function (occ) { return occ.id === selectedOccurrenceId; }) || null;
        }, [localOccurrences, selectedOccurrenceId]);

        var _editorState = useState(createEditorState(selectedOccurrence));
        var editorState = _editorState[0];
        var setEditorState = _editorState[1];

        useEffect(function () {
            if (selectedOccurrence) {
                setEditorState(createEditorState(selectedOccurrence));
            }
        }, [selectedOccurrence]);

        var editorHasDate = editorState && typeof editorState.date === 'string' && editorState.date !== '';
        var editorHasExistingId = editorState && !!editorState.id;
        var hasExistingOccurrences = localOccurrences.length > 0;
        var shouldShowEditorCard = !!(occurrenceEditorModal.isOpen && (selectedOccurrenceId || editorHasExistingId || editorHasDate));
        var isCreatingNewOccurrence = !selectedOccurrenceId && !editorHasExistingId && (editorHasDate || !hasExistingOccurrences);
        var editorCardTitle = isCreatingNewOccurrence
            ? getString(strings, 'occurrenceEditorCreateTitle', 'Ajoute une occurrence')
            : getString(strings, 'occurrenceEditorTitle', "Modifier l'occurrence sélectionnée");

        var statusOptions = useMemo(function () {
            return [
                { value: 'planned', label: getString(strings, 'occurrenceStatusPlanned', 'Prévu') },
                { value: 'confirmed', label: getString(strings, 'occurrenceStatusConfirmed', 'Confirmée') },
                { value: 'cancelled', label: getString(strings, 'occurrenceStatusCancelled', 'Annulé') },
            ];
        }, [strings]);

        var statusLabelMap = useMemo(function () {
            return {
                planned: getString(strings, 'occurrenceStatusPlanned', 'Prévu'),
                confirmed: getString(strings, 'occurrenceStatusConfirmed', 'Confirmée'),
                cancelled: getString(strings, 'occurrenceStatusCancelled', 'Annulé'),
            };
        }, [strings]);

        var weekdayLabels = useMemo(function () {
            return [
                getString(strings, 'occurrenceDayMon', 'Lun'),
                getString(strings, 'occurrenceDayTue', 'Mar'),
                getString(strings, 'occurrenceDayWed', 'Mer'),
                getString(strings, 'occurrenceDayThu', 'Jeu'),
                getString(strings, 'occurrenceDayFri', 'Ven'),
                getString(strings, 'occurrenceDaySat', 'Sam'),
                getString(strings, 'occurrenceDaySun', 'Dim'),
            ];
        }, [strings]);

        var weekdayFullLabels = useMemo(function () {
            return [
                getString(strings, 'occurrenceDayMondayFull', 'Lundi'),
                getString(strings, 'occurrenceDayTuesdayFull', 'Mardi'),
                getString(strings, 'occurrenceDayWednesdayFull', 'Mercredi'),
                getString(strings, 'occurrenceDayThursdayFull', 'Jeudi'),
                getString(strings, 'occurrenceDayFridayFull', 'Vendredi'),
                getString(strings, 'occurrenceDaySaturdayFull', 'Samedi'),
                getString(strings, 'occurrenceDaySundayFull', 'Dimanche'),
            ];
        }, [strings]);

        var monthlyOrdinalOptions = useMemo(function () {
            return [
                { value: 'first', label: getString(strings, 'occurrenceGeneratorOrdinalFirst', '1er') },
                { value: 'second', label: getString(strings, 'occurrenceGeneratorOrdinalSecond', '2e') },
                { value: 'third', label: getString(strings, 'occurrenceGeneratorOrdinalThird', '3e') },
                { value: 'fourth', label: getString(strings, 'occurrenceGeneratorOrdinalFourth', '4e') },
                { value: 'last', label: getString(strings, 'occurrenceGeneratorOrdinalLast', 'Dernier') },
            ];
        }, [strings]);

        var months = useMemo(function () {
            if (viewMode !== 'quarter') {
                return [];
            }
            return buildQuarterMonths(pivotDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId);
        }, [pivotDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId, viewMode]);

        var singleMonthOverview = useMemo(function () {
            if (!(pivotDate instanceof Date) || Number.isNaN(pivotDate.getTime())) {
                return null;
            }
            var monthDate = new Date(pivotDate.getFullYear(), pivotDate.getMonth(), 1);
            return buildMonthOverview(monthDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId);
        }, [pivotDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId]);

        var weekOverview = useMemo(function () {
            if (viewMode !== 'week') {
                return null;
            }
            return buildWeekOverview(pivotDate, occurrencesByDate, selectedOccurrenceId);
        }, [pivotDate, occurrencesByDate, selectedOccurrenceId, viewMode]);

        var calendarMonths = viewMode === 'month'
            ? (singleMonthOverview ? [singleMonthOverview] : [])
            : months;

        var weekTimeScale = useMemo(function () {
            if (!weekOverview) {
                return {
                    min: 9 * 60,
                    max: 17 * 60,
                    range: 8 * 60,
                    ticks: [],
                };
            }
            var minMinutes = weekOverview.minMinutes;
            var maxMinutes = weekOverview.maxMinutes;
            var paddedMin = Math.max(0, Math.floor((minMinutes - 30) / 60) * 60);
            var paddedMax = Math.min(24 * 60, Math.ceil((maxMinutes + 30) / 60) * 60);
            if (paddedMax <= paddedMin) {
                paddedMax = Math.min(24 * 60, paddedMin + 120);
            }
            var ticks = [];
            for (var cursor = paddedMin; cursor <= paddedMax; cursor += 60) {
                ticks.push({
                    minutes: cursor,
                    label: formatPreviewTime(minutesToTime(cursor)),
                });
            }
            return {
                min: paddedMin,
                max: paddedMax,
                range: Math.max(60, paddedMax - paddedMin),
                ticks: ticks,
            };
        }, [weekOverview]);

        var WEEK_VIEW_HEIGHT = 560;
        var WEEK_CREATION_STEP_MINUTES = 15;
        var WEEK_CREATION_DEFAULT_DURATION = 60;
        var MINUTES_PER_PIXEL_FOR_WEEK_DRAG = 2;
        var weekTimelineRange = Math.max(60, weekTimeScale.range || 0);

        var weekRangeLabel = useMemo(function () {
            if (!weekOverview) {
                return '';
            }
            var start = weekOverview.start;
            var end = weekOverview.end;
            if (!(start instanceof Date) || Number.isNaN(start.getTime()) || !(end instanceof Date) || Number.isNaN(end.getTime())) {
                return '';
            }
            var options = { day: 'numeric', month: 'short' };
            var startLabel;
            var endLabel;
            try {
                startLabel = start.toLocaleDateString(resolvedLocale, options);
            } catch (error) {
                startLabel = start.toLocaleDateString('fr', options);
            }
            try {
                endLabel = end.toLocaleDateString(resolvedLocale, options);
            } catch (error2) {
                endLabel = end.toLocaleDateString('fr', options);
            }
            var template = getString(strings, 'occurrenceWeekRange', 'Semaine du {start} au {end}');
            return template.replace('{start}', startLabel).replace('{end}', endLabel);
        }, [weekOverview, resolvedLocale, strings]);

        var handleWeekColumnBackgroundClick = useCallback(function (day, event) {
            if (!day || !weekOverview || !weekTimeScale || typeof event !== 'object') {
                return;
            }
            var target = event.currentTarget;
            if (!target || typeof target.getBoundingClientRect !== 'function') {
                return;
            }
            var rect = target.getBoundingClientRect();
            var pointerY = typeof event.clientY === 'number' ? event.clientY : rect.top;
            var offsetY = pointerY - rect.top;
            if (offsetY < 0) {
                offsetY = 0;
            }
            var height = rect.height > 0 ? rect.height : 1;
            var ratio = offsetY / height;
            if (!Number.isFinite(ratio)) {
                ratio = 0;
            }
            ratio = Math.min(1, Math.max(0, ratio));
            var rawMinutes = weekTimeScale.min + (ratio * weekTimelineRange);
            var snappedStart = Math.floor(rawMinutes / WEEK_CREATION_STEP_MINUTES) * WEEK_CREATION_STEP_MINUTES;
            var safeMaxStart = weekTimeScale.max - WEEK_CREATION_STEP_MINUTES;
            if (safeMaxStart < weekTimeScale.min) {
                safeMaxStart = weekTimeScale.min;
            }
            if (snappedStart < weekTimeScale.min) {
                snappedStart = weekTimeScale.min;
            }
            if (snappedStart > safeMaxStart) {
                snappedStart = safeMaxStart;
            }
            var snappedEnd = snappedStart + WEEK_CREATION_DEFAULT_DURATION;
            if (snappedEnd > weekTimeScale.max) {
                snappedEnd = weekTimeScale.max;
                snappedStart = Math.max(weekTimeScale.min, snappedEnd - WEEK_CREATION_DEFAULT_DURATION);
            }
            if (snappedEnd <= snappedStart) {
                snappedEnd = Math.min(weekTimeScale.max, snappedStart + WEEK_CREATION_STEP_MINUTES);
            }
            var startTime = minutesToTime(snappedStart);
            var endTime = minutesToTime(snappedEnd);
            setSelectedOccurrenceId(null);
            setEditorState(function () {
                var next = createEditorState(null);
                next.date = day.iso;
                next.endDate = day.iso;
                next.startTime = startTime;
                next.endTime = endTime;
                next.status = 'planned';
                next.reason = '';
                return next;
            });
            openOccurrenceEditor(day.iso);
        }, [openOccurrenceEditor, weekOverview, weekTimeScale, weekTimelineRange, setSelectedOccurrenceId, setEditorState]);

        var finalizeDayDragSelection = useCallback(function (forcedEndIso) {
            var startIso = dragRangeStartIsoRef.current || '';
            var endIso = forcedEndIso || dragRangeEndIsoRef.current || '';
            if (!startIso || !endIso) {
                return false;
            }
            var bounds = normalizeIsoDateRange(startIso, endIso);
            isPointerDraggingRangeRef.current = false;
            dragRangeStartIsoRef.current = '';
            dragRangeEndIsoRef.current = '';
            setRangeSelection(null);
            if (!bounds) {
                return false;
            }
            suppressDayClickUntilRef.current = Date.now() + 280;
            var dateList = buildIsoDateRange(bounds.start, bounds.end);
            if (!dateList.length) {
                return false;
            }

            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            var manualLotGroup = 'manual-range-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
            var createdOccurrences = dateList.map(function (dateIso, index) {
                return {
                    id: generateOccurrenceId(dateIso, '00:00', localOccurrences.length + index),
                    date: dateIso,
                    startTime: '00:00',
                    endTime: '23:59',
                    isAllDay: true,
                    status: 'planned',
                    reason: '',
                    source: 'manual',
                    title: dateList.length > 1
                        ? getString(strings, 'occurrenceRangeDraftLabel', 'Plage de dates')
                        : getString(strings, 'occurrenceFixedDraftLabel', 'Date fixe'),
                    noteCalendar: dateList.length > 1
                        ? getString(strings, 'occurrenceRangeDraftLabel', 'Plage de dates')
                        : getString(strings, 'occurrenceFixedDraftLabel', 'Date fixe'),
                    createAsManualLot: true,
                    createAsManualLotGroup: manualLotGroup,
                };
            });

            var updatedList = localOccurrences.concat(createdOccurrences);
            var firstCreated = createdOccurrences[0] || null;
            setLocalOccurrences(updatedList);
            if (firstCreated) {
                setSelectedOccurrenceId(firstCreated.id);
                setEditorState(createEditorState(firstCreated));
            }
            persistOccurrences(updatedList, function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
            }, '', true).catch(function () {
                // Notification handled upstream.
            });
            return true;
        }, [localOccurrences, selectedOccurrenceId, persistOccurrences, setSelectedOccurrenceId, setEditorState, strings]);

        useEffect(function () {
            if (!isPointerDraggingRangeRef.current) {
                return;
            }
            if (typeof window === 'undefined' || typeof window.addEventListener !== 'function') {
                return;
            }
            var handlePointerUp = function () {
                if (!isPointerDraggingRangeRef.current) {
                    return;
                }
                finalizeDayDragSelection(dragRangeEndIsoRef.current || '');
            };
            window.addEventListener('mouseup', handlePointerUp);
            window.addEventListener('pointerup', handlePointerUp);
            return function () {
                window.removeEventListener('mouseup', handlePointerUp);
                window.removeEventListener('pointerup', handlePointerUp);
            };
        }, [rangeSelection, finalizeDayDragSelection]);

        var _generatorState = useState(createGeneratorState(initialPivotDate));
        var generatorState = _generatorState[0];
        var setGeneratorState = _generatorState[1];

        var safeGeneratorState = generatorState && typeof generatorState === 'object'
            ? generatorState
            : createGeneratorState(initialPivotDate);

        var generatorDays = safeGeneratorState.days && typeof safeGeneratorState.days === 'object'
            ? safeGeneratorState.days
            : {};
        var generatorMode = typeof safeGeneratorState.mode === 'string'
            ? safeGeneratorState.mode
            : 'weekly';
        var generatorFrequency = typeof safeGeneratorState.frequency === 'string'
            ? safeGeneratorState.frequency
            : 'every_week';
        var generatorOverrides = safeGeneratorState.timeOverrides && typeof safeGeneratorState.timeOverrides === 'object'
            ? safeGeneratorState.timeOverrides
            : {};
        var generatorStartDate = typeof safeGeneratorState.startDate === 'string'
            ? safeGeneratorState.startDate
            : '';
        var generatorEndDate = typeof safeGeneratorState.endDate === 'string'
            ? safeGeneratorState.endDate
            : '';
        var generatorStartTime = typeof safeGeneratorState.startTime === 'string'
            ? safeGeneratorState.startTime
            : '';
        var generatorEndTime = typeof safeGeneratorState.endTime === 'string'
            ? safeGeneratorState.endTime
            : '';
        var generatorAllDay = generatorStartTime === '00:00' && generatorEndTime === '23:59';
        var isSingleGeneratorMode = generatorMode === 'custom';
        var showGeneratorEndDate = generatorMode !== 'custom';
        var generatorMonthlyOrdinal = typeof safeGeneratorState.monthlyOrdinal === 'string'
            ? safeGeneratorState.monthlyOrdinal
            : 'first';
        var generatorMonthlyWeekday = typeof safeGeneratorState.monthlyWeekday === 'string'
            ? safeGeneratorState.monthlyWeekday
            : 'mon';
        useEffect(function () {
            if (!eventGeneratorPlan) {
                return;
            }
            setGeneratorState(function () {
                return createGeneratorStateFromPlan(eventGeneratorPlan, initialPivotDate);
            });
        }, [eventGeneratorPlanSignature, initialPivotDate, eventGeneratorPlan]);

        useEffect(function () {
            if (eventGeneratorPlan) {
                return;
            }
            setGeneratorState(function (prev) {
                var next = Object.assign({}, prev);
                if (!prev._explicitStart) {
                    next.startDate = formatISODate(initialPivotDate);
                }
                if (typeof prev.endDate === 'string' && prev.endDate !== '') {
                    var startDateObj = parseISODate(next.startDate);
                    var endDateObj = parseISODate(prev.endDate);
                    if (startDateObj && endDateObj && endDateObj < startDateObj) {
                        next.endDate = next.startDate;
                    }
                } else {
                    next.endDate = '';
                }
                return next;
            });
        }, [initialPivotDate, eventGeneratorPlan]);

        var initialSchedulePreview = useMemo(function () {
            if (event && typeof event.occurrenceScheduleSummary === 'string' && event.occurrenceScheduleSummary !== '') {
                return event.occurrenceScheduleSummary;
            }
            if (event && typeof event.scheduleSummary === 'string' && event.scheduleSummary !== '') {
                return event.scheduleSummary;
            }
            if (event && typeof event.scheduleDetail === 'string' && event.scheduleDetail !== '') {
                return event.scheduleDetail;
            }
            return '';
        }, [event]);

        var _schedulePreview = useState(initialSchedulePreview);
        var schedulePreview = _schedulePreview[0];
        var setSchedulePreview = _schedulePreview[1];

        var initialSchedulePreviewHtml = useMemo(function () {
            if (event && typeof event.inlineScheduleHtml === 'string' && event.inlineScheduleHtml.trim() !== '') {
                return event.inlineScheduleHtml;
            }
            return '';
        }, [event]);

        var _schedulePreviewHtml = useState(initialSchedulePreviewHtml);
        var schedulePreviewHtml = _schedulePreviewHtml[0];
        var setSchedulePreviewHtml = _schedulePreviewHtml[1];

        var _schedulePreviewVisible = useState(initialSchedulePreview !== '');
        var schedulePreviewVisible = _schedulePreviewVisible[0];
        var setSchedulePreviewVisible = _schedulePreviewVisible[1];

        var _schedulePreviewAutoSync = useState(false);
        var schedulePreviewAutoSync = _schedulePreviewAutoSync[0];
        var setSchedulePreviewAutoSync = _schedulePreviewAutoSync[1];

        useEffect(function () {
            setSchedulePreview(initialSchedulePreview);
            setSchedulePreviewVisible(initialSchedulePreview !== '');
            setSchedulePreviewAutoSync(initialSchedulePreview !== '');
        }, [initialSchedulePreview]);

        useEffect(function () {
            setSchedulePreviewHtml(initialSchedulePreviewHtml);
        }, [initialSchedulePreviewHtml]);

        var selectedDayOccurrences = useMemo(function () {
            if (!selectedOccurrence || !selectedOccurrence.date) {
                return [];
            }
            return occurrencesByDate[selectedOccurrence.date] || [selectedOccurrence];
        }, [selectedOccurrence, occurrencesByDate]);

        var eventUsesGenerator = !!(event && event.isFromGenerator);
        var hasManualOccurrences = hasExistingOccurrences && !eventUsesGenerator;
        var canShowGeneratorForm = true;
        var hasSchedulePreviewHtml = typeof schedulePreviewHtml === 'string' && schedulePreviewHtml.trim() !== '';
        var canShowGeneratedPreview = localOccurrences.length > 0 || hasSchedulePreviewHtml;
        var highlightedBatchId = hoveredBatchId || activeBatchId;
        var batchDatesMap = useMemo(function () {
            var map = {};
            localOccurrences.forEach(function (occurrence) {
                if (!occurrence || !occurrence.date) {
                    return;
                }
                var batchId = getOccurrenceBatchId(occurrence);
                if (!batchId) {
                    return;
                }
                if (!map[batchId]) {
                    map[batchId] = {};
                }
                map[batchId][occurrence.date] = true;
            });
            return map;
        }, [localOccurrences]);
        var _localBatches = useState(null);
        var localBatches = _localBatches[0];
        var setLocalBatches = _localBatches[1];

        var _batchProcessingId = useState('');
        var batchProcessingId = _batchProcessingId[0];
        var setBatchProcessingId = _batchProcessingId[1];

        var _batchConfigDrafts = useState({});
        var batchConfigDrafts = _batchConfigDrafts[0];
        var setBatchConfigDrafts = _batchConfigDrafts[1];

        var generationHistory = (localBatches !== null
            ? localBatches
            : (event && Array.isArray(event.occurrenceGenerationBatches)
                ? event.occurrenceGenerationBatches
                : [])
        ).filter(function (batch) {
            return batch && isOccurrenceBatchActive(batch.status);
        });

        var batchConfigById = useMemo(function () {
            var map = {};
            generationHistory.forEach(function (batch) {
                if (!batch || !batch.batchId) {
                    return;
                }
                var config = batch.configSnapshot && typeof batch.configSnapshot === 'object'
                    ? batch.configSnapshot
                    : {};
                map[String(batch.batchId)] = config;
            });
            return map;
        }, [generationHistory]);

        var lotLocationOptions = useMemo(function () {
            var rawOptions = globalLocationOptions.length > 0 ? globalLocationOptions : loadedGlobalOptions.locations;
            if (Array.isArray(rawOptions) && rawOptions.length > 0) {
                return rawOptions
                    .map(function (option) {
                        if (!option || typeof option !== 'object') {
                            return null;
                        }
                        var locId = option.id !== undefined ? parseInt(option.id, 10) : parseInt(option.value || option.location_id || '0', 10);
                        var locName = option.name || option.label || option.text || '';
                        if (locId <= 0 || !locName) {
                            return null;
                        }
                        return { id: locId, name: String(locName) };
                    })
                    .filter(function (option) { return !!option; });
            }

            var map = {};
            var options = [];

            if (event && Array.isArray(event.locationLinks)) {
                event.locationLinks.forEach(function (link) {
                    var loc = link && link.location ? link.location : link;
                    var locId = loc && loc.id ? parseInt(loc.id, 10) : 0;
                    var locName = loc && loc.name ? String(loc.name) : '';
                    if (locId > 0 && locName && !map[locId]) {
                        map[locId] = true;
                        options.push({ id: locId, name: locName });
                    }
                });
            }

            if (event && event.location && event.location.id && event.location.name) {
                var fallbackId = parseInt(event.location.id, 10);
                if (fallbackId > 0 && !map[fallbackId]) {
                    map[fallbackId] = true;
                    options.push({ id: fallbackId, name: String(event.location.name) });
                }
            }

            return options;
        }, [globalLocationOptions, loadedGlobalOptions.locations, event && event.locationLinks, event && event.location]);

        var lotMemberOptions = useMemo(function () {
            var rawMembers = globalMemberOptions.length > 0 ? globalMemberOptions : loadedGlobalOptions.members;
            if (Array.isArray(rawMembers) && rawMembers.length > 0) {
                return rawMembers
                    .map(function (member) {
                        if (!member || typeof member !== 'object') {
                            return null;
                        }
                        var memberId = member.id !== undefined ? parseInt(member.id, 10) : parseInt(member.value || member.member_id || '0', 10);
                        var memberName = member.name || member.label || member.text || '';
                        if (memberId <= 0 || !memberName) {
                            return null;
                        }
                        return { id: memberId, name: String(memberName) };
                    })
                    .filter(function (member) { return !!member; });
            }

            if (!event || !Array.isArray(event.animateurs)) {
                return [];
            }
            return event.animateurs
                .map(function (member) {
                    var memberId = member && member.id ? parseInt(member.id, 10) : 0;
                    var memberName = member && member.name ? String(member.name) : '';
                    if (memberId <= 0 || !memberName) {
                        return null;
                    }
                    return { id: memberId, name: memberName };
                })
                .filter(function (member) { return !!member; });
        }, [globalMemberOptions, loadedGlobalOptions.members, event && event.animateurs]);

        var locationNameById = useMemo(function () {
            var map = {};
            lotLocationOptions.forEach(function (option) {
                if (!option || !option.id) {
                    return;
                }
                map[option.id] = option.name || '';
            });
            return map;
        }, [lotLocationOptions]);

        var memberNameById = useMemo(function () {
            var map = {};
            lotMemberOptions.forEach(function (option) {
                if (!option || !option.id) {
                    return;
                }
                map[option.id] = option.name || '';
            });
            return map;
        }, [lotMemberOptions]);

        var formatFrenchHourLabel = useCallback(function (dateObj) {
            if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
                return '';
            }
            var hours = dateObj.getHours();
            var minutes = dateObj.getMinutes();
            if (minutes === 0) {
                return String(hours) + 'h';
            }
            return String(hours) + 'h' + String(minutes).padStart(2, '0');
        }, []);

        var normalizeLegacyManualSummary = useCallback(function (value) {
            if (typeof value !== 'string') {
                return '';
            }
            var text = value.trim();
            var legacyMatch = text.match(/^Occurrence manuelle\s*:\s*(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/i);
            if (!legacyMatch) {
                return text;
            }

            var startDate = new Date(
                parseInt(legacyMatch[3], 10),
                parseInt(legacyMatch[2], 10) - 1,
                parseInt(legacyMatch[1], 10),
                parseInt(legacyMatch[4], 10),
                parseInt(legacyMatch[5], 10),
                0,
                0
            );
            var endDate = new Date(
                parseInt(legacyMatch[8], 10),
                parseInt(legacyMatch[7], 10) - 1,
                parseInt(legacyMatch[6], 10),
                parseInt(legacyMatch[9], 10),
                parseInt(legacyMatch[10], 10),
                0,
                0
            );

            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                return text;
            }

            var dayLabel = startDate.toLocaleDateString('fr-BE', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
            if (dayLabel) {
                dayLabel = dayLabel.charAt(0).toUpperCase() + dayLabel.slice(1);
            }

            var startHour = formatFrenchHourLabel(startDate);
            var endHour = formatFrenchHourLabel(endDate);
            return dayLabel + ' de ' + startHour + ' à ' + endHour;
        }, [formatFrenchHourLabel]);

        var formatBatchPreviewDate = useCallback(function (value) {
            if (typeof value !== 'string') {
                return '';
            }

            var raw = value.trim();
            if (!raw) {
                return '';
            }

            var hasTime = /\d{2}:\d{2}/.test(raw);
            var candidate = raw;
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                candidate = raw + 'T00:00:00';
            } else if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/.test(raw)) {
                candidate = raw.replace(' ', 'T');
            }

            var parsed = new Date(candidate);
            if (isNaN(parsed.getTime())) {
                return raw;
            }

            var dateLabel = parsed.toLocaleDateString('fr-BE', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });

            if (hasTime) {
                dateLabel += ' ' + formatFrenchHourLabel(parsed);
            }

            return dateLabel;
        }, [formatFrenchHourLabel]);

        var selectedGlobalLotLines = generationHistory.reduce(function (acc, batch, index) {
            if (!batch || typeof batch !== 'object') {
                return acc;
            }
            if (!isOccurrenceBatchActive(batch.status)) {
                return acc;
            }
            var summary = batch.summary && typeof batch.summary === 'object' ? batch.summary : {};
            if (!summary.include_in_global_schedule) {
                return acc;
            }

            var defaultLotLabel = getString(strings, 'occurrenceGenerationHistoryLot', 'Lot #{{n}}').replace('{{n}}', String(generationHistory.length - index));
            var batchTitle = typeof summary.batch_title === 'string' ? summary.batch_title.trim() : '';
            var lotLabel = batchTitle !== '' ? batchTitle : defaultLotLabel;
            var previewLabel = batchTitle !== '' ? lotLabel : '';
            var text = typeof summary.schedule_summary === 'string' ? summary.schedule_summary : '';
            if (!text) {
                text = getString(strings, 'occurrenceGenerationSchedulePreviewFallback', 'Aucun résumé disponible pour ce lot.');
            }
            text = normalizeLegacyManualSummary(text);

            var assignedLocationName = '';
            if (typeof summary.assigned_location_name === 'string' && summary.assigned_location_name.trim() !== '') {
                assignedLocationName = summary.assigned_location_name.trim();
            } else if (summary.assigned_location_id) {
                var assignedLocId = parseInt(summary.assigned_location_id, 10);
                if (assignedLocId > 0 && locationNameById[assignedLocId]) {
                    assignedLocationName = locationNameById[assignedLocId];
                }
            }

            var assignedMemberNames = [];
            if (Array.isArray(summary.assigned_member_names)) {
                assignedMemberNames = summary.assigned_member_names
                    .map(function (name) { return String(name || '').trim(); })
                    .filter(function (name) { return name !== ''; });
            }
            if (assignedMemberNames.length === 0) {
                var assignedMemberIds = Array.isArray(summary.assigned_member_ids)
                    ? summary.assigned_member_ids
                    : (summary.assigned_member_id ? [summary.assigned_member_id] : []);
                assignedMemberNames = assignedMemberIds
                    .map(function (id) {
                        var parsed = parseInt(id, 10);
                        return parsed > 0 && memberNameById[parsed] ? memberNameById[parsed] : '';
                    })
                    .filter(function (name) { return name !== ''; });
            }

            var assignmentParts = [];
            if (assignedLocationName) {
                assignmentParts.push(
                    getString(strings, 'occurrenceGenerationPreviewLocationPrefix', 'Lieu : {{value}}')
                        .replace('{{value}}', assignedLocationName)
                );
            }
            if (assignedMemberNames.length > 0) {
                assignmentParts.push(
                    getString(strings, 'occurrenceGenerationPreviewMembersPrefix', 'Animateur : {{value}}')
                        .replace('{{value}}', assignedMemberNames.join(', '))
                );
            }

            var dateParts = [];
            if (summary.include_dates_in_schedule_preview) {
                var batchConfig = batch.configSnapshot && typeof batch.configSnapshot === 'object' ? batch.configSnapshot : {};
                var lotStartRaw = '';
                var lotEndRaw = '';
                if (typeof batchConfig.startDate === 'string' && batchConfig.startDate !== '') {
                    lotStartRaw = batchConfig.startDate;
                } else if (typeof batchConfig.startDateISO === 'string' && batchConfig.startDateISO !== '') {
                    lotStartRaw = batchConfig.startDateISO;
                } else if (typeof batchConfig.start === 'string' && batchConfig.start !== '') {
                    lotStartRaw = batchConfig.start;
                }
                if (typeof batchConfig.endDate === 'string' && batchConfig.endDate !== '') {
                    lotEndRaw = batchConfig.endDate;
                } else if (typeof batchConfig.endDateISO === 'string' && batchConfig.endDateISO !== '') {
                    lotEndRaw = batchConfig.endDateISO;
                } else if (typeof batchConfig.end === 'string' && batchConfig.end !== '') {
                    lotEndRaw = batchConfig.end;
                }

                var lotStartLabel = formatBatchPreviewDate(lotStartRaw);
                var lotEndLabel = formatBatchPreviewDate(lotEndRaw);
                if (lotStartLabel) {
                    dateParts.push(
                        getString(strings, 'occurrenceGenerationPreviewStartDate', 'Date de début : {{value}}').replace('{{value}}', lotStartLabel)
                    );
                }
                if (lotEndLabel) {
                    dateParts.push(
                        getString(strings, 'occurrenceGenerationPreviewEndDate', 'Date de fin : {{value}}').replace('{{value}}', lotEndLabel)
                    );
                }
            }

            acc.push({
                key: (batch.batchId || 'lot-' + index),
                label: previewLabel,
                text: text,
                assignment: assignmentParts.join(' | '),
                dates: dateParts.join(' | '),
            });
            return acc;
        }, []);

        if (selectedGlobalLotLines.length > 0) {
            canShowGeneratedPreview = true;
        }

        useEffect(function () {
            setLocalBatches(null);
            setBatchProcessingId('');
            setBatchConfigDrafts({});
        }, [event && event.id]);

        useEffect(function () {
            var eventId = event && event.id ? parseInt(event.id, 10) : 0;
            if (!apiPost || eventId <= 0) {
                return;
            }
            if (globalLocationOptions.length > 0 || globalMemberOptions.length > 0) {
                return;
            }

            apiPost('mj_regmgr_get_event_editor', { eventId: eventId })
                .then(function (data) {
                    var options = data && data.form && data.form.options ? data.form.options : {};
                    setLoadedGlobalOptions({
                        locations: Array.isArray(options.locations) ? options.locations : [],
                        members: Array.isArray(options.animateurs) ? options.animateurs : [],
                    });
                })
                .catch(function () {
                    // Keep graceful fallback to event-specific options.
                });
        }, [apiPost, event && event.id, globalLocationOptions, globalMemberOptions]);

        var lastSavedSchedulePreviewRef = useRef(null);
        useEffect(function () {
            var eventId = event && event.id ? parseInt(event.id, 10) : 0;
            if (!apiPost || eventId <= 0) { return; }
            var lines = [];
            for (var _i = 0; _i < selectedGlobalLotLines.length; _i++) {
                var _line = selectedGlobalLotLines[_i];
                var _parts = [];
                var _header = _line.label
                    ? (_line.label + (_line.text ? ' : ' + _line.text : ''))
                    : (_line.text || '');
                if (_header) { _parts.push(_header); }
                if (_line.assignment) { _parts.push(_line.assignment); }
                if (_line.dates) { _parts.push(_line.dates); }
                if (_parts.length > 0) { lines.push(_parts.join('\n')); }
            }
            var previewText = lines.join('\n\n');
            if (previewText === lastSavedSchedulePreviewRef.current) { return; }
            lastSavedSchedulePreviewRef.current = previewText;
            apiPost('mj_regmgr_save_event_schedule_preview', {
                eventId: eventId,
                schedulePreview: previewText,
            }).catch(function (err) {
                console.warn('[MjRegMgr] saveEventSchedulePreview error', err && err.message ? err.message : err);
            });
        }, [localBatches, event && event.id, apiPost]);

        var handleDeleteBatch = useCallback(function (batchId) {
            if (!apiPost || !event || !batchId) { return; }
            var eventId = event.id;
            setBatchProcessingId(batchId + ':delete');
            apiPost('mj_regmgr_delete_occurrence_batch', { eventId: eventId, batchId: batchId })
                .then(function (data) {
                    setBatchProcessingId('');
                    var nextBatches = data && Array.isArray(data.occurrenceGenerationBatches)
                        ? data.occurrenceGenerationBatches.filter(function (batch) { return batch && isOccurrenceBatchActive(batch.status); })
                        : null;
                    if (nextBatches) {
                        setLocalBatches(nextBatches);
                        if (onBatchesUpdate) { onBatchesUpdate(nextBatches); }
                    }
                    setLocalOccurrences(function (prev) {
                        if (!Array.isArray(prev)) {
                            return prev;
                        }
                        return prev.filter(function (occ) {
                            if (!occ || typeof occ !== 'object') {
                                return true;
                            }
                            var occBatchId = occ.generationBatchId || occ.generation_batch_id || '';
                            return occBatchId !== batchId;
                        });
                    });
                })
                .catch(function (err) {
                    setBatchProcessingId('');
                    if (typeof window !== 'undefined' && window.alert) {
                        window.alert(err && err.message ? err.message : 'Erreur lors de la suppression du lot.');
                    }
                    console.warn('[MjRegMgr] deleteOccurrenceBatch error', err && err.message ? err.message : err);
                });
        }, [apiPost, event, onBatchesUpdate]);

        var handleToggleBatchScheduleFlag = useCallback(function (batchId, include) {
            if (!apiPost || !event) { return; }
            var eventId = event.id;
            setBatchProcessingId(batchId + ':flag');
            apiPost('mj_regmgr_update_batch_schedule_flag', { eventId: eventId, batchId: batchId, includeInGlobalSchedule: include })
                .then(function (data) {
                    setBatchProcessingId('');
                    if (data && Array.isArray(data.occurrenceGenerationBatches)) {
                        var nextBatches = data.occurrenceGenerationBatches.filter(function (batch) {
                            return batch && isOccurrenceBatchActive(batch.status);
                        });
                        setLocalBatches(nextBatches);
                        if (onBatchesUpdate) { onBatchesUpdate(nextBatches); }
                    }
                })
                .catch(function (err) {
                    setBatchProcessingId('');
                    console.warn('[MjRegMgr] updateBatchScheduleFlag error', err && err.message ? err.message : err);
                });
        }, [apiPost, event, onBatchesUpdate]);

        var handleToggleBatchPreviewDatesFlag = useCallback(function (batchId, include) {
            if (!apiPost || !event) { return; }
            var eventId = event.id;
            setBatchProcessingId(batchId + ':preview-dates');
            apiPost('mj_regmgr_update_batch_preview_dates_flag', { eventId: eventId, batchId: batchId, includeDatesInSchedulePreview: include })
                .then(function (data) {
                    setBatchProcessingId('');
                    if (data && Array.isArray(data.occurrenceGenerationBatches)) {
                        var nextBatches = data.occurrenceGenerationBatches.filter(function (batch) {
                            return batch && isOccurrenceBatchActive(batch.status);
                        });
                        setLocalBatches(nextBatches);
                        if (onBatchesUpdate) { onBatchesUpdate(nextBatches); }
                    }
                })
                .catch(function (err) {
                    setBatchProcessingId('');
                    console.warn('[MjRegMgr] updateBatchPreviewDatesFlag error', err && err.message ? err.message : err);
                });
        }, [apiPost, event, onBatchesUpdate]);

        var handleRenameBatch = useCallback(function (batchId, currentTitle) {
            if (!apiPost || !event || !batchId) {
                return;
            }

            var promptLabel = getString(strings, 'occurrenceGenerationRenameBatchPrompt', 'Titre du lot');
            var nextTitle = window.prompt(promptLabel, currentTitle || '');
            if (nextTitle === null) {
                return;
            }

            var eventId = event.id;
            setBatchProcessingId(batchId + ':title');
            apiPost('mj_regmgr_update_batch_title', { eventId: eventId, batchId: batchId, title: nextTitle })
                .then(function (data) {
                    setBatchProcessingId('');
                    if (data && Array.isArray(data.occurrenceGenerationBatches)) {
                        setLocalBatches(data.occurrenceGenerationBatches);
                        if (onBatchesUpdate) { onBatchesUpdate(data.occurrenceGenerationBatches); }
                    }
                })
                .catch(function (err) {
                    setBatchProcessingId('');
                    if (typeof window !== 'undefined' && window.alert) {
                        window.alert(err && err.message ? err.message : 'Erreur lors de la mise à jour du titre.');
                    }
                    console.warn('[MjRegMgr] updateBatchTitle error', err && err.message ? err.message : err);
                });
        }, [apiPost, event, onBatchesUpdate, strings]);

        var handleUpdateBatchAssignment = useCallback(function (batchId, locationId, memberIds, occurrenceStatus) {
            if (!apiPost || !event || !batchId) {
                return;
            }

            var eventId = event.id;
            var normalizedOccurrenceStatus = normalizeOccurrenceStatus(occurrenceStatus || 'planned');
            setBatchProcessingId(batchId + ':assignment');
            apiPost('mj_regmgr_update_batch_assignment', {
                eventId: eventId,
                batchId: batchId,
                locationId: locationId > 0 ? locationId : 0,
                memberIds: Array.isArray(memberIds) ? memberIds : [],
                occurrenceStatus: normalizedOccurrenceStatus,
            })
                .then(function (data) {
                    setBatchProcessingId('');
                    if (data && Array.isArray(data.occurrenceGenerationBatches)) {
                        setLocalBatches(data.occurrenceGenerationBatches);
                        if (onBatchesUpdate) { onBatchesUpdate(data.occurrenceGenerationBatches); }
                    }
                })
                .catch(function (err) {
                    setBatchProcessingId('');
                    if (typeof window !== 'undefined' && window.alert) {
                        window.alert(err && err.message ? err.message : 'Erreur lors de la mise à jour de l\'attribution du lot.');
                    }
                    console.warn('[MjRegMgr] updateBatchAssignment error', err && err.message ? err.message : err);
                });
                }, [apiPost, event, onBatchesUpdate]);

        var handleUpdateBatchConfig = useCallback(function (batchId, partialConfig) {
            if (!apiPost || !event || !batchId || !partialConfig || typeof partialConfig !== 'object') {
                return;
            }

            var eventId = event.id;
            setBatchProcessingId(batchId + ':config');
            apiPost('mj_regmgr_update_occurrence_batch_config', {
                eventId: eventId,
                batchId: batchId,
                config: partialConfig,
            })
                .then(function (data) {
                    setBatchProcessingId('');
                    setBatchConfigDrafts(function (prev) {
                        var next = Object.assign({}, prev || {});
                        delete next[batchId];
                        return next;
                    });
                    if (data && Array.isArray(data.occurrenceGenerationBatches)) {
                        var nextBatches = data.occurrenceGenerationBatches.filter(function (batch) {
                            return batch && isOccurrenceBatchActive(batch.status);
                        });
                        setLocalBatches(nextBatches);
                        if (onBatchesUpdate) { onBatchesUpdate(nextBatches); }
                    }
                    if (data && Array.isArray(data.occurrences)) {
                        setLocalOccurrences(data.occurrences.map(function (occ, index) {
                            return normalizeOccurrence(occ, index);
                        }));
                    }
                })
                .catch(function (err) {
                    setBatchProcessingId('');
                    if (typeof window !== 'undefined' && window.alert) {
                        window.alert(err && err.message ? err.message : 'Erreur lors de la mise à jour de la configuration du lot.');
                    }
                    console.warn('[MjRegMgr] updateBatchConfig error', err && err.message ? err.message : err);
                });
        }, [apiPost, event, onBatchesUpdate]);

        var generatedPreviewCard = canShowGeneratedPreview && h('div', {
            class: 'mj-regmgr-occurrence__header-preview',
            style: {
                display: 'flex',
                alignItems: 'flex-start',
                gap: '12px',
                padding: '10px 12px',
                borderRadius: '16px',
                border: '1px solid rgba(148, 163, 184, 0.18)',
                background: 'rgba(255, 255, 255, 0.82)',
                boxShadow: '0 8px 20px rgba(15, 23, 42, 0.06)',
                maxWidth: '560px',
                minWidth: '280px',
                flex: '1 1 420px',
            },
        }, [
            h('div', { class: 'mj-regmgr-occurrence__header-preview-copy', style: { display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 } }, [
                h('strong', { style: { fontSize: '0.88rem', lineHeight: 1.2, color: '#0f172a' } }, getString(strings, 'occurrenceGeneratorPreviewLabel', 'Aperçu de l’horaire')),
            ]),
            selectedGlobalLotLines.length > 0 && h('div', {
                class: 'mj-regmgr-occurrence__header-preview-text',
                style: {
                    fontSize: '0.84rem',
                    lineHeight: 1.45,
                    color: '#0f172a',
                    overflow: 'hidden',
                    display: '-webkit-box',
                    WebkitBoxOrient: 'vertical',
                    WebkitLineClamp: 2,
                    flex: '1 1 auto',
                    minWidth: 0,
                },
                dangerouslySetInnerHTML: { __html: formatPreviewTextWithBoldWeekdays(selectedGlobalLotLines[0].text) },
            }),
            !selectedGlobalLotLines.length && hasSchedulePreviewHtml && h('div', {
                class: 'mj-regmgr-occurrence__header-preview-text',
                style: {
                    fontSize: '0.84rem',
                    lineHeight: 1.45,
                    color: '#0f172a',
                    overflow: 'hidden',
                    display: '-webkit-box',
                    WebkitBoxOrient: 'vertical',
                    WebkitLineClamp: 2,
                    flex: '1 1 auto',
                    minWidth: 0,
                },
                dangerouslySetInnerHTML: { __html: schedulePreviewHtml },
            }),
            !selectedGlobalLotLines.length && !hasSchedulePreviewHtml && h('span', { class: 'mj-regmgr-occurrence__hint', style: { fontSize: '0.84rem', margin: 0 } }, getString(strings, 'occurrenceSchedulePreviewEmpty', 'Aucun horaire détecté')),
        ]);

        var sidebarContent = h('div', { class: 'mj-regmgr-occurrence__sidebar' }, [
            h('details', { class: 'mj-regmgr-occurrence__card mj-regmgr-occurrence__card--fold mj-regmgr-occurrence__card--generation-history', open: true }, [
                h('summary', { class: 'mj-regmgr-occurrence__fold-summary' }, [
                    h('div', { class: 'mj-regmgr-occurrence__fold-heading' }, [
                        h('strong', null, getString(strings, 'occurrenceGenerationHistoryTitle', 'Lot d\'occurrences')),
                    ]),
                    h('span', { class: 'mj-regmgr-occurrence__fold-toggle', 'aria-hidden': true }, '⌄'),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__fold-body' }, [
                    generationHistory.length === 0
                    ? h('p', { class: 'mj-regmgr-occurrence__hint' }, getString(strings, 'occurrenceGenerationHistoryEmpty', 'Aucune génération enregistrée pour cet événement.'))
                    : h('ul', { class: 'mj-regmgr-occurrence__history-list' }, generationHistory.map(function (batch, index) {
                        if (!batch || typeof batch !== 'object') {
                            return null;
                        }
                        var batchId = batch.batchId || '';
                        var shortId = batchId;
                        var count = typeof batch.occurrencesCount === 'number' ? batch.occurrencesCount : 0;
                        var rawDate = typeof batch.createdAt === 'string' && batch.createdAt !== '' ? batch.createdAt : '';
                        var createdAtLabel = '';
                        if (rawDate) {
                            try {
                                var d = new Date(rawDate.replace(' ', 'T'));
                                createdAtLabel = d.toLocaleDateString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric' })
                                    + ' ' + d.toLocaleTimeString('fr-BE', { hour: '2-digit', minute: '2-digit' });
                            } catch (e) {
                                createdAtLabel = rawDate;
                            }
                        } else {
                            createdAtLabel = getString(strings, 'occurrenceGenerationHistoryUnknownDate', 'date inconnue');
                        }
                        var batchSummary = batch.summary && typeof batch.summary === 'object' ? batch.summary : {};
                        var batchTitle = typeof batchSummary.batch_title === 'string' ? batchSummary.batch_title.trim() : '';
                        var lotLabel = batchTitle;
                        var countLabel = getString(strings, 'occurrenceGenerationHistoryCount', '{{count}} occurrence(s)').replace('{{count}}', String(count));
                        var lotSchedulePreview = typeof batchSummary.schedule_summary === 'string' ? batchSummary.schedule_summary : '';
                        if (!lotSchedulePreview) {
                            lotSchedulePreview = getString(strings, 'occurrenceGenerationSchedulePreviewFallback', 'Aucun résumé disponible pour ce lot.');
                        }
                        lotSchedulePreview = normalizeLegacyManualSummary(lotSchedulePreview);
                        var batchConfig = batch.configSnapshot && typeof batch.configSnapshot === 'object' ? batch.configSnapshot : {};
                        var batchDraft = batchId && batchConfigDrafts && typeof batchConfigDrafts === 'object' && batchConfigDrafts[batchId] && typeof batchConfigDrafts[batchId] === 'object'
                            ? batchConfigDrafts[batchId]
                            : null;
                        var effectiveBatchConfig = batchDraft
                            ? Object.assign({}, batchConfig, batchDraft)
                            : batchConfig;
                        var lotStartRaw = '';
                        var lotEndRaw = '';
                        if (typeof effectiveBatchConfig.startDate === 'string' && effectiveBatchConfig.startDate !== '') {
                            lotStartRaw = effectiveBatchConfig.startDate;
                        } else if (typeof effectiveBatchConfig.startDateISO === 'string' && effectiveBatchConfig.startDateISO !== '') {
                            lotStartRaw = effectiveBatchConfig.startDateISO;
                        } else if (typeof effectiveBatchConfig.start === 'string' && effectiveBatchConfig.start !== '') {
                            lotStartRaw = effectiveBatchConfig.start;
                        }
                        if (typeof effectiveBatchConfig.endDate === 'string' && effectiveBatchConfig.endDate !== '') {
                            lotEndRaw = effectiveBatchConfig.endDate;
                        } else if (typeof effectiveBatchConfig.endDateISO === 'string' && effectiveBatchConfig.endDateISO !== '') {
                            lotEndRaw = effectiveBatchConfig.endDateISO;
                        } else if (typeof effectiveBatchConfig.end === 'string' && effectiveBatchConfig.end !== '') {
                            lotEndRaw = effectiveBatchConfig.end;
                        }
                        var lotStartLabel = formatBatchPreviewDate(lotStartRaw);
                        var lotEndLabel = formatBatchPreviewDate(lotEndRaw);
                        var includeInGlobalSchedule = !!batchSummary.include_in_global_schedule;
                        var includeDatesInSchedulePreview = !!batchSummary.include_dates_in_schedule_preview;
                        var assignedLocationId = batchSummary.assigned_location_id ? parseInt(batchSummary.assigned_location_id, 10) : 0;
                        var assignedMemberIds = Array.isArray(batchSummary.assigned_member_ids)
                            ? batchSummary.assigned_member_ids.map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; })
                            : (batchSummary.assigned_member_id ? [parseInt(batchSummary.assigned_member_id, 10)] : []);
                        var batchOccurrenceStatus = 'planned';
                        if (typeof batchSummary.generated_occurrence_status === 'string' && batchSummary.generated_occurrence_status !== '') {
                            batchOccurrenceStatus = normalizeOccurrenceStatus(batchSummary.generated_occurrence_status);
                        } else if (typeof effectiveBatchConfig.occurrenceStatus === 'string' && effectiveBatchConfig.occurrenceStatus !== '') {
                            batchOccurrenceStatus = normalizeOccurrenceStatus(effectiveBatchConfig.occurrenceStatus);
                        }
                        var batchMode = typeof effectiveBatchConfig.mode === 'string' && effectiveBatchConfig.mode !== '' ? effectiveBatchConfig.mode : 'weekly';
                        var batchFrequency = typeof effectiveBatchConfig.frequency === 'string' && effectiveBatchConfig.frequency !== ''
                            ? effectiveBatchConfig.frequency
                            : 'every_week';
                        var batchStartDate = typeof effectiveBatchConfig.startDate === 'string' ? effectiveBatchConfig.startDate : '';
                        var batchEndDate = typeof effectiveBatchConfig.endDate === 'string' ? effectiveBatchConfig.endDate : '';
                        var batchStartTime = typeof effectiveBatchConfig.startTime === 'string' ? effectiveBatchConfig.startTime : '09:00';
                        var batchEndTime = typeof effectiveBatchConfig.endTime === 'string' ? effectiveBatchConfig.endTime : '12:00';
                        var batchAllDay = batchMode === 'range' && batchStartTime === '00:00' && batchEndTime === '23:59';
                        var batchModeLabel = batchMode === 'weekly'
                            ? getString(strings, 'occurrenceGeneratorModeWeekly', 'Hebdomadaire')
                            : (batchMode === 'monthly'
                                ? getString(strings, 'occurrenceGeneratorModeMonthly', 'Mensuel')
                                : (batchMode === 'range'
                                    ? getString(strings, 'occurrenceGeneratorModeRange', 'Plage de dates')
                                    : getString(strings, 'occurrenceGeneratorModeCustom', 'Date fixe')));
                        var batchMonthlyOrdinal = typeof effectiveBatchConfig.monthlyOrdinal === 'string' && effectiveBatchConfig.monthlyOrdinal !== ''
                            ? effectiveBatchConfig.monthlyOrdinal
                            : 'first';
                        var batchMonthlyWeekday = typeof effectiveBatchConfig.monthlyWeekday === 'string' && effectiveBatchConfig.monthlyWeekday !== ''
                            ? effectiveBatchConfig.monthlyWeekday
                            : 'mon';
                        var batchDays = { mon: false, tue: false, wed: false, thu: false, fri: false, sat: false, sun: false };
                        if (Array.isArray(effectiveBatchConfig.days)) {
                            effectiveBatchConfig.days.forEach(function (dayKey) {
                                if (typeof dayKey === 'string' && Object.prototype.hasOwnProperty.call(batchDays, dayKey)) {
                                    batchDays[dayKey] = true;
                                }
                            });
                        } else if (effectiveBatchConfig.days && typeof effectiveBatchConfig.days === 'object') {
                            Object.keys(batchDays).forEach(function (dayKey) {
                                batchDays[dayKey] = !!effectiveBatchConfig.days[dayKey];
                            });
                        }
                        var batchOverridesSource = effectiveBatchConfig.overrides && typeof effectiveBatchConfig.overrides === 'object'
                            ? effectiveBatchConfig.overrides
                            : (effectiveBatchConfig.timeOverrides && typeof effectiveBatchConfig.timeOverrides === 'object'
                                ? effectiveBatchConfig.timeOverrides
                                : {});
                        var batchOverrides = {};
                        if (batchOverridesSource && typeof batchOverridesSource === 'object') {
                            OCCURRENCE_WEEKDAY_KEYS.forEach(function (dayKey) {
                                if (!batchOverridesSource[dayKey] || typeof batchOverridesSource[dayKey] !== 'object') {
                                    return;
                                }
                                var overrideStart = sanitizeTimeValue(batchOverridesSource[dayKey].start);
                                var overrideEnd = sanitizeTimeValue(batchOverridesSource[dayKey].end);
                                if (!overrideStart && !overrideEnd) {
                                    return;
                                }
                                var overrideEntry = {};
                                if (overrideStart) {
                                    overrideEntry.start = overrideStart;
                                }
                                if (overrideEnd) {
                                    overrideEntry.end = overrideEnd;
                                }
                                batchOverrides[dayKey] = overrideEntry;
                            });
                        }
                        var updateBatchConfigDraft = function (changes) {
                            var payload = {
                                mode: batchMode,
                                frequency: batchFrequency,
                                startDate: batchStartDate,
                                endDate: batchEndDate,
                                startTime: batchStartTime,
                                endTime: batchEndTime,
                                monthlyOrdinal: batchMonthlyOrdinal,
                                monthlyWeekday: batchMonthlyWeekday,
                                days: batchDays,
                                overrides: batchOverrides,
                            };
                            if (changes && typeof changes === 'object') {
                                Object.keys(changes).forEach(function (key) {
                                    payload[key] = changes[key];
                                });
                            }
                            setBatchConfigDrafts(function (prev) {
                                var next = Object.assign({}, prev || {});
                                next[batchId] = payload;
                                return next;
                            });
                        };
                        var saveBatchConfig = function () {
                            var payload = {
                                mode: batchMode,
                                frequency: batchFrequency,
                                startDate: batchStartDate,
                                endDate: batchEndDate,
                                startTime: batchStartTime,
                                endTime: batchEndTime,
                                monthlyOrdinal: batchMonthlyOrdinal,
                                monthlyWeekday: batchMonthlyWeekday,
                                days: batchDays,
                                overrides: batchOverrides,
                            };
                            handleUpdateBatchConfig(batchId, payload);
                        };
                        var isProcessing = batchProcessingId !== '';
                        var lotStateTone = includeInGlobalSchedule ? 'shared' : (batchOccurrenceStatus === 'cancelled' ? 'cancelled' : (batchOccurrenceStatus === 'confirmed' ? 'confirmed' : 'planned'));
                        var lotStatsLabel = countLabel + (batchAllDay ? ' • ' + getString(strings, 'occurrenceAllDayLabel', 'Toute la journée') : ' • ' + batchStartTime + ' → ' + batchEndTime);
                        var lotPrimaryMeta = [];
                        if (lotStartLabel || lotEndLabel) {
                            lotPrimaryMeta.push(lotStartLabel || '—');
                            if (lotEndLabel) {
                                lotPrimaryMeta.push(lotEndLabel);
                            }
                        }
                        var lotSecondaryMeta = [];
                        lotSecondaryMeta.push(getString(strings, 'occurrenceGenerationHistoryCount', '{{count}} occurrence(s)').replace('{{count}}', String(count)));
                        lotSecondaryMeta.push(getString(strings, 'occurrenceGenerationHistoryUnknownDate', 'date inconnue'));
                        var lotCardStyle = {
                            border: '1px solid rgba(15, 23, 42, 0.08)',
                            borderRadius: '18px',
                            background: 'linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%)',
                            boxShadow: '0 8px 24px rgba(15, 23, 42, 0.07)',
                            padding: '14px',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: '10px',
                        };
                        var lotTopbarStyle = {
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '10px',
                        };
                        var lotIdentityStyle = {
                            display: 'flex',
                            flexDirection: 'column',
                            gap: '3px',
                            minWidth: 0,
                        };
                        var lotBadgeStyle = {
                            display: 'inline-flex',
                            alignItems: 'center',
                            width: 'fit-content',
                            padding: '3px 8px',
                            borderRadius: '999px',
                            fontSize: '10px',
                            fontWeight: 700,
                            letterSpacing: '0.04em',
                            textTransform: 'uppercase',
                        };
                        var lotHeroStyle = {
                            display: 'flex',
                            flexDirection: 'column',
                            gap: '8px',
                            padding: '10px 12px',
                            borderRadius: '14px',
                            background: lotStateTone === 'shared'
                                ? 'linear-gradient(135deg, rgba(14, 116, 144, 0.12), rgba(14, 165, 233, 0.08))'
                                : (lotStateTone === 'cancelled'
                                    ? 'linear-gradient(135deg, rgba(220, 38, 38, 0.10), rgba(248, 113, 113, 0.06))'
                                    : (lotStateTone === 'confirmed'
                                        ? 'linear-gradient(135deg, rgba(22, 163, 74, 0.10), rgba(74, 222, 128, 0.06))'
                                        : 'linear-gradient(135deg, rgba(249, 250, 251, 0.96), rgba(255, 255, 255, 0.96))')),
                            border: '1px solid rgba(15, 23, 42, 0.06)',
                        };
                        var lotPillRowStyle = {
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: '6px',
                        };
                        var lotMetaGridStyle = {
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
                            gap: '12px',
                        };
                        var lotConfigStyle = {
                            marginTop: '4px',
                            borderRadius: '18px',
                            border: '1px solid rgba(148, 163, 184, 0.18)',
                            background: 'rgba(255,255,255,0.7)',
                            overflow: 'hidden',
                        };
                        var lotConfigBodyStyle = {
                            paddingTop: '14px',
                        };
                        var lotFooterStyle = {
                            display: 'flex',
                            justifyContent: 'flex-end',
                            paddingTop: '8px',
                        };
                        return h('li', {
                            key: batchId || ('batch-' + index),
                            class: classNames('mj-regmgr-occurrence__history-item', 'mj-regmgr-occurrence__batch-card', 'mj-regmgr-occurrence__batch-card--' + lotStateTone, {
                                'mj-regmgr-occurrence__batch-card--selected': !!includeInGlobalSchedule,
                                'mj-regmgr-occurrence__batch-card--has-dates': !!(lotStartLabel || lotEndLabel),
                            }),
                            style: Object.assign({}, lotCardStyle, highlightedBatchId && batchId && highlightedBatchId === String(batchId)
                                ? {
                                    borderColor: 'rgba(14, 165, 233, 0.65)',
                                    boxShadow: '0 0 0 2px rgba(14, 165, 233, 0.28), 0 8px 24px rgba(15, 23, 42, 0.07)',
                                }
                                : null),
                            onMouseEnter: function () {
                                if (batchId) {
                                    setHoveredBatchId(String(batchId));
                                }
                            },
                            onMouseLeave: function () {
                                setHoveredBatchId('');
                            },
                            onFocus: function () {
                                if (batchId) {
                                    setHoveredBatchId(String(batchId));
                                }
                            },
                            onBlur: function () {
                                setHoveredBatchId('');
                            },
                            onClick: function () {
                                if (batchId) {
                                    setActiveBatchId(String(batchId));
                                }
                            },
                        }, [
                            h('div', { class: 'mj-regmgr-occurrence__batch-card__topbar', style: lotTopbarStyle }, [
                                h('div', { class: 'mj-regmgr-occurrence__batch-card__identity', style: lotIdentityStyle }, [
                                    lotLabel !== '' && h('strong', { class: 'mj-regmgr-occurrence__batch-card__title', style: { fontSize: '1rem', lineHeight: 1.15, letterSpacing: '-0.02em' } }, lotLabel),
                                    h('span', { class: 'mj-regmgr-occurrence__history-item-count', style: { fontSize: '0.8rem', color: '#475569' } }, countLabel + ' • ' + batchModeLabel),
                                ]),
                                h('div', { class: 'mj-regmgr-occurrence__batch-card__actions', style: { display: 'flex', gap: '6px', flexWrap: 'wrap', justifyContent: 'flex-end' } }, [
                                    apiPost && h('button', {
                                        type: 'button',
                                        class: 'mj-regmgr-occurrence__batch-card__action mj-regmgr-occurrence__batch-card__action--ghost mj-regmgr-occurrence__batch-card__action--icon',
                                        style: { width: '30px', height: '30px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' },
                                        disabled: isProcessing,
                                        title: getString(strings, 'occurrenceGenerationRenameBatch', 'Renommer le lot'),
                                        onClick: function () { handleRenameBatch(batchId, batchTitle); },
                                    }, h('span', {
                                        class: 'mj-btn__icon',
                                        'aria-hidden': true,
                                        dangerouslySetInnerHTML: {
                                            __html: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
                                        },
                                    })),
                                    apiPost && h('button', {
                                        type: 'button',
                                        class: 'mj-regmgr-occurrence__batch-card__action mj-regmgr-occurrence__batch-card__action--danger mj-regmgr-occurrence__batch-card__action--icon',
                                        style: { width: '30px', height: '30px', padding: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' },
                                        disabled: isProcessing,
                                        title: getString(strings, 'occurrenceGenerationDeleteBatch', 'Supprimer ce lot'),
                                        onClick: function () {
                                            var confirmMsg = getString(strings, 'occurrenceGenerationDeleteConfirm', 'Supprimer les {{count}} occurrence(s) de ce lot ?').replace('{{count}}', String(count));
                                            if (window.confirm(confirmMsg)) {
                                                handleDeleteBatch(batchId);
                                            }
                                        },
                                    }, h('span', {
                                        class: 'mj-btn__icon',
                                        'aria-hidden': true,
                                        dangerouslySetInnerHTML: {
                                            __html: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
                                        },
                                    })),
                                ]),
                            ]),
                            h('div', { class: 'mj-regmgr-occurrence__batch-card__hero', style: lotHeroStyle }, [
                                h('div', { class: 'mj-regmgr-occurrence__batch-card__pill-row', style: lotPillRowStyle }, [
                                    h('span', { class: 'mj-regmgr-occurrence__batch-card__pill mj-regmgr-occurrence__batch-card__pill--count', style: Object.assign({}, lotBadgeStyle, { background: 'rgba(15, 118, 110, 0.14)', color: '#115e59' }) }, lotStatsLabel),
                                    h('span', { class: 'mj-regmgr-occurrence__batch-card__pill mj-regmgr-occurrence__batch-card__pill--meta', style: Object.assign({}, lotBadgeStyle, { background: 'rgba(15, 23, 42, 0.08)', color: '#334155' }) }, shortId ? ('#' + shortId) : getString(strings, 'occurrenceGenerationHistoryUnknownBatch', 'Lot sans identifiant')),
                                    h('span', { class: 'mj-regmgr-occurrence__batch-card__pill mj-regmgr-occurrence__batch-card__pill--date', style: Object.assign({}, lotBadgeStyle, { background: 'rgba(217, 119, 6, 0.12)', color: '#92400e' }) }, createdAtLabel),
                                ]),
                                lotSchedulePreview && h('div', { class: 'mj-regmgr-occurrence__batch-card__preview', style: { fontSize: '0.88rem', lineHeight: 1.35, color: '#0f172a', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, lotSchedulePreview),
                            ]),
                            apiPost && h('details', {
                                class: 'mj-regmgr-occurrence__history-item-assignment mj-regmgr-occurrence__history-item-config-fold mj-regmgr-occurrence__batch-card__config',
                                open: false,
                            }, [
                                h('summary', { class: 'mj-regmgr-occurrence__fold-summary mj-regmgr-occurrence__history-config-summary' }, [
                                    h('div', { class: 'mj-regmgr-occurrence__fold-heading' }, [
                                        h('span', { class: 'mj-regmgr-occurrence__history-config-title' }, getString(strings, 'occurrenceGenerationBatchConfigTitle', 'Configuration')),
                                    ]),
                                    h('span', { class: 'mj-regmgr-occurrence__fold-toggle', 'aria-hidden': true }, '⌄'),
                                ]),
                                h('div', { class: 'mj-regmgr-occurrence__fold-body mj-regmgr-occurrence__batch-card__config-body' }, [
                                    h('div', { class: 'mj-regmgr-occurrence__batch-card__flags', style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '12px' } }, [
                                        h('label', { class: 'mj-regmgr-occurrence__history-item-flag mj-regmgr-occurrence__batch-card__flag', style: { display: 'flex', gap: '10px', alignItems: 'flex-start', padding: '14px 16px', borderRadius: '16px', background: 'rgba(255,255,255,0.82)', border: '1px solid rgba(148, 163, 184, 0.16)' } }, [
                                            h('input', {
                                                type: 'checkbox',
                                                checked: includeInGlobalSchedule,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    handleToggleBatchScheduleFlag(batchId, e.currentTarget.checked);
                                                },
                                            }),
                                            h('span', { style: { fontWeight: 600, color: '#0f172a', lineHeight: 1.35 } }, getString(strings, 'occurrenceGenerationAddToGlobalSchedule', 'Ajouter à l\'horaire global')),
                                        ]),
                                        h('label', { class: 'mj-regmgr-occurrence__history-item-flag mj-regmgr-occurrence__batch-card__flag', style: { display: 'flex', gap: '10px', alignItems: 'flex-start', padding: '14px 16px', borderRadius: '16px', background: 'rgba(255,255,255,0.82)', border: '1px solid rgba(148, 163, 184, 0.16)' } }, [
                                            h('input', {
                                                type: 'checkbox',
                                                checked: includeDatesInSchedulePreview,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    handleToggleBatchPreviewDatesFlag(batchId, e.currentTarget.checked);
                                                },
                                            }),
                                            h('span', { style: { fontWeight: 600, color: '#0f172a', lineHeight: 1.35 } }, getString(strings, 'occurrenceGenerationIncludeDatesInPreview', 'Ajouter date début/fin dans l\'aperçu horaire')),
                                        ]),
                                    ]),
                                    h('div', { class: 'mj-regmgr-occurrence__form-row mj-regmgr-occurrence__batch-card__grid' }, [
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorModeLabel', 'Mode')),
                                            h('select', {
                                                class: 'mj-regmgr-occurrence__history-item-assignment-select',
                                                value: batchMode,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    var nextMode = e.currentTarget.value;
                                                    updateBatchConfigDraft({ mode: nextMode });
                                                },
                                            }, [
                                                h('option', { value: 'custom' }, getString(strings, 'occurrenceGeneratorModeCustom', 'Date unique')),
                                                h('option', { value: 'range' }, getString(strings, 'occurrenceGeneratorModeRange', 'Plage de dates')),
                                                h('option', { value: 'weekly' }, getString(strings, 'occurrenceGeneratorModeWeekly', 'Hebdomadaire')),
                                                h('option', { value: 'monthly' }, getString(strings, 'occurrenceGeneratorModeMonthly', 'Mensuel')),
                                            ]),
                                        ]),
                                        (batchMode === 'weekly') && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorFrequencyLabel', 'Fréquence')),
                                            h('select', {
                                                class: 'mj-regmgr-occurrence__history-item-assignment-select',
                                                value: batchFrequency,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    updateBatchConfigDraft({ frequency: e.currentTarget.value });
                                                },
                                            }, [
                                                h('option', { value: 'every_week' }, getString(strings, 'occurrenceGeneratorEveryWeek', 'Chaque semaine')),
                                                h('option', { value: 'every_two_weeks' }, getString(strings, 'occurrenceGeneratorEveryTwoWeeks', 'Toutes les deux semaines')),
                                            ]),
                                        ]),
                                    ]),
                                    h('div', { class: 'mj-regmgr-occurrence__form-row mj-regmgr-occurrence__batch-card__grid' }, [
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorStartDate', 'Date de début')),
                                            h('input', {
                                                type: 'date',
                                                class: 'mj-regmgr-occurrence__input',
                                                value: batchStartDate,
                                                disabled: isProcessing,
                                                onInput: function (e) {
                                                    updateBatchConfigDraft({ startDate: e.currentTarget.value });
                                                },
                                            }),
                                        ]),
                                        (batchMode !== 'custom') && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorEndDate', 'Date de fin')),
                                            h('input', {
                                                type: 'date',
                                                class: 'mj-regmgr-occurrence__input',
                                                value: batchEndDate,
                                                disabled: isProcessing,
                                                onInput: function (e) {
                                                    updateBatchConfigDraft({ endDate: e.currentTarget.value });
                                                },
                                            }),
                                        ]),
                                    ]),
                                    (batchMode === 'range') && h('div', { class: 'mj-regmgr-occurrence__form-field mj-regmgr-occurrence__batch-card__full-row' }, [
                                        h('label', { class: 'mj-regmgr-occurrence__label' }, [
                                            h('input', {
                                                type: 'checkbox',
                                                checked: batchAllDay,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    var checked = !!e.currentTarget.checked;
                                                    if (checked) {
                                                        updateBatchConfigDraft({ startTime: '00:00', endTime: '23:59' });
                                                    } else {
                                                        updateBatchConfigDraft({ startTime: '09:00', endTime: '17:00' });
                                                    }
                                                },
                                            }),
                                            ' ',
                                            getString(strings, 'occurrenceAllDayCheckbox', 'Toute la journée'),
                                        ]),
                                    ]),
                                    h('div', { class: 'mj-regmgr-occurrence__form-row mj-regmgr-occurrence__batch-card__grid' }, [
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceStartLabel', 'Heure de début')),
                                            h('input', {
                                                type: 'time',
                                                class: 'mj-regmgr-occurrence__input',
                                                value: batchStartTime,
                                                disabled: isProcessing || batchAllDay,
                                                onInput: function (e) {
                                                    updateBatchConfigDraft({ startTime: e.currentTarget.value });
                                                },
                                            }),
                                        ]),
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceEndLabel', 'Heure de fin')),
                                            h('input', {
                                                type: 'time',
                                                class: 'mj-regmgr-occurrence__input',
                                                value: batchEndTime,
                                                disabled: isProcessing || batchAllDay,
                                                onInput: function (e) {
                                                    updateBatchConfigDraft({ endTime: e.currentTarget.value });
                                                },
                                            }),
                                        ]),
                                    ]),
                                    (batchMode === 'weekly') && h('div', { class: 'mj-regmgr-occurrence__days mj-regmgr-occurrence__batch-card__days' }, OCCURRENCE_WEEKDAY_KEYS.map(function (dayKey, dayIndex) {
                                        var isDayActive = !!batchDays[dayKey];
                                        var dayOverride = batchOverrides[dayKey] || null;
                                        var dayStartValue = dayOverride && dayOverride.start ? dayOverride.start : batchStartTime;
                                        var dayEndValue = dayOverride && dayOverride.end ? dayOverride.end : batchEndTime;
                                        var hasDayOverride = !!(dayOverride && (dayOverride.start || dayOverride.end));
                                        return h('label', {
                                            key: 'batch-day-' + batchId + '-' + dayKey,
                                            class: classNames('mj-regmgr-occurrence__day-row', {
                                                'mj-regmgr-occurrence__day-row--active': isDayActive,
                                                'mj-regmgr-occurrence__day-row--override': hasDayOverride,
                                            }),
                                        }, [
                                            h('input', {
                                                type: 'checkbox',
                                                class: 'mj-regmgr-occurrence__day-row-checkbox',
                                                checked: isDayActive,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    var nextDays = Object.assign({}, batchDays);
                                                    var nextOverrides = Object.assign({}, batchOverrides);
                                                    nextDays[dayKey] = !!e.currentTarget.checked;
                                                    if (!nextDays[dayKey] && nextOverrides[dayKey]) {
                                                        delete nextOverrides[dayKey];
                                                    }
                                                    updateBatchConfigDraft({ days: nextDays, overrides: nextOverrides });
                                                },
                                            }),
                                            h('span', { class: 'mj-regmgr-occurrence__day-row-label' }, weekdayLabels[dayIndex] || dayKey),
                                            h('span', { class: 'mj-regmgr-occurrence__day-row-times' }, [
                                                h('input', {
                                                    type: 'time',
                                                    class: classNames('mj-regmgr-occurrence__day-row-input', 'mj-regmgr-occurrence__day-row-input--start', {
                                                        'mj-regmgr-occurrence__day-row-input--override': hasDayOverride && !!(dayOverride && dayOverride.start),
                                                    }),
                                                    value: dayStartValue || '',
                                                    disabled: !isDayActive || isProcessing,
                                                    onInput: function (e) {
                                                        var nextOverrides = Object.assign({}, batchOverrides);
                                                        var nextOverride = nextOverrides[dayKey]
                                                            ? Object.assign({ start: '', end: '' }, nextOverrides[dayKey])
                                                            : { start: '', end: '' };
                                                        nextOverride.start = sanitizeTimeValue(e.currentTarget.value);
                                                        if (!nextOverride.start && !nextOverride.end) {
                                                            delete nextOverrides[dayKey];
                                                        } else {
                                                            nextOverrides[dayKey] = nextOverride;
                                                        }
                                                        updateBatchConfigDraft({ overrides: nextOverrides });
                                                    },
                                                }),
                                                h('span', { class: 'mj-regmgr-occurrence__day-row-separator' }, ' - '),
                                                h('input', {
                                                    type: 'time',
                                                    class: classNames('mj-regmgr-occurrence__day-row-input', 'mj-regmgr-occurrence__day-row-input--end', {
                                                        'mj-regmgr-occurrence__day-row-input--override': hasDayOverride && !!(dayOverride && dayOverride.end),
                                                    }),
                                                    value: dayEndValue || '',
                                                    disabled: !isDayActive || isProcessing,
                                                    onInput: function (e) {
                                                        var nextOverrides = Object.assign({}, batchOverrides);
                                                        var nextOverride = nextOverrides[dayKey]
                                                            ? Object.assign({ start: '', end: '' }, nextOverrides[dayKey])
                                                            : { start: '', end: '' };
                                                        nextOverride.end = sanitizeTimeValue(e.currentTarget.value);
                                                        if (!nextOverride.start && !nextOverride.end) {
                                                            delete nextOverrides[dayKey];
                                                        } else {
                                                            nextOverrides[dayKey] = nextOverride;
                                                        }
                                                        updateBatchConfigDraft({ overrides: nextOverrides });
                                                    },
                                                }),
                                            ]),
                                        ]);
                                    })),
                                    (batchMode === 'monthly') && h('div', { class: 'mj-regmgr-occurrence__form-row mj-regmgr-occurrence__batch-card__grid' }, [
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorMonthlyOrdinalLabel', 'Ordre dans le mois')),
                                            h('select', {
                                                class: 'mj-regmgr-occurrence__history-item-assignment-select',
                                                value: batchMonthlyOrdinal,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    updateBatchConfigDraft({ monthlyOrdinal: e.currentTarget.value });
                                                },
                                            }, monthlyOrdinalOptions.map(function (option) {
                                                return h('option', { key: option.value, value: option.value }, option.label);
                                            })),
                                        ]),
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorMonthlyWeekdayLabel', 'Jour de la semaine')),
                                            h('select', {
                                                class: 'mj-regmgr-occurrence__history-item-assignment-select',
                                                value: batchMonthlyWeekday,
                                                disabled: isProcessing,
                                                onChange: function (e) {
                                                    updateBatchConfigDraft({ monthlyWeekday: e.currentTarget.value });
                                                },
                                            }, OCCURRENCE_WEEKDAY_KEYS.map(function (dayKey, dayIndex) {
                                                return h('option', { key: dayKey, value: dayKey }, weekdayFullLabels[dayIndex] || weekdayLabels[dayIndex]);
                                            })),
                                        ]),
                                    ]),
                                    h('div', { class: 'mj-regmgr-occurrence__history-item-actions mj-regmgr-occurrence__batch-card__footer', style: lotFooterStyle }, [
                                        h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--primary',
                                            disabled: isProcessing,
                                            onClick: saveBatchConfig,
                                        }, getString(strings, 'occurrenceGenerationSaveBatchConfig', 'Enregistrer')),
                                    ]),
                                ]),
                            ]),
                        ]);
                    })),
                ]),
            ]),
            !shouldShowEditorCard && h('p', { class: 'mj-regmgr-occurrence__hint mj-regmgr-occurrence__hint--empty' },
                getString(strings, 'occurrenceEmptySelection', 'Sélectionnez une date dans le calendrier pour commencer.')
            ),
            shouldShowEditorCard && ModalComponent && h(ModalComponent, {
                isOpen: occurrenceEditorModal.isOpen,
                onClose: function (event) {
                    if (typeof handleCancelEdit === 'function') {
                        handleCancelEdit(event);
                    }
                },
                title: editorCardTitle,
                size: 'large',
            }, [
                h('div', { class: 'mj-regmgr-occurrence__card' }, [
                selectedOccurrence && selectedDayOccurrences.length > 1 && h('div', { class: 'mj-regmgr-occurrence__occurrence-list' }, selectedDayOccurrences.map(function (item) {
                    var chipStatus = item && typeof item.status === 'string' ? normalizeOccurrenceStatus(item.status) : '';
                    return h('button', {
                        key: item.id,
                        type: 'button',
                        class: classNames('mj-regmgr-occurrence__occurrence-chip', {
                            'mj-regmgr-occurrence__occurrence-chip--active': item.id === selectedOccurrenceId,
                        }, chipStatus ? 'mj-regmgr-occurrence__occurrence-chip--status-' + chipStatus : null),
                        onClick: function () { setSelectedOccurrenceId(item.id); },
                    }, formatPreviewRange(item.startTime, item.endTime, !!item.isAllDay, getString(strings, 'occurrenceAllDayLabel', 'Toute la journée')));
                })),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceDateLabel', 'Date')),
                    h('input', {
                        type: 'date',
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.date,
                        onInput: function (event) { handleEditorChange('date', event.currentTarget.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceEndDateLabel', 'Date de fin')),
                    h('input', {
                        type: 'date',
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.endDate || editorState.date,
                        onInput: function (event) { handleEditorChange('endDate', event.currentTarget.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceTitleLabel', 'Titre')),
                    h('input', {
                        type: 'text',
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.title || '',
                        placeholder: getString(strings, 'occurrenceTitlePlaceholder', 'Ex: Stage découverte'),
                        onInput: function (event) { handleEditorChange('title', event.currentTarget.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceTypeLabel', 'Type')),
                    h('select', {
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.status,
                        onInput: function (event) { handleEditorChange('status', event.currentTarget.value); },
                    }, statusOptions.map(function (option) {
                        return h('option', { key: option.value, value: option.value }, option.label);
                    })),
                ]),
                editorState.status === 'cancelled' && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceReasonLabel', 'Motif d\'annulation')),
                    h('input', {
                        type: 'text',
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.reason,
                        placeholder: getString(strings, 'occurrenceReasonPlaceholder', 'Ex: Problème technique'),
                        onInput: function (event) { handleEditorChange('reason', event.currentTarget.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, [
                        h('input', {
                            type: 'checkbox',
                            checked: !!editorState.isAllDay,
                            onChange: function (event) {
                                var checked = !!event.currentTarget.checked;
                                setEditorState(function (prev) {
                                    var next = Object.assign({}, prev, { isAllDay: checked });
                                    if (checked) {
                                        next.startTime = '00:00';
                                        next.endTime = '23:59';
                                    }
                                    return next;
                                });
                            },
                        }),
                        ' ',
                        getString(strings, 'occurrenceAllDayCheckbox', 'Toute la journée'),
                    ]),
                ]),
                !editorState.isAllDay && h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceStartLabel', 'Heure de début')),
                        h('input', {
                            type: 'time',
                            class: 'mj-regmgr-occurrence__input',
                            value: editorState.startTime,
                            onInput: function (event) { handleEditorChange('startTime', event.currentTarget.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceEndLabel', 'Heure de fin')),
                        h('input', {
                            type: 'time',
                            class: 'mj-regmgr-occurrence__input',
                            value: editorState.endTime,
                            onInput: function (event) { handleEditorChange('endTime', event.currentTarget.value); },
                        }),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__actions' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--primary',
                        onClick: function (e) { handleUpdateOccurrence(e); },
                        disabled: isPersisting,
                    }, editorState.id
                        ? getString(strings, 'occurrenceUpdateButton', 'Modifier cette occurrence')
                        : getString(strings, 'occurrenceCreateButton', 'Créer l\'occurrence')
                    ),
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--secondary',
                        onClick: function (e) { handleCancelEdit(e); },
                    }, getString(strings, 'occurrenceCancelButton', 'Annuler')),
                    selectedOccurrenceId && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--danger',
                        onClick: function (e) { handleDeleteOccurrence(e); },
                        disabled: isPersisting,
                    }, getString(strings, 'occurrenceDeleteButton', 'Supprimer')),
                ]),
                ]),
            ]),
            canShowGeneratorForm && h('div', { class: 'mj-regmgr-occurrence__actions' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary',
                    onClick: function () { occurrenceGeneratorModal.open(); },
                }, getString(strings, 'occurrenceGeneratorTitle', 'Générer des occurrences')),
            ]),
            canShowGeneratorForm && ModalComponent && h(ModalComponent, {
                isOpen: occurrenceGeneratorModal.isOpen,
                onClose: function () { occurrenceGeneratorModal.close(); },
                title: getString(strings, 'occurrenceGeneratorTitle', 'Générer des occurrences'),
                size: 'large',
            }, [
                h('div', { class: 'mj-regmgr-occurrence__card' }, [
                    h('p', { class: 'mj-regmgr-occurrence__description' },
                        getString(strings, 'occurrenceGeneratorDescription', 'Planifiez la récurrence automatique de cet événement.')
                    ),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorModeLabel', 'Mode')),
                        h('select', {
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorMode,
                            onInput: function (event) { handleGeneratorChange('mode', event.currentTarget.value); },
                        }, [
                            h('option', { value: 'custom' }, getString(strings, 'occurrenceGeneratorModeCustom', 'Date unique')),
                            h('option', { value: 'range' }, getString(strings, 'occurrenceGeneratorModeRange', 'Plage de dates')),
                            h('option', { value: 'weekly' }, getString(strings, 'occurrenceGeneratorModeWeekly', 'Hebdomadaire')),
                            h('option', { value: 'monthly' }, getString(strings, 'occurrenceGeneratorModeMonthly', 'Mensuel')),
                        ]),
                    ]),
                    generatorMode === 'weekly' && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorFrequencyLabel', 'Fréquence')),
                        h('select', {
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorFrequency,
                            onInput: function (event) { handleGeneratorChange('frequency', event.currentTarget.value); },
                        }, [
                            h('option', { value: 'every_week' }, getString(strings, 'occurrenceGeneratorEveryWeek', 'Chaque semaine')),
                            h('option', { value: 'every_two_weeks' }, getString(strings, 'occurrenceGeneratorEveryTwoWeeks', 'Toutes les deux semaines')),
                        ]),
                    ]),
                    generatorMode === 'monthly' && h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorMonthlyOrdinalLabel', 'Ordre dans le mois')),
                            h('select', {
                                class: 'mj-regmgr-occurrence__input',
                                value: generatorMonthlyOrdinal,
                                onInput: function (event) { handleGeneratorChange('monthlyOrdinal', event.currentTarget.value); },
                            }, monthlyOrdinalOptions.map(function (option) {
                                return h('option', { key: option.value, value: option.value }, option.label);
                            })),
                        ]),
                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorMonthlyWeekdayLabel', 'Jour de la semaine')),
                            h('select', {
                                class: 'mj-regmgr-occurrence__input',
                                value: generatorMonthlyWeekday,
                                onInput: function (event) { handleGeneratorChange('monthlyWeekday', event.currentTarget.value); },
                            }, OCCURRENCE_WEEKDAY_KEYS.map(function (dayKey, index) {
                                return h('option', { key: dayKey, value: dayKey }, weekdayFullLabels[index] || weekdayLabels[index]);
                            })),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                            h('label', { class: 'mj-regmgr-occurrence__label' }, isSingleGeneratorMode
                                ? getString(strings, 'occurrenceDateLabel', 'Date')
                                : getString(strings, 'occurrenceGeneratorStartDate', 'Date de début')
                            ),
                            h('input', {
                                type: 'date',
                                class: 'mj-regmgr-occurrence__input',
                                value: generatorStartDate,
                                onInput: function (event) { handleGeneratorChange('startDate', event.currentTarget.value); },
                            }),
                        ]),
                        showGeneratorEndDate && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorEndDate', 'Date de fin')),
                            h('input', {
                                type: 'date',
                                class: 'mj-regmgr-occurrence__input',
                                value: generatorEndDate,
                                min: generatorStartDate || undefined,
                                onInput: function (event) { handleGeneratorChange('endDate', event.currentTarget.value); },
                            }),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, [
                            h('input', {
                                type: 'checkbox',
                                checked: !!generatorAllDay,
                                onChange: function (event) {
                                    var checked = !!event.currentTarget.checked;
                                    setGeneratorState(function (prev) {
                                        var next = Object.assign({}, prev);
                                        if (checked) {
                                            next.startTime = '00:00';
                                            next.endTime = '23:59';
                                        } else {
                                            if (next.startTime === '00:00' && next.endTime === '23:59') {
                                                next.startTime = '09:00';
                                                next.endTime = '17:00';
                                            }
                                        }
                                        return next;
                                    });
                                },
                            }),
                            ' ',
                            getString(strings, 'occurrenceAllDayCheckbox', 'Toute la journée'),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceStartLabel', 'Heure de début')),
                            h('input', {
                                type: 'time',
                                class: 'mj-regmgr-occurrence__input',
                                value: generatorStartTime,
                                disabled: !!generatorAllDay,
                                onInput: function (event) { handleGeneratorChange('startTime', event.currentTarget.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                            h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceEndLabel', 'Heure de fin')),
                            h('input', {
                                type: 'time',
                                class: 'mj-regmgr-occurrence__input',
                                value: generatorEndTime,
                                disabled: !!generatorAllDay,
                                onInput: function (event) { handleGeneratorChange('endTime', event.currentTarget.value); },
                            }),
                        ]),
                    ]),
                    generatorMode === 'weekly' && h('div', { class: 'mj-regmgr-occurrence__days' }, OCCURRENCE_WEEKDAY_KEYS.map(function (dayKey, index) {
                        var isActive = !!generatorDays[dayKey];
                        var override = generatorOverrides[dayKey] || null;
                        var startValue = override && override.start ? override.start : generatorStartTime;
                        var endValue = override && override.end ? override.end : generatorEndTime;
                        var hasOverride = !!(override && (override.start || override.end));
                        return h('label', {
                            key: dayKey,
                            class: classNames('mj-regmgr-occurrence__day-row', {
                                'mj-regmgr-occurrence__day-row--active': isActive,
                                'mj-regmgr-occurrence__day-row--override': hasOverride,
                            }),
                        }, [
                            h('input', {
                                type: 'checkbox',
                                class: 'mj-regmgr-occurrence__day-row-checkbox',
                                checked: isActive,
                                onChange: function () { handleGeneratorDayToggle(dayKey); },
                            }),
                            h('span', { class: 'mj-regmgr-occurrence__day-row-label' }, weekdayLabels[index]),
                            h('span', { class: 'mj-regmgr-occurrence__day-row-times' }, [
                                h('input', {
                                    type: 'time',
                                    class: classNames('mj-regmgr-occurrence__day-row-input', 'mj-regmgr-occurrence__day-row-input--start', {
                                        'mj-regmgr-occurrence__day-row-input--override': hasOverride && !!(override && override.start),
                                    }),
                                    value: startValue || '',
                                    disabled: !isActive || !!generatorAllDay,
                                    onInput: function (event) { handleGeneratorTimeChange(dayKey, 'start', event.currentTarget.value); },
                                }),
                                h('span', { class: 'mj-regmgr-occurrence__day-row-separator' }, ' - '),
                                h('input', {
                                    type: 'time',
                                    class: classNames('mj-regmgr-occurrence__day-row-input', 'mj-regmgr-occurrence__day-row-input--end', {
                                        'mj-regmgr-occurrence__day-row-input--override': hasOverride && !!(override && override.end),
                                    }),
                                    value: endValue || '',
                                    disabled: !isActive || !!generatorAllDay,
                                    onInput: function (event) { handleGeneratorTimeChange(dayKey, 'end', event.currentTarget.value); },
                                }),
                            ]),
                        ]);
                    })),
                    h('div', { class: 'mj-regmgr-occurrence__generator-actions' }, [
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--primary',
                            onClick: function (e) { handleAddOccurrences(e); },
                            disabled: isPersisting,
                        }, getString(strings, 'occurrenceGeneratorAddButton', 'Ajouter les occurrences')),
                    ]),
                ]),
            ]),
        ]);

        var handleGeneratorTimeChange = useCallback(function (dayKey, field, value) {
            if (!dayKey || (field !== 'start' && field !== 'end')) {
                return;
            }
            setGeneratorState(function (prev) {
                var next = Object.assign({}, prev);
                var overrides = Object.assign({}, prev.timeOverrides || {});
                var current = overrides[dayKey] ? Object.assign({ start: '', end: '' }, overrides[dayKey]) : { start: '', end: '' };
                current[field] = value || '';
                if (!current.start && !current.end) {
                    delete overrides[dayKey];
                } else {
                    overrides[dayKey] = current;
                }
                next.timeOverrides = overrides;
                return next;
            });
        }, []);

        var handleUpdateSchedulePreview = useCallback(function () {
            var planResult = buildGeneratorPlan();
            var previewText = computeSchedulePreview(planResult);
            var trimmed = typeof previewText === 'string' ? previewText.trim() : '';
            setSchedulePreview(trimmed);
            setSchedulePreviewVisible(true);
            setSchedulePreviewAutoSync(true);
        }, [buildGeneratorPlan, computeSchedulePreview]);

        var persistOccurrences = useCallback(function (nextList, rollback, previewOverride, clearGenerator, persistOptions) {
            if (!onPersistOccurrences) {
                return Promise.resolve();
            }
            setIsPersisting(true);
            var summaryPayload = typeof previewOverride === 'string' ? previewOverride : schedulePreview;
            var planResult = buildGeneratorPlan();
            var generatorPayload = clearGenerator === true ? {} : serializeGeneratorPlan(planResult, generatorState);
            var optionsPayload = persistOptions && typeof persistOptions === 'object' ? persistOptions : {};
            return Promise.resolve(onPersistOccurrences(nextList, summaryPayload, generatorPayload, optionsPayload))
                .then(function (result) {
                    var responseEvent = result && result.event && typeof result.event === 'object'
                        ? result.event
                        : null;
                    if (responseEvent) {
                        if (Array.isArray(responseEvent.occurrenceGenerationBatches)) {
                            setLocalBatches(responseEvent.occurrenceGenerationBatches.filter(function (batch) {
                                return batch && isOccurrenceBatchActive(batch.status);
                            }));
                        }

                        if (responseEvent.occurrenceGenerator && typeof responseEvent.occurrenceGenerator === 'object') {
                            setGeneratorState(createGeneratorStateFromPlan(responseEvent.occurrenceGenerator, initialPivotDate));
                        }

                        if (typeof responseEvent.inlineScheduleHtml === 'string') {
                            setSchedulePreviewHtml(responseEvent.inlineScheduleHtml);
                        }
                    }

                    setIsPersisting(false);
                    return result;
                })
                .catch(function (error) {
                    setIsPersisting(false);
                    if (typeof rollback === 'function') {
                        rollback();
                    }
                    return Promise.reject(error);
                });
        }, [onPersistOccurrences, schedulePreview, buildGeneratorPlan, generatorState, initialPivotDate]);

        var handleSelectDay = useCallback(function (day) {
            if (day.hasOccurrences && day.occurrences.length > 0) {
                var target = day.occurrences[0];
                setSelectedOccurrenceId(target.id);
                setActiveBatchId(getOccurrenceBatchId(target));
            } else {
                setSelectedOccurrenceId(null);
                var baseState = createEditorState(null);
                baseState.date = day.iso;
                baseState.endDate = day.iso;
                setEditorState(baseState);
            }
        }, [setEditorState, setSelectedOccurrenceId, setActiveBatchId]);

        var handleCalendarDayDragStart = useCallback(function (day, event) {
            if (!day || !day.iso) {
                return;
            }
            if (event && typeof event.button === 'number' && event.button !== 0) {
                return;
            }
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            isPointerDraggingRangeRef.current = true;
            dragRangeStartIsoRef.current = day.iso;
            dragRangeEndIsoRef.current = day.iso;
            setRangeSelection({ startIso: day.iso, endIso: day.iso });
        }, []);

        var handleCalendarDayDragEnter = useCallback(function (day) {
            if (!isPointerDraggingRangeRef.current || !day || !day.iso) {
                return;
            }
            dragRangeEndIsoRef.current = day.iso;
            setRangeSelection(function (prev) {
                if (!prev || !prev.startIso) {
                    return { startIso: day.iso, endIso: day.iso };
                }
                if (prev.endIso === day.iso) {
                    return prev;
                }
                return { startIso: prev.startIso, endIso: day.iso };
            });
        }, []);

        var handleCalendarDayDragEnd = useCallback(function (day, event) {
            if (!isPointerDraggingRangeRef.current) {
                return;
            }
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            if (day && day.iso) {
                dragRangeEndIsoRef.current = day.iso;
                setRangeSelection(function (prev) {
                    if (!prev || !prev.startIso) {
                        return { startIso: day.iso, endIso: day.iso };
                    }
                    return { startIso: prev.startIso, endIso: day.iso };
                });
            }
            finalizeDayDragSelection(day && day.iso ? day.iso : '');
        }, [finalizeDayDragSelection]);

        var handleOccurrenceDragStart = useCallback(function (occurrence, event) {
            if (!occurrence || !occurrence.id) {
                return;
            }
            var occId = String(occurrence.id);
            setDraggingOccurrenceId(occId);
            if (event && event.dataTransfer) {
                try {
                    event.dataTransfer.setData('text/plain', occId);
                    event.dataTransfer.effectAllowed = 'move';
                } catch (error) {
                    // Ignore browser limitations.
                }
            }
        }, []);

        var handleOccurrenceDragEnd = useCallback(function () {
            setDraggingOccurrenceId('');
        }, []);

        var commitOccurrenceList = useCallback(function (updatedList) {
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            setLocalOccurrences(updatedList);
            persistOccurrences(updatedList, function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
            }, '', true).catch(function () {
                // Notification handled upstream.
            });
        }, [localOccurrences, selectedOccurrenceId, persistOccurrences]);

        var handleOccurrenceDropOnDay = useCallback(function (dayIso, event) {
            if (!dayIso) {
                return;
            }
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            var droppedId = draggingOccurrenceId;
            if (!droppedId && event && event.dataTransfer) {
                try {
                    droppedId = event.dataTransfer.getData('text/plain') || '';
                } catch (error) {
                    droppedId = '';
                }
            }
            droppedId = String(droppedId || '');
            if (!droppedId) {
                return;
            }

            var targetOccurrence = localOccurrences.find(function (occ) {
                return occ && String(occ.id) === droppedId;
            }) || null;
            if (!targetOccurrence) {
                return;
            }

            var sourceDate = sanitizeDateValue(targetOccurrence.date);
            var targetDate = sanitizeDateValue(dayIso);
            if (!sourceDate || !targetDate || sourceDate === targetDate) {
                setDraggingOccurrenceId('');
                return;
            }

            var batchId = getOccurrenceBatchId(targetOccurrence);
            var updatedList;
            if (batchId) {
                var shiftDays = diffIsoDateInDays(sourceDate, targetDate);
                var linkedBatchConfig = batchConfigById[String(batchId)] || null;
                var linkedBatchMode = linkedBatchConfig && typeof linkedBatchConfig.mode === 'string'
                    ? sanitizeGeneratorMode(linkedBatchConfig.mode)
                    : '';
                if ((linkedBatchMode === 'weekly' || linkedBatchMode === 'monthly') && shiftDays !== 0) {
                    var shiftedBatchConfig = shiftBatchConfigByDays(linkedBatchConfig, shiftDays);
                    setDraggingOccurrenceId('');
                    setSelectedOccurrenceId(droppedId);
                    handleUpdateBatchConfig(String(batchId), shiftedBatchConfig);
                    return;
                }
                updatedList = shiftBatchOccurrencesByDays(localOccurrences, batchId, shiftDays);
            } else {
                updatedList = localOccurrences.map(function (occ) {
                    if (!occ || String(occ.id) !== droppedId) {
                        return occ;
                    }
                    return Object.assign({}, occ, { date: targetDate });
                });
            }

            setDraggingOccurrenceId('');
            setSelectedOccurrenceId(droppedId);
            commitOccurrenceList(updatedList);
        }, [draggingOccurrenceId, localOccurrences, commitOccurrenceList, setSelectedOccurrenceId, batchConfigById, handleUpdateBatchConfig]);

        var startWeekOccurrenceInteraction = useCallback(function (occurrence, mode, event) {
            if (!occurrence || !occurrence.id || !mode) {
                return;
            }
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
            var startMinutes = parseTimeToMinutes(occurrence.startTime);
            var endMinutes = parseTimeToMinutes(occurrence.endTime);
            if (startMinutes === null) {
                startMinutes = 9 * 60;
            }
            if (endMinutes === null || endMinutes <= startMinutes) {
                endMinutes = startMinutes + 60;
            }
            setWeekInteraction({
                mode: mode,
                occurrenceId: String(occurrence.id),
                startClientY: event && typeof event.clientY === 'number' ? event.clientY : 0,
                originalStartMinutes: startMinutes,
                originalEndMinutes: endMinutes,
            });
            setSelectedOccurrenceId(String(occurrence.id));
        }, [setSelectedOccurrenceId]);

        useEffect(function () {
            if (!weekInteraction || typeof window === 'undefined') {
                return;
            }
            var isCommitted = false;
            var onPointerMove = function (event) {
                var deltaY = (typeof event.clientY === 'number' ? event.clientY : 0) - (weekInteraction.startClientY || 0);
                var deltaMinutesRaw = deltaY * MINUTES_PER_PIXEL_FOR_WEEK_DRAG;
                var deltaMinutes = Math.round(deltaMinutesRaw / WEEK_CREATION_STEP_MINUTES) * WEEK_CREATION_STEP_MINUTES;
                var nextStart = weekInteraction.originalStartMinutes;
                var nextEnd = weekInteraction.originalEndMinutes;

                if (weekInteraction.mode === 'move') {
                    var duration = Math.max(WEEK_CREATION_STEP_MINUTES, weekInteraction.originalEndMinutes - weekInteraction.originalStartMinutes);
                    nextStart = clampMinutesToDay(weekInteraction.originalStartMinutes + deltaMinutes);
                    nextEnd = nextStart + duration;
                    if (nextEnd > (24 * 60 - 1)) {
                        nextEnd = 24 * 60 - 1;
                        nextStart = Math.max(0, nextEnd - duration);
                    }
                } else if (weekInteraction.mode === 'resize-start') {
                    nextStart = clampMinutesToDay(weekInteraction.originalStartMinutes + deltaMinutes);
                    nextEnd = weekInteraction.originalEndMinutes;
                    if (nextStart > nextEnd - WEEK_CREATION_STEP_MINUTES) {
                        nextStart = nextEnd - WEEK_CREATION_STEP_MINUTES;
                    }
                } else if (weekInteraction.mode === 'resize-end') {
                    nextStart = weekInteraction.originalStartMinutes;
                    nextEnd = clampMinutesToDay(weekInteraction.originalEndMinutes + deltaMinutes);
                    if (nextEnd < nextStart + WEEK_CREATION_STEP_MINUTES) {
                        nextEnd = nextStart + WEEK_CREATION_STEP_MINUTES;
                    }
                }

                var interactionId = weekInteraction.occurrenceId;
                setLocalOccurrences(function (prevList) {
                    return prevList.map(function (occ) {
                        if (!occ || String(occ.id) !== interactionId) {
                            return occ;
                        }
                        return Object.assign({}, occ, {
                            startTime: minutesToTime(nextStart),
                            endTime: minutesToTime(nextEnd),
                            isAllDay: false,
                        });
                    });
                });
            };

            var onPointerUp = function () {
                if (isCommitted) {
                    return;
                }
                isCommitted = true;
                setWeekInteraction(null);
                commitOccurrenceList(cloneOccurrenceList(localOccurrencesRef.current));
            };

            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', onPointerUp);
            return function () {
                window.removeEventListener('pointermove', onPointerMove);
                window.removeEventListener('pointerup', onPointerUp);
            };
        }, [weekInteraction, commitOccurrenceList]);

        var handleEditorChange = useCallback(function (field, value) {
            setEditorState(function (prev) {
                var next = Object.assign({}, prev);
                next[field] = value;
                return next;
            });
        }, []);

        var handleCancelEdit = useCallback(function () {
            modalReopenGuardUntilRef.current = Date.now() + 300;
            occurrenceEditorModal.close();
            var reset = createEditorState(selectedOccurrence);
            if (!selectedOccurrence && editorState && editorState.date) {
                reset.date = editorState.date;
            }
            setEditorState(reset);
        }, [occurrenceEditorModal.close, selectedOccurrence, editorState]);

        var handleUpdateOccurrence = useCallback(function () {
            if (!editorState || !editorState.date) {
                return;
            }
            var isAllDayOccurrence = !!editorState.isAllDay;
            var resolvedStartTime = isAllDayOccurrence ? '00:00' : editorState.startTime;
            var resolvedEndTime = isAllDayOccurrence ? '23:59' : editorState.endTime;
            var titleValue = typeof editorState.title === 'string' ? editorState.title.trim() : '';
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            if (editorState.id) {
                var updatedList = localOccurrences.map(function (occ) {
                    if (occ.id !== editorState.id) {
                        return occ;
                    }
                    return Object.assign({}, occ, {
                        date: editorState.date,
                        startTime: resolvedStartTime,
                        endTime: resolvedEndTime,
                        isAllDay: isAllDayOccurrence,
                        status: editorState.status,
                        reason: editorState.reason,
                        source: occ && occ.source ? occ.source : 'manual',
                        noteCalendar: titleValue,
                    });
                });
                setLocalOccurrences(updatedList);
                persistOccurrences(updatedList, function () {
                    setLocalOccurrences(previousList);
                    setSelectedOccurrenceId(previousSelection);
                }, '', true).catch(function () {
                    // Already handled by parent notifications
                });
            } else {
                var startIso = sanitizeDateValue(editorState.date);
                var endIso = sanitizeDateValue(editorState.endDate || editorState.date);
                var normalizedRange = normalizeIsoDateRange(startIso, endIso);
                var rangeStart = normalizedRange ? normalizedRange.start : startIso;
                var rangeEnd = normalizedRange ? normalizedRange.end : startIso;
                var dateList = buildIsoDateRange(rangeStart, rangeEnd);
                if (!dateList.length) {
                    dateList = [startIso];
                }
                var manualLotGroup = dateList.length > 1
                    ? ('manual-range-' + Date.now() + '-' + Math.floor(Math.random() * 100000))
                    : '';
                var newOccurrences = dateList.map(function (dateIso, index) {
                    return {
                        id: generateOccurrenceId(dateIso, resolvedStartTime || editorState.startTime, localOccurrences.length + index),
                        date: dateIso,
                        startTime: resolvedStartTime,
                        endTime: resolvedEndTime,
                        isAllDay: isAllDayOccurrence,
                        status: editorState.status,
                        reason: editorState.reason,
                        source: 'manual',
                        noteCalendar: titleValue,
                        createAsManualLot: true,
                        createAsManualLotGroup: manualLotGroup,
                    };
                });
                var firstNewOccurrence = newOccurrences[0] || null;
                var updatedList = localOccurrences.concat(newOccurrences);
                setLocalOccurrences(updatedList);
                if (firstNewOccurrence) {
                    setSelectedOccurrenceId(firstNewOccurrence.id);
                    setEditorState(createEditorState(firstNewOccurrence));
                }
                persistOccurrences(updatedList, function () {
                    setLocalOccurrences(previousList);
                    setSelectedOccurrenceId(previousSelection);
                }, '', true).catch(function () {
                    // Already handled by parent notifications
                });
            }
        }, [editorState, localOccurrences, selectedOccurrenceId, persistOccurrences]);

        var handleDeleteOccurrence = useCallback(function (options) {
            if (!selectedOccurrenceId) {
                return;
            }
            var allowWithoutConfirm = !!(options && options.skipConfirm);
            var confirmMessage = getString(strings, 'occurrenceDeleteConfirm', 'Supprimer cette occurrence ?');
            if (!allowWithoutConfirm && typeof window !== 'undefined' && !window.confirm(confirmMessage)) {
                return;
            }
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            var previousEditorState = editorState ? Object.assign({}, editorState) : createEditorState(null);
            var updatedList = localOccurrences.filter(function (occ) { return occ.id !== selectedOccurrenceId; });
            setLocalOccurrences(updatedList);
            setSelectedOccurrenceId(null);
            var resetState = createEditorState(null);
            if (editorState && editorState.date) {
                resetState.date = editorState.date;
            }
            setEditorState(resetState);
            persistOccurrences(updatedList, function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
                setEditorState(previousEditorState);
            }, '', true).catch(function () {
                // Notification already handled upstream
            });
        }, [selectedOccurrenceId, strings, editorState, localOccurrences, persistOccurrences]);

        useEffect(function () {
            if (typeof window === 'undefined') {
                return undefined;
            }
            var onKeyDown = function (event) {
                if (!event) {
                    return;
                }
                var key = typeof event.key === 'string' ? event.key : '';
                if (key !== 'Delete' && key !== 'Backspace') {
                    return;
                }

                var target = event.target;
                if (target && typeof target === 'object') {
                    var tagName = target.tagName ? String(target.tagName).toUpperCase() : '';
                    var isEditable = !!target.isContentEditable;
                    if (isEditable || tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                        return;
                    }
                }

                if (!selectedOccurrenceId) {
                    return;
                }

                if (typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                handleDeleteOccurrence({ skipConfirm: true });
            };

            window.addEventListener('keydown', onKeyDown);
            return function () {
                window.removeEventListener('keydown', onKeyDown);
            };
        }, [selectedOccurrenceId, handleDeleteOccurrence]);

        var handleGeneratorChange = useCallback(function (field, value) {
            setGeneratorState(function (prev) {
                var next = Object.assign({}, prev);
                var normalizedValue = typeof value === 'string' ? value.trim() : value;
                next[field] = normalizedValue;
                if (field === 'startDate') {
                    var startValue = typeof normalizedValue === 'string' ? normalizedValue : '';
                    next._explicitStart = startValue !== '';
                }
                if (field === 'startDate' && typeof normalizedValue === 'string' && normalizedValue !== '' && typeof prev.endDate === 'string' && prev.endDate !== '') {
                    var startDateObj = parseISODate(normalizedValue);
                    var endDateObj = parseISODate(prev.endDate);
                    if (startDateObj && endDateObj && endDateObj < startDateObj) {
                        next.endDate = normalizedValue;
                    }
                } else if (field === 'endDate') {
                    if (typeof normalizedValue !== 'string' || normalizedValue === '') {
                        next.endDate = '';
                    } else {
                        var startObj = parseISODate(next.startDate);
                        var candidateEnd = parseISODate(normalizedValue);
                        if (startObj && candidateEnd && candidateEnd < startObj) {
                            next.endDate = next.startDate;
                        } else {
                            next.endDate = normalizedValue;
                        }
                    }
                }
                return next;
            });
        }, []);

        var handleGeneratorDayToggle = useCallback(function (dayKey) {
            setGeneratorState(function (prev) {
                var nextDays = Object.assign({}, prev && typeof prev.days === 'object' ? prev.days : {});
                var nextOverrides = Object.assign({}, prev.timeOverrides || {});
                var nextValue = !nextDays[dayKey];
                nextDays[dayKey] = nextValue;
                if (!nextValue && nextOverrides[dayKey]) {
                    delete nextOverrides[dayKey];
                }
                return Object.assign({}, prev, { days: nextDays, timeOverrides: nextOverrides });
            });
        }, []);

        var handleAddOccurrences = useCallback(function () {
            var planResult = buildGeneratorPlan();
            var additions = planResult && Array.isArray(planResult.additions) ? planResult.additions : [];
            if (additions.length === 0) {
                return;
            }
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            var previousEditorState = editorState ? Object.assign({}, editorState) : createEditorState(null);
            var updatedList = localOccurrences.concat(additions);
            setLocalOccurrences(updatedList);
            var firstNew = additions[0];
            setSelectedOccurrenceId(firstNew.id);
            setEditorState(createEditorState(firstNew));
            var previewText = computeSchedulePreview(planResult);
            persistOccurrences(updatedList, function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
                setEditorState(previousEditorState);
            }, previewText).catch(function () {
                // Parent handles error notification
            });
        }, [buildGeneratorPlan, computeSchedulePreview, localOccurrences, selectedOccurrenceId, editorState, persistOccurrences]);

        var handleShiftPivot = useCallback(function (offset) {
            setPivotDate(function (current) {
                var reference = current instanceof Date && !Number.isNaN(current.getTime())
                    ? new Date(current.getTime())
                    : new Date();
                reference.setHours(0, 0, 0, 0);
                if (viewMode === 'week') {
                    var shifted = new Date(reference.getFullYear(), reference.getMonth(), reference.getDate() + (offset * 7));
                    return alignDateToWeekStart(shifted);
                }
                return new Date(reference.getFullYear(), reference.getMonth() + offset, 1);
            });
        }, [viewMode]);

        var generatorOverrides = generatorState && generatorState.timeOverrides && typeof generatorState.timeOverrides === 'object'
            ? generatorState.timeOverrides
            : {};
        var WEEKLY_GENERATION_LIMIT = 8;
        var WEEKLY_GENERATION_HARD_CAP = 208; // safeguard (~4 years weekly)
        var MONTHLY_GENERATION_LIMIT = 12;
        var MONTHLY_GENERATION_HARD_CAP = 120; // safeguard (10 years monthly)
        var RANGE_GENERATION_LIMIT = 60;
        var RANGE_GENERATION_HARD_CAP = 730; // safeguard (2 years daily)
        var DAY_IN_MS = 24 * 60 * 60 * 1000;

        var buildGeneratorPlan = useCallback(function () {
            var startDateValue = typeof safeGeneratorState.startDate === 'string'
                ? safeGeneratorState.startDate
                : '';
            var startDate = parseISODate(startDateValue);
            if (!startDate) {
                return { additions: [], plan: null };
            }

            var endDateInput = typeof safeGeneratorState.endDate === 'string'
                ? safeGeneratorState.endDate.trim()
                : '';
            var hasEndDateInput = endDateInput !== '';
            var endDate = hasEndDateInput
                ? parseISODate(endDateInput)
                : null;
            if (endDate && endDate < startDate) {
                endDate = null;
            }
            var allowExtendedCap = hasEndDateInput;
            if (endDate) {
                allowExtendedCap = true;
            }

            var additions = [];
            var plan = {
                mode: generatorMode,
                startDateISO: formatISODate(startDate),
                endDateISO: endDate ? formatISODate(endDate) : '',
                startTime: typeof safeGeneratorState.startTime === 'string'
                    ? safeGeneratorState.startTime
                    : '',
                endTime: typeof safeGeneratorState.endTime === 'string'
                    ? safeGeneratorState.endTime
                    : '',
                frequency: generatorFrequency,
                days: [],
                overrides: generatorOverrides,
                monthlyOrdinal: typeof safeGeneratorState.monthlyOrdinal === 'string'
                    ? safeGeneratorState.monthlyOrdinal
                    : 'first',
                monthlyWeekday: typeof safeGeneratorState.monthlyWeekday === 'string'
                    ? safeGeneratorState.monthlyWeekday
                    : 'mon',
            };

            if (generatorMode === 'custom') {
                var customStart = plan.startTime;
                var customEnd = plan.endTime;
                if (customStart && customEnd) {
                    var customDateIso = formatISODate(startDate);
                    additions.push({
                        id: generateOccurrenceId(customDateIso, customStart, localOccurrences.length),
                        date: customDateIso,
                        startTime: customStart,
                        endTime: customEnd,
                        status: 'planned',
                        reason: '',
                        source: 'generated',
                    });
                }
                plan.endDateISO = '';
                return { additions: additions, plan: plan };
            }

            if (generatorMode === 'range') {
                var rangeEndDate = endDate || startDate;
                if (rangeEndDate < startDate) {
                    rangeEndDate = startDate;
                }

                plan.endDateISO = formatISODate(rangeEndDate);

                var rangeStartTime = plan.startTime;
                var rangeEndTime = plan.endTime;
                if (rangeStartTime && rangeEndTime) {
                    var rangeCap = allowExtendedCap ? RANGE_GENERATION_HARD_CAP : RANGE_GENERATION_LIMIT;
                    var rangeCursor = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    var rangeIterations = 0;
                    while (rangeIterations < rangeCap && rangeCursor <= rangeEndDate) {
                        var rangeIso = formatISODate(rangeCursor);
                        additions.push({
                            id: generateOccurrenceId(rangeIso, rangeStartTime, localOccurrences.length + additions.length),
                            date: rangeIso,
                            startTime: rangeStartTime,
                            endTime: rangeEndTime,
                            status: 'planned',
                            reason: '',
                            source: 'generated',
                        });
                        rangeIterations += 1;
                        rangeCursor = addDays(rangeCursor, 1);
                    }
                }

                return { additions: additions, plan: plan };
            }

            if (generatorMode === 'monthly') {
                var ordinalKey = plan.monthlyOrdinal;
                var weekdayKey = plan.monthlyWeekday;
                var monthWeekdayIndex = OCCURRENCE_WEEKDAY_TO_JS_INDEX[weekdayKey];
                if (monthWeekdayIndex === undefined) {
                    monthWeekdayIndex = 1;
                }

                var monthlyCap = allowExtendedCap ? MONTHLY_GENERATION_HARD_CAP : MONTHLY_GENERATION_LIMIT;
                var monthCursor = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
                var monthIterations = 0;

                while (monthIterations < monthlyCap) {
                    var candidate = findNthWeekdayOfMonth(monthCursor, monthWeekdayIndex, ordinalKey);
                    if (candidate && candidate >= startDate && (!endDate || candidate <= endDate)) {
                        var timeStart = plan.startTime;
                        var timeEnd = plan.endTime;
                        if (timeStart && timeEnd) {
                            var candidateIso = formatISODate(candidate);
                            var additionSeed = localOccurrences.length + additions.length;
                            additions.push({
                                id: generateOccurrenceId(candidateIso, timeStart, additionSeed),
                                date: candidateIso,
                                startTime: timeStart,
                                endTime: timeEnd,
                                status: 'planned',
                                reason: '',
                                source: 'generated',
                            });
                        }
                    }

                    if (endDate && monthCursor > endDate) {
                        break;
                    }

                    monthIterations += 1;
                    monthCursor = new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 1);
                }

                plan.days = [weekdayKey];

                if (startDate || endDate) {
                    additions = additions.filter(function (occurrence) {
                        var occurrenceDate = parseISODate(occurrence.date);
                        if (!occurrenceDate) {
                            return false;
                        }
                        if (startDate && occurrenceDate < startDate) {
                            return false;
                        }
                        if (endDate && occurrenceDate > endDate) {
                            return false;
                        }
                        return true;
                    });
                }

                return { additions: additions, plan: plan };
            }

            var selectedKeys = OCCURRENCE_WEEKDAY_KEYS.filter(function (key) {
                return !!generatorDays[key];
            });
            if (selectedKeys.length === 0) {
                return { additions: [], plan: plan };
            }

            var interval = generatorFrequency === 'every_two_weeks' ? 14 : 7;
            selectedKeys.forEach(function (dayKey) {
                var targetIndex = OCCURRENCE_WEEKDAY_TO_INDEX[dayKey];
                var firstDate = findNextWeekday(startDate, targetIndex);
                if (endDate && firstDate > endDate) {
                    return;
                }

                var iterationCap = allowExtendedCap ? WEEKLY_GENERATION_HARD_CAP : WEEKLY_GENERATION_LIMIT;
                var occurrencesGenerated = 0;
                var candidateDate = new Date(firstDate.getFullYear(), firstDate.getMonth(), firstDate.getDate());

                while (occurrencesGenerated < iterationCap) {
                    if (endDate && candidateDate > endDate) {
                        break;
                    }
                    var iso = formatISODate(candidateDate);
                    var override = generatorOverrides[dayKey] || null;
                    var dayStart = override && override.start ? override.start : plan.startTime;
                    var dayEnd = override && override.end ? override.end : plan.endTime;
                    if (dayStart && dayEnd) {
                        var additionSeed = localOccurrences.length + additions.length;
                        additions.push({
                            id: generateOccurrenceId(iso, dayStart, additionSeed),
                            date: iso,
                            startTime: dayStart,
                            endTime: dayEnd,
                            status: 'planned',
                            reason: '',
                            source: 'generated',
                        });
                    }

                    occurrencesGenerated += 1;
                    candidateDate = addDays(candidateDate, interval);
                }
            });

            plan.days = selectedKeys;

            if (startDate || endDate) {
                additions = additions.filter(function (occurrence) {
                    var occurrenceDate = parseISODate(occurrence.date);
                    if (!occurrenceDate) {
                        return false;
                    }
                    if (startDate && occurrenceDate < startDate) {
                        return false;
                    }
                    if (endDate && occurrenceDate > endDate) {
                        return false;
                    }
                    return true;
                });
            }

            return { additions: additions, plan: plan };
        }, [generatorState, generatorOverrides, generatorDays, generatorMode, generatorFrequency, localOccurrences]);

        var computeSchedulePreview = useCallback(function (planResult) {
            var plan = planResult && planResult.plan ? planResult.plan : null;
            var additions = planResult && Array.isArray(planResult.additions) ? planResult.additions : [];
            return deriveSchedulePreviewText({
                plan: plan,
                additions: additions,
                occurrences: localOccurrences,
                weekdayFullLabels: weekdayFullLabels,
                monthlyOrdinalOptions: monthlyOrdinalOptions,
                locale: resolvedLocale,
                strings: strings,
            });
        }, [localOccurrences, weekdayFullLabels, monthlyOrdinalOptions, resolvedLocale, strings]);

        useEffect(function () {
            if (!schedulePreviewAutoSync) {
                return;
            }
            var planResult = buildGeneratorPlan();
            var previewText = computeSchedulePreview(planResult);
            var trimmed = typeof previewText === 'string' ? previewText.trim() : '';
            if (trimmed !== schedulePreview) {
                setSchedulePreview(trimmed);
            }
        }, [schedulePreviewAutoSync, buildGeneratorPlan, computeSchedulePreview, schedulePreview]);

        var headingSubLabel = null;
        if (viewMode === 'week' && weekRangeLabel) {
            headingSubLabel = weekRangeLabel;
        } else if (pivotDate instanceof Date && !Number.isNaN(pivotDate.getTime())) {
            if (viewMode === 'quarter') {
                headingSubLabel = getString(strings, 'occurrenceQuarterRange', 'Vue trimestrielle');
            } else if (viewMode === 'month') {
                headingSubLabel = singleMonthOverview
                    ? singleMonthOverview.label
                    : getString(strings, 'occurrenceMonthRange', 'Vue mensuelle');
            }
        }

        return h('div', { class: 'mj-regmgr-occurrence' }, [
            h('div', { class: 'mj-regmgr-occurrence__header' }, [
                h('div', { class: 'mj-regmgr-occurrence__header-main', style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '16px', flexWrap: 'wrap' } }, [
                    h('div', { class: 'mj-regmgr-occurrence__heading' }, [
                        h('h2', null, getString(strings, 'occurrencePanelTitle', 'Gestionnaire d\'occurrences')),
                        headingSubLabel && h('span', { class: 'mj-regmgr-occurrence__subheading' }, headingSubLabel),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__header-controls' }, [
                        h('div', { class: 'mj-regmgr-occurrence__nav' }, [
                            h('button', {
                                type: 'button',
                                class: 'mj-regmgr-occurrence__nav-button',
                                'aria-label': getString(strings, 'occurrenceNavPrevious', 'Mois précédent'),
                                onClick: function () { handleShiftPivot(-1); },
                            }, [
                                h('span', { class: 'mj-regmgr-occurrence__nav-icon', 'aria-hidden': true }, '‹'),
                            ]),
                            h('button', {
                                type: 'button',
                                class: 'mj-regmgr-occurrence__nav-button',
                                'aria-label': getString(strings, 'occurrenceNavNext', 'Mois suivant'),
                                onClick: function () { handleShiftPivot(1); },
                            }, [
                                h('span', { class: 'mj-regmgr-occurrence__nav-icon', 'aria-hidden': true }, '›'),
                            ]),
                        ]),
                        h('div', { class: 'mj-regmgr-occurrence__view-toggle' }, [
                            h('button', {
                                type: 'button',
                                class: classNames('mj-regmgr-occurrence__view-button', {
                                    'mj-regmgr-occurrence__view-button--active': viewMode === 'quarter',
                                }),
                                onClick: function () { setViewMode('quarter'); },
                            }, getString(strings, 'occurrenceViewQuarter', '4 mois')),
                            h('button', {
                                type: 'button',
                                class: classNames('mj-regmgr-occurrence__view-button', {
                                    'mj-regmgr-occurrence__view-button--active': viewMode === 'month',
                                }),
                                onClick: function () { setViewMode('month'); },
                            }, getString(strings, 'occurrenceViewMonth', 'Mois')),
                            h('button', {
                                type: 'button',
                                class: classNames('mj-regmgr-occurrence__view-button', {
                                    'mj-regmgr-occurrence__view-button--active': viewMode === 'week',
                                }),
                                onClick: function () { setViewMode('week'); },
                            }, getString(strings, 'occurrenceViewWeek', 'Semaine')),
                        ]),
                    ]),
                ]),
                generatedPreviewCard && h('div', { class: 'mj-regmgr-occurrence__header-preview-row', style: { marginTop: '12px', display: 'flex', justifyContent: 'flex-start' } }, [
                    generatedPreviewCard,
                ]),
            ]),

            (viewMode === 'quarter' || viewMode === 'month') && h('div', { class: 'mj-regmgr-occurrence__body' }, [
                h('div', { class: 'mj-regmgr-occurrence__calendar' }, [
                    h('div', { class: 'mj-regmgr-occurrence__months' }, calendarMonths.map(function (month) {
                        return h('div', { key: month.key, class: 'mj-regmgr-occurrence__month' }, [
                            h('div', { class: 'mj-regmgr-occurrence__month-header' }, month.label),
                            h('div', { class: 'mj-regmgr-occurrence__weekdays' }, weekdayLabels.map(function (label, index) {
                                return h('div', { key: month.key + '-weekday-' + index, class: 'mj-regmgr-occurrence__weekday' }, label);
                            })),
                            month.weeks.map(function (week, weekIndex) {
                                return h('div', { key: month.key + '-week-' + weekIndex, class: 'mj-regmgr-occurrence__week' }, week.map(function (day) {
                                    return h('button', {
                                        key: day.iso,
                                        type: 'button',
                                        class: classNames('mj-regmgr-occurrence__day', {
                                            'mj-regmgr-occurrence__day--muted': !day.isCurrentMonth,
                                            'mj-regmgr-occurrence__day--selected': day.isSelected,
                                            'mj-regmgr-occurrence__day--today': day.isToday,
                                            'mj-regmgr-occurrence__day--with-occurrence': day.hasOccurrences,
                                            'mj-regmgr-occurrence__day--range-drag': !!(rangeSelection && isIsoDateInsideRange(day.iso, rangeSelection.startIso, rangeSelection.endIso)),
                                        }, day.status ? 'mj-regmgr-occurrence__day--status-' + day.status : null),
                                        style: (rangeSelection && isIsoDateInsideRange(day.iso, rangeSelection.startIso, rangeSelection.endIso)
                                                ? {
                                                    boxShadow: 'inset 0 0 0 2px rgba(59,130,246,0.35)',
                                                    background: 'linear-gradient(180deg, rgba(59,130,246,0.10), rgba(255,255,255,0.98))',
                                                }
                                                : null),
                                        onPointerDown: function (event) { handleCalendarDayDragStart(day, event); },
                                        onPointerEnter: function () { handleCalendarDayDragEnter(day); },
                                        onPointerUp: function (event) { handleCalendarDayDragEnd(day, event); },
                                        onDragOver: function (event) {
                                            if (event && typeof event.preventDefault === 'function') {
                                                event.preventDefault();
                                            }
                                        },
                                        onDrop: function (event) {
                                            handleOccurrenceDropOnDay(day.iso, event);
                                        },
                                        onClick: function () {
                                            if (Date.now() < suppressDayClickUntilRef.current) {
                                                return;
                                            }
                                            handleSelectDay(day);
                                        },
                                    }, [
                                        h('span', { class: 'mj-regmgr-occurrence__day-number' }, day.label),
                                        rangeSelection && isIsoDateInsideRange(day.iso, rangeSelection.startIso, rangeSelection.endIso) && h('div', {
                                            style: {
                                                marginTop: '5px',
                                                borderRadius: '10px',
                                                padding: '4px 8px',
                                                background: 'linear-gradient(120deg, rgba(59,130,246,0.16), rgba(147,197,253,0.2))',
                                                border: '1px dashed rgba(37,99,235,0.45)',
                                                color: '#1e40af',
                                                fontSize: '10px',
                                                fontWeight: 700,
                                                textTransform: 'uppercase',
                                                letterSpacing: '0.03em',
                                            },
                                        }, getString(strings, 'occurrenceDragCreateLabel', 'Nouvel objet')),
                                        day.hasOccurrences && h('div', {
                                            style: {
                                                marginTop: '4px',
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: '3px',
                                            },
                                        }, day.occurrences.slice(0, 4).map(function (occurrence, occurrenceIndex) {
                                            var chipBatchId = getOccurrenceBatchId(occurrence);
                                            var chipBatchSize = chipBatchId
                                                ? localOccurrences.filter(function (item) { return getOccurrenceBatchId(item) === chipBatchId; }).length
                                                : 1;
                                            var hasPrevBatchDay = !!(chipBatchId
                                                && batchDatesMap[chipBatchId]
                                                && batchDatesMap[chipBatchId][shiftIsoDate(day.iso, -1)]);
                                            var hasNextBatchDay = !!(chipBatchId
                                                && batchDatesMap[chipBatchId]
                                                && batchDatesMap[chipBatchId][shiftIsoDate(day.iso, 1)]);
                                            var chipSegment = 'single';
                                            if (chipBatchSize > 1) {
                                                if (hasPrevBatchDay && hasNextBatchDay) {
                                                    chipSegment = 'middle';
                                                } else if (hasPrevBatchDay) {
                                                    chipSegment = 'end';
                                                } else if (hasNextBatchDay) {
                                                    chipSegment = 'start';
                                                }
                                            }
                                            var chipIsConnected = chipBatchSize > 1;
                                            return h('div', {
                                                key: day.iso + '-occ-chip-' + occurrence.id + '-' + occurrenceIndex,
                                                draggable: true,
                                                onDragStart: function (event) { handleOccurrenceDragStart(occurrence, event); },
                                                onDragEnd: function () { handleOccurrenceDragEnd(); },
                                                onPointerDown: function (event) {
                                                    if (event && typeof event.stopPropagation === 'function') {
                                                        event.stopPropagation();
                                                    }
                                                },
                                                onClick: function (event) {
                                                    if (event && typeof event.stopPropagation === 'function') {
                                                        event.stopPropagation();
                                                    }
                                                    setSelectedOccurrenceId(occurrence.id);
                                                    setActiveBatchId(chipBatchId);
                                                },
                                                style: {
                                                    position: 'relative',
                                                    fontSize: '11px',
                                                    lineHeight: '1.2',
                                                    display: 'block',
                                                    width: 'calc(100% + 16px)',
                                                    borderRadius: chipIsConnected
                                                        ? (chipSegment === 'start'
                                                            ? '12px 4px 4px 12px'
                                                            : (chipSegment === 'middle'
                                                                ? '4px'
                                                                : (chipSegment === 'end'
                                                                    ? '4px 12px 12px 4px'
                                                                    : '12px')))
                                                        : '12px',
                                                    padding: chipIsConnected && chipSegment === 'middle' ? '6px 6px' : '6px 9px',
                                                    marginLeft: chipIsConnected
                                                        ? (chipSegment === 'middle' || chipSegment === 'end' ? '-10px' : '-8px')
                                                        : '-8px',
                                                    marginRight: chipIsConnected
                                                        ? (chipSegment === 'middle' || chipSegment === 'start' ? '-10px' : '-8px')
                                                        : '-8px',
                                                    border: highlightedBatchId && chipBatchId === highlightedBatchId
                                                        ? '1px solid rgba(14,165,233,0.65)'
                                                        : '1px solid rgba(26,45,71,0.10)',
                                                    background: draggingOccurrenceId && String(occurrence.id) === draggingOccurrenceId
                                                        ? 'linear-gradient(135deg, rgba(59,130,246,0.30), rgba(255,255,255,0.98))'
                                                        : 'linear-gradient(135deg, rgba(29,78,216,0.12), rgba(255,255,255,0.98))',
                                                    boxShadow: selectedOccurrenceId === occurrence.id
                                                        ? '0 0 0 2px rgba(14,165,233,0.30), 0 10px 24px rgba(15,35,95,0.12)'
                                                        : (highlightedBatchId && chipBatchId === highlightedBatchId
                                                            ? '0 0 0 2px rgba(14,165,233,0.28), 0 10px 24px rgba(15,35,95,0.12)'
                                                            : '0 8px 18px rgba(15,35,95,0.08)'),
                                                    cursor: 'grab',
                                                    userSelect: 'none',
                                                    overflow: 'hidden',
                                                    opacity: highlightedBatchId && chipBatchId && chipBatchId !== highlightedBatchId ? 0.42 : 1,
                                                    color: '#1e3a8a',
                                                    minHeight: '18px',
                                                    zIndex: chipIsConnected ? 3 : 2,
                                                },
                                                title: (occurrence.title || occurrence.noteCalendar || formatPreviewRange(
                                                    occurrence.startTime,
                                                    occurrence.endTime,
                                                    !!occurrence.isAllDay,
                                                    getString(strings, 'occurrenceAllDayLabel', 'Toute la journée')
                                                )),
                                            }, []);
                                        })),
                                    ]);
                                }));
                            }),
                        ]);
                    })),
                ]),
                sidebarContent,
            ]),

            viewMode === 'week' && h('div', { class: 'mj-regmgr-occurrence__body' }, [
                weekOverview
                    ? h('div', { class: 'mj-regmgr-occurrence__week-view' }, [
                        h('div', { class: 'mj-regmgr-occurrence__week-grid' }, [
                            h('div', { class: 'mj-regmgr-occurrence__week-time' }, [
                                h('div', { class: 'mj-regmgr-occurrence__week-time-header' },
                                    getString(strings, 'occurrenceWeekTimeColumn', 'Horaires')
                                ),
                                h('div', {
                                    class: 'mj-regmgr-occurrence__week-time-scale',
                                    style: { height: WEEK_VIEW_HEIGHT + 'px' },
                                }, weekTimeScale.ticks.map(function (tick, index) {
                                    var topRatio = (tick.minutes - weekTimeScale.min) / weekTimelineRange;
                                    var top = Math.max(0, Math.min(1, topRatio)) * WEEK_VIEW_HEIGHT;
                                    return h('div', {
                                        key: 'time-' + tick.minutes,
                                        class: classNames('mj-regmgr-occurrence__week-time-marker', {
                                            'mj-regmgr-occurrence__week-time-marker--first': index === 0,
                                        }),
                                        style: { top: top + 'px' },
                                    }, [
                                        h('span', { class: 'mj-regmgr-occurrence__week-time-label' }, tick.label),
                                    ]);
                                })),
                            ]),
                            weekOverview.days.map(function (day) {
                                var weekdayIndex = typeof day.weekdayIndex === 'number' ? day.weekdayIndex : 0;
                                var weekdayLabel = weekdayLabels[weekdayIndex] || '';
                                var dateLabel = '';
                                if (day.date instanceof Date && !Number.isNaN(day.date.getTime())) {
                                    try {
                                        dateLabel = capitalizeLabel(day.date.toLocaleDateString(resolvedLocale, { day: 'numeric', month: 'short' }));
                                    } catch (error) {
                                        dateLabel = capitalizeLabel(day.date.toLocaleDateString('fr', { day: 'numeric', month: 'short' }));
                                    }
                                }
                                var combinedLabel = weekdayLabel ? (weekdayLabel + ' ' + dateLabel) : dateLabel;
                                var hasOccurrences = Array.isArray(day.occurrences) && day.occurrences.length > 0;
                                return h('div', {
                                    key: day.iso,
                                    class: classNames('mj-regmgr-occurrence__week-column', {
                                        'mj-regmgr-occurrence__week-column--today': !!day.isToday,
                                        'mj-regmgr-occurrence__week-column--selected': !!day.isSelected,
                                    }),
                                }, [
                                    h('div', { class: 'mj-regmgr-occurrence__week-column-header' }, [
                                        combinedLabel && h('span', { class: 'mj-regmgr-occurrence__week-column-title' }, combinedLabel),
                                        day.timeSummary && h('span', { class: 'mj-regmgr-occurrence__week-column-summary' }, day.timeSummary),
                                        hasOccurrences && h('span', { class: 'mj-regmgr-occurrence__week-column-count' }, day.occurrences.length),
                                    ]),
                                    h('div', {
                                        class: 'mj-regmgr-occurrence__week-column-body',
                                        style: { height: WEEK_VIEW_HEIGHT + 'px' },
                                        onPointerDown: function (event) {
                                            if (event.target === event.currentTarget) {
                                                handleCalendarDayDragStart(day, event);
                                            }
                                        },
                                        onPointerEnter: function () {
                                            handleCalendarDayDragEnter(day);
                                        },
                                        onPointerUp: function (event) {
                                            if (event.target === event.currentTarget) {
                                                handleCalendarDayDragEnd(day, event);
                                            }
                                        },
                                        onClick: function (event) {
                                            if (Date.now() < suppressDayClickUntilRef.current) {
                                                return;
                                            }
                                            if (event.target === event.currentTarget) {
                                                handleSelectDay(day);
                                            }
                                        },
                                    }, [
                                        h('div', { class: 'mj-regmgr-occurrence__week-guides' }, weekTimeScale.ticks.map(function (tick, index) {
                                            var guideRatio = (tick.minutes - weekTimeScale.min) / weekTimelineRange;
                                            var guideTop = Math.max(0, Math.min(1, guideRatio)) * WEEK_VIEW_HEIGHT;
                                            return h('div', {
                                                key: day.iso + '-guide-' + tick.minutes,
                                                class: classNames('mj-regmgr-occurrence__week-guide', {
                                                    'mj-regmgr-occurrence__week-guide--first': index === 0,
                                                }),
                                                style: { top: guideTop + 'px' },
                                            });
                                        })),
                                        !hasOccurrences && h('div', { class: 'mj-regmgr-occurrence__week-empty' },
                                            getString(strings, 'occurrenceWeekEmptyDay', 'Aucune occurrence planifiée')
                                        ),
                                        hasOccurrences && day.occurrences.map(function (occurrence) {
                                            var startMinutes = typeof occurrence.startMinutes === 'number'
                                                ? occurrence.startMinutes
                                                : parseTimeToMinutes(occurrence.startTime);
                                            var endMinutes = typeof occurrence.endMinutes === 'number'
                                                ? occurrence.endMinutes
                                                : parseTimeToMinutes(occurrence.endTime);
                                            if (startMinutes === null) {
                                                startMinutes = weekTimeScale.min;
                                            }
                                            if (endMinutes === null) {
                                                endMinutes = startMinutes + 60;
                                            }
                                            var effectiveStart = Math.max(weekTimeScale.min, startMinutes);
                                            var effectiveEnd = Math.min(weekTimeScale.max, Math.max(endMinutes, effectiveStart + 30));
                                            var blockTop = ((effectiveStart - weekTimeScale.min) / weekTimelineRange) * WEEK_VIEW_HEIGHT;
                                            var blockHeight = ((effectiveEnd - effectiveStart) / weekTimelineRange) * WEEK_VIEW_HEIGHT;
                                            if (blockHeight < 24) {
                                                blockHeight = 24;
                                            }
                                            var statusKey = occurrence.status ? normalizeOccurrenceStatus(occurrence.status) : 'planned';
                                            var isSelectedBlock = selectedOccurrenceId === occurrence.id;
                                            var ariaLabel = formatTimeRange(
                                                occurrence.startTime,
                                                occurrence.endTime,
                                                !!occurrence.isAllDay,
                                                getString(strings, 'occurrenceAllDayLabel', 'Toute la journée')
                                            );
                                            return h('button', {
                                                key: occurrence.id,
                                                type: 'button',
                                                class: classNames('mj-regmgr-occurrence__week-block',
                                                    statusKey ? 'mj-regmgr-occurrence__week-block--status-' + statusKey : null,
                                                    {
                                                        'mj-regmgr-occurrence__week-block--selected': isSelectedBlock,
                                                        'mj-regmgr-occurrence__week-block--batch-highlighted': !!(highlightedBatchId && getOccurrenceBatchId(occurrence) === highlightedBatchId),
                                                    }
                                                ),
                                                style: {
                                                    top: blockTop + 'px',
                                                    height: blockHeight + 'px',
                                                    border: highlightedBatchId && getOccurrenceBatchId(occurrence) === highlightedBatchId
                                                        ? '1px solid rgba(14, 165, 233, 0.65)'
                                                        : '1px solid rgba(26,45,71,0.10)',
                                                    background: weekInteraction && String(occurrence.id) === weekInteraction.occurrenceId
                                                        ? 'linear-gradient(135deg, rgba(59,130,246,0.30), rgba(255,255,255,0.98))'
                                                        : 'linear-gradient(135deg, rgba(29,78,216,0.12), rgba(255,255,255,0.98))',
                                                    opacity: highlightedBatchId && getOccurrenceBatchId(occurrence) !== highlightedBatchId ? 0.42 : 1,
                                                    boxShadow: highlightedBatchId && getOccurrenceBatchId(occurrence) === highlightedBatchId
                                                        ? '0 0 0 2px rgba(14, 165, 233, 0.65), 0 8px 18px rgba(14,165,233,0.22)'
                                                        : '0 8px 18px rgba(15,35,95,0.08)',
                                                    cursor: weekInteraction && String(occurrence.id) === weekInteraction.occurrenceId
                                                        ? 'grabbing'
                                                        : 'grab',
                                                    touchAction: 'none',
                                                },
                                                onClick: function (event) {
                                                    event.stopPropagation();
                                                    setSelectedOccurrenceId(occurrence.id);
                                                    setActiveBatchId(getOccurrenceBatchId(occurrence));
                                                },
                                                onPointerDown: function (event) {
                                                    startWeekOccurrenceInteraction(occurrence, 'move', event);
                                                },
                                                onMouseEnter: function () {
                                                    var batchId = getOccurrenceBatchId(occurrence);
                                                    if (batchId) {
                                                        setHoveredBatchId(batchId);
                                                    }
                                                },
                                                onMouseLeave: function () {
                                                    setHoveredBatchId('');
                                                },
                                                'aria-label': ariaLabel,
                                            }, [
                                                h('span', {
                                                    onPointerDown: function (event) {
                                                        startWeekOccurrenceInteraction(occurrence, 'resize-start', event);
                                                    },
                                                    style: {
                                                        position: 'absolute',
                                                        top: '-2px',
                                                        left: '8px',
                                                        right: '8px',
                                                        height: '7px',
                                                        borderRadius: '8px',
                                                        background: 'rgba(255,255,255,0.62)',
                                                        cursor: 'ns-resize',
                                                    },
                                                }),
                                                h('span', { class: 'mj-regmgr-occurrence__week-block-time' }, formatPreviewRange(
                                                    occurrence.startTime,
                                                    occurrence.endTime,
                                                    !!occurrence.isAllDay,
                                                    getString(strings, 'occurrenceAllDayLabel', 'Toute la journée')
                                                )),
                                                statusLabelMap[statusKey] && h('span', { class: 'mj-regmgr-occurrence__week-block-status' }, statusLabelMap[statusKey]),
                                                occurrence.status === 'cancelled' && occurrence.reason && h('span', { class: 'mj-regmgr-occurrence__week-block-reason' }, occurrence.reason),
                                                h('span', {
                                                    onPointerDown: function (event) {
                                                        startWeekOccurrenceInteraction(occurrence, 'resize-end', event);
                                                    },
                                                    style: {
                                                        position: 'absolute',
                                                        bottom: '-2px',
                                                        left: '8px',
                                                        right: '8px',
                                                        height: '7px',
                                                        borderRadius: '8px',
                                                        background: 'rgba(255,255,255,0.62)',
                                                        cursor: 'ns-resize',
                                                    },
                                                }),
                                            ]);
                                        }),
                                    ]),
                                ]);
                            }),
                        ]),
                    ])
                    : h('div', { class: 'mj-regmgr-occurrence__week-view mj-regmgr-occurrence__week-view--empty' }, [
                        h('div', { class: 'mj-regmgr-occurrence__placeholder' },
                            getString(strings, 'occurrenceWeekPlaceholder', 'Aucune occurrence à afficher.')
                        ),
                    ]),
                sidebarContent,
            ]),
        ]);
    }

    var OCCURRENCE_WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    var OCCURRENCE_WEEKDAY_TO_INDEX = {
        mon: 1,
        tue: 2,
        wed: 3,
        thu: 4,
        fri: 5,
        sat: 6,
        sun: 0,
    };
    var OCCURRENCE_WEEKDAY_TO_JS_INDEX = {
        mon: 1,
        tue: 2,
        wed: 3,
        thu: 4,
        fri: 5,
        sat: 6,
        sun: 0,
    };

    function padNumber(value) {
        var str = String(value);
        return str.length < 2 ? '0' + str : str;
    }

    function formatISODate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        return date.getFullYear() + '-' + padNumber(date.getMonth() + 1) + '-' + padNumber(date.getDate());
    }

    function parseISODate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return null;
        }
        var parts = trimmed.split('-');
        if (parts.length !== 3) {
            return null;
        }
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10) - 1;
        var day = parseInt(parts[2], 10);
        if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
            return null;
        }
        var date = new Date(year, month, day, 0, 0, 0, 0);
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function parseISODateTime(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return null;
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            trimmed += 'T00:00:00';
        } else if (trimmed.indexOf('T') === -1) {
            trimmed = trimmed.replace(' ', 'T');
        }
        var date = new Date(trimmed);
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function formatTimeFromDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '09:00';
        }
        return padNumber(date.getHours()) + ':' + padNumber(date.getMinutes());
    }

    function addMinutesToTime(time, minutes) {
        if (!time || typeof time !== 'string') {
            return '';
        }
        var parts = time.split(':');
        if (parts.length < 2) {
            return time;
        }
        var total = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10) + minutes;
        if (Number.isNaN(total)) {
            return time;
        }
        total = ((total % (24 * 60)) + (24 * 60)) % (24 * 60);
        var hours = Math.floor(total / 60);
        var mins = total % 60;
        return padNumber(hours) + ':' + padNumber(mins);
    }

    function formatTimeRange(start, end, isAllDay, allDayLabel) {
        if (isAllDay) {
            return allDayLabel || 'Toute la journée';
        }
        var startValue = typeof start === 'string' ? start.trim() : '';
        var endValue = typeof end === 'string' ? end.trim() : '';
        if (!startValue && !endValue) {
            return '';
        }
        if (!startValue) {
            return endValue;
        }
        if (!endValue) {
            return startValue;
        }
        if (startValue === endValue) {
            return startValue;
        }
        return startValue + ' - ' + endValue;
    }

    function formatPreviewTime(time) {
        if (!time || typeof time !== 'string') {
            return '';
        }
        var trimmed = time.trim();
        if (!/^[0-9]{2}:[0-9]{2}$/.test(trimmed)) {
            return trimmed;
        }
        var parts = trimmed.split(':');
        return parts[0] + 'h' + parts[1];
    }

    function formatPreviewRange(start, end, isAllDay, allDayLabel) {
        if (isAllDay) {
            return allDayLabel || 'Toute la journée';
        }
        var startLabel = formatPreviewTime(start);
        var endLabel = formatPreviewTime(end);
        if (startLabel && endLabel) {
            return startLabel + ' > ' + endLabel;
        }
        return startLabel || endLabel || '';
    }

    function normalizeTimeValue(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        var match = trimmed.match(/^(\d{2}:\d{2})(?::\d{2})?$/);
        return match ? match[1] : trimmed;
    }

    function isAllDayTimeRange(start, end) {
        var startValue = normalizeTimeValue(start);
        var endValue = normalizeTimeValue(end);
        return startValue === '00:00' && endValue === '23:59';
    }

    function formatSchedulePreviewRange(start, end) {
        if (isAllDayTimeRange(start, end)) {
            return '';
        }
        return formatPreviewRange(start, end);
    }

    function formatPreviewTextWithBoldWeekdays(text) {
        if (typeof text !== 'string' || text === '') {
            return '';
        }

        var escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        var withBoldDays = escaped.replace(/\b(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)\b/gi, '<strong>$1</strong>');
        return withBoldDays.replace(/\n/g, '<br />');
    }

    function resolveWeekdayKeyFromDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        var jsIndex = date.getDay();
        switch (jsIndex) {
            case 0: return 'sun';
            case 1: return 'mon';
            case 2: return 'tue';
            case 3: return 'wed';
            case 4: return 'thu';
            case 5: return 'fri';
            case 6: return 'sat';
            default: return '';
        }
    }

    function resolveWeekdayLabel(dayKey, weekdayFullLabels) {
        if (typeof dayKey !== 'string') {
            return '';
        }
        var index = OCCURRENCE_WEEKDAY_KEYS.indexOf(dayKey);
        if (index === -1) {
            return '';
        }
        return weekdayFullLabels && weekdayFullLabels[index] ? weekdayFullLabels[index] : '';
    }

    function normaliseOccurrencesForPreview(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        var normalised = [];
        list.forEach(function (occurrence) {
            if (!occurrence) {
                return;
            }
            var dateIso = '';
            if (typeof occurrence.date === 'string' && occurrence.date !== '') {
                dateIso = occurrence.date;
            } else if (typeof occurrence.start === 'string' && occurrence.start !== '') {
                dateIso = occurrence.start.slice(0, 10);
            }
            if (dateIso === '') {
                return;
            }
            var dateObj = parseISODate(dateIso);
            if (!dateObj) {
                return;
            }
            var startTime = '';
            if (typeof occurrence.startTime === 'string' && occurrence.startTime !== '') {
                startTime = occurrence.startTime;
            } else if (typeof occurrence.start === 'string' && occurrence.start !== '') {
                var startDate = parseISODateTime(occurrence.start);
                if (startDate) {
                    startTime = formatTimeFromDate(startDate);
                }
            }
            var endTime = '';
            if (typeof occurrence.endTime === 'string' && occurrence.endTime !== '') {
                endTime = occurrence.endTime;
            } else if (typeof occurrence.end === 'string' && occurrence.end !== '') {
                var endDate = parseISODateTime(occurrence.end);
                if (endDate) {
                    endTime = formatTimeFromDate(endDate);
                }
            }
            normalised.push({
                date: dateIso,
                dateObj: dateObj,
                startTime: startTime,
                endTime: endTime,
                weekdayKey: resolveWeekdayKeyFromDate(dateObj),
            });
        });
        normalised.sort(function (a, b) {
            if (a.dateObj && b.dateObj && a.dateObj.getTime() !== b.dateObj.getTime()) {
                return a.dateObj.getTime() - b.dateObj.getTime();
            }
            if (a.startTime && b.startTime) {
                return a.startTime < b.startTime ? -1 : (a.startTime > b.startTime ? 1 : 0);
            }
            return 0;
        });
        return normalised;
    }

    function formatDateForLocale(date, locale, includeMonth, includeYear) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        var options = { weekday: 'long', day: 'numeric' };
        if (includeMonth) {
            options.month = 'long';
        }
        if (includeYear) {
            options.year = 'numeric';
        }
        try {
            return capitalizeLabel(date.toLocaleDateString(locale || 'fr', options));
        } catch (error) {
            return capitalizeLabel(date.toLocaleDateString('fr', options));
        }
    }

    function capitalizeWordsLabel(label) {
        if (!label || typeof label !== 'string') {
            return '';
        }
        return label.split(' ').map(function (token) {
            if (!token) {
                return token;
            }
            return token.charAt(0).toUpperCase() + token.slice(1);
        }).join(' ');
    }

    function formatHourForRangePreview(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var match = value.trim().match(/^(\d{2}):(\d{2})$/);
        if (!match) {
            return value;
        }
        var hours = parseInt(match[1], 10);
        var minutes = parseInt(match[2], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return value;
        }
        if (minutes === 0) {
            return String(hours) + 'h';
        }
        return String(hours) + 'h' + String(minutes).padStart(2, '0');
    }

    function buildWeeklyPreviewFromPlan(plan, weekdayFullLabels, strings) {
        if (!plan || !Array.isArray(plan.days) || plan.days.length === 0) {
            return '';
        }
        var overrides = plan.overrides || {};
        var segments = [];
        plan.days.forEach(function (dayKey) {
            var label = resolveWeekdayLabel(dayKey, weekdayFullLabels);
            if (!label) {
                return;
            }
            var override = overrides[dayKey] || {};
            var startTime = override.start || plan.startTime || '';
            var endTime = override.end || plan.endTime || '';
            var range = formatSchedulePreviewRange(startTime, endTime);
            segments.push(range ? (label + ' ' + range) : label);
        });
        if (segments.length === 0) {
            return '';
        }
        var prefix = '';
        if (plan.frequency === 'every_two_weeks') {
            prefix = getString(strings, 'occurrencePreviewBiweeklyPrefix', 'Toutes les deux semaines : ');
        }
        return prefix + segments.join(', ');
    }

    function buildMonthlyPreview(plan, weekdayFullLabels, monthlyOrdinalOptions, strings) {
        if (!plan) {
            return '';
        }
        var weekdayLabel = resolveWeekdayLabel(plan.monthlyWeekday, weekdayFullLabels);
        if (!weekdayLabel) {
            return '';
        }
        var ordinalMap = {};
        if (Array.isArray(monthlyOrdinalOptions)) {
            monthlyOrdinalOptions.forEach(function (option) {
                ordinalMap[option.value] = option.label;
            });
        }
        var ordinalLabel = ordinalMap[plan.monthlyOrdinal] || '';
        if (!ordinalLabel) {
            return '';
        }
        var pattern = getString(strings, 'occurrencePreviewMonthlyPattern', 'Tous les {{ordinal}} {{weekday}} du mois');
        var summary = pattern.replace('{{ordinal}}', ordinalLabel).replace('{{weekday}}', weekdayLabel);
        var range = formatSchedulePreviewRange(plan.startTime, plan.endTime);
        if (range) {
            summary += ' · ' + range;
        }
        if (summary.charAt(summary.length - 1) !== '.') {
            summary += '.';
        }
        return summary;
    }

    function buildSingleDatePreview(occurrences, locale) {
        if (!occurrences || occurrences.length === 0) {
            return '';
        }
        var first = occurrences[0];
        var label = formatDateForLocale(first.dateObj, locale, true, true);
        if (!label) {
            return '';
        }
        var range = formatSchedulePreviewRange(first.startTime, first.endTime);
        return range ? label + ' · ' + range : label;
    }

    function buildConsecutiveRangePreview(occurrences, locale) {
        if (!occurrences || occurrences.length < 2) {
            return '';
        }
        var uniqueDates = [];
        occurrences.forEach(function (occ) {
            if (uniqueDates.length === 0 || uniqueDates[uniqueDates.length - 1].date !== occ.date) {
                uniqueDates.push({ date: occ.date, dateObj: occ.dateObj });
            }
        });
        if (uniqueDates.length < 2) {
            return '';
        }
        for (var index = 1; index < uniqueDates.length; index++) {
            var previous = uniqueDates[index - 1];
            var current = uniqueDates[index];
            if (!(previous.dateObj instanceof Date) || !(current.dateObj instanceof Date)) {
                return '';
            }
            var diff = Math.round((current.dateObj.getTime() - previous.dateObj.getTime()) / (24 * 60 * 60 * 1000));
            if (diff !== 1) {
                return '';
            }
        }
        var startDate = uniqueDates[0].dateObj;
        var endDate = uniqueDates[uniqueDates.length - 1].dateObj;
        var includeYear = startDate.getFullYear() !== endDate.getFullYear();
        var includeMonthOnEnd = includeYear || startDate.getMonth() !== endDate.getMonth();
        var startLabel = formatDateForLocale(startDate, locale, true, true);
        var endLabel = formatDateForLocale(endDate, locale, includeMonthOnEnd, includeYear);
        if (!startLabel || !endLabel) {
            return '';
        }
        return startLabel + ' au ' + endLabel;
    }

    function buildRangePreviewFromPlan(plan, locale, strings) {
        if (!plan) {
            return '';
        }
        var startDate = parseISODate(plan.startDateISO || plan.startDate || '');
        var endDate = parseISODate(plan.endDateISO || plan.endDate || '');
        if (!startDate) {
            return '';
        }
        if (!endDate || endDate < startDate) {
            endDate = startDate;
        }

        var startLabel = formatDateForLocale(startDate, locale, true, false);
        var endLabel = formatDateForLocale(endDate, locale, true, false);
        if (!startLabel || !endLabel) {
            return '';
        }
        startLabel = capitalizeWordsLabel(startLabel);
        endLabel = capitalizeWordsLabel(endLabel);

        var startTime = typeof plan.startTime === 'string' ? plan.startTime : '';
        var endTime = typeof plan.endTime === 'string' ? plan.endTime : '';
        if (startTime && endTime && !isAllDayTimeRange(startTime, endTime)) {
            return getString(strings, 'occurrencePreviewRangePattern', 'Du {{startDay}} à {{endDay}} de {{startTime}} à {{endTime}}')
                .replace('{{startDay}}', startLabel)
                .replace('{{endDay}}', endLabel)
                .replace('{{startTime}}', formatHourForRangePreview(startTime))
                .replace('{{endTime}}', formatHourForRangePreview(endTime));
        }

        return getString(strings, 'occurrencePreviewRangeDatesOnly', 'Du {{startDay}} à {{endDay}}')
            .replace('{{startDay}}', startLabel)
            .replace('{{endDay}}', endLabel);
    }

    function buildWeeklyPreviewFromOccurrences(occurrences, weekdayFullLabels) {
        if (!occurrences || occurrences.length === 0) {
            return '';
        }
        var grouped = {};
        occurrences.forEach(function (occ) {
            if (!occ.weekdayKey) {
                return;
            }
            if (!grouped[occ.weekdayKey]) {
                grouped[occ.weekdayKey] = [];
            }
            grouped[occ.weekdayKey].push(occ);
        });
        var weekdayKeys = Object.keys(grouped);
        if (weekdayKeys.length === 0) {
            return '';
        }
        weekdayKeys.forEach(function (key) {
            grouped[key].sort(function (a, b) {
                return a.dateObj.getTime() - b.dateObj.getTime();
            });
        });
        var segments = [];
        var hasWeeklyPattern = true;
        weekdayKeys.forEach(function (key) {
            var items = grouped[key];
            if (!items || items.length === 0) {
                hasWeeklyPattern = false;
                return;
            }
            var referenceStart = items[0].startTime;
            var referenceEnd = items[0].endTime;
            for (var i = 0; i < items.length; i++) {
                if (items[i].startTime !== referenceStart || items[i].endTime !== referenceEnd) {
                    hasWeeklyPattern = false;
                    break;
                }
                if (i > 0) {
                    var deltaDays = Math.round((items[i].dateObj.getTime() - items[i - 1].dateObj.getTime()) / (24 * 60 * 60 * 1000));
                    if (deltaDays % 7 !== 0) {
                        hasWeeklyPattern = false;
                        break;
                    }
                }
            }
            if (!hasWeeklyPattern) {
                return;
            }
            var weekdayLabel = resolveWeekdayLabel(key, weekdayFullLabels);
            var range = formatSchedulePreviewRange(referenceStart, referenceEnd);
            if (!weekdayLabel) {
                hasWeeklyPattern = false;
                return;
            }
            segments.push({ key: key, label: range ? (weekdayLabel + ' ' + range) : weekdayLabel });
        });
        if (!hasWeeklyPattern || segments.length === 0) {
            return '';
        }
        segments.sort(function (a, b) {
            return OCCURRENCE_WEEKDAY_KEYS.indexOf(a.key) - OCCURRENCE_WEEKDAY_KEYS.indexOf(b.key);
        });
        return segments.map(function (entry) { return entry.label; }).join(', ');
    }

    function buildOccurrenceListPreview(occurrences, locale) {
        if (!occurrences || occurrences.length === 0) {
            return '';
        }
        var segments = [];
        var limit = Math.min(occurrences.length, 3);
        for (var index = 0; index < limit; index++) {
            var occ = occurrences[index];
            var label = formatDateForLocale(occ.dateObj, locale, true, true);
            if (!label) {
                continue;
            }
            var range = formatSchedulePreviewRange(occ.startTime, occ.endTime);
            segments.push(range ? label + ' · ' + range : label);
        }
        if (segments.length === 0) {
            return '';
        }
        var result = segments.join(', ');
        if (occurrences.length > limit) {
            result += '…';
        }
        return result;
    }

    function deriveSchedulePreviewText(context) {
        var plan = context && context.plan ? context.plan : null;
        var additions = context && Array.isArray(context.additions) ? context.additions : [];
        var occurrences = context && Array.isArray(context.occurrences) ? context.occurrences : [];
        var weekdayFullLabels = context && context.weekdayFullLabels ? context.weekdayFullLabels : [];
        var monthlyOrdinalOptions = context && context.monthlyOrdinalOptions ? context.monthlyOrdinalOptions : [];
        var locale = context && context.locale ? context.locale : 'fr';
        var strings = context && context.strings ? context.strings : {};

        if (plan && plan.mode === 'weekly') {
            var weeklyPreview = buildWeeklyPreviewFromPlan(plan, weekdayFullLabels, strings);
            if (weeklyPreview) {
                return weeklyPreview;
            }
        }

        if (plan && plan.mode === 'monthly') {
            var monthlyPreview = buildMonthlyPreview(plan, weekdayFullLabels, monthlyOrdinalOptions, strings);
            if (monthlyPreview) {
                return monthlyPreview;
            }
        }

        if (plan && plan.mode === 'range') {
            var rangePreviewFromPlan = buildRangePreviewFromPlan(plan, locale, strings);
            if (rangePreviewFromPlan) {
                return rangePreviewFromPlan;
            }
        }

        var normalizedOccurrences = normaliseOccurrencesForPreview(occurrences);
        if (normalizedOccurrences.length === 0) {
            normalizedOccurrences = normaliseOccurrencesForPreview(additions);
        }
        if (normalizedOccurrences.length === 0) {
            return '';
        }

        if (normalizedOccurrences.length === 1) {
            var singlePreview = buildSingleDatePreview(normalizedOccurrences, locale);
            if (singlePreview) {
                return singlePreview;
            }
        }

        var rangePreview = buildConsecutiveRangePreview(normalizedOccurrences, locale);
        if (rangePreview) {
            return rangePreview;
        }

        var weeklyFromOccurrences = buildWeeklyPreviewFromOccurrences(normalizedOccurrences, weekdayFullLabels);
        if (weeklyFromOccurrences) {
            return weeklyFromOccurrences;
        }

        return buildOccurrenceListPreview(normalizedOccurrences, locale);
    }

    function parseTimeToMinutes(value) {
        if (typeof value !== 'string') {
            return null;
        }
        var parts = value.split(':');
        if (parts.length < 2) {
            return null;
        }
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return null;
        }
        return (hours * 60) + minutes;
    }

    function minutesToTime(totalMinutes) {
        if (typeof totalMinutes !== 'number' || Number.isNaN(totalMinutes)) {
            return '';
        }
        var normalized = ((totalMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
        var hours = Math.floor(normalized / 60);
        var minutes = normalized % 60;
        return padNumber(hours) + ':' + padNumber(minutes);
    }

    function deriveOccurrenceTimeSummary(list) {
        if (!Array.isArray(list) || list.length === 0) {
            return '';
        }
        var onlyAllDayOccurrences = true;
        var minStart = null;
        var maxEnd = null;
        var fallbackStart = '';
        var fallbackEnd = '';
        list.forEach(function (occurrence) {
            if (!occurrence) {
                return;
            }
            if (!occurrence.isAllDay) {
                onlyAllDayOccurrences = false;
            }
            var startStr = typeof occurrence.startTime === 'string' ? occurrence.startTime : '';
            var endStr = typeof occurrence.endTime === 'string' ? occurrence.endTime : '';
            if (!fallbackStart && startStr) {
                fallbackStart = startStr;
            }
            if (!fallbackEnd && endStr) {
                fallbackEnd = endStr;
            }
            var startMinutes = parseTimeToMinutes(startStr);
            if (startMinutes !== null && (minStart === null || startMinutes < minStart)) {
                minStart = startMinutes;
            }
            var endMinutes = parseTimeToMinutes(endStr);
            if (endMinutes !== null && (maxEnd === null || endMinutes > maxEnd)) {
                maxEnd = endMinutes;
            }
        });
        if (onlyAllDayOccurrences) {
            return 'Toute la journée';
        }
        var startText = minStart !== null ? minutesToTime(minStart) : fallbackStart;
        var endText = maxEnd !== null ? minutesToTime(maxEnd) : fallbackEnd;
        return formatTimeRange(startText, endText);
    }

    function deriveDayStatus(list) {
        if (!Array.isArray(list) || list.length === 0) {
            return '';
        }
        var priority = {
            cancelled: 3,
            confirmed: 2,
            planned: 1,
        };
        var resolved = '';
        list.forEach(function (occurrence) {
            if (!occurrence || typeof occurrence.status !== 'string') {
                return;
            }
            var candidate = normalizeOccurrenceStatus(occurrence.status);
            var candidatePriority = priority[candidate] || 0;
            var resolvedPriority = priority[resolved] || 0;
            if (!resolved || candidatePriority > resolvedPriority) {
                resolved = candidate;
            }
        });
        return resolved;
    }

    function isSameDate(a, b) {
        if (!(a instanceof Date) || !(b instanceof Date)) {
            return false;
        }
        return a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    function capitalizeLabel(label) {
        if (!label || typeof label !== 'string') {
            return '';
        }
        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function normalizeOccurrenceStatus(status) {
        if (typeof status !== 'string') {
            return 'planned';
        }
        var value = status.trim().toLowerCase();
        if (value === 'confirmed' || value === 'active') {
            return 'confirmed';
        }
        if (value === 'cancelled' || value === 'annule') {
            return 'cancelled';
        }
        if (value === 'postponed' || value === 'reporté' || value === 'reporte') {
            return 'planned';
        }
        if (value === 'pending' || value === 'a_confirmer' || value === 'planned') {
            return 'planned';
        }
        return 'planned';
    }

    function cloneOccurrenceList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(function (occ) {
            return Object.assign({}, occ);
        });
    }

    function normalizeOccurrence(occurrence, index) {
        var start = parseISODateTime(occurrence && (occurrence.start || occurrence.start_time || occurrence.date));
        var end = parseISODateTime(occurrence && occurrence.end);
        var dateValue = start ? formatISODate(start) : (occurrence && typeof occurrence.date === 'string' ? occurrence.date : formatISODate(new Date()));
        var startTime = start ? formatTimeFromDate(start) : (occurrence && typeof occurrence.startTime === 'string' ? occurrence.startTime : '09:00');
        var endTime = end ? formatTimeFromDate(end) : (occurrence && typeof occurrence.endTime === 'string' ? occurrence.endTime : addMinutesToTime(startTime, 60));
        var isAllDay = false;
        if (occurrence && typeof occurrence.isAllDay === 'boolean') {
            isAllDay = occurrence.isAllDay;
        } else if (occurrence && typeof occurrence.allDay === 'boolean') {
            isAllDay = occurrence.allDay;
        } else if (occurrence && occurrence.meta && typeof occurrence.meta === 'object' && occurrence.meta !== null) {
            var metaAllDay = occurrence.meta.all_day;
            if (metaAllDay === true || metaAllDay === 1 || metaAllDay === '1' || metaAllDay === 'true') {
                isAllDay = true;
            }
        }
        if (!isAllDay && startTime === '00:00' && (endTime === '23:59' || endTime === '23:58')) {
            isAllDay = true;
        }
        if (isAllDay) {
            startTime = '00:00';
            endTime = '23:59';
        }
        var statusValue = normalizeOccurrenceStatus(occurrence && occurrence.status);
        var reasonValue = '';
        if (occurrence && typeof occurrence.reason === 'string') {
            reasonValue = occurrence.reason;
        } else if (occurrence && occurrence.meta && typeof occurrence.meta.reason === 'string') {
            reasonValue = occurrence.meta.reason;
        } else if (occurrence && typeof occurrence.cancelReason === 'string') {
            reasonValue = occurrence.cancelReason;
        }

        var titleValue = '';
        if (occurrence && typeof occurrence.title === 'string') {
            titleValue = occurrence.title;
        } else if (occurrence && typeof occurrence.noteCalendar === 'string') {
            titleValue = occurrence.noteCalendar;
        } else if (occurrence && typeof occurrence.note_calendar === 'string') {
            titleValue = occurrence.note_calendar;
        }

        var generationBatchValue = '';
        if (occurrence && typeof occurrence.generationBatchId === 'string') {
            generationBatchValue = occurrence.generationBatchId;
        } else if (occurrence && typeof occurrence.generation_batch_id === 'string') {
            generationBatchValue = occurrence.generation_batch_id;
        } else if (occurrence && typeof occurrence.batch_id === 'string') {
            generationBatchValue = occurrence.batch_id;
        }

        return {
            id: occurrence && occurrence.id ? String(occurrence.id) : 'occurrence-' + index,
            date: dateValue,
            startTime: startTime || '09:00',
            endTime: endTime || addMinutesToTime(startTime || '09:00', 60),
            isAllDay: !!isAllDay,
            status: statusValue,
            reason: reasonValue,
            source: occurrence && typeof occurrence.source === 'string' ? occurrence.source : 'manual',
            generationBatchId: generationBatchValue,
            visibility: occurrence && typeof occurrence.visibility === 'string' ? occurrence.visibility : 'tous',
            noteSchedule: occurrence && typeof occurrence.noteSchedule === 'string' ? occurrence.noteSchedule : '',
            noteCalendar: occurrence && typeof occurrence.noteCalendar === 'string' ? occurrence.noteCalendar : '',
            title: titleValue,
        };
    }

    function normalizeIsoDateRange(startIso, endIso) {
        var startDate = parseISODate(startIso);
        var endDate = parseISODate(endIso);
        if (!startDate || !endDate) {
            return null;
        }
        if (endDate < startDate) {
            var swap = startDate;
            startDate = endDate;
            endDate = swap;
        }
        return {
            start: formatISODate(startDate),
            end: formatISODate(endDate),
        };
    }

    function isIsoDateInsideRange(dateIso, startIso, endIso) {
        var range = normalizeIsoDateRange(startIso, endIso);
        if (!range || !dateIso) {
            return false;
        }
        return dateIso >= range.start && dateIso <= range.end;
    }

    function buildIsoDateRange(startIso, endIso) {
        var range = normalizeIsoDateRange(startIso, endIso);
        if (!range) {
            return [];
        }
        var startDate = parseISODate(range.start);
        var endDate = parseISODate(range.end);
        if (!startDate || !endDate) {
            return [];
        }
        var result = [];
        var cursor = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
        var guard = 0;
        while (cursor <= endDate && guard < 366) {
            result.push(formatISODate(cursor));
            cursor = addDays(cursor, 1);
            guard += 1;
        }
        return result;
    }

    function diffIsoDateInDays(fromIso, toIso) {
        var fromDate = parseISODate(fromIso);
        var toDate = parseISODate(toIso);
        if (!fromDate || !toDate) {
            return 0;
        }
        var diffMs = toDate.getTime() - fromDate.getTime();
        return Math.round(diffMs / (24 * 60 * 60 * 1000));
    }

    function shiftIsoDate(iso, deltaDays) {
        var dateObj = parseISODate(iso);
        if (!dateObj) {
            return iso;
        }
        return formatISODate(addDays(dateObj, deltaDays));
    }

    function shiftBatchOccurrencesByDays(list, batchId, shiftDays) {
        var numericShift = typeof shiftDays === 'number' && !Number.isNaN(shiftDays) ? Math.round(shiftDays) : 0;
        if (!numericShift) {
            return list;
        }
        var targetBatchId = String(batchId || '');
        return (Array.isArray(list) ? list : []).map(function (occ) {
            if (!occ || getOccurrenceBatchId(occ) !== targetBatchId) {
                return occ;
            }
            return Object.assign({}, occ, {
                date: shiftIsoDate(occ.date, numericShift),
            });
        });
    }

    function shiftBatchConfigByDays(config, shiftDays) {
        var numericShift = typeof shiftDays === 'number' && !Number.isNaN(shiftDays) ? Math.round(shiftDays) : 0;
        var base = config && typeof config === 'object' ? config : {};
        if (!numericShift) {
            return Object.assign({}, base);
        }

        var next = Object.assign({}, base);
        if (typeof next.startDate === 'string' && next.startDate) {
            next.startDate = shiftIsoDate(next.startDate, numericShift);
        }
        if (typeof next.endDate === 'string' && next.endDate) {
            next.endDate = shiftIsoDate(next.endDate, numericShift);
        }
        if (typeof next.start === 'string' && next.start) {
            next.start = shiftDateTimeByDays(next.start, numericShift);
        }
        if (typeof next.end === 'string' && next.end) {
            next.end = shiftDateTimeByDays(next.end, numericShift);
        }

        var mode = typeof next.mode === 'string' ? sanitizeGeneratorMode(next.mode) : '';
        if (mode === 'weekly') {
            next.days = shiftWeekDays(next.days, numericShift);
        } else if (mode === 'monthly' && typeof next.monthlyWeekday === 'string' && next.monthlyWeekday) {
            next.monthlyWeekday = shiftWeekDayKey(next.monthlyWeekday, numericShift);
        }

        return next;
    }

    function shiftDateTimeByDays(value, shiftDays) {
        if (typeof value !== 'string' || !value) {
            return value;
        }
        var parsed = parseISODateTime(value);
        if (!parsed) {
            return value;
        }
        var shifted = addDays(parsed, shiftDays);
        return formatISODate(shifted)
            + ' '
            + String(shifted.getHours()).padStart(2, '0')
            + ':'
            + String(shifted.getMinutes()).padStart(2, '0')
            + ':'
            + String(shifted.getSeconds()).padStart(2, '0');
    }

    function shiftWeekDays(daysValue, shiftDays) {
        var keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        var shiftedMap = { mon: false, tue: false, wed: false, thu: false, fri: false, sat: false, sun: false };
        if (Array.isArray(daysValue)) {
            daysValue.forEach(function (dayKey) {
                var shifted = shiftWeekDayKey(dayKey, shiftDays);
                if (shiftedMap.hasOwnProperty(shifted)) {
                    shiftedMap[shifted] = true;
                }
            });
            return keys.filter(function (key) { return shiftedMap[key]; });
        }
        if (daysValue && typeof daysValue === 'object') {
            Object.keys(shiftedMap).forEach(function (key) {
                shiftedMap[key] = false;
            });
            Object.keys(daysValue).forEach(function (rawKey) {
                if (!daysValue[rawKey]) {
                    return;
                }
                var shifted = shiftWeekDayKey(rawKey, shiftDays);
                if (shiftedMap.hasOwnProperty(shifted)) {
                    shiftedMap[shifted] = true;
                }
            });
            return shiftedMap;
        }
        return daysValue;
    }

    function shiftWeekDayKey(dayKey, shiftDays) {
        var keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        var normalized = typeof dayKey === 'string' ? sanitizeGeneratorWeekday(dayKey) : '';
        var index = keys.indexOf(normalized);
        if (index === -1) {
            return normalized;
        }
        var shift = typeof shiftDays === 'number' && !Number.isNaN(shiftDays) ? Math.round(shiftDays) : 0;
        var normalizedShift = ((shift % 7) + 7) % 7;
        return keys[(index + normalizedShift) % 7];
    }

    function rebuildBatchOccurrencesForRange(list, batchId, startIso, endIso) {
        var targetBatchId = String(batchId || '');
        var safeList = Array.isArray(list) ? list : [];
        var rangeDays = buildIsoDateRange(startIso, endIso);
        if (!rangeDays.length) {
            return safeList;
        }

        var batchItems = safeList
            .filter(function (occ) { return occ && getOccurrenceBatchId(occ) === targetBatchId; })
            .sort(function (left, right) { return String(left.date).localeCompare(String(right.date)); });
        if (!batchItems.length) {
            return safeList;
        }

        var template = batchItems[0];
        var newBatchOccurrences = rangeDays.map(function (iso, index) {
            var source = batchItems[index] || template;
            return Object.assign({}, source, {
                id: source.id || generateOccurrenceId(iso, source.startTime || '09:00', index),
                date: iso,
            });
        });

        var remaining = safeList.filter(function (occ) {
            return !occ || getOccurrenceBatchId(occ) !== targetBatchId;
        });
        return remaining.concat(newBatchOccurrences).sort(function (left, right) {
            var leftDate = left && left.date ? String(left.date) : '';
            var rightDate = right && right.date ? String(right.date) : '';
            if (leftDate !== rightDate) {
                return leftDate.localeCompare(rightDate);
            }
            var leftTime = left && left.startTime ? String(left.startTime) : '';
            var rightTime = right && right.startTime ? String(right.startTime) : '';
            return leftTime.localeCompare(rightTime);
        });
    }

    function clampMinutesToDay(value) {
        var numeric = typeof value === 'number' && !Number.isNaN(value) ? value : 0;
        if (numeric < 0) {
            return 0;
        }
        if (numeric > (24 * 60 - 1)) {
            return 24 * 60 - 1;
        }
        return numeric;
    }

    function getOccurrenceBatchId(occurrence) {
        if (!occurrence || typeof occurrence !== 'object') {
            return '';
        }
        var raw = occurrence.generationBatchId || occurrence.generation_batch_id || occurrence.batch_id || '';
        if (raw === null || raw === undefined) {
            return '';
        }
        return String(raw);
    }

    function dayHasBatch(day, batchId) {
        if (!day || !Array.isArray(day.occurrences) || !batchId) {
            return false;
        }
        return day.occurrences.some(function (occ) {
            return getOccurrenceBatchId(occ) === String(batchId);
        });
    }

    function isOccurrenceBatchActive(status) {
        if (typeof status !== 'string') {
            return true;
        }
        var normalized = status.trim().toLowerCase();
        if (!normalized) {
            return true;
        }
        return normalized !== 'deleted' && normalized !== 'inactive' && normalized !== 'archived';
    }

    function deriveInitialPivotDate(occurrences, event) {
        var candidate = null;
        if (occurrences && occurrences.length > 0) {
            candidate = parseISODate(occurrences[0].date);
        }
        if (!candidate && event && typeof event.dateDebut === 'string') {
            candidate = parseISODateTime(event.dateDebut);
        }
        if (!candidate && event && typeof event.dateFin === 'string') {
            candidate = parseISODateTime(event.dateFin);
        }
        if (!candidate) {
            candidate = new Date();
        }
        candidate.setHours(0, 0, 0, 0);
        return candidate;
    }

    function resolveLocaleTag(locale) {
        if (typeof locale !== 'string') {
            return 'fr';
        }
        var trimmed = locale.trim();
        if (trimmed === '') {
            return 'fr';
        }
        var normalised = trimmed.replace(/_/g, '-');
        try {
            new Intl.DateTimeFormat(normalised);
            return normalised;
        } catch (error) {
            var primary = normalised.split('-')[0];
            if (primary) {
                try {
                    new Intl.DateTimeFormat(primary);
                    return primary;
                } catch (error2) {
                    // Ignore and fallback below
                }
            }
        }
        return 'fr';
    }

    function buildQuarterMonths(pivotDate, locale, occurrencesByDate, selectedId) {
        if (!(pivotDate instanceof Date) || Number.isNaN(pivotDate.getTime())) {
            return [];
        }
        var months = [];
        var base = new Date(pivotDate.getFullYear(), pivotDate.getMonth(), 1);
        for (var offset = 0; offset < 4; offset++) {
            var monthDate = new Date(base.getFullYear(), base.getMonth() + offset, 1);
            months.push(buildMonthOverview(monthDate, locale, occurrencesByDate, selectedId));
        }
        return months;
    }

    function alignDateToWeekStart(date) {
        var reference = date instanceof Date && !Number.isNaN(date.getTime())
            ? new Date(date.getTime())
            : new Date();
        reference.setHours(0, 0, 0, 0);
        var jsIndex = reference.getDay();
        var diff = (jsIndex + 6) % 7;
        reference.setDate(reference.getDate() - diff);
        reference.setHours(0, 0, 0, 0);
        return reference;
    }

    function buildWeekOverview(pivotDate, occurrencesByDate, selectedId) {
        if (!(pivotDate instanceof Date) || Number.isNaN(pivotDate.getTime())) {
            return null;
        }
        var start = alignDateToWeekStart(pivotDate);
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var days = [];
        var minMinutes = null;
        var maxMinutes = null;
        for (var offset = 0; offset < 7; offset++) {
            var current = new Date(start.getFullYear(), start.getMonth(), start.getDate() + offset);
            current.setHours(0, 0, 0, 0);
            var iso = formatISODate(current);
            var sourceList = occurrencesByDate[iso] || [];
            var occurrences = [];
            sourceList.forEach(function (occ, index) {
                if (!occ) {
                    return;
                }
                var normalized = Object.assign({}, occ);
                normalized.id = occ.id ? String(occ.id) : ('occurrence-' + iso + '-' + index);
                normalized.status = normalizeOccurrenceStatus(occ.status);
                var startValue = typeof occ.startMinutes === 'number'
                    ? occ.startMinutes
                    : parseTimeToMinutes(occ.startTime);
                var endValue = typeof occ.endMinutes === 'number'
                    ? occ.endMinutes
                    : parseTimeToMinutes(occ.endTime);
                if (startValue === null) {
                    startValue = 9 * 60;
                }
                if (endValue === null || endValue <= startValue) {
                    endValue = startValue + 60;
                }
                normalized.startMinutes = startValue;
                normalized.endMinutes = endValue;
                if (minMinutes === null || startValue < minMinutes) {
                    minMinutes = startValue;
                }
                if (maxMinutes === null || endValue > maxMinutes) {
                    maxMinutes = endValue;
                }
                occurrences.push(normalized);
            });
            var weekdayIndex = (current.getDay() + 6) % 7;
            var dayStatus = deriveDayStatus(occurrences);
            var normalizedSelectedId = selectedId ? String(selectedId) : '';
            var isSelected = !!(normalizedSelectedId && occurrences.some(function (item) {
                return item && item.id === normalizedSelectedId;
            }));
            days.push({
                key: iso,
                iso: iso,
                date: current,
                weekdayIndex: weekdayIndex,
                dayNumber: current.getDate(),
                occurrences: occurrences,
                timeSummary: deriveOccurrenceTimeSummary(occurrences),
                status: dayStatus,
                isSelected: isSelected,
                isToday: isSameDate(current, today),
            });
        }
        if (minMinutes === null || maxMinutes === null) {
            minMinutes = 9 * 60;
            maxMinutes = 17 * 60;
        }
        var end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
        end.setHours(0, 0, 0, 0);
        return {
            key: 'week-' + formatISODate(start),
            start: start,
            end: end,
            days: days,
            minMinutes: minMinutes,
            maxMinutes: maxMinutes,
        };
    }

    function buildMonthOverview(monthDate, locale, occurrencesByDate, selectedId) {
        var month = monthDate.getMonth();
        var year = monthDate.getFullYear();
        var monthLabel;
        try {
            monthLabel = monthDate.toLocaleDateString(locale || 'fr', { month: 'long', year: 'numeric' });
        } catch (error) {
            monthLabel = monthDate.toLocaleDateString('fr', { month: 'long', year: 'numeric' });
        }
        var label = capitalizeLabel(monthLabel);
        var firstDay = new Date(year, month, 1);
        var startOffset = (firstDay.getDay() + 6) % 7;
        var cursor = new Date(year, month, 1 - startOffset);
        var today = new Date();
        var weeks = [];
        for (var weekIndex = 0; weekIndex < 6; weekIndex++) {
            var days = [];
            for (var dayIndex = 0; dayIndex < 7; dayIndex++) {
                var current = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + (weekIndex * 7) + dayIndex);
                var iso = formatISODate(current);
                var list = occurrencesByDate[iso] || [];
                var dayStatus = deriveDayStatus(list);
                var isSelected = selectedId ? list.some(function (item) { return item.id === selectedId; }) : false;
                var timeSummary = deriveOccurrenceTimeSummary(list);
                days.push({
                    iso: iso,
                    label: current.getDate(),
                    isCurrentMonth: current.getMonth() === month,
                    isToday: isSameDate(current, today),
                    hasOccurrences: list.length > 0,
                    occurrences: list,
                    isSelected: isSelected,
                    timeSummary: timeSummary,
                    status: dayStatus,
                });
            }
            weeks.push(days);
        }
        return {
            key: 'month-' + year + '-' + (month + 1),
            label: label,
            weeks: weeks,
        };
    }

    function createEditorState(occurrence) {
        if (!occurrence) {
            return {
                id: null,
                date: '',
                endDate: '',
                startTime: '09:00',
                endTime: '10:00',
                isAllDay: false,
                status: 'planned',
                reason: '',
                title: '',
            };
        }
        return {
            id: occurrence.id,
            date: occurrence.date,
            endDate: occurrence.endDate || occurrence.date,
            startTime: occurrence.startTime || '09:00',
            endTime: occurrence.endTime || '10:00',
            isAllDay: !!occurrence.isAllDay,
            status: occurrence.status || 'planned',
            reason: occurrence.reason || '',
            title: occurrence.title || occurrence.noteCalendar || '',
        };
    }

    function createGeneratorState(pivotDate) {
        var reference = pivotDate instanceof Date ? new Date(pivotDate.getTime()) : new Date();
        reference.setHours(0, 0, 0, 0);
        return {
            mode: 'weekly',
            frequency: 'every_week',
            startDate: formatISODate(reference),
            endDate: '',
            startTime: '09:00',
            endTime: '11:00',
            days: {
                mon: true,
                tue: true,
                wed: false,
                thu: false,
                fri: false,
                sat: false,
                sun: false,
            },
            timeOverrides: {},
            monthlyOrdinal: 'first',
            monthlyWeekday: 'mon',
        };
    }

    function sanitizeTimeValue(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (!/^\d{2}:\d{2}$/.test(trimmed)) {
            return '';
        }
        var parts = trimmed.split(':');
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return '';
        }
        if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
            return '';
        }
        return padNumber(hours) + ':' + padNumber(minutes);
    }

    function sanitizeDateValue(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }
        var parsed = parseISODate(trimmed);
        if (!parsed) {
            return '';
        }
        return formatISODate(parsed);
    }

    function normalizeGeneratorPlan(plan) {
        if (!plan || typeof plan !== 'object') {
            return null;
        }

        var normalized = {
            version: 'occurrence-editor',
            mode: 'weekly',
            frequency: 'every_week',
            startDate: '',
            endDate: '',
            startTime: '',
            endTime: '',
            days: {},
            overrides: {},
            monthlyOrdinal: 'first',
            monthlyWeekday: 'mon',
            explicitStart: false,
        };

        var mode = typeof plan.mode === 'string' ? plan.mode.trim().toLowerCase() : '';
        if (mode === 'monthly') {
            normalized.mode = 'monthly';
        } else if (mode === 'range') {
            normalized.mode = 'range';
        } else if (mode === 'custom') {
            normalized.mode = 'custom';
        } else {
            normalized.mode = 'weekly';
        }

        var frequency = typeof plan.frequency === 'string' ? plan.frequency.trim() : '';
        if (frequency === 'every_two_weeks') {
            normalized.frequency = 'every_two_weeks';
        } else {
            normalized.frequency = 'every_week';
        }

        var startCandidate = '';
        if (typeof plan.startDateISO === 'string' && plan.startDateISO !== '') {
            startCandidate = plan.startDateISO;
        } else if (typeof plan.startDate === 'string') {
            startCandidate = plan.startDate;
        }
        normalized.startDate = sanitizeDateValue(startCandidate);

        var endCandidate = '';
        if (typeof plan.endDateISO === 'string' && plan.endDateISO !== '') {
            endCandidate = plan.endDateISO;
        } else if (typeof plan.endDate === 'string') {
            endCandidate = plan.endDate;
        }
        normalized.endDate = sanitizeDateValue(endCandidate);

        normalized.startTime = sanitizeTimeValue(plan.startTime);
        normalized.endTime = sanitizeTimeValue(plan.endTime);

        var daysSource = plan.days;
        var hasSelectedDay = false;
        OCCURRENCE_WEEKDAY_KEYS.forEach(function (key) {
            var value = false;
            if (Array.isArray(daysSource)) {
                value = daysSource.indexOf(key) !== -1;
            } else if (daysSource && typeof daysSource === 'object') {
                value = !!daysSource[key];
            }
            normalized.days[key] = value;
            if (value) {
                hasSelectedDay = true;
            }
        });

        var overridesSource = plan.overrides || plan.timeOverrides || {};
        if (overridesSource && typeof overridesSource === 'object') {
            var overrides = {};
            OCCURRENCE_WEEKDAY_KEYS.forEach(function (key) {
                var overrideValue = overridesSource[key];
                if (!overrideValue || typeof overrideValue !== 'object') {
                    return;
                }
                var overrideEntry = {};
                var overrideStart = sanitizeTimeValue(overrideValue.start);
                var overrideEnd = sanitizeTimeValue(overrideValue.end);
                if (overrideStart) {
                    overrideEntry.start = overrideStart;
                }
                if (overrideEnd) {
                    overrideEntry.end = overrideEnd;
                }
                if (Object.keys(overrideEntry).length > 0) {
                    overrides[key] = overrideEntry;
                }
            });
            normalized.overrides = overrides;
        }

        var ordinal = typeof plan.monthlyOrdinal === 'string' ? plan.monthlyOrdinal.trim().toLowerCase() : '';
        if (['first', 'second', 'third', 'fourth', 'last'].indexOf(ordinal) === -1) {
            ordinal = 'first';
        }
        normalized.monthlyOrdinal = ordinal;

        var weekday = typeof plan.monthlyWeekday === 'string' ? plan.monthlyWeekday.trim().toLowerCase() : '';
        if (OCCURRENCE_WEEKDAY_KEYS.indexOf(weekday) === -1) {
            weekday = 'mon';
        }
        normalized.monthlyWeekday = weekday;

        var explicitStart = false;
        if (plan.explicitStart !== undefined) {
            explicitStart = !!plan.explicitStart;
        } else if (plan._explicitStart !== undefined) {
            explicitStart = !!plan._explicitStart;
        }
        if (normalized.startDate !== '') {
            explicitStart = true;
        }
        normalized.explicitStart = explicitStart;

        normalized.hasSelectedDay = hasSelectedDay;

        return normalized;
    }

    function createGeneratorStateFromPlan(plan, pivotDate) {
        var normalized = normalizeGeneratorPlan(plan);
        var base = createGeneratorState(pivotDate);
        if (!normalized) {
            return base;
        }

        var next = Object.assign({}, base);
        next.mode = normalized.mode;
        next.frequency = normalized.frequency;
        if (normalized.startDate !== '') {
            next.startDate = normalized.startDate;
            next._explicitStart = true;
        } else {
            next.startDate = formatISODate(pivotDate);
            next._explicitStart = false;
        }
        next.endDate = normalized.endDate !== '' ? normalized.endDate : '';
        next.startTime = normalized.startTime || base.startTime;
        next.endTime = normalized.endTime || base.endTime;

        var days = {};
        var hasDay = false;
        OCCURRENCE_WEEKDAY_KEYS.forEach(function (key) {
            var value = normalized.days && normalized.days.hasOwnProperty(key) ? !!normalized.days[key] : false;
            days[key] = value;
            if (value) {
                hasDay = true;
            }
        });
        if (!hasDay) {
            days = Object.assign({}, base.days);
        }
        next.days = days;

        next.timeOverrides = normalized.overrides ? Object.assign({}, normalized.overrides) : {};
        next.monthlyOrdinal = normalized.monthlyOrdinal || base.monthlyOrdinal;
        next.monthlyWeekday = normalized.monthlyWeekday || base.monthlyWeekday;

        return next;
    }

    function serializeGeneratorPlan(planResult, generatorState) {
        var plan = planResult && planResult.plan ? planResult.plan : null;
        var candidate = {};

        if (generatorState && typeof generatorState === 'object') {
            candidate.mode = generatorState.mode;
            candidate.frequency = generatorState.frequency;
            candidate.startDate = generatorState.startDate;
            candidate.endDate = generatorState.endDate;
            candidate.startTime = generatorState.startTime;
            candidate.endTime = generatorState.endTime;
            candidate.days = generatorState.days;
            candidate.overrides = generatorState.timeOverrides;
            candidate.monthlyOrdinal = generatorState.monthlyOrdinal;
            candidate.monthlyWeekday = generatorState.monthlyWeekday;
            candidate.explicitStart = !!generatorState._explicitStart;
        }

        if (plan) {
            if (plan.mode !== undefined) {
                candidate.mode = plan.mode;
            }
            if (plan.frequency !== undefined) {
                candidate.frequency = plan.frequency;
            }
            if (plan.startDateISO !== undefined) {
                candidate.startDate = plan.startDateISO;
            }
            if (plan.endDateISO !== undefined) {
                candidate.endDate = plan.endDateISO;
            }
            if (plan.startTime !== undefined) {
                candidate.startTime = plan.startTime;
            }
            if (plan.endTime !== undefined) {
                candidate.endTime = plan.endTime;
            }
            if (plan.days !== undefined) {
                candidate.days = plan.days;
            }
            if (plan.overrides !== undefined) {
                candidate.overrides = plan.overrides;
            }
            if (plan.monthlyOrdinal !== undefined) {
                candidate.monthlyOrdinal = plan.monthlyOrdinal;
            }
            if (plan.monthlyWeekday !== undefined) {
                candidate.monthlyWeekday = plan.monthlyWeekday;
            }
        }

        if (!candidate.mode && generatorState) {
            candidate.mode = generatorState.mode;
        }

        var normalized = normalizeGeneratorPlan(candidate);
        if (!normalized) {
            return null;
        }

        if (generatorState && generatorState._explicitStart && normalized.startDate === '') {
            normalized.startDate = sanitizeDateValue(generatorState.startDate);
            normalized.explicitStart = normalized.startDate !== '';
        }

        normalized.version = 'occurrence-editor';

        return normalized;
    }

    function findNextWeekday(startDate, targetIndex) {
        var base = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
        var delta = (targetIndex - base.getDay() + 7) % 7;
        if (delta !== 0) {
            base = addDays(base, delta);
        }
        return base;
    }

    function resolveMonthlyOrdinalValue(key) {
        switch (key) {
            case 'second':
                return 2;
            case 'third':
                return 3;
            case 'fourth':
                return 4;
            case 'last':
                return 'last';
            case 'first':
            default:
                return 1;
        }
    }

    function findNthWeekdayOfMonth(baseMonthDate, weekdayIndex, ordinalKey) {
        if (!(baseMonthDate instanceof Date) || Number.isNaN(baseMonthDate.getTime())) {
            return null;
        }

        var year = baseMonthDate.getFullYear();
        var month = baseMonthDate.getMonth();
        var ordinalValue = resolveMonthlyOrdinalValue(ordinalKey);
        if (ordinalValue !== 'last' && (typeof ordinalValue !== 'number' || ordinalValue <= 0)) {
            ordinalValue = 1;
        }

        if (ordinalValue === 'last') {
            var lastDay = new Date(year, month + 1, 0);
            var adjustment = (lastDay.getDay() - weekdayIndex + 7) % 7;
            return new Date(year, month + 1, 0 - adjustment);
        }

        var firstOfMonth = new Date(year, month, 1);
        var offset = (weekdayIndex - firstOfMonth.getDay() + 7) % 7;
        var day = 1 + offset + 7 * (ordinalValue - 1);
        var candidate = new Date(year, month, day);

        if (candidate.getMonth() !== month) {
            return null;
        }

        return candidate;
    }

    function addDays(base, count) {
        return new Date(base.getFullYear(), base.getMonth(), base.getDate() + count);
    }

    function generateOccurrenceId(date, time, seed) {
        var cleanDate = (date || '').replace(/[^0-9]/g, '');
        var cleanTime = (time || '').replace(':', '');
        return 'occ-' + cleanDate + '-' + cleanTime + '-' + seed;
    }

    global.MjRegMgrOccurrenceEditor = {
        OccurrenceEncoderPanel: OccurrenceEncoderPanel,
        normalizeOccurrence: normalizeOccurrence,
        normalizeOccurrenceStatus: normalizeOccurrenceStatus,
        normalizeGeneratorPlan: normalizeGeneratorPlan,
        deriveSchedulePreviewText: deriveSchedulePreviewText,
    };

})(window);
