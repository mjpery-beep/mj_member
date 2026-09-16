/**
 * Day Notes - Shared note form (Preact)
 *
 * Composant unique utilisé à la fois par la modal "Créer une note" du widget
 * calendrier (mj-member-events-calendar) et par le widget de gestion des
 * notes (mj-member-day-notes) - DRY, un seul formulaire à maintenir.
 */
(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var ModalsPkg = global.MjRegMgrModals;
    var EmojiPickerPkg = global.MjRegMgrEmojiPicker;
    var RegComps = global.MjRegMgrRegistrations;
    var OccurrencePickerPkg = global.MjOccurrencePicker;

    if (!preact || !hooks || !ModalsPkg || !OccurrencePickerPkg) {
        console.warn('[MjDayNoteForm] Dépendances manquantes (preact/preactHooks/MjRegMgrModals/MjOccurrencePicker).');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;

    var Modal = ModalsPkg.Modal;
    var EmojiPickerField = EmojiPickerPkg ? EmojiPickerPkg.EmojiPickerField : null;
    var MemberAvatar = RegComps ? RegComps.MemberAvatar : function () { return null; };

    var DEFAULT_GROUP_OPTIONS = [
        { value: 'private', label: 'Uniquement moi' },
        { value: 'role:animateur', label: '🎭 Animateurs' },
        { value: 'role:coordinateur', label: '👔 Coordinateurs' },
        { value: 'role:benevole', label: '🤝 Bénévoles' },
        { value: 'role:jeune', label: '🧒 Jeunes' },
        { value: 'staff', label: '🏢 Staff' },
        { value: 'all', label: '👥 Tous' },
    ];

    function getString(strings, key, fallback) {
        if (strings && typeof strings[key] === 'string' && strings[key] !== '') {
            return strings[key];
        }
        return fallback;
    }

    function encodeForm(fields) {
        var parts = [];
        Object.keys(fields).forEach(function (key) {
            var value = fields[key];
            if (value === undefined || value === null) {
                return;
            }
            parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
        });
        return parts.join('&');
    }

    function postAjax(ajaxUrl, fields, isMultipart) {
        var options = {
            method: 'POST',
            credentials: 'same-origin',
        };
        if (isMultipart) {
            options.body = fields; // FormData
        } else {
            options.headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' };
            options.body = encodeForm(fields);
        }

        return fetch(ajaxUrl, options).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || !payload.success) {
                    var message = (payload && payload.data && payload.data.message) || 'Une erreur est survenue.';
                    throw new Error(message);
                }
                return payload.data;
            });
        });
    }

    // ============================================
    // Color input (pattern from event-editor.js)
    // ============================================
    function normalizeHexColor(value) {
        var v = String(value || '').trim();
        if (!v) return '';
        if (v[0] !== '#') v = '#' + v;
        return /^#[0-9a-fA-F]{6}$/.test(v) ? v : '';
    }

    function ColorInput(props) {
        var value = props.value || '';
        var onChange = props.onChange;

        return h('div', { class: 'mj-day-note-form__color-input' }, [
            h('input', {
                type: 'color',
                value: normalizeHexColor(value) || '#3b82f6',
                onChange: function (e) { onChange(e.target.value); },
            }),
            h('input', {
                type: 'text',
                class: 'mj-regmgr-form__input',
                value: value,
                placeholder: '#3b82f6',
                onChange: function (e) { onChange(e.target.value); },
            }),
        ]);
    }

    // ============================================
    // Recurrence prefill helpers
    // ============================================
    function buildInitialOccurrence(note) {
        // The picker's own state (mode + params) is stored verbatim when
        // available, so editing reopens on the same mode (e.g. "plage de
        // dates" + récurrence hebdomadaire) instead of a flat date list.
        if (note && note.recurrence_rule && note.recurrence_rule.mode) {
            return note.recurrence_rule;
        }
        if (note && note.series_id && Array.isArray(note.series_dates) && note.series_dates.length) {
            return { mode: 'multiple', multipleDates: note.series_dates.slice() };
        }
        return { mode: 'single', singleDate: note ? (note.note_date || '') : '' };
    }

    // ============================================
    // Note types manager (add/edit/delete, color + emoji)
    // ============================================
    function NoteTypeRow(props) {
        var type = props.type;
        var busy = props.busy;
        var onSave = props.onSave;
        var onDelete = props.onDelete;

        var stateLabel = useState(type.label || '');
        var label = stateLabel[0], setLabel = stateLabel[1];
        var stateColor = useState(type.color || '');
        var color = stateColor[0], setColor = stateColor[1];
        var stateEmoji = useState(type.emoji || '');
        var emoji = stateEmoji[0], setEmoji = stateEmoji[1];

        var dirty = label !== (type.label || '') || color !== (type.color || '') || emoji !== (type.emoji || '');

        return h('div', { class: 'mj-note-types-modal__row' }, [
            EmojiPickerField && h(EmojiPickerField, {
                value: emoji,
                onChange: setEmoji,
                fallbackPlaceholder: '🏷️',
            }),
            h('input', {
                type: 'text',
                class: 'mj-regmgr-form__input mj-note-types-modal__label',
                value: label,
                onChange: function (e) { setLabel(e.target.value); },
            }),
            h(ColorInput, { value: color, onChange: setColor }),
            h('button', {
                type: 'button',
                class: 'mj-regmgr-btn mj-regmgr-btn--primary',
                disabled: busy || !dirty || !label.trim(),
                onClick: function () { onSave(type.id, { label: label, color: color, emoji: emoji, sort_order: type.sort_order }); },
            }, 'Enregistrer'),
            h('button', {
                type: 'button',
                class: 'mj-regmgr-btn mj-regmgr-btn--danger',
                disabled: busy,
                onClick: function () { onDelete(type.id); },
            }, 'Supprimer'),
        ]);
    }

    function NoteTypesManagerModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var config = props.config || {};
        var types = props.types || [];
        var onChanged = props.onChanged;

        var stateBusy = useState(false);
        var busy = stateBusy[0], setBusy = stateBusy[1];
        var stateError = useState('');
        var error = stateError[0], setError = stateError[1];
        var stateNewLabel = useState('');
        var newLabel = stateNewLabel[0], setNewLabel = stateNewLabel[1];
        var stateNewColor = useState('#3b82f6');
        var newColor = stateNewColor[0], setNewColor = stateNewColor[1];
        var stateNewEmoji = useState('');
        var newEmoji = stateNewEmoji[0], setNewEmoji = stateNewEmoji[1];

        useEffect(function () {
            if (isOpen) {
                setError('');
                setNewLabel('');
                setNewColor('#3b82f6');
                setNewEmoji('');
            }
        }, [isOpen]);

        var refresh = useCallback(function () {
            return postAjax(config.ajaxUrl, {
                action: 'mj_member_note_types_list',
                nonce: config.nonce || '',
            }).then(function (data) {
                var list = Array.isArray(data.types) ? data.types : [];
                if (typeof onChanged === 'function') onChanged(list);
                return list;
            });
        }, [config, onChanged]);

        var handleSave = useCallback(function (id, data) {
            setBusy(true);
            setError('');
            postAjax(config.ajaxUrl, {
                action: 'mj_member_note_types_update',
                nonce: config.nonce || '',
                id: id,
                label: data.label,
                color: data.color || '',
                emoji: data.emoji || '',
                sort_order: data.sort_order || 0,
            }).then(refresh).then(function () { setBusy(false); })
                .catch(function (err) { setBusy(false); setError(err.message); });
        }, [config, refresh]);

        var handleDelete = useCallback(function (id) {
            if (!global.confirm('Supprimer ce type de note ? Les notes existantes seront détachées de ce type.')) return;
            setBusy(true);
            setError('');
            postAjax(config.ajaxUrl, {
                action: 'mj_member_note_types_delete',
                nonce: config.nonce || '',
                id: id,
            }).then(refresh).then(function () { setBusy(false); })
                .catch(function (err) { setBusy(false); setError(err.message); });
        }, [config, refresh]);

        var handleCreate = useCallback(function () {
            if (!newLabel.trim()) return;
            setBusy(true);
            setError('');
            postAjax(config.ajaxUrl, {
                action: 'mj_member_note_types_create',
                nonce: config.nonce || '',
                label: newLabel,
                color: newColor || '',
                emoji: newEmoji || '',
                sort_order: types.length,
            }).then(function () {
                setNewLabel('');
                setNewColor('#3b82f6');
                setNewEmoji('');
                return refresh();
            }).then(function () { setBusy(false); })
                .catch(function (err) { setBusy(false); setError(err.message); });
        }, [config, newLabel, newColor, newEmoji, types, refresh]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: 'Gérer les types de note',
        }, h('div', { class: 'mj-note-types-modal' }, [
            error && h('p', { class: 'mj-regmgr-form__error' }, error),
            !types.length && h('p', { class: 'mj-regmgr-form__hint' }, 'Aucun type de note pour le moment.'),
            types.map(function (t) {
                return h(NoteTypeRow, { key: t.id, type: t, busy: busy, onSave: handleSave, onDelete: handleDelete });
            }),
            h('div', { class: 'mj-note-types-modal__new' }, [
                h('h4', null, 'Nouveau type'),
                h('div', { class: 'mj-note-types-modal__row' }, [
                    EmojiPickerField && h(EmojiPickerField, {
                        value: newEmoji,
                        onChange: setNewEmoji,
                        fallbackPlaceholder: '🏷️',
                    }),
                    h('input', {
                        type: 'text',
                        class: 'mj-regmgr-form__input mj-note-types-modal__label',
                        placeholder: 'Libellé du type…',
                        value: newLabel,
                        onChange: function (e) { setNewLabel(e.target.value); },
                    }),
                    h(ColorInput, { value: newColor, onChange: setNewColor }),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-btn mj-regmgr-btn--primary',
                        disabled: busy || !newLabel.trim(),
                        onClick: handleCreate,
                    }, 'Ajouter'),
                ]),
            ]),
        ]));
    }

    // ============================================
    // Assignment: search & pick a "jeune" (staff are a fixed, short list
    // shown as checkboxes; jeunes can be numerous, so they get a search
    // field instead - see NoteFormModal's "Assigné à" group).
    // ============================================
    function AssignedJeuneSearch(props) {
        var ajaxUrl = props.ajaxUrl;
        var nonce = props.nonce;
        var selected = props.selected || [];
        var onAdd = props.onAdd;
        var onRemove = props.onRemove;

        var stateQuery = useState('');
        var query = stateQuery[0], setQuery = stateQuery[1];
        var stateResults = useState([]);
        var results = stateResults[0], setResults = stateResults[1];
        var stateLoading = useState(false);
        var loading = stateLoading[0], setLoading = stateLoading[1];

        useEffect(function () {
            var term = query.trim();
            if (term.length < 2) {
                setResults([]);
                return undefined;
            }
            var cancelled = false;
            setLoading(true);
            var handle = setTimeout(function () {
                postAjax(ajaxUrl, {
                    action: 'mj_member_day_notes_search_jeunes',
                    nonce: nonce || '',
                    search: term,
                }).then(function (data) {
                    if (cancelled) return;
                    setResults(Array.isArray(data.members) ? data.members : []);
                    setLoading(false);
                }).catch(function () {
                    if (cancelled) return;
                    setLoading(false);
                });
            }, 300);
            return function () {
                cancelled = true;
                clearTimeout(handle);
            };
        }, [query, ajaxUrl, nonce]);

        var selectedIds = selected.map(function (m) { return String(m.id); });
        var visibleResults = results.filter(function (m) { return selectedIds.indexOf(String(m.id)) === -1; });

        return h('div', { class: 'mj-day-note-form__jeune-search' }, [
            !!selected.length && h('div', { class: 'mj-day-note-form__chips' }, selected.map(function (m) {
                return h('span', { key: m.id, class: 'mj-day-note-form__chip' }, [
                    m.name,
                    h('button', { type: 'button', onClick: function () { onRemove(m.id); } }, '×'),
                ]);
            })),
            h('input', {
                type: 'text',
                class: 'mj-regmgr-form__input',
                placeholder: 'Rechercher un jeune…',
                value: query,
                onChange: function (e) { setQuery(e.target.value); },
            }),
            query.trim().length >= 2 && h('div', { class: 'mj-day-note-form__jeune-results' }, [
                loading && h('p', { class: 'mj-regmgr-form__hint' }, 'Recherche…'),
                !loading && !visibleResults.length && h('p', { class: 'mj-regmgr-form__hint' }, 'Aucun jeune trouvé.'),
                !loading && visibleResults.map(function (m) {
                    return h('button', {
                        key: m.id,
                        type: 'button',
                        class: 'mj-day-note-form__jeune-result',
                        onClick: function () {
                            onAdd(m);
                            setQuery('');
                            setResults([]);
                        },
                    }, m.name);
                }),
            ]),
        ]);
    }

    // ============================================
    // Main form
    // ============================================
    function NoteFormModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var onSaved = props.onSaved;
        var onDeleted = props.onDeleted;
        var note = props.note || null;
        var config = props.config || {};
        var ajaxUrl = config.ajaxUrl;
        var nonce = config.nonce;
        var noteTypes = config.noteTypes || [];
        var groupOptions = config.groupOptions && config.groupOptions.length ? config.groupOptions : DEFAULT_GROUP_OPTIONS;
        var members = config.members || [];
        var strings = config.strings || {};

        var isEdit = !!(note && note.id);

        var nextcloudPanel = global.MjRegMgrNextcloudFiles && global.MjRegMgrNextcloudFiles.NextcloudFilesPanel;
        var nextcloudApi = global.MjRegMgrServices && typeof global.MjRegMgrServices.createApiService === 'function'
            ? global.MjRegMgrServices.createApiService({ ajaxUrl: ajaxUrl, nonce: config.nextcloudNonce || '' })
            : null;
        var stateNextcloudOpen = useState(false);
        var nextcloudOpen = stateNextcloudOpen[0], setNextcloudOpen = stateNextcloudOpen[1];

        var stateTitle = useState(note ? (note.title || '') : '');
        var stateContent = useState(note ? (note.content || '') : '');
        var stateEmoji = useState(note ? (note.emoji || '') : '');
        var stateColor = useState(note ? (note.color || '') : '');
        var stateNoteTypeId = useState(note && note.note_type_id ? String(note.note_type_id) : '');
        var stateMemberIds = useState(note && note.assigned_member_ids ? note.assigned_member_ids.map(String) : []);
        var stateAssignedJeunes = useState([]);
        var stateVisibleToAssignees = useState(note ? !!note.visible_to_assignees : false);
        var stateGroup = useState(note && note.visibility ? note.visibility : 'staff');

        var stateOccurrence = useState(function () {
            return OccurrencePickerPkg.defaultOccurrenceValue(buildInitialOccurrence(note));
        });
        var stateStartTime = useState(note ? (note.start_time || '') : '');
        var stateEndTime = useState(note ? (note.end_time || '') : '');

        var stateMedia = useState((note && note.media) || []);
        var stateSaving = useState(false);
        var stateError = useState('');
        var stateSeriesLoading = useState(false);
        var stateTypesList = useState(noteTypes);
        var stateTypesManagerOpen = useState(false);

        var title = stateTitle[0], setTitle = stateTitle[1];
        var content = stateContent[0], setContent = stateContent[1];
        var emoji = stateEmoji[0], setEmoji = stateEmoji[1];
        var color = stateColor[0], setColor = stateColor[1];
        var noteTypeId = stateNoteTypeId[0], setNoteTypeId = stateNoteTypeId[1];
        var memberIds = stateMemberIds[0], setMemberIds = stateMemberIds[1];
        var assignedJeunes = stateAssignedJeunes[0], setAssignedJeunes = stateAssignedJeunes[1];
        var visibleToAssignees = stateVisibleToAssignees[0], setVisibleToAssignees = stateVisibleToAssignees[1];
        var group = stateGroup[0], setGroup = stateGroup[1];

        var occurrence = stateOccurrence[0], setOccurrence = stateOccurrence[1];
        var startTime = stateStartTime[0], setStartTime = stateStartTime[1];
        var endTime = stateEndTime[0], setEndTime = stateEndTime[1];

        var media = stateMedia[0], setMedia = stateMedia[1];
        var saving = stateSaving[0], setSaving = stateSaving[1];
        var error = stateError[0], setError = stateError[1];
        var seriesLoading = stateSeriesLoading[0], setSeriesLoading = stateSeriesLoading[1];
        var typesList = stateTypesList[0], setTypesList = stateTypesList[1];
        var typesManagerOpen = stateTypesManagerOpen[0], setTypesManagerOpen = stateTypesManagerOpen[1];

        useEffect(function () {
            if (!isOpen) return;
            setTypesList(noteTypes);
            setTitle(note ? (note.title || '') : '');
            setContent(note ? (note.content || '') : '');
            setEmoji(note ? (note.emoji || '') : '');
            setColor(note ? (note.color || '') : '');
            setNoteTypeId(note && note.note_type_id ? String(note.note_type_id) : '');
            setGroup(note && note.visibility ? note.visibility : 'staff');
            setVisibleToAssignees(note ? !!note.visible_to_assignees : false);
            setStartTime(note ? (note.start_time || '') : '');
            setEndTime(note ? (note.end_time || '') : '');
            setMedia((note && note.media) || []);
            setNextcloudOpen(false);
            setError('');

            var initialAssignedIds = note && note.assigned_member_ids ? note.assigned_member_ids.map(String) : [];
            setMemberIds(initialAssignedIds);
            var staffIds = members.map(function (m) { return String(m.id); });
            var unknownIds = initialAssignedIds.filter(function (id) { return staffIds.indexOf(id) === -1; });
            if (unknownIds.length && ajaxUrl) {
                // Ids assigned to the note but absent from the fixed staff list
                // are jeunes added via search - hydrate their names for the chips.
                postAjax(ajaxUrl, {
                    action: 'mj_member_day_notes_search_jeunes',
                    nonce: nonce || '',
                    ids: JSON.stringify(unknownIds),
                }).then(function (data) {
                    setAssignedJeunes(Array.isArray(data.members) ? data.members : []);
                }).catch(function () { setAssignedJeunes([]); });
            } else {
                setAssignedJeunes([]);
            }

            var hasUsableRule = !!(note && note.recurrence_rule && note.recurrence_rule.mode);
            var hasSeriesDates = !!(note && Array.isArray(note.series_dates));

            if (note && note.series_id && !hasUsableRule && !hasSeriesDates) {
                // Legacy note (no stored recurrence_rule) and the caller only
                // handed us this one occurrence (e.g. the calendar's per-day
                // view) — fetch the full series before allowing a save, so
                // editing never silently drops the other dates it doesn't
                // know about.
                setOccurrence(OccurrencePickerPkg.defaultOccurrenceValue({ mode: 'single', singleDate: note.note_date || '' }));
                setSeriesLoading(true);
                postAjax(ajaxUrl, {
                    action: 'mj_member_day_notes_get_series',
                    nonce: nonce || '',
                    series_id: note.series_id,
                }).then(function (data) {
                    if (data.recurrence_rule && data.recurrence_rule.mode) {
                        setOccurrence(OccurrencePickerPkg.defaultOccurrenceValue(data.recurrence_rule));
                    } else if (Array.isArray(data.dates) && data.dates.length) {
                        setOccurrence(OccurrencePickerPkg.defaultOccurrenceValue({ mode: 'multiple', multipleDates: data.dates }));
                    }
                    setSeriesLoading(false);
                }).catch(function () { setSeriesLoading(false); });
            } else {
                setOccurrence(OccurrencePickerPkg.defaultOccurrenceValue(buildInitialOccurrence(note)));
            }
        }, [isOpen, note]);

        var resolveDates = useCallback(function () {
            return OccurrencePickerPkg.resolveOccurrenceDates(occurrence.mode, occurrence);
        }, [occurrence]);

        var handleAddJeune = useCallback(function (member) {
            var idStr = String(member.id);
            setAssignedJeunes(function (prev) {
                return prev.some(function (m) { return String(m.id) === idStr; }) ? prev : prev.concat([member]);
            });
            setMemberIds(function (prev) {
                return prev.indexOf(idStr) >= 0 ? prev : prev.concat([idStr]);
            });
        }, []);

        var handleRemoveJeune = useCallback(function (id) {
            var idStr = String(id);
            setAssignedJeunes(function (prev) { return prev.filter(function (m) { return String(m.id) !== idStr; }); });
            setMemberIds(function (prev) { return prev.filter(function (mid) { return mid !== idStr; }); });
        }, []);

        var handleSubmit = useCallback(function (e) {
            if (e && e.preventDefault) e.preventDefault();
            if (!ajaxUrl) return;

            var dates = resolveDates();
            if (!dates.length) {
                setError(getString(strings, 'dateRequired', 'Au moins une date est requise.'));
                return;
            }
            if (endTime && !startTime || (startTime && endTime && endTime <= startTime)) {
                setError(getString(strings, 'timeRangeInvalid', 'L’heure de fin doit être postérieure à l’heure de début.'));
                return;
            }

            setSaving(true);
            setError('');

            var fields = {
                action: isEdit ? 'mj_member_day_notes_update' : 'mj_member_day_notes_create',
                nonce: nonce || '',
                title: title,
                content: content,
                emoji: emoji,
                color: color,
                start_time: startTime,
                end_time: endTime,
                note_type_id: noteTypeId || '',
                visibility: group,
                member_ids: JSON.stringify(memberIds),
                visible_to_assignees: visibleToAssignees ? 1 : 0,
                attachment_ids: JSON.stringify(media.map(function (m) { return m.id; })),
            };

            if (isEdit) {
                fields.id = note.id;
            }
            fields.dates = JSON.stringify(dates);
            fields.recurrence_rule = JSON.stringify(occurrence);

            postAjax(ajaxUrl, fields, false)
                .then(function (data) {
                    setSaving(false);
                    if (typeof onSaved === 'function') {
                        onSaved(data.note || data.notes || null);
                    }
                    if (typeof onClose === 'function') onClose();
                })
                .catch(function (err) {
                    setSaving(false);
                    setError(err.message);
                });
        }, [ajaxUrl, nonce, title, content, emoji, color, startTime, endTime, noteTypeId, group, memberIds, visibleToAssignees, media, isEdit, note, occurrence, resolveDates, onSaved, onClose]);

        var handleDelete = useCallback(function () {
            if (!isEdit || !ajaxUrl) return;
            // A series_id alone is enough to know this note has several
            // occurrences — series_dates (the full date list) isn't always
            // loaded here (e.g. opened from the calendar's per-day view),
            // but deleting must still target the whole series, not just the
            // one occurrence this modal happens to know about.
            var isSeries = !!note.series_id;
            var occurrenceCount = Array.isArray(note.series_dates) ? note.series_dates.length : 0;
            var confirmMsg = isSeries
                ? getString(strings, 'deleteSeriesConfirm', occurrenceCount
                    ? 'Supprimer cette note et ses ' + occurrenceCount + ' occurrences ?'
                    : 'Supprimer cette note et toutes ses occurrences ?')
                : getString(strings, 'deleteConfirm', 'Supprimer cette note ?');
            if (!global.confirm(confirmMsg)) return;

            setSaving(true);
            var deleteFields = { action: 'mj_member_day_notes_delete', nonce: nonce || '' };
            if (isSeries) {
                deleteFields.series_id = note.series_id;
            } else {
                deleteFields.id = note.id;
            }
            postAjax(ajaxUrl, deleteFields)
                .then(function () {
                    setSaving(false);
                    if (typeof onDeleted === 'function') onDeleted(note);
                    if (typeof onClose === 'function') onClose();
                })
                .catch(function (err) {
                    setSaving(false);
                    setError(err.message);
                });
        }, [ajaxUrl, nonce, isEdit, note, onDeleted, onClose]);

        var footer = h(Fragment, null, [
            isEdit && h('button', {
                type: 'button',
                class: 'mj-regmgr-btn mj-regmgr-btn--danger',
                onClick: handleDelete,
                disabled: saving || seriesLoading,
            }, getString(strings, 'delete', 'Supprimer')),
            h('button', {
                type: 'button',
                class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                onClick: onClose,
                disabled: saving,
            }, getString(strings, 'cancel', 'Annuler')),
            h('button', {
                type: 'button',
                class: 'mj-regmgr-btn mj-regmgr-btn--primary',
                onClick: handleSubmit,
                disabled: saving || seriesLoading,
            }, saving ? getString(strings, 'saving', 'Enregistrement…') : getString(strings, 'save', 'Enregistrer')),
        ]);

        return h(Fragment, null, [
        h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: getString(strings, 'title', isEdit ? 'Modifier la note' : 'Créer une note'),
            footer: footer,
        }, h('div', { class: 'mj-day-note-form' }, [
            error && h('p', { class: 'mj-regmgr-form__error' }, error),

            h('div', { class: 'mj-day-note-form__row' }, [
                EmojiPickerField && h(EmojiPickerField, {
                    value: emoji,
                    onChange: setEmoji,
                    fallbackPlaceholder: '📝',
                }),
                h('textarea', {
                    rows: 1,
                    class: 'mj-regmgr-form__input mj-day-note-form__title',
                    placeholder: getString(strings, 'titlePlaceholder', 'Titre'),
                    value: title,
                    onChange: function (e) { setTitle(e.target.value); },
                }),
            ]),

            h('textarea', {
                class: 'mj-regmgr-form__input',
                rows: 4,
                placeholder: getString(strings, 'contentPlaceholder', 'Description (facultatif)'),
                value: content,
                onChange: function (e) { setContent(e.target.value); },
            }),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Visibilité'),
                h('select', {
                    class: 'mj-regmgr-form__input',
                    value: group,
                    onChange: function (e) { setGroup(e.target.value); },
                }, groupOptions.map(function (opt) {
                    return h('option', { value: opt.value }, opt.label);
                })),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Assigné à'),
                h('div', { class: 'mj-day-note-form__member-checkboxes' }, members.length ? members.map(function (m) {
                    var idStr = String(m.id);
                    var checked = memberIds.indexOf(idStr) >= 0;
                    return h('label', { key: idStr, class: 'mj-day-note-form__member-checkbox' }, [
                        h('input', {
                            type: 'checkbox',
                            checked: checked,
                            onChange: function () {
                                setMemberIds(checked
                                    ? memberIds.filter(function (id) { return id !== idStr; })
                                    : memberIds.concat([idStr]));
                            },
                        }),
                        ' ' + m.name,
                    ]);
                }) : h('p', { class: 'mj-regmgr-form__hint' }, 'Aucun membre du staff disponible.')),

                h(AssignedJeuneSearch, {
                    ajaxUrl: ajaxUrl,
                    nonce: nonce,
                    selected: assignedJeunes,
                    onAdd: handleAddJeune,
                    onRemove: handleRemoveJeune,
                }),

                h('label', { class: 'mj-day-note-form__visible-to-assignees' }, [
                    h('input', {
                        type: 'checkbox',
                        checked: visibleToAssignees,
                        onChange: function (e) { setVisibleToAssignees(e.target.checked); },
                    }),
                    ' ' + getString(strings, 'visibleToAssignees', 'Visible également pour les personnes assignées'),
                ]),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('div', { class: 'mj-day-note-form__type-header' }, [
                    h('label', { class: 'mj-regmgr-form__label' }, 'Type de note'),
                    config.canManageTypes && h('button', {
                        type: 'button',
                        class: 'mj-day-note-form__manage-types',
                        onClick: function () { setTypesManagerOpen(true); },
                    }, getString(strings, 'manageTypes', 'Gérer les types')),
                ]),
                h('select', {
                    class: 'mj-regmgr-form__input',
                    value: noteTypeId,
                    onChange: function (e) {
                        var newId = e.target.value;
                        setNoteTypeId(newId);
                        var match = typesList.filter(function (t) { return String(t.id) === newId; })[0];
                        if (match) {
                            setColor(match.color || '');
                        }
                    },
                }, [h('option', { value: '' }, '—')].concat(typesList.map(function (t) {
                    return h('option', { value: String(t.id) }, (t.emoji ? t.emoji + ' ' : '') + t.label);
                }))),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Couleur'),
                h(ColorInput, { value: color, onChange: setColor }),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Date(s)'),
                h(OccurrencePickerPkg.OccurrencePicker, {
                    value: occurrence,
                    onChange: setOccurrence,
                }),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Heure (facultatif)'),
                h('div', { class: 'mj-day-note-form__range' }, [
                    h('label', null, [
                        'De ',
                        h('input', { type: 'time', class: 'mj-regmgr-form__input', value: startTime, onChange: function (e) { setStartTime(e.target.value); } }),
                    ]),
                    h('label', null, [
                        'à ',
                        h('input', { type: 'time', class: 'mj-regmgr-form__input', value: endTime, onChange: function (e) { setEndTime(e.target.value); } }),
                    ]),
                ]),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Fichiers'),
                isEdit
                    ? h('button', {
                        type: 'button',
                        class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                        onClick: function () { setNextcloudOpen(true); },
                        disabled: !nextcloudPanel || !nextcloudApi,
                    }, '☁️ Fichiers')
                    : h('p', { class: 'mj-regmgr-form__hint' }, getString(strings, 'filesRequireSave', 'Enregistrez d’abord la note pour ajouter des fichiers.')),
            ]),

            isEdit && note.author_name && h('div', { class: 'mj-day-note-form__meta' }, [
                h(MemberAvatar, { member: { firstName: note.author_name, avatarUrl: note.author_avatar_url }, size: 'small' }),
                h('span', null, 'Créée par ' + note.author_name),
            ]),
        ])),
        h(NoteTypesManagerModal, {
            isOpen: typesManagerOpen,
            onClose: function () { setTypesManagerOpen(false); },
            config: { ajaxUrl: ajaxUrl, nonce: nonce },
            types: typesList,
            onChanged: setTypesList,
        }),
        nextcloudPanel && nextcloudApi && h('div', { class: 'mj-day-note-form__nextcloud-modal' }, h(Modal, {
            isOpen: isEdit && nextcloudOpen,
            onClose: function () { setNextcloudOpen(false); },
            title: '☁️ Fichiers' + (title ? ' — ' + title : ''),
            size: 'large',
        }, isEdit && h(nextcloudPanel, { context: 'note', contextId: note.id, apiService: nextcloudApi }))),
        ]);
    }

    global.MjDayNoteForm = {
        NoteFormModal: NoteFormModal,
        NoteTypesManagerModal: NoteTypesManagerModal,
    };

})(window);
