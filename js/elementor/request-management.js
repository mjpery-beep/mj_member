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
        var [dragState, setDragState] = useState(null);

        var [form, setForm] = useState({
            requestType: '',
            roomId: 0,
            isOutdoor: false,
            roomOptions: [],
            materials: [],
            slotDay: 0,
            slotStart: '14:00',
            slotEnd: '16:00',
            title: '',
            description: '',
            ageRange: '12-15',
            assignedToMemberId: 0,
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
                (room.materials || []).forEach(function (item) {
                    if (!item || seen[item]) {
                        return;
                    }
                    seen[item] = true;
                    out.push(item);
                });
            });
            return out;
        }, [rooms]);

        function typeOption(name, fallbackValue) {
            if (!selectedType || !selectedType.options || selectedType.options[name] === undefined) {
                return fallbackValue;
            }
            return !!selectedType.options[name];
        }

        var allowLocation = typeOption('allowsLocation', true);
        var allowMaterials = typeOption('allowsMaterials', true);
        var allowDate = typeOption('allowsDate', true);
        var requiresAnimateur = typeOption('requiresAnimateur', false);

        useEffect(function () {
            var mountNode = document.querySelector('[data-mj-request-management-app]');
            if (!mountNode) {
                return undefined;
            }

            var container = mountNode.closest('.mj-request-management');
            if (!container) {
                return undefined;
            }

            var titleNode = container.querySelector('.mj-request-management__title');
            var baseTitle = titleNode && titleNode.getAttribute('data-base-title')
                ? titleNode.getAttribute('data-base-title')
                : (titleNode ? titleNode.textContent : 'Nouvelle Demande');
            var accent = selectedTypeColor || '#1F6FEB';
            container.style.setProperty('--rm-accent', accent);
            container.style.setProperty('--rm-accent-soft', accent + '1A');
            container.style.setProperty('--rm-accent-strong', accent);

            if (titleNode) {
                var nextTitle = baseTitle;
                if (selectedType) {
                    nextTitle = baseTitle + ' · ' + withEmoji(selectedType.emoji, selectedType.label);
                }
                titleNode.textContent = nextTitle;
            }

            return function () {
                if (titleNode) {
                    titleNode.textContent = baseTitle || 'Nouvelle Demande';
                }
                container.style.removeProperty('--rm-accent');
                container.style.removeProperty('--rm-accent-soft');
                container.style.removeProperty('--rm-accent-strong');
            };
        }, [selectedType, selectedTypeColor]);

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

        function canGoNext() {
            if (currentStep.key === 'essential') {
                return form.requestType !== '';
            }
            if (currentStep.key === 'location') {
                if (!allowLocation) {
                    return true;
                }
                return form.isOutdoor || Number(form.roomId) > 0;
            }
            if (currentStep.key === 'date') {
                if (!allowDate) {
                    return true;
                }
                return !!form.slotStart && !!form.slotEnd;
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
                title: form.title,
                description: form.description,
                age_range: form.ageRange,
                assigned_to_member_id: form.assignedToMemberId || 0,
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
                        title: '',
                        description: '',
                        ageRange: '12-15',
                        assignedToMemberId: 0,
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
            patchForm('slotStart', fmtMinute(start));
            patchForm('slotEnd', fmtMinute(end));
            patchForm('slotDay', dayIndex);
            setDragState(null);
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

        var staffList = staff;
        if (staffStatusFilter) {
            staffList = staffList.filter(function (req) { return req.status === staffStatusFilter; });
        }

        return h('div', { class: 'mj-request-management__grid' }, [
            h('div', { class: 'mj-request-management__wizard', key: 'wizard' }, [
                h('div', { class: 'mj-request-management__step-tabs', key: 'tabs' }, visibleSteps.map(function (entry, index) {
                    var cls = 'mj-request-management__step-tab';
                    if (step === index) {
                        cls += ' is-active';
                    }
                    return h('button', {
                        type: 'button',
                        class: cls,
                        onClick: function () { setStep(index); },
                    }, (index + 1) + '. ' + entry.label);
                })),

                currentStep.key === 'essential' && h('div', { class: 'mj-request-management__step-panel', key: 'step-1' }, [
                    h('h2', null, 'Type de demande'),
                    h('div', { class: 'mj-request-management__type-grid' }, requestTypes.map(function (type) {
                        var active = form.requestType === type.key;
                        return h('button', {
                            type: 'button',
                            class: 'mj-request-management__type-btn' + (active ? ' is-active' : ''),
                            onClick: function () { patchForm('requestType', type.key); },
                        }, withEmoji(type.emoji, type.label));
                    })),
                    selectedType && renderRichDescription('mj-request-management__type-desc', selectedType.descriptionHtml || selectedType.description || ''),
                ]),

                currentStep.key === 'location' && h('div', { class: 'mj-request-management__step-panel', key: 'step-2' }, [
                    h('h2', null, 'Lieu'),
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
                            h('strong', null, withEmoji(room.emoji, room.name)),
                            h('span', null, 'Capacité max: ' + (room.capacity || 0) + ' pers.'),
                            room.description ? renderRichDescription('mj-request-management__room-desc', room.descriptionHtml || room.description) : null,
                        ]);
                    })),
                    allowLocation && selectedRoom && h('div', { class: 'mj-request-management__room-options' }, [
                        h('p', null, 'Options de salle'),
                        (selectedRoom.options || []).map(function (opt) {
                            var checked = (form.roomOptions || []).indexOf(opt) >= 0;
                            return h('label', { class: 'mj-request-management__check' }, [
                                h('input', {
                                    type: 'checkbox',
                                    checked: checked,
                                    onChange: function () { toggleStringItem('roomOptions', opt); },
                                }),
                                h('span', null, opt),
                            ]);
                        }),
                        allowMaterials && h('p', null, 'Matériel'),
                        (selectedRoom.materials || []).map(function (opt) {
                            if (!allowMaterials) {
                                return null;
                            }
                            var checked = (form.materials || []).indexOf(opt) >= 0;
                            return h('label', { class: 'mj-request-management__check' }, [
                                h('input', {
                                    type: 'checkbox',
                                    checked: checked,
                                    onChange: function () { toggleStringItem('materials', opt); },
                                }),
                                h('span', null, opt),
                            ]);
                        }),
                    ]),
                    (allowMaterials && !allowLocation) && h('div', { class: 'mj-request-management__room-options' }, [
                        h('p', null, 'Matériel'),
                        materialOptionsCatalog.map(function (opt) {
                            var checked = (form.materials || []).indexOf(opt) >= 0;
                            return h('label', { class: 'mj-request-management__check' }, [
                                h('input', {
                                    type: 'checkbox',
                                    checked: checked,
                                    onChange: function () { toggleStringItem('materials', opt); },
                                }),
                                h('span', null, opt),
                            ]);
                        }),
                    ]),
                ]),

                currentStep.key === 'date' && h('div', { class: 'mj-request-management__step-panel', key: 'step-3' }, [
                    h('h2', null, 'Date'),
                    allowDate ? h('div', { class: 'mj-request-management__week-nav' }, [
                        h('button', {
                            type: 'button',
                            onClick: function () {
                                var d = new Date(weekStart + 'T00:00:00');
                                d.setDate(d.getDate() - 7);
                                setWeekStart(isoDate(d));
                            },
                        }, 'Semaine -'),
                        h('strong', null, weekStart),
                        h('button', {
                            type: 'button',
                            onClick: function () {
                                var d = new Date(weekStart + 'T00:00:00');
                                d.setDate(d.getDate() + 7);
                                setWeekStart(isoDate(d));
                            },
                        }, 'Semaine +'),
                    ]) : h('p', { class: 'mj-request-management__type-desc' }, 'Ce type de demande ne nécessite pas de créneau horaire.'),
                    allowDate && h('div', { class: 'mj-request-management__timeline' }, days.map(function (day, dayIndex) {
                        var selectionStyle = timelineSelectionStyle(dayIndex);
                        return h('div', { class: 'mj-request-management__day', key: day.iso }, [
                            h('div', { class: 'mj-request-management__day-title' }, day.short),
                            h('div', {
                                class: 'mj-request-management__day-grid',
                                onPointerDown: function (evt) { onTimelinePointerDown(dayIndex, evt); },
                                onPointerMove: function (evt) { onTimelinePointerMove(dayIndex, evt); },
                                onPointerUp: function () { onTimelinePointerUp(dayIndex); },
                                onPointerLeave: function () { onTimelinePointerUp(dayIndex); },
                            }, [
                                h('div', { class: 'mj-request-management__hour-lines' }, timelineHourMarks.map(function (hourMark) {
                                    return h('span', {
                                        class: 'mj-request-management__hour-line',
                                        style: { top: ((hourMark * 60 - START_MINUTE) / (END_MINUTE - START_MINUTE)) * 100 + '%' },
                                    }, String(hourMark).padStart(2, '0') + ':00');
                                })),
                                selectionStyle ? h('div', { class: 'mj-request-management__slot', style: selectionStyle }, form.slotStart + ' - ' + form.slotEnd) : null,
                            ]),
                        ]);
                    })),
                ]),

                currentStep.key === 'details' && h('div', { class: 'mj-request-management__step-panel', key: 'step-4' }, [
                    h('h2', null, 'Détails'),
                    h('label', null, [
                        h('span', null, selectedType ? ('Titre - ' + withEmoji(selectedType.emoji, selectedType.label)) : 'Titre de la demande'),
                        h('input', {
                            type: 'text',
                            value: form.title,
                            onInput: function (evt) { patchForm('title', evt.target.value || ''); },
                        }),
                    ]),
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
                        h('span', null, requiresAnimateur ? 'Animateur référent (obligatoire)' : 'Animateur référent (optionnel)'),
                        h('select', {
                            value: String(form.assignedToMemberId || 0),
                            onChange: function (evt) { patchForm('assignedToMemberId', Number(evt.target.value || 0)); },
                        }, [
                            h('option', { value: '0' }, 'Aucun'),
                        ].concat(animateurs.map(function (a) {
                            return h('option', { value: String(a.id) }, a.name + (a.role ? ' (' + a.role + ')' : ''));
                        }))),
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
                        disabled: step <= 0,
                        onClick: function () { setStep(Math.max(0, step - 1)); },
                    }, 'Précédent'),
                    !isLastStep && h('button', {
                        type: 'button',
                        disabled: !canGoNext(),
                        onClick: function () { setStep(Math.min(visibleSteps.length - 1, step + 1)); },
                    }, 'Suivant'),
                    isLastStep && h('button', {
                        type: 'button',
                        disabled: saving || !form.title || !form.requestType || (requiresAnimateur && !form.assignedToMemberId),
                        onClick: submitRequest,
                    }, 'Envoyer la demande ✈'),
                ]),
                notice ? h('p', { class: 'mj-request-management__notice' }, notice) : null,
            ]),

            h('aside', { class: 'mj-request-management__side', key: 'side' }, [
                h('section', { class: 'mj-request-management__list-card' }, [
                    h('h2', null, cfg.i18n && cfg.i18n.mine ? cfg.i18n.mine : 'Mes demandes'),
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

                isStaff && h('section', { class: 'mj-request-management__list-card' }, [
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
            ]),
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
