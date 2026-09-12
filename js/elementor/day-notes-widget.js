/**
 * Day Notes - Management widget (list, filter, paginate, create/edit via
 * the shared NoteFormModal).
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

    var PAGE_SIZE = 10;

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

    function NoteRow(props) {
        var note = props.note;
        var onEdit = props.onEdit;
        var onDelete = props.onDelete;
        var onOpenNextcloud = props.onOpenNextcloud;
        var occurrenceCount = Array.isArray(note.series_dates) ? note.series_dates.length : 0;

        return h('div', { class: 'mj-day-notes-widget__row', style: note.color ? 'border-left-color:' + note.color : '' }, [
            h('span', { class: 'mj-day-notes-widget__row-emoji' }, note.emoji || '📝'),
            h('div', { class: 'mj-day-notes-widget__row-main' }, [
                h('div', { class: 'mj-day-notes-widget__row-title' }, note.title || (note.content || '').slice(0, 60)),
                h('div', { class: 'mj-day-notes-widget__row-meta' }, [
                    note.note_date,
                    occurrenceCount > 1 ? ' · 🔁 ' + occurrenceCount + ' dates' : '',
                    note.author_name ? ' · ' + note.author_name : '',
                ]),
            ]),
            h('div', { class: 'mj-day-notes-widget__row-actions' }, [
                onOpenNextcloud && h('button', { type: 'button', onClick: function () { onOpenNextcloud(note); } }, '☁️ Fichiers'),
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
        var noteTypes = config.noteTypes || [];
        var stateSearch = useState('');
        var search = stateSearch[0], setSearch = stateSearch[1];
        var stateFilterType = useState('');
        var filterType = stateFilterType[0], setFilterType = stateFilterType[1];
        var statePage = useState(0);
        var page = statePage[0], setPage = statePage[1];
        var stateNextcloudNote = useState(null);
        var nextcloudNote = stateNextcloudNote[0], setNextcloudNote = stateNextcloudNote[1];
        var nextcloudPanel = window.MjRegMgrNextcloudFiles && window.MjRegMgrNextcloudFiles.NextcloudFilesPanel;
        var nextcloudApi = window.MjRegMgrServices && typeof window.MjRegMgrServices.createApiService === 'function'
            ? window.MjRegMgrServices.createApiService({ ajaxUrl: config.ajaxUrl, nonce: config.nextcloudNonce || '' })
            : null;

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
                collapse_series: 1,
            }).then(function (data) {
                setNotes(Array.isArray(data.notes) ? data.notes : []);
                setLoading(false);
            }).catch(function () { setLoading(false); });
        }, [config]);

        useEffect(function () { refresh(); }, [refresh]);

        var handleDelete = useCallback(function (note) {
            var isSeries = !!note.series_id;
            var occurrenceCount = Array.isArray(note.series_dates) ? note.series_dates.length : 0;
            var confirmMsg = isSeries
                ? (occurrenceCount ? 'Supprimer cette note et ses ' + occurrenceCount + ' occurrences ?' : 'Supprimer cette note et toutes ses occurrences ?')
                : 'Supprimer cette note ?';
            if (!global.confirm(confirmMsg)) return;
            var fields = { action: 'mj_member_day_notes_delete', nonce: config.nonce };
            if (isSeries) {
                fields.series_id = note.series_id;
            } else {
                fields.id = note.id;
            }
            postAjax(config.ajaxUrl, fields).then(refresh).catch(function () {});
        }, [config, refresh]);

        var filteredNotes = useMemo(function () {
            var needle = search.trim().toLowerCase();
            return notes.filter(function (n) {
                if (needle) {
                    var matchesSearch = (n.title || '').toLowerCase().indexOf(needle) >= 0
                        || (n.content || '').toLowerCase().indexOf(needle) >= 0;
                    if (!matchesSearch) return false;
                }
                if (filterType && String(n.note_type_id || '') !== filterType) return false;
                return true;
            });
        }, [notes, search, filterType]);

        useEffect(function () { setPage(0); }, [search, filterType]);

        var pageCount = Math.max(1, Math.ceil(filteredNotes.length / PAGE_SIZE));
        var currentPage = Math.min(page, pageCount - 1);
        var pagedNotes = useMemo(function () {
            return filteredNotes.slice(currentPage * PAGE_SIZE, (currentPage + 1) * PAGE_SIZE);
        }, [filteredNotes, currentPage]);

        var noteFormConfig = useMemo(function () {
            return {
                ajaxUrl: config.ajaxUrl,
                nonce: config.nonce,
                noteTypes: noteTypes,
                groupOptions: config.groupOptions,
                members: config.members,
                canManageTypes: config.canManageTypes,
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
                h('select', {
                    class: 'mj-regmgr-form__input mj-day-notes-widget__type-filter',
                    value: filterType,
                    onChange: function (e) { setFilterType(e.target.value); },
                }, [h('option', { value: '' }, 'Tous les types')].concat(noteTypes.map(function (t) {
                    return h('option', { value: String(t.id) }, (t.emoji ? t.emoji + ' ' : '') + t.label);
                }))),
                h('button', {
                    type: 'button',
                    class: 'mj-regmgr-btn mj-regmgr-btn--primary',
                    onClick: function () { setEditingNote(null); setModalOpen(true); },
                }, '+ ' + (config.i18n.newNote || 'Nouvelle note')),
            ]),

            loading && h('p', null, 'Chargement…'),
            !loading && !filteredNotes.length && h('p', null, config.i18n.empty || 'Aucune note.'),
            !loading && h('div', { class: 'mj-day-notes-widget__list' }, pagedNotes.map(function (note) {
                return h(NoteRow, {
                    key: note.id,
                    note: note,
                    onEdit: function (n) { setEditingNote(n); setModalOpen(true); },
                    onDelete: handleDelete,
                    onOpenNextcloud: nextcloudPanel && nextcloudApi ? function (n) { setNextcloudNote(n); } : null,
                });
            })),

            !loading && filteredNotes.length > PAGE_SIZE && h('div', { class: 'mj-day-notes-widget__pagination' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                    disabled: currentPage <= 0,
                    onClick: function () { setPage(currentPage - 1); },
                }, '← Précédent'),
                h('span', { class: 'mj-day-notes-widget__pagination-status' }, 'Page ' + (currentPage + 1) + ' / ' + pageCount),
                h('button', {
                    type: 'button',
                    class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                    disabled: currentPage >= pageCount - 1,
                    onClick: function () { setPage(currentPage + 1); },
                }, 'Suivant →'),
            ]),

            h(DayNoteForm.NoteFormModal, {
                isOpen: modalOpen,
                note: editingNote,
                config: noteFormConfig,
                onClose: function () { setModalOpen(false); },
                onSaved: function () { setModalOpen(false); refresh(); },
                onDeleted: function () { setModalOpen(false); refresh(); },
            }),
            nextcloudPanel && nextcloudApi && nextcloudNote && h('div', { class: 'mj-day-notes-widget__nextcloud' }, [
                h('button', { type: 'button', onClick: function () { setNextcloudNote(null); } }, 'Fermer'),
                h(nextcloudPanel, { context: 'note', contextId: nextcloudNote.id, apiService: nextcloudApi }),
            ]),
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
