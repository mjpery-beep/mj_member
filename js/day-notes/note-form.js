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

    if (!preact || !hooks || !ModalsPkg) {
        console.warn('[MjDayNoteForm] Dépendances manquantes (preact/preactHooks/MjRegMgrModals).');
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

    var WEEKDAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

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

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function toIsoDate(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function parseIsoDate(value) {
        var parts = String(value || '').split('-');
        if (parts.length !== 3) return null;
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return isNaN(d.getTime()) ? null : d;
    }

    /**
     * @param {string} startIso
     * @param {string} endIso
     * @param {number[]} weekdays 0=Lundi ... 6=Dimanche
     * @return {string[]}
     */
    function expandWeeklyDates(startIso, endIso, weekdays) {
        var start = parseIsoDate(startIso);
        var end = parseIsoDate(endIso);
        if (!start || !end || !weekdays || !weekdays.length) {
            return [];
        }
        if (end < start) {
            var tmp = start; start = end; end = tmp;
        }

        var set = {};
        weekdays.forEach(function (d) { set[d] = true; });

        var dates = [];
        var cursor = new Date(start.getTime());
        var guard = 0;
        while (cursor <= end && guard < 730) {
            var isoWeekday = (cursor.getDay() + 6) % 7; // 0=Lundi
            if (set[isoWeekday]) {
                dates.push(toIsoDate(cursor));
            }
            cursor.setDate(cursor.getDate() + 1);
            guard++;
        }

        return dates;
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
    // Media gallery (upload multiple + thumbnails)
    // ============================================
    function MediaGallery(props) {
        var media = props.media || [];
        var onUpload = props.onUpload;
        var onRemove = props.onRemove;
        var uploading = props.uploading;
        var disabled = props.disabled;

        function handleFiles(e) {
            var files = e.target.files;
            if (!files || !files.length) return;
            Array.prototype.forEach.call(files, function (file) {
                onUpload(file);
            });
            e.target.value = '';
        }

        return h('div', { class: 'mj-day-note-form__media' }, [
            h('div', { class: 'mj-day-note-form__media-list' }, media.map(function (item) {
                return h('div', { key: item.id, class: 'mj-day-note-form__media-item' }, [
                    h('img', { src: item.thumbUrl || item.url, alt: '' }),
                    h('button', {
                        type: 'button',
                        class: 'mj-day-note-form__media-remove',
                        'aria-label': 'Supprimer l’image',
                        onClick: function () { onRemove(item.id); },
                        disabled: disabled,
                    }, '×'),
                ]);
            })),
            h('label', { class: 'mj-day-note-form__media-upload' }, [
                h('input', {
                    type: 'file',
                    accept: 'image/*',
                    multiple: true,
                    onChange: handleFiles,
                    disabled: disabled || uploading,
                }),
                uploading ? 'Envoi en cours…' : '+ Ajouter des images',
            ]),
        ]);
    }

    // ============================================
    // Date picker (single / multiple / weekly)
    // ============================================
    function DatesPicker(props) {
        var mode = props.mode;
        var onModeChange = props.onModeChange;
        var singleDate = props.singleDate;
        var onSingleDateChange = props.onSingleDateChange;
        var multipleDates = props.multipleDates || [];
        var onAddDate = props.onAddDate;
        var onRemoveDate = props.onRemoveDate;
        var weeklyDays = props.weeklyDays || [];
        var onToggleWeekday = props.onToggleWeekday;
        var weeklyStart = props.weeklyStart;
        var weeklyEnd = props.weeklyEnd;
        var onWeeklyStartChange = props.onWeeklyStartChange;
        var onWeeklyEndChange = props.onWeeklyEndChange;
        var disableModeChange = props.disableModeChange;
        var pendingDateValue = props.pendingDateValue;
        var onPendingDateChange = props.onPendingDateChange;

        return h('div', { class: 'mj-day-note-form__dates' }, [
            !disableModeChange && h('div', { class: 'mj-day-note-form__date-mode' }, [
                h('label', null, [
                    h('input', { type: 'radio', checked: mode === 'single', onChange: function () { onModeChange('single'); } }),
                    ' Date unique',
                ]),
                h('label', null, [
                    h('input', { type: 'radio', checked: mode === 'multiple', onChange: function () { onModeChange('multiple'); } }),
                    ' Dates multiples',
                ]),
                h('label', null, [
                    h('input', { type: 'radio', checked: mode === 'weekly', onChange: function () { onModeChange('weekly'); } }),
                    ' Jours de la semaine récurrents',
                ]),
            ]),

            mode === 'single' && h('input', {
                type: 'date',
                class: 'mj-regmgr-form__input',
                value: singleDate || '',
                onChange: function (e) { onSingleDateChange(e.target.value); },
            }),

            mode === 'multiple' && h(Fragment, null, [
                h('div', { class: 'mj-day-note-form__multi-add' }, [
                    h('input', {
                        type: 'date',
                        class: 'mj-regmgr-form__input',
                        value: pendingDateValue || '',
                        onChange: function (e) { onPendingDateChange(e.target.value); },
                    }),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                        onClick: function () { if (pendingDateValue) onAddDate(pendingDateValue); },
                    }, 'Ajouter'),
                ]),
                h('div', { class: 'mj-day-note-form__chips' }, multipleDates.map(function (d) {
                    return h('span', { key: d, class: 'mj-day-note-form__chip' }, [
                        d,
                        h('button', { type: 'button', onClick: function () { onRemoveDate(d); } }, '×'),
                    ]);
                })),
            ]),

            mode === 'weekly' && h(Fragment, null, [
                h('div', { class: 'mj-day-note-form__weekdays' }, WEEKDAY_LABELS.map(function (label, index) {
                    var active = weeklyDays.indexOf(index) >= 0;
                    return h('button', {
                        key: index,
                        type: 'button',
                        class: 'mj-day-note-form__weekday' + (active ? ' mj-day-note-form__weekday--active' : ''),
                        onClick: function () { onToggleWeekday(index); },
                    }, label);
                })),
                h('div', { class: 'mj-day-note-form__range' }, [
                    h('label', null, [
                        'Du ',
                        h('input', { type: 'date', class: 'mj-regmgr-form__input', value: weeklyStart || '', onChange: function (e) { onWeeklyStartChange(e.target.value); } }),
                    ]),
                    h('label', null, [
                        'au ',
                        h('input', { type: 'date', class: 'mj-regmgr-form__input', value: weeklyEnd || '', onChange: function (e) { onWeeklyEndChange(e.target.value); } }),
                    ]),
                ]),
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

        var stateTitle = useState(note ? (note.title || '') : '');
        var stateContent = useState(note ? (note.content || '') : '');
        var stateEmoji = useState(note ? (note.emoji || '') : '');
        var stateColor = useState(note ? (note.color || '') : '');
        var stateNoteTypeId = useState(note && note.note_type_id ? String(note.note_type_id) : '');
        var stateVisMode = useState(note && note.member_id ? 'member' : 'group');
        var stateMemberId = useState(note && note.member_id ? String(note.member_id) : '');
        var stateGroup = useState(note && note.visibility ? note.visibility : 'staff');

        var stateDateMode = useState('single');
        var stateSingleDate = useState(note ? note.note_date || '' : '');
        var stateMultipleDates = useState([]);
        var statePendingDate = useState('');
        var stateWeeklyDays = useState([]);
        var stateWeeklyStart = useState('');
        var stateWeeklyEnd = useState('');

        var stateMedia = useState((note && note.media) || []);
        var stateUploading = useState(false);
        var stateSaving = useState(false);
        var stateError = useState('');

        var title = stateTitle[0], setTitle = stateTitle[1];
        var content = stateContent[0], setContent = stateContent[1];
        var emoji = stateEmoji[0], setEmoji = stateEmoji[1];
        var color = stateColor[0], setColor = stateColor[1];
        var noteTypeId = stateNoteTypeId[0], setNoteTypeId = stateNoteTypeId[1];
        var visMode = stateVisMode[0], setVisMode = stateVisMode[1];
        var memberId = stateMemberId[0], setMemberId = stateMemberId[1];
        var group = stateGroup[0], setGroup = stateGroup[1];

        var dateMode = stateDateMode[0], setDateMode = stateDateMode[1];
        var singleDate = stateSingleDate[0], setSingleDate = stateSingleDate[1];
        var multipleDates = stateMultipleDates[0], setMultipleDates = stateMultipleDates[1];
        var pendingDate = statePendingDate[0], setPendingDate = statePendingDate[1];
        var weeklyDays = stateWeeklyDays[0], setWeeklyDays = stateWeeklyDays[1];
        var weeklyStart = stateWeeklyStart[0], setWeeklyStart = stateWeeklyStart[1];
        var weeklyEnd = stateWeeklyEnd[0], setWeeklyEnd = stateWeeklyEnd[1];

        var media = stateMedia[0], setMedia = stateMedia[1];
        var uploading = stateUploading[0], setUploading = stateUploading[1];
        var saving = stateSaving[0], setSaving = stateSaving[1];
        var error = stateError[0], setError = stateError[1];

        useEffect(function () {
            if (!isOpen) return;
            setTitle(note ? (note.title || '') : '');
            setContent(note ? (note.content || '') : '');
            setEmoji(note ? (note.emoji || '') : '');
            setColor(note ? (note.color || '') : '');
            setNoteTypeId(note && note.note_type_id ? String(note.note_type_id) : '');
            setVisMode(note && note.member_id ? 'member' : 'group');
            setMemberId(note && note.member_id ? String(note.member_id) : '');
            setGroup(note && note.visibility ? note.visibility : 'staff');
            setDateMode('single');
            setSingleDate(note ? note.note_date || '' : '');
            setMultipleDates([]);
            setPendingDate('');
            setWeeklyDays([]);
            setWeeklyStart('');
            setWeeklyEnd('');
            setMedia((note && note.media) || []);
            setError('');
        }, [isOpen, note]);

        var handleAddDate = useCallback(function (date) {
            setMultipleDates(function (prev) {
                if (prev.indexOf(date) >= 0) return prev;
                return prev.concat([date]).sort();
            });
            setPendingDate('');
        }, []);

        var handleRemoveDate = useCallback(function (date) {
            setMultipleDates(function (prev) { return prev.filter(function (d) { return d !== date; }); });
        }, []);

        var handleToggleWeekday = useCallback(function (index) {
            setWeeklyDays(function (prev) {
                return prev.indexOf(index) >= 0 ? prev.filter(function (d) { return d !== index; }) : prev.concat([index]).sort();
            });
        }, []);

        var handleUpload = useCallback(function (file) {
            if (!ajaxUrl) return;
            setUploading(true);
            setError('');
            var form = new FormData();
            form.append('action', 'mj_member_day_notes_upload_media');
            form.append('nonce', nonce || '');
            form.append('file', file);

            postAjax(ajaxUrl, form, true)
                .then(function (data) {
                    setMedia(function (prev) { return prev.concat([{ id: data.id, url: data.url, thumbUrl: data.thumbUrl }]); });
                })
                .catch(function (err) { setError(err.message); })
                .then(function () { setUploading(false); });
        }, [ajaxUrl, nonce]);

        var handleRemoveMedia = useCallback(function (attachmentId) {
            setMedia(function (prev) { return prev.filter(function (m) { return m.id !== attachmentId; }); });
            if (isEdit && ajaxUrl) {
                postAjax(ajaxUrl, {
                    action: 'mj_member_day_notes_delete_media',
                    nonce: nonce || '',
                    note_id: note.id,
                    attachment_id: attachmentId,
                }).catch(function () {});
            }
        }, [ajaxUrl, nonce, isEdit, note]);

        var resolveDates = useCallback(function () {
            if (dateMode === 'single') {
                return singleDate ? [singleDate] : [];
            }
            if (dateMode === 'multiple') {
                return multipleDates.slice();
            }
            return expandWeeklyDates(weeklyStart, weeklyEnd, weeklyDays);
        }, [dateMode, singleDate, multipleDates, weeklyStart, weeklyEnd, weeklyDays]);

        var handleSubmit = useCallback(function (e) {
            if (e && e.preventDefault) e.preventDefault();
            if (!ajaxUrl) return;

            if (!content.trim()) {
                setError(getString(strings, 'contentRequired', 'La description est requise.'));
                return;
            }

            var dates = resolveDates();
            if (!dates.length) {
                setError(getString(strings, 'dateRequired', 'Au moins une date est requise.'));
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
                note_type_id: noteTypeId || '',
                visibility: visMode === 'member' ? 'private' : group,
                member_id: visMode === 'member' ? memberId : '',
                attachment_ids: JSON.stringify(media.map(function (m) { return m.id; })),
            };

            if (isEdit) {
                fields.id = note.id;
                fields.note_date = dates[0];
            } else {
                fields.dates = JSON.stringify(dates);
            }

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
        }, [ajaxUrl, nonce, title, content, emoji, color, noteTypeId, visMode, group, memberId, media, isEdit, note, resolveDates, onSaved, onClose]);

        var handleDelete = useCallback(function () {
            if (!isEdit || !ajaxUrl) return;
            if (!global.confirm(getString(strings, 'deleteConfirm', 'Supprimer cette note ?'))) return;

            setSaving(true);
            postAjax(ajaxUrl, {
                action: 'mj_member_day_notes_delete',
                nonce: nonce || '',
                id: note.id,
            })
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
                disabled: saving,
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
                disabled: saving,
            }, saving ? getString(strings, 'saving', 'Enregistrement…') : getString(strings, 'save', 'Enregistrer')),
        ]);

        return h(Modal, {
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
                h('input', {
                    type: 'text',
                    class: 'mj-regmgr-form__input mj-day-note-form__title',
                    placeholder: getString(strings, 'titlePlaceholder', 'Titre'),
                    value: title,
                    onChange: function (e) { setTitle(e.target.value); },
                }),
            ]),

            h('textarea', {
                class: 'mj-regmgr-form__input',
                rows: 4,
                placeholder: getString(strings, 'contentPlaceholder', 'Description'),
                value: content,
                onChange: function (e) { setContent(e.target.value); },
            }),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Visibilité'),
                h('div', { class: 'mj-day-note-form__vis-mode' }, [
                    h('label', null, [
                        h('input', { type: 'radio', checked: visMode === 'group', onChange: function () { setVisMode('group'); } }),
                        ' Groupe',
                    ]),
                    h('label', null, [
                        h('input', { type: 'radio', checked: visMode === 'member', onChange: function () { setVisMode('member'); } }),
                        ' Personne assignée',
                    ]),
                ]),
                visMode === 'group' && h('select', {
                    class: 'mj-regmgr-form__input',
                    value: group,
                    onChange: function (e) { setGroup(e.target.value); },
                }, groupOptions.map(function (opt) {
                    return h('option', { value: opt.value }, opt.label);
                })),
                visMode === 'member' && h('select', {
                    class: 'mj-regmgr-form__input',
                    value: memberId,
                    onChange: function (e) { setMemberId(e.target.value); },
                }, [h('option', { value: '' }, '—')].concat(members.map(function (m) {
                    return h('option', { value: String(m.id) }, m.name);
                }))),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Type de note'),
                h('select', {
                    class: 'mj-regmgr-form__input',
                    value: noteTypeId,
                    onChange: function (e) { setNoteTypeId(e.target.value); },
                }, [h('option', { value: '' }, '—')].concat(noteTypes.map(function (t) {
                    return h('option', { value: String(t.id) }, (t.emoji ? t.emoji + ' ' : '') + t.label);
                }))),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Couleur'),
                h(ColorInput, { value: color, onChange: setColor }),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Date(s)'),
                h(DatesPicker, {
                    mode: dateMode,
                    onModeChange: setDateMode,
                    disableModeChange: isEdit,
                    singleDate: singleDate,
                    onSingleDateChange: setSingleDate,
                    multipleDates: multipleDates,
                    onAddDate: handleAddDate,
                    onRemoveDate: handleRemoveDate,
                    pendingDateValue: pendingDate,
                    onPendingDateChange: setPendingDate,
                    weeklyDays: weeklyDays,
                    onToggleWeekday: handleToggleWeekday,
                    weeklyStart: weeklyStart,
                    weeklyEnd: weeklyEnd,
                    onWeeklyStartChange: setWeeklyStart,
                    onWeeklyEndChange: setWeeklyEnd,
                }),
            ]),

            h('div', { class: 'mj-day-note-form__group' }, [
                h('label', { class: 'mj-regmgr-form__label' }, 'Images'),
                h(MediaGallery, {
                    media: media,
                    onUpload: handleUpload,
                    onRemove: handleRemoveMedia,
                    uploading: uploading,
                    disabled: saving,
                }),
            ]),

            isEdit && note.author_name && h('div', { class: 'mj-day-note-form__meta' }, [
                h(MemberAvatar, { member: { firstName: note.author_name, avatarUrl: note.author_avatar_url }, size: 'small' }),
                h('span', null, 'Créée par ' + note.author_name),
            ]),
        ]));
    }

    global.MjDayNoteForm = {
        NoteFormModal: NoteFormModal,
        expandWeeklyDates: expandWeeklyDates,
    };

})(window);
