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
        var stateVisMode = useState(note && note.assigned_member_ids && note.assigned_member_ids.length ? 'member' : 'group');
        var stateMemberIds = useState(note && note.assigned_member_ids ? note.assigned_member_ids.map(String) : []);
        var stateGroup = useState(note && note.visibility ? note.visibility : 'staff');

        var stateOccurrence = useState(function () {
            return OccurrencePickerPkg.defaultOccurrenceValue({
                mode: 'single',
                singleDate: note ? note.note_date || '' : '',
            });
        });
        var stateStartTime = useState(note ? (note.start_time || '') : '');
        var stateEndTime = useState(note ? (note.end_time || '') : '');

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
        var memberIds = stateMemberIds[0], setMemberIds = stateMemberIds[1];
        var group = stateGroup[0], setGroup = stateGroup[1];

        var occurrence = stateOccurrence[0], setOccurrence = stateOccurrence[1];
        var startTime = stateStartTime[0], setStartTime = stateStartTime[1];
        var endTime = stateEndTime[0], setEndTime = stateEndTime[1];

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
            setVisMode(note && note.assigned_member_ids && note.assigned_member_ids.length ? 'member' : 'group');
            setMemberIds(note && note.assigned_member_ids ? note.assigned_member_ids.map(String) : []);
            setGroup(note && note.visibility ? note.visibility : 'staff');
            setOccurrence(OccurrencePickerPkg.defaultOccurrenceValue({
                mode: 'single',
                singleDate: note ? note.note_date || '' : '',
            }));
            setStartTime(note ? (note.start_time || '') : '');
            setEndTime(note ? (note.end_time || '') : '');
            setMedia((note && note.media) || []);
            setError('');
        }, [isOpen, note]);

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
            return OccurrencePickerPkg.resolveOccurrenceDates(occurrence.mode, occurrence);
        }, [occurrence]);

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
                visibility: visMode === 'member' ? 'private' : group,
                member_ids: JSON.stringify(visMode === 'member' ? memberIds : []),
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
        }, [ajaxUrl, nonce, title, content, emoji, color, startTime, endTime, noteTypeId, visMode, group, memberIds, media, isEdit, note, resolveDates, onSaved, onClose]);

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
                visMode === 'member' && h('div', { class: 'mj-day-note-form__member-checkboxes' }, members.length ? members.map(function (m) {
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
                }) : h('p', { class: 'mj-regmgr-form__hint' }, 'Aucun membre disponible.')),
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
                h(OccurrencePickerPkg.OccurrencePicker, {
                    value: occurrence,
                    onChange: setOccurrence,
                    disableModeChange: isEdit,
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
    };

})(window);
