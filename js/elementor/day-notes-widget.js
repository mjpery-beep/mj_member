/**
 * Day Notes - Management widget (list, filter, create/edit via the shared
 * NoteFormModal, and a small note-types manager for coordinators/staff).
 */
(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var DayNoteForm = global.MjDayNoteForm;

    if (!preact || !hooks || !DayNoteForm) {
        console.warn('[MjDayNotesWidget] Dépendances manquantes.');
        return;
    }

    var h = preact.h;
    var render = preact.render;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;

    function pad2(n) { return n < 10 ? '0' + n : String(n); }
    function toIsoDate(date) { return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate()); }

    function encodeForm(fields) {
        var parts = [];
        Object.keys(fields).forEach(function (key) {
            var value = fields[key];
            if (value === undefined || value === null) return;
            parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
        });
        return parts.join('&');
    }

    function postAjax(ajaxUrl, fields) {
        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: encodeForm(fields),
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || !payload.success) {
                    var message = (payload && payload.data && payload.data.message) || 'Une erreur est survenue.';
                    throw new Error(message);
                }
                return payload.data;
            });
        });
    }

    function NoteTypesManager(props) {
        var config = props.config;
        var types = props.types;
        var onChange = props.onChange;

        var stateNewLabel = useState('');
        var newLabel = stateNewLabel[0], setNewLabel = stateNewLabel[1];
        var stateBusy = useState(false);
        var busy = stateBusy[0], setBusy = stateBusy[1];

        var handleAdd = useCallback(function () {
            if (!newLabel.trim()) return;
            setBusy(true);
            postAjax(config.ajaxUrl, {
                action: 'mj_member_note_types_create',
                nonce: config.nonce,
                label: newLabel,
            }).then(function () {
                setNewLabel('');
                setBusy(false);
                onChange();
            }).catch(function () { setBusy(false); });
        }, [newLabel, config, onChange]);

        var handleDelete = useCallback(function (id) {
            if (!global.confirm('Supprimer ce type de note ?')) return;
            postAjax(config.ajaxUrl, {
                action: 'mj_member_note_types_delete',
                nonce: config.nonce,
                id: id,
            }).then(onChange).catch(function () {});
        }, [config, onChange]);

        return h('div', { class: 'mj-day-notes-widget__types' }, [
            h('h3', null, 'Types de notes'),
            h('ul', { class: 'mj-day-notes-widget__types-list' }, types.map(function (t) {
                return h('li', { key: t.id }, [
                    h('span', null, (t.emoji ? t.emoji + ' ' : '') + t.label),
                    h('button', { type: 'button', onClick: function () { handleDelete(t.id); } }, 'Supprimer'),
                ]);
            })),
            h('div', { class: 'mj-day-notes-widget__types-add' }, [
                h('input', {
                    type: 'text',
                    class: 'mj-regmgr-form__input',
                    placeholder: 'Nouveau type…',
                    value: newLabel,
                    onChange: function (e) { setNewLabel(e.target.value); },
                }),
                h('button', {
                    type: 'button',
                    class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                    disabled: busy,
                    onClick: handleAdd,
                }, 'Ajouter'),
            ]),
        ]);
    }

    function NoteRow(props) {
        var note = props.note;
        var onEdit = props.onEdit;
        var onDelete = props.onDelete;

        return h('div', { class: 'mj-day-notes-widget__row', style: note.color ? 'border-left-color:' + note.color : '' }, [
            h('span', { class: 'mj-day-notes-widget__row-emoji' }, note.emoji || '📝'),
            h('div', { class: 'mj-day-notes-widget__row-main' }, [
                h('div', { class: 'mj-day-notes-widget__row-title' }, note.title || (note.content || '').slice(0, 60)),
                h('div', { class: 'mj-day-notes-widget__row-meta' }, [
                    note.note_date,
                    note.author_name ? ' · ' + note.author_name : '',
                ]),
            ]),
            h('div', { class: 'mj-day-notes-widget__row-actions' }, [
                h('button', { type: 'button', onClick: function () { onEdit(note); } }, 'Modifier'),
                h('button', { type: 'button', onClick: function () { onDelete(note); } }, 'Supprimer'),
            ]),
        ]);
    }

    function DayNotesApp(props) {
        var config = props.config;

        var stateNotes = useState([]);
        var notes = stateNotes[0], setNotes = stateNotes[1];
        var stateLoading = useState(true);
        var loading = stateLoading[0], setLoading = stateLoading[1];
        var stateModalOpen = useState(false);
        var modalOpen = stateModalOpen[0], setModalOpen = stateModalOpen[1];
        var stateEditingNote = useState(null);
        var editingNote = stateEditingNote[0], setEditingNote = stateEditingNote[1];
        var stateTypesOpen = useState(false);
        var typesOpen = stateTypesOpen[0], setTypesOpen = stateTypesOpen[1];
        var stateNoteTypes = useState(config.noteTypes || []);
        var noteTypes = stateNoteTypes[0], setNoteTypes = stateNoteTypes[1];
        var stateSearch = useState('');
        var search = stateSearch[0], setSearch = stateSearch[1];

        var refresh = useCallback(function () {
            setLoading(true);
            var today = new Date();
            var from = new Date(today.getTime() - 30 * 86400000);
            var to = new Date(today.getTime() + 180 * 86400000);

            postAjax(config.ajaxUrl, {
                action: 'mj_member_day_notes_list',
                nonce: config.nonce,
                date_from: toIsoDate(from),
                date_to: toIsoDate(to),
            }).then(function (data) {
                setNotes(Array.isArray(data.notes) ? data.notes : []);
                setLoading(false);
            }).catch(function () { setLoading(false); });
        }, [config]);

        var refreshTypes = useCallback(function () {
            postAjax(config.ajaxUrl, { action: 'mj_member_note_types_list', nonce: config.nonce })
                .then(function (data) { setNoteTypes(Array.isArray(data.types) ? data.types : []); })
                .catch(function () {});
        }, [config]);

        useEffect(function () { refresh(); }, [refresh]);

        var handleDelete = useCallback(function (note) {
            if (!global.confirm('Supprimer cette note ?')) return;
            postAjax(config.ajaxUrl, {
                action: 'mj_member_day_notes_delete',
                nonce: config.nonce,
                id: note.id,
            }).then(refresh).catch(function () {});
        }, [config, refresh]);

        var filteredNotes = useMemo(function () {
            if (!search.trim()) return notes;
            var needle = search.trim().toLowerCase();
            return notes.filter(function (n) {
                return (n.title || '').toLowerCase().indexOf(needle) >= 0
                    || (n.content || '').toLowerCase().indexOf(needle) >= 0;
            });
        }, [notes, search]);

        var noteFormConfig = useMemo(function () {
            return {
                ajaxUrl: config.ajaxUrl,
                nonce: config.nonce,
                noteTypes: noteTypes,
                groupOptions: config.groupOptions,
                members: config.members,
            };
        }, [config, noteTypes]);

        return h('div', null, [
            h('div', { class: 'mj-day-notes-widget__toolbar' }, [
                h('input', {
                    type: 'search',
                    class: 'mj-regmgr-form__input',
                    placeholder: 'Rechercher…',
                    value: search,
                    onChange: function (e) { setSearch(e.target.value); },
                }),
                h('button', {
                    type: 'button',
                    class: 'mj-regmgr-btn mj-regmgr-btn--primary',
                    onClick: function () { setEditingNote(null); setModalOpen(true); },
                }, '+ ' + (config.i18n.newNote || 'Nouvelle note')),
                config.canManageTypes && h('button', {
                    type: 'button',
                    class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                    onClick: function () { setTypesOpen(!typesOpen); },
                }, config.i18n.manageTypes || 'Gérer les types'),
            ]),

            typesOpen && h(NoteTypesManager, {
                config: config,
                types: noteTypes,
                onChange: refreshTypes,
            }),

            loading && h('p', null, 'Chargement…'),
            !loading && !filteredNotes.length && h('p', null, config.i18n.empty || 'Aucune note.'),
            !loading && h('div', { class: 'mj-day-notes-widget__list' }, filteredNotes.map(function (note) {
                return h(NoteRow, {
                    key: note.id,
                    note: note,
                    onEdit: function (n) { setEditingNote(n); setModalOpen(true); },
                    onDelete: handleDelete,
                });
            })),

            h(DayNoteForm.NoteFormModal, {
                isOpen: modalOpen,
                note: editingNote,
                config: noteFormConfig,
                onClose: function () { setModalOpen(false); },
                onSaved: function () { setModalOpen(false); refresh(); },
                onDeleted: function () { setModalOpen(false); refresh(); },
            }),
        ]);
    }

    function boot() {
        var root = document.getElementById('mj-day-notes-app');
        var config = global.mjMemberDayNotes;
        if (!root || !config || !config.ajaxUrl) {
            return;
        }
        var loadingEl = root.querySelector('.mj-day-notes-widget__loading');
        if (loadingEl) loadingEl.remove();

        var mount = document.createElement('div');
        root.appendChild(mount);
        render(h(DayNotesApp, { config: config }), mount);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})(window);
