(function () {
    'use strict';

    if (!window.preact || !window.preactHooks) {
        return;
    }

    var h = window.preact.h;
    var render = window.preact.render;
    var useEffect = window.preactHooks.useEffect;
    var useMemo = window.preactHooks.useMemo;
    var useState = window.preactHooks.useState;

    var cfg = window.mjRequestManagement || {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var nonce = cfg.nonce || '';

    var BASE_STEPS = [
        { key: 'essential', label: 'L\'essentiel' },
        { key: 'location', label: 'Lieu' },
        { key: 'date', label: 'Date' },
        { key: 'details', label: 'Détails' },
    ];
    var START_MINUTE = 8 * 60;
    var END_MINUTE = 21 * 60;
    var STEP_MINUTES = 15;

    function post(action, payload, files) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);

        Object.keys(payload || {}).forEach(function (k) {
            var v = payload[k];
            if (v === undefined || v === null) {
                return;
            }
            fd.append(k, typeof v === 'string' ? v : String(v));
        });

        if (files && files.length) {
            files.forEach(function (file) {
                fd.append('images[]', file);
            });
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
        }).then(function (r) { return r.json(); });
    }

    function fmtMinute(value) {
        var hVal = Math.floor(value / 60);
        var mVal = value % 60;
        return String(hVal).padStart(2, '0') + ':' + String(mVal).padStart(2, '0');
    }

    function parseMinuteText(value) {
        if (typeof value !== 'string') {
            return null;
        }
        var match = value.match(/^(\d{2}):(\d{2})$/);
        if (!match) {
            return null;
        }
        var hours = Number(match[1]);
        var minutes = Number(match[2]);
        if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
            return null;
        }
        return (hours * 60) + minutes;
    }

    function mondayOf(date) {
        var d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        var day = d.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        d.setDate(d.getDate() + diff);
        return d;
    }

    function isoDate(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function parseIsoDate(value) {
        if (typeof value !== 'string' || value === '') {
            return null;
        }
        var date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function startOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1);
    }

    function addMonths(date, delta) {
        return new Date(date.getFullYear(), date.getMonth() + delta, 1);
    }

    function buildMiniCalendar(monthDate, selectedIso) {
        var firstDay = startOfMonth(monthDate);
        var gridStart = mondayOf(firstDay);
        var todayIso = isoDate(new Date());
        var days = [];

        for (var idx = 0; idx < 42; idx += 1) {
            var dayDate = new Date(gridStart.getTime());
            dayDate.setDate(gridStart.getDate() + idx);
            var dayIso = isoDate(dayDate);
            days.push({
                iso: dayIso,
                dayNumber: dayDate.getDate(),
                outside: dayDate.getMonth() !== monthDate.getMonth(),
                isToday: dayIso === todayIso,
                isSelected: dayIso === selectedIso,
            });
        }

        return days;
    }

    function dayLabels(weekStartIso) {
        var start = new Date(weekStartIso + 'T00:00:00');
        var labels = [];
        for (var i = 0; i < 7; i += 1) {
            var d = new Date(start.getTime());
            d.setDate(start.getDate() + i);
            labels.push({
                iso: isoDate(d),
                short: d.toLocaleDateString('fr-BE', { weekday: 'short', day: '2-digit' }),
            });
        }
        return labels;
    }

    function minuteFromPointer(evt, rect) {
        var y = evt.clientY - rect.top;
        var ratio = Math.max(0, Math.min(1, y / rect.height));
        var minute = START_MINUTE + Math.round(((END_MINUTE - START_MINUTE) * ratio) / STEP_MINUTES) * STEP_MINUTES;
        return Math.max(START_MINUTE, Math.min(END_MINUTE, minute));
    }

    function withEmoji(emoji, label) {
        var emojiText = typeof emoji === 'string' ? emoji.trim() : '';
        var labelText = typeof label === 'string' ? label : '';
        return emojiText ? (emojiText + ' ' + labelText).trim() : labelText;
    }

    function renderRichDescription(className, html) {
        if (typeof html !== 'string' || html.trim() === '') {
            return null;
        }

        return h('div', {
            class: className,
            dangerouslySetInnerHTML: { __html: html }
        });
    }

    function RequestManagementApp() {
        var [mainTab, setMainTab] = useState('compose');
        var [step, setStep] = useState(0);
        var [mine, setMine] = useState(cfg.mine || []);
        var [staff, setStaff] = useState(cfg.staff || []);
        var [staffStatusFilter, setStaffStatusFilter] = useState('');
        var [saving, setSaving] = useState(false);
        var [notice, setNotice] = useState('');
        var [statusNoteDraft, setStatusNoteDraft] = useState('');
        var [selectedRequestId, setSelectedRequestId] = useState(0);

        var [weekStart, setWeekStart] = useState(function () {
            var start = mondayOf(new Date());
            return isoDate(start);
        });
        var [calendarMonth, setCalendarMonth] = useState(function () {
            return startOfMonth(new Date());
        });
        var [dragState, setDragState] = useState(null);
        var [dayPlanner, setDayPlanner] = useState({
            open: false,
            dayIso: '',
            allDay: false,
        });
        var [rangeDraft, setRangeDraft] = useState({
            start: '',
            end: '',
            startTime: '14:00',
            endTime: '16:00',
        });

        var [form, setForm] = useState({
            requestType: '',
            roomId: 0,
            isOutdoor: false,
            roomOptions: [],
            materials: [],
            slotDay: 0,
            slotStart: '14:00',
            slotEnd: '16:00',
            slots: [],
            title: '',
            description: '',
            ageRange: '12-15',
            assignedMemberIds: [],
        });

        var [files, setFiles] = useState([]);

        var days = useMemo(function () { return dayLabels(weekStart); }, [weekStart]);
        var requestTypes = cfg.requestTypes || [];
        var rooms = cfg.rooms || [];
        var animateurs = cfg.animateurs || [];
        var statusLabels = cfg.statusLabels || {};
        var isStaff = !!cfg.isStaff;

        var selectedType = requestTypes.find(function (entry) { return entry.key === form.requestType; });
        var selectedRoom = rooms.find(function (entry) { return Number(entry.id) === Number(form.roomId); }) || null;
        var selectedTypeColor = selectedType && typeof selectedType.color === 'string' && selectedType.color
            ? selectedType.color
            : '#1F6FEB';
        var materialOptionsCatalog = useMemo(function () {
            var seen = Object.create(null);
            var out = [];
            (rooms || []).forEach(function (room) {
                var source = Array.isArray(room.materialsDetailed) && room.materialsDetailed.length
                    ? room.materialsDetailed
                    : (room.materials || []);

                source.forEach(function (item) {
                    var title = '';
                    var emoji = '';

                    if (typeof item === 'string') {
                        title = item.trim();
                    } else if (item && typeof item === 'object') {
                        title = String(item.title || '').trim();
                        emoji = String(item.emoji || '').trim();
                    }

                    if (!title || seen[title]) {
                        return;
                    }

                    seen[title] = true;
                    out.push({ title: title, emoji: emoji });
                });
            });
            return out;
        }, [rooms]);

        function normalizeCatalogEntries(listDetailed, listFallback) {
            var out = [];
            var seen = Object.create(null);
            var source = Array.isArray(listDetailed) && listDetailed.length ? listDetailed : listFallback;

            (source || []).forEach(function (entry) {
                var title = '';
                var emoji = '';

                if (typeof entry === 'string') {
                    title = entry.trim();
                } else if (entry && typeof entry === 'object') {
                    title = String(entry.title || '').trim();
                    emoji = String(entry.emoji || '').trim();
                }

                if (!title || seen[title]) {
                    return;
                }

                seen[title] = true;
                out.push({ title: title, emoji: emoji });
            });

            return out;
        }

        var selectedSlotDate = useMemo(function () {
            var baseDate = parseIsoDate(weekStart);
            if (!baseDate) {
                return null;
            }
            var target = new Date(baseDate.getTime());
            target.setDate(baseDate.getDate() + Number(form.slotDay || 0));
            return target;
        }, [weekStart, form.slotDay]);

        var selectedSlotIso = selectedSlotDate ? isoDate(selectedSlotDate) : '';
        var selectedSlotLabel = selectedSlotDate
            ? selectedSlotDate.toLocaleDateString('fr-BE', { weekday: 'long', day: '2-digit', month: 'long' })
            : '';

        var calendarDays = useMemo(function () {
            return buildMiniCalendar(calendarMonth, selectedSlotIso);
        }, [calendarMonth, selectedSlotIso]);

        var calendarMonthLabel = calendarMonth.toLocaleDateString('fr-BE', { month: 'long', year: 'numeric' });

        function typeOption(name, fallbackValue) {
            if (!selectedType || !selectedType.options || selectedType.options[name] === undefined) {
                return fallbackValue;
            }
            return !!selectedType.options[name];
        }

        var allowLocation = typeOption('allowsLocation', true);
        var allowMaterials = typeOption('allowsMaterials', true);
        var allowDate = typeOption('allowsDate', true);
        // Une demande peut toujours contenir plusieurs dates avec des plages horaires distinctes.
        var allowMultipleDates = allowDate;

        var slotDatesIndex = useMemo(function () {
            var index = Object.create(null);
            (form.slots || []).forEach(function (slot) {
                if (slot && slot.date) {
                    index[slot.date] = true;
                }
            });
            return index;
        }, [form.slots]);

        var mainTabs = useMemo(function () {
            var tabs = [
                { key: 'compose', label: '🚀 Encoder' },
                { key: 'mine', label: '📥 Mes demandes' },
            ];
            if (isStaff) {
                tabs.push({ key: 'staff', label: '🛠️ Traitement animateur' });
            }
            return tabs;
        }, [isStaff]);

        useEffect(function () {
            var mountNode = document.querySelector('[data-mj-request-management-app]');
            if (!mountNode) {
                return undefined;
            }

            var container = mountNode.closest('.mj-request-management');
            if (!container) {
                return undefined;
            }

            var accent = selectedTypeColor || '#1F6FEB';
            container.style.setProperty('--rm-accent', accent);
            container.style.setProperty('--rm-accent-soft', accent + '1A');
            container.style.setProperty('--rm-accent-strong', accent);

            return function () {
                container.style.removeProperty('--rm-accent');
                container.style.removeProperty('--rm-accent-soft');
                container.style.removeProperty('--rm-accent-strong');
            };
        }, [selectedTypeColor]);

        useEffect(function () {
            var hasCurrentTab = false;
            for (var i = 0; i < mainTabs.length; i += 1) {
                if (mainTabs[i].key === mainTab) {
                    hasCurrentTab = true;
                    break;
                }
            }
            if (!hasCurrentTab) {
                setMainTab('compose');
            }
        }, [mainTabs, mainTab]);

        function patchForm(key, value) {
            setForm(function (prev) {
                var copy = Object.assign({}, prev);
                copy[key] = value;
                return copy;
            });
        }

        function toggleStringItem(key, value) {
            setForm(function (prev) {
                var list = Array.isArray(prev[key]) ? prev[key].slice() : [];
                var idx = list.indexOf(value);
                if (idx >= 0) {
                    list.splice(idx, 1);
                } else {
                    list.push(value);
                }
                var copy = Object.assign({}, prev);
                copy[key] = list;
                return copy;
            });
        }

        var visibleSteps = useMemo(function () {
            return BASE_STEPS.filter(function (entry) {
                if (entry.key === 'location') {
                    return allowLocation || allowMaterials;
                }
                return true;
            });
        }, [allowLocation, allowMaterials]);

        var currentStep = visibleSteps[step] || visibleSteps[0] || BASE_STEPS[0];
        var isLastStep = step >= visibleSteps.length - 1;

        useEffect(function () {
            if (step > visibleSteps.length - 1) {
                setStep(Math.max(0, visibleSteps.length - 1));
            }
        }, [step, visibleSteps]);

        useEffect(function () {
            if (!allowDate && dayPlanner.open) {
                setDayPlanner({ open: false, dayIso: '', allDay: false });
            }
        }, [allowDate, dayPlanner.open]);

        function isStepValid(stepKey) {
            if (stepKey === 'essential') {
                return form.requestType !== '' && !!String(form.title || '').trim();
            }
            if (stepKey === 'location') {
                if (!allowLocation) {
                    return true;
                }
                return form.isOutdoor || Number(form.roomId) > 0;
            }
            if (stepKey === 'date') {
                if (!allowDate) {
                    return true;
                }
                if (allowMultipleDates) {
                    return (form.slots || []).length > 0;
                }
                return !!form.slotStart && !!form.slotEnd;
            }
            return true;
        }

        function canGoNext() {
            return isStepValid(currentStep.key);
        }

        function canNavigateToStep(targetIndex) {
            if (targetIndex <= step) {
                return true;
            }

            for (var i = 0; i < targetIndex; i += 1) {
                var targetStep = visibleSteps[i];
                if (!targetStep || !isStepValid(targetStep.key)) {
                    return false;
                }
            }

            return true;
        }

        function submitRequest() {
            setSaving(true);
            setNotice('');

            var payload = {
                request_type: form.requestType,
                room_id: form.roomId,
                is_outdoor: form.isOutdoor ? '1' : '0',
                room_options_json: JSON.stringify(form.roomOptions || []),
                materials_json: JSON.stringify(form.materials || []),
                week_start: weekStart,
                slot_day: form.slotDay,
                slot_start: form.slotStart,
                slot_end: form.slotEnd,
                slots_json: JSON.stringify(allowMultipleDates ? (form.slots || []) : []),
                title: form.title,
                description: form.description,
                age_range: form.ageRange,
                assigned_to_member_id: (form.assignedMemberIds && form.assignedMemberIds[0]) || 0,
                assigned_member_ids_json: JSON.stringify(form.assignedMemberIds || []),
            };

            return post('mj_request_management_create', payload, files)
                .then(function (json) {
                    if (!json || !json.success) {
                        throw new Error((json && json.data && json.data.message) || 'Erreur');
                    }
                    var data = json.data || {};
                    setMine(data.mine || []);
                    setNotice('Demande créée.');
                    setStep(0);
                    setFiles([]);
                    setForm({
                        requestType: '',
                        roomId: 0,
                        isOutdoor: false,
                        roomOptions: [],
                        materials: [],
                        slotDay: 0,
                        slotStart: '14:00',
                        slotEnd: '16:00',
                        slots: [],
                        title: '',
                        description: '',
                        ageRange: '12-15',
                        assignedMemberIds: [],
                    });
                    if (!isStaff) {
                        return Promise.resolve();
                    }
                    return post('mj_request_management_list_staff', { status: staffStatusFilter })
                        .then(function (staffJson) {
                            if (staffJson && staffJson.success) {
                                setStaff((staffJson.data && staffJson.data.staff) || []);
                            }
                        });
                })
                .catch(function (err) {
                    setNotice(err.message || 'Erreur lors de la création.');
                })
                .finally(function () {
                    setSaving(false);
                });
        }

        function refreshMine() {
            return post('mj_request_management_list_mine', {})
                .then(function (json) {
                    if (json && json.success) {
                        setMine((json.data && json.data.mine) || []);
                    }
                });
        }

        function refreshStaff() {
            if (!isStaff) {
                return Promise.resolve();
            }
            return post('mj_request_management_list_staff', { status: staffStatusFilter })
                .then(function (json) {
                    if (json && json.success) {
                        setStaff((json.data && json.data.staff) || []);
                    }
                });
        }

        function changeStatus(requestId, nextStatus) {
            if (!isStaff) {
                return;
            }

            setSaving(true);
            post('mj_request_management_change_status', {
                request_id: requestId,
                status: nextStatus,
                status_note: statusNoteDraft,
            }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || 'Erreur');
                }
                setNotice('Statut mis à jour.');
                setStatusNoteDraft('');
                return Promise.all([refreshMine(), refreshStaff()]);
            }).catch(function (err) {
                setNotice(err.message || 'Erreur de mise à jour du statut.');
            }).finally(function () {
                setSaving(false);
            });
        }

        function addNote(requestId) {
            if (!isStaff || !statusNoteDraft.trim()) {
                return;
            }

            setSaving(true);
            post('mj_request_management_add_note', {
                request_id: requestId,
                content: statusNoteDraft,
            }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || 'Erreur');
                }
                setStatusNoteDraft('');
                setNotice('Note ajoutée.');
                return Promise.all([refreshMine(), refreshStaff()]);
            }).catch(function (err) {
                setNotice(err.message || 'Erreur lors de l\'ajout de la note.');
            }).finally(function () {
                setSaving(false);
            });
        }

        function onTimelinePointerDown(dayIndex, evt) {
            var rect = evt.currentTarget.getBoundingClientRect();
            var minute = minuteFromPointer(evt, rect);
            setDragState({ day: dayIndex, start: minute, end: minute, rect: rect });
            patchForm('slotDay', dayIndex);
        }

        function onTimelinePointerMove(dayIndex, evt) {
            if (!dragState || dragState.day !== dayIndex) {
                return;
            }
            var minute = minuteFromPointer(evt, dragState.rect);
            setDragState({ day: dragState.day, start: dragState.start, end: minute, rect: dragState.rect });
        }

        function onTimelinePointerUp(dayIndex) {
            if (!dragState || dragState.day !== dayIndex) {
                return;
            }
            var start = Math.min(dragState.start, dragState.end);
            var end = Math.max(dragState.start, dragState.end);
            if (start === end) {
                end = Math.min(END_MINUTE, start + 60);
            }
            start = Math.max(START_MINUTE, Math.min(END_MINUTE - STEP_MINUTES, start));
            end = Math.max(start + STEP_MINUTES, Math.min(END_MINUTE, end));
            patchForm('slotStart', fmtMinute(start));
            patchForm('slotEnd', fmtMinute(end));
            patchForm('slotDay', dayIndex);
            setDragState(null);
        }

        function openPlannerForIso(dayIso) {
            var dayDate = parseIsoDate(dayIso);
            if (!dayDate) {
                return;
            }

            var weekStartDate = mondayOf(dayDate);
            var dayIndex = Math.max(0, Math.min(6, Math.round((dayDate.getTime() - weekStartDate.getTime()) / 86400000)));
            setWeekStart(isoDate(weekStartDate));
            patchForm('slotDay', dayIndex);

            var existingSlot = allowMultipleDates
                ? (form.slots || []).find(function (slot) { return slot.date === dayIso; })
                : null;
            var slotStart = existingSlot ? existingSlot.start : form.slotStart;
            var slotEnd = existingSlot ? existingSlot.end : form.slotEnd;
            patchForm('slotStart', slotStart);
            patchForm('slotEnd', slotEnd);

            var startMinutes = parseMinuteText(slotStart);
            var endMinutes = parseMinuteText(slotEnd);
            var isAllDay = startMinutes === START_MINUTE && endMinutes === END_MINUTE;

            setDayPlanner({
                open: true,
                dayIso: dayIso,
                allDay: isAllDay,
            });
        }

        function closePlanner() {
            if (allowMultipleDates && dayPlanner.dayIso) {
                upsertSlot(dayPlanner.dayIso, form.slotStart, form.slotEnd);
            }

            setDayPlanner({
                open: false,
                dayIso: '',
                allDay: false,
            });
        }

        function upsertSlot(dayIso, start, end) {
            setForm(function (prev) {
                var list = Array.isArray(prev.slots) ? prev.slots.slice() : [];
                var idx = list.findIndex(function (slot) { return slot.date === dayIso; });
                var entry = { date: dayIso, start: start, end: end };
                if (idx >= 0) {
                    list[idx] = entry;
                } else {
                    list.push(entry);
                }
                list.sort(function (a, b) { return a.date.localeCompare(b.date); });
                var copy = Object.assign({}, prev);
                copy.slots = list;
                return copy;
            });
        }

        function removeSlot(dayIso) {
            setForm(function (prev) {
                var list = Array.isArray(prev.slots) ? prev.slots.slice() : [];
                var copy = Object.assign({}, prev);
                copy.slots = list.filter(function (slot) { return slot.date !== dayIso; });
                return copy;
            });
        }

        function patchRangeDraft(key, value) {
            setRangeDraft(function (prev) {
                var copy = Object.assign({}, prev);
                copy[key] = value;
                return copy;
            });
        }

        function addDateRange() {
            var startDate = parseIsoDate(rangeDraft.start);
            var endDate = parseIsoDate(rangeDraft.end);
            if (!startDate || !endDate || endDate.getTime() < startDate.getTime()) {
                return;
            }

            var start = rangeDraft.startTime || '14:00';
            var end = rangeDraft.endTime || '16:00';
            var entries = [];
            var cursor = new Date(startDate.getTime());
            while (cursor.getTime() <= endDate.getTime()) {
                entries.push({ date: isoDate(cursor), start: start, end: end });
                cursor.setDate(cursor.getDate() + 1);
            }

            if (!entries.length) {
                return;
            }

            setForm(function (prev) {
                var list = Array.isArray(prev.slots) ? prev.slots.slice() : [];
                entries.forEach(function (entry) {
                    var idx = list.findIndex(function (slot) { return slot.date === entry.date; });
                    if (idx >= 0) {
                        list[idx] = entry;
                    } else {
                        list.push(entry);
                    }
                });
                list.sort(function (a, b) { return a.date.localeCompare(b.date); });
                var copy = Object.assign({}, prev);
                copy.slots = list;
                return copy;
            });

            setRangeDraft({ start: '', end: '', startTime: start, endTime: end });
        }

        function toggleAllDay(enabled) {
            setDayPlanner(function (prev) {
                return {
                    open: prev.open,
                    dayIso: prev.dayIso,
                    allDay: enabled,
                };
            });

            if (enabled) {
                patchForm('slotStart', fmtMinute(START_MINUTE));
                patchForm('slotEnd', fmtMinute(END_MINUTE));
            } else {
                patchForm('slotStart', '14:00');
                patchForm('slotEnd', '16:00');
            }
        }

        function updateTimeRange(startText, endText) {
            var start = parseMinuteText(startText);
            var end = parseMinuteText(endText);
            if (start === null || end === null) {
                return;
            }

            start = Math.max(START_MINUTE, Math.min(END_MINUTE - STEP_MINUTES, start));
            end = Math.max(start + STEP_MINUTES, Math.min(END_MINUTE, end));

            patchForm('slotStart', fmtMinute(start));
            patchForm('slotEnd', fmtMinute(end));
        }

        function timelineSelectionStyle(dayIndex) {
            var selected = dayIndex === form.slotDay;
            if (!selected) {
                return null;
            }
            var start = Number(form.slotStart.split(':')[0]) * 60 + Number(form.slotStart.split(':')[1]);
            var end = Number(form.slotEnd.split(':')[0]) * 60 + Number(form.slotEnd.split(':')[1]);
            var top = ((start - START_MINUTE) / (END_MINUTE - START_MINUTE)) * 100;
            var height = Math.max(4, ((end - start) / (END_MINUTE - START_MINUTE)) * 100);
            return { top: top + '%', height: height + '%' };
        }

        var timelineHourMarks = [];
        for (var hour = 8; hour <= 21; hour += 1) {
            timelineHourMarks.push(hour);
        }

        var selectedDayLabel = days[form.slotDay] ? days[form.slotDay].short : '';

        var staffList = staff;
        if (staffStatusFilter) {
            staffList = staffList.filter(function (req) { return req.status === staffStatusFilter; });
        }

        return h('div', { class: 'mj-request-management__shell' }, [
            h('nav', { class: 'mj-request-management__main-tabs', key: 'main-tabs' }, mainTabs.map(function (tab) {
                var cls = 'mj-request-management__main-tab';
                if (tab.key === 'compose') {
                    cls += ' is-compose';
                }
                if (mainTab === tab.key) {
                    cls += ' is-active';
                }
                return h('button', {
                    type: 'button',
                    class: cls,
                    onClick: function () { setMainTab(tab.key); },
                }, tab.label);
            })),

            mainTab === 'compose' && h('div', { class: 'mj-request-management__grid mj-request-management__tab-panel', key: 'compose-grid' }, [
                h('div', { class: 'mj-request-management__wizard' }, [
                    currentStep.key !== 'essential' && (form.title || selectedType) && h('div', { class: 'mj-request-management__summary-bar', key: 'summary-bar' }, [
                        selectedType && h('span', { class: 'mj-request-management__summary-type' }, withEmoji(selectedType.emoji, selectedType.label || '')),
                        form.title && h('strong', { class: 'mj-request-management__summary-title' }, form.title),
                    ]),

                    currentStep.key === 'essential' && h('div', { class: 'mj-request-management__step-panel mj-request-management__content-enter', key: 'step-1' }, [
                        h('label', null, [
                            h('span', null, 'TITRE DE LA DEMANDE *'),
                            h('input', {
                                type: 'text',
                                value: form.title,
                                placeholder: 'Nom de votre demande...',
                                onInput: function (evt) { patchForm('title', evt.target.value || ''); },
                            }),
                        ]),
                        h('div', { class: 'mj-request-management__type-grid' }, requestTypes.map(function (type, index) {
                            var active = form.requestType === type.key;
                            return h('button', {
                                type: 'button',
                                class: 'mj-request-management__type-btn' + (active ? ' is-active' : ''),
                                onClick: function () { patchForm('requestType', type.key); },
                            }, [
                                h('span', { class: 'mj-request-management__type-emoji type-tone-' + (index % 8) }, type.emoji || '🧩'),
                                h('span', { class: 'mj-request-management__type-label' }, type.label || ''),
                            ]);
                        })),
                        selectedType && renderRichDescription('mj-request-management__type-desc', selectedType.descriptionHtml || selectedType.description || ''),
                    ]),

                    currentStep.key === 'location' && h('div', { class: 'mj-request-management__step-panel mj-request-management__content-enter', key: 'step-2' }, [
                        h('h2', null, 'Lieu & matériel'),
                        allowLocation && h('label', { class: 'mj-request-management__check' }, [
                            h('input', {
                                type: 'checkbox',
                                checked: form.isOutdoor,
                                onChange: function (evt) { patchForm('isOutdoor', !!evt.target.checked); },
                            }),
                            h('span', null, 'Activité extérieure'),
                        ]),
                        allowLocation && !form.isOutdoor && h('div', { class: 'mj-request-management__rooms' }, rooms.map(function (room) {
                            var active = Number(form.roomId) === Number(room.id);
                            return h('button', {
                                type: 'button',
                                class: 'mj-request-management__room-card' + (active ? ' is-active' : ''),
                                onClick: function () { patchForm('roomId', Number(room.id)); },
                            }, [
                                h('div', { class: 'mj-request-management__room-card-head' }, [
                                    h('strong', { class: 'mj-request-management__room-name' }, withEmoji(room.emoji, room.name)),
                                    h('span', { class: 'mj-request-management__room-capacity' }, (room.capacity || 0) + ' pers. max'),
                                ]),
                                room.description ? renderRichDescription('mj-request-management__room-desc', room.descriptionHtml || room.description) : null,
                            ]);
                        })),
                        allowLocation && selectedRoom && h('div', { class: 'mj-request-management__room-options' }, [
                            (function () {
                                var roomOptionEntries = normalizeCatalogEntries(selectedRoom.optionsDetailed, selectedRoom.options || []);
                                if (!roomOptionEntries.length) {
                                    return null;
                                }
                                return h('div', { class: 'mj-request-management__choices-group' }, [
                                    h('p', null, 'Options de salle'),
                                    h('div', { class: 'mj-request-management__choices-grid' }, roomOptionEntries.map(function (entry) {
                                        var value = entry.title;
                                        var checked = (form.roomOptions || []).indexOf(value) >= 0;
                                        return h('label', { class: 'mj-request-management__check mj-request-management__check--compact' }, [
                                            h('input', {
                                                type: 'checkbox',
                                                checked: checked,
                                                onChange: function () { toggleStringItem('roomOptions', value); },
                                            }),
                                            h('span', { class: 'mj-request-management__check-text' }, withEmoji(entry.emoji, value)),
                                        ]);
                                    })),
                                ]);
                            })(),
                            (function () {
                                if (!allowMaterials) {
                                    return null;
                                }
                                var roomMaterialEntries = normalizeCatalogEntries(selectedRoom.materialsDetailed, selectedRoom.materials || []);
                                if (!roomMaterialEntries.length) {
                                    return null;
                                }
                                return h('div', { class: 'mj-request-management__choices-group' }, [
                                    h('p', null, 'Matériel'),
                                    h('div', { class: 'mj-request-management__choices-grid' }, roomMaterialEntries.map(function (entry) {
                                        var value = entry.title;
                                        var checked = (form.materials || []).indexOf(value) >= 0;
                                        return h('label', { class: 'mj-request-management__check mj-request-management__check--compact' }, [
                                            h('input', {
                                                type: 'checkbox',
                                                checked: checked,
                                                onChange: function () { toggleStringItem('materials', value); },
                                            }),
                                            h('span', { class: 'mj-request-management__check-text' }, withEmoji(entry.emoji, value)),
                                        ]);
                                    })),
                                ]);
                            })(),
                        ]),
                        (allowMaterials && !allowLocation) && h('div', { class: 'mj-request-management__room-options' }, [
                            h('p', null, 'Matériel'),
                            h('div', { class: 'mj-request-management__choices-grid' }, materialOptionsCatalog.map(function (entry) {
                                var value = entry.title;
                                var checked = (form.materials || []).indexOf(value) >= 0;
                                return h('label', { class: 'mj-request-management__check mj-request-management__check--compact' }, [
                                    h('input', {
                                        type: 'checkbox',
                                        checked: checked,
                                        onChange: function () { toggleStringItem('materials', value); },
                                    }),
                                    h('span', { class: 'mj-request-management__check-text' }, withEmoji(entry.emoji, value)),
                                ]);
                            })),
                        ]),
                    ]),

                    currentStep.key === 'date' && h('div', { class: 'mj-request-management__step-panel mj-request-management__content-enter', key: 'step-3' }, [
                        h('h2', null, 'Plage horaire'),

                        allowDate ? h('div', { class: 'mj-regmgr-occurrence' }, [
                            h('div', { class: 'mj-regmgr-occurrence__header' }, [
                                h('div', { class: 'mj-regmgr-occurrence__header-main' }, [
                                    h('div', { class: 'mj-regmgr-occurrence__heading' }, [
                                        h('h2', null, 'Dates de la demande'),
                                        h('span', { class: 'mj-regmgr-occurrence__subheading' }, calendarMonthLabel),
                                    ]),
                                    h('div', { class: 'mj-regmgr-occurrence__header-controls' }, [
                                        h('div', { class: 'mj-regmgr-occurrence__nav' }, [
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-regmgr-occurrence__nav-button',
                                                'aria-label': 'Mois précédent',
                                                onClick: function () { setCalendarMonth(addMonths(calendarMonth, -1)); },
                                            }, [h('span', { class: 'mj-regmgr-occurrence__nav-icon', 'aria-hidden': true }, '‹')]),
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-regmgr-occurrence__nav-button',
                                                'aria-label': 'Mois suivant',
                                                onClick: function () { setCalendarMonth(addMonths(calendarMonth, 1)); },
                                            }, [h('span', { class: 'mj-regmgr-occurrence__nav-icon', 'aria-hidden': true }, '›')]),
                                        ]),
                                    ]),
                                ]),
                            ]),

                            h('div', { class: 'mj-regmgr-occurrence__body' }, [
                                h('div', { class: 'mj-regmgr-occurrence__calendar' }, [
                                    h('div', { class: 'mj-regmgr-occurrence__months' }, [
                                        h('div', { class: 'mj-regmgr-occurrence__month' }, [
                                            h('div', { class: 'mj-regmgr-occurrence__month-header' }, calendarMonthLabel),
                                            h('div', { class: 'mj-regmgr-occurrence__weekdays' }, ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map(function (dayKey, dayKeyIndex) {
                                                return h('div', { key: 'weekday-' + dayKeyIndex, class: 'mj-regmgr-occurrence__weekday' }, dayKey);
                                            })),
                                            (function () {
                                                var weeks = [];
                                                for (var i = 0; i < calendarDays.length; i += 7) {
                                                    weeks.push(calendarDays.slice(i, i + 7));
                                                }
                                                return weeks.map(function (week, weekIndex) {
                                                    return h('div', { key: 'week-' + weekIndex, class: 'mj-regmgr-occurrence__week' }, week.map(function (day) {
                                                        var hasOccurrence = allowMultipleDates ? !!slotDatesIndex[day.iso] : day.isSelected;
                                                        var dayCls = 'mj-regmgr-occurrence__day';
                                                        if (day.outside) {
                                                            dayCls += ' mj-regmgr-occurrence__day--muted';
                                                        }
                                                        if (day.isToday) {
                                                            dayCls += ' mj-regmgr-occurrence__day--today';
                                                        }
                                                        if (hasOccurrence) {
                                                            dayCls += ' mj-regmgr-occurrence__day--selected mj-regmgr-occurrence__day--with-occurrence';
                                                        }

                                                        return h('button', {
                                                            key: day.iso,
                                                            type: 'button',
                                                            class: dayCls,
                                                            onClick: function () { openPlannerForIso(day.iso); },
                                                        }, [
                                                            h('span', { class: 'mj-regmgr-occurrence__day-number' }, String(day.dayNumber)),
                                                        ]);
                                                    }));
                                                });
                                            })(),
                                        ]),
                                    ]),
                                ]),
                            ]),
                        ]) : h('p', { class: 'mj-request-management__type-desc' }, 'Ce type de demande ne nécessite pas de créneau horaire.'),

                        allowDate && !allowMultipleDates && h('div', { class: 'mj-request-management__range-meta' }, [
                            h('strong', null, 'Plage sélectionnée'),
                            h('span', null, selectedSlotLabel + ' · ' + form.slotStart + ' → ' + form.slotEnd),
                        ]),

                        allowDate && allowMultipleDates && h('div', { class: 'mj-request-management__range-picker' }, [
                            h('p', null, 'Ajouter une plage de dates'),
                            h('div', { class: 'mj-request-management__range-picker-grid' }, [
                                h('label', null, [
                                    h('span', null, 'Du'),
                                    h('input', {
                                        type: 'date',
                                        value: rangeDraft.start,
                                        onChange: function (evt) { patchRangeDraft('start', evt.target.value || ''); },
                                    }),
                                ]),
                                h('label', null, [
                                    h('span', null, 'Au'),
                                    h('input', {
                                        type: 'date',
                                        value: rangeDraft.end,
                                        onChange: function (evt) { patchRangeDraft('end', evt.target.value || ''); },
                                    }),
                                ]),
                                h('label', null, [
                                    h('span', null, 'Début'),
                                    h('input', {
                                        type: 'time',
                                        step: '900',
                                        value: rangeDraft.startTime,
                                        onChange: function (evt) { patchRangeDraft('startTime', evt.target.value || rangeDraft.startTime); },
                                    }),
                                ]),
                                h('label', null, [
                                    h('span', null, 'Fin'),
                                    h('input', {
                                        type: 'time',
                                        step: '900',
                                        value: rangeDraft.endTime,
                                        onChange: function (evt) { patchRangeDraft('endTime', evt.target.value || rangeDraft.endTime); },
                                    }),
                                ]),
                            ]),
                            h('button', {
                                type: 'button',
                                class: 'mj-request-management__range-picker-add',
                                disabled: !rangeDraft.start || !rangeDraft.end,
                                onClick: addDateRange,
                            }, 'Ajouter la plage'),
                        ]),

                        allowDate && allowMultipleDates && h('div', { class: 'mj-request-management__slots-list' }, [
                            h('p', null, 'Dates ajoutées (' + (form.slots || []).length + ')'),
                            (form.slots || []).length === 0 && h('p', { class: 'mj-request-management__type-desc' }, 'Cliquez sur une date du calendrier pour l\'ajouter.'),
                            (form.slots || []).map(function (slot) {
                                var slotDate = parseIsoDate(slot.date);
                                var label = slotDate
                                    ? slotDate.toLocaleDateString('fr-BE', { weekday: 'short', day: '2-digit', month: 'short' })
                                    : slot.date;
                                return h('div', { class: 'mj-request-management__slot-row', key: 'slot-' + slot.date }, [
                                    h('span', { class: 'mj-request-management__slot-date' }, label),
                                    h('span', { class: 'mj-request-management__slot-time' }, slot.start + ' → ' + slot.end),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-request-management__slot-edit',
                                        onClick: function () { openPlannerForIso(slot.date); },
                                    }, '✎'),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-request-management__slot-remove',
                                        onClick: function () { removeSlot(slot.date); },
                                    }, '✕'),
                                ]);
                            }),
                        ]),

                        allowDate && dayPlanner.open && h('div', { class: 'mj-regmgr-modal' }, [
                            h('div', {
                                class: 'mj-regmgr-modal__overlay',
                                onClick: closePlanner,
                            }),
                            h('div', { class: 'mj-regmgr-modal__container mj-regmgr-modal__container--small' }, [
                                h('div', { class: 'mj-regmgr-modal__header' }, [
                                    h('h2', { class: 'mj-regmgr-modal__title' }, allowMultipleDates ? 'Ajoute une occurrence' : 'Encoder la demande'),
                                    h('button', { type: 'button', class: 'mj-regmgr-modal__close', onClick: closePlanner }, '✕'),
                                ]),
                                h('div', { class: 'mj-regmgr-modal__body' }, [
                                    h('div', { class: 'mj-regmgr-occurrence__card' }, [
                                        h('p', { class: 'mj-regmgr-occurrence__subheading' }, selectedSlotLabel),
                                        h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label mj-request-management__check' }, [
                                                h('input', {
                                                    type: 'checkbox',
                                                    checked: !!dayPlanner.allDay,
                                                    onChange: function (evt) { toggleAllDay(!!evt.target.checked); },
                                                }),
                                                h('span', null, 'Toute la journée'),
                                            ]),
                                        ]),
                                        !dayPlanner.allDay && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, 'Début'),
                                            h('input', {
                                                type: 'time',
                                                class: 'mj-regmgr-occurrence__input',
                                                step: '900',
                                                value: form.slotStart,
                                                onChange: function (evt) { updateTimeRange(evt.target.value || form.slotStart, form.slotEnd); },
                                            }),
                                        ]),
                                        !dayPlanner.allDay && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                                            h('label', { class: 'mj-regmgr-occurrence__label' }, 'Fin'),
                                            h('input', {
                                                type: 'time',
                                                class: 'mj-regmgr-occurrence__input',
                                                step: '900',
                                                value: form.slotEnd,
                                                onChange: function (evt) { updateTimeRange(form.slotStart, evt.target.value || form.slotEnd); },
                                            }),
                                        ]),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-modal__footer' }, [
                                    h('button', { type: 'button', class: 'mj-btn mj-btn--primary', onClick: closePlanner }, allowMultipleDates ? 'Ajouter cette date' : 'Valider'),
                                ]),
                            ]),
                        ]),
                    ]),

                    currentStep.key === 'details' && h('div', { class: 'mj-request-management__step-panel mj-request-management__content-enter', key: 'step-4' }, [
                        h('h2', null, 'Détails complémentaires'),
                        h('label', null, [
                            h('span', null, 'Description'),
                            h('textarea', {
                                value: form.description,
                                onInput: function (evt) { patchForm('description', evt.target.value || ''); },
                            }),
                        ]),
                        h('label', null, [
                            h('span', null, 'Tranche d\'âge'),
                            h('select', {
                                value: form.ageRange,
                                onChange: function (evt) { patchForm('ageRange', evt.target.value || '12-15'); },
                            }, [
                                h('option', { value: '8-11' }, '8-11 ans'),
                                h('option', { value: '12-15' }, '12-15 ans'),
                                h('option', { value: '16-18' }, '16-18 ans'),
                                h('option', { value: '18+' }, '18+'),
                            ]),
                        ]),
                        h('label', null, [
                            h('span', null, 'Animateur référent (optionnel)'),
                            animateurs.length === 0
                                ? h('p', { class: 'mj-request-management__type-desc' }, 'Aucun animateur disponible.')
                                : h('div', { class: 'mj-request-management__choices-grid' }, animateurs.map(function (a) {
                                    var checked = (form.assignedMemberIds || []).indexOf(a.id) >= 0;
                                    return h('label', { class: 'mj-request-management__check mj-request-management__check--compact' }, [
                                        h('input', {
                                            type: 'checkbox',
                                            checked: checked,
                                            onChange: function () { toggleStringItem('assignedMemberIds', a.id); },
                                        }),
                                        h('span', { class: 'mj-request-management__check-text' }, a.name + (a.role ? ' (' + a.role + ')' : '')),
                                    ]);
                                })),
                        ]),
                        h('label', null, [
                            h('span', null, 'Images'),
                            h('input', {
                                type: 'file',
                                multiple: true,
                                accept: 'image/*',
                                onChange: function (evt) {
                                    var selected = Array.prototype.slice.call((evt.target && evt.target.files) || []);
                                    setFiles(selected);
                                },
                            }),
                        ]),
                    ]),

                    h('div', { class: 'mj-request-management__actions', key: 'actions' }, [
                        h('button', {
                            type: 'button',
                            class: 'mj-request-management__nav-btn is-prev',
                            disabled: step <= 0,
                            onClick: function () { setStep(Math.max(0, step - 1)); },
                        }, '←'),

                        h('div', { class: 'mj-request-management__step-tabs mj-request-management__step-tabs--inline', key: 'tabs' }, visibleSteps.map(function (entry, index) {
                            var cls = 'mj-request-management__step-tab';
                            if (step === index) {
                                cls += ' is-active';
                            }
                            return h('button', {
                                type: 'button',
                                class: cls,
                                title: entry.label,
                                'aria-label': entry.label,
                                'data-step-title': entry.label,
                                onClick: function () {
                                    if (!canNavigateToStep(index)) {
                                        return;
                                    }
                                    setStep(index);
                                },
                            }, (index + 1) + '. ' + entry.label);
                        })),

                        !isLastStep && h('button', {
                            type: 'button',
                            class: 'mj-request-management__nav-btn is-next',
                            disabled: !canGoNext(),
                            onClick: function () { setStep(Math.min(visibleSteps.length - 1, step + 1)); },
                        }, '→'),
                        isLastStep && h('button', {
                            type: 'button',
                            class: 'mj-request-management__nav-btn is-next',
                            disabled: saving || !form.title || !form.requestType,
                            onClick: submitRequest,
                        }, '→'),
                    ]),
                ]),
            ]),

            mainTab === 'mine' && h('section', { class: 'mj-request-management__list-card mj-request-management__tab-panel', key: 'mine-panel' }, [
                mine.length === 0 && h('p', null, 'Aucune demande.'),
                mine.map(function (req) {
                    return h('article', { class: 'mj-request-management__request-item', key: 'mine-' + req.id }, [
                        h('header', null, [
                            h('strong', null, req.title || 'Sans titre'),
                            h('span', { class: 'status is-' + req.status }, req.statusLabel || req.status),
                        ]),
                        h('p', null, req.requestType + ' - ' + (req.weekStart || '') + ' - ' + (req.slotStart || '') + ' / ' + (req.slotEnd || '')),
                        req.statusNote ? h('p', { class: 'mj-request-management__status-note' }, req.statusNote) : null,
                        (req.notes || []).map(function (note) {
                            return h('p', { class: 'mj-request-management__note', key: 'note-' + note.id }, note.authorName + ': ' + note.content);
                        }),
                    ]);
                }),
            ]),

            isStaff && mainTab === 'staff' && h('section', { class: 'mj-request-management__list-card mj-request-management__tab-panel', key: 'staff-panel' }, [
                h('h2', null, cfg.i18n && cfg.i18n.staff ? cfg.i18n.staff : 'Traitement animateurs'),
                h('div', { class: 'mj-request-management__staff-tools' }, [
                    h('select', {
                        value: staffStatusFilter,
                        onChange: function (evt) {
                            setStaffStatusFilter(evt.target.value || '');
                            refreshStaff();
                        },
                    }, [
                        h('option', { value: '' }, 'Tous statuts'),
                    ].concat(Object.keys(statusLabels).map(function (k) {
                        return h('option', { value: k }, statusLabels[k]);
                    }))),
                ]),
                staffList.length === 0 && h('p', null, 'Aucune demande.'),
                staffList.map(function (req) {
                    var selected = selectedRequestId === req.id;
                    return h('article', {
                        class: 'mj-request-management__request-item is-staff' + (selected ? ' is-selected' : ''),
                        key: 'staff-' + req.id,
                        onClick: function () { setSelectedRequestId(req.id); },
                    }, [
                        h('header', null, [
                            h('strong', null, req.title || 'Sans titre'),
                            h('span', { class: 'status is-' + req.status }, req.statusLabel || req.status),
                        ]),
                        h('p', null, (req.memberName || '') + ' - ' + (req.requestType || '')),
                        selected && h('div', { class: 'mj-request-management__staff-actions' }, [
                            h('textarea', {
                                placeholder: 'Note animateur/gestionnaire',
                                value: statusNoteDraft,
                                onInput: function (evt) { setStatusNoteDraft(evt.target.value || ''); },
                            }),
                            h('div', { class: 'mj-request-management__status-actions' }, [
                                h('button', { type: 'button', onClick: function () { changeStatus(req.id, 'approved'); }, disabled: saving }, 'Approuver'),
                                h('button', { type: 'button', onClick: function () { changeStatus(req.id, 'rejected'); }, disabled: saving }, 'Refuser'),
                                h('button', { type: 'button', onClick: function () { changeStatus(req.id, 'cancelled'); }, disabled: saving }, 'Annuler'),
                                h('button', { type: 'button', onClick: function () { changeStatus(req.id, 'pending'); }, disabled: saving }, 'Remettre en attente'),
                                h('button', { type: 'button', onClick: function () { addNote(req.id); }, disabled: saving || !statusNoteDraft.trim() }, 'Ajouter note'),
                            ]),
                        ]),
                    ]);
                }),
            ]),

            notice ? h('p', { class: 'mj-request-management__notice' }, notice) : null,
        ]);
    }

    function mount() {
        var nodes = document.querySelectorAll('[data-mj-request-management-app]');
        if (!nodes.length) {
            return;
        }

        nodes.forEach(function (root) {
            render(h(RequestManagementApp), root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
