/**
 * Registration Manager - Modal Components
 * Composants pour les fenêtres modales
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var RegComps = global.MjRegMgrRegistrations;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour modals.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;
    var useRef = hooks.useRef;

    var formatDate = Utils.formatDate;
    var getInitials = Utils.getInitials;
    var stringToColor = Utils.stringToColor;
    var classNames = Utils.classNames;
    var getString = Utils.getString;
    var debounce = Utils.debounce;

    var MemberAvatar = RegComps ? RegComps.MemberAvatar : function () { return null; };

    // ============================================
    // MODAL BASE
    // ============================================

    function Modal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var title = props.title;
        var children = props.children;
        var footer = props.footer;
        var size = props.size || 'medium';

        useEffect(function () {
            if (isOpen) {
                document.body.style.overflow = 'hidden';
            }
            return function () {
                document.body.style.overflow = '';
            };
        }, [isOpen]);

        // Fermer avec Escape
        useEffect(function () {
            function handleKeyDown(e) {
                if (e.key === 'Escape' && isOpen) {
                    onClose();
                }
            }
            document.addEventListener('keydown', handleKeyDown);
            return function () {
                document.removeEventListener('keydown', handleKeyDown);
            };
        }, [isOpen, onClose]);

        if (!isOpen) return null;

        return h('div', { class: 'mj-regmgr-modal' }, [
            h('div', { 
                class: 'mj-regmgr-modal__overlay',
                onClick: onClose,
            }),
            h('div', { class: classNames('mj-regmgr-modal__container', 'mj-regmgr-modal__container--' + size) }, [
                h('div', { class: 'mj-regmgr-modal__header' }, [
                    h('h2', { class: 'mj-regmgr-modal__title' }, title),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-modal__close',
                        onClick: onClose,
                        'aria-label': 'Fermer',
                    }, [
                        h('svg', {
                            width: 24,
                            height: 24,
                            viewBox: '0 0 24 24',
                            fill: 'none',
                            stroke: 'currentColor',
                            'stroke-width': 2,
                        }, [
                            h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                            h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                        ]),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-modal__body' }, children),
                footer && h('div', { class: 'mj-regmgr-modal__footer' }, footer),
            ]),
        ]);
    }

    // ============================================
    // ADD PARTICIPANT MODAL
    // ============================================

    function AddParticipantModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var onAdd = props.onAdd;
        var event = props.event;
        var searchMembers = props.searchMembers;
        var strings = props.strings;
        var config = props.config;
        var onCreateMember = props.onCreateMember;

        var _search = useState('');
        var search = _search[0];
        var setSearch = _search[1];

        var _results = useState([]);
        var results = _results[0];
        var setResults = _results[1];

        var _loading = useState(false);
        var loading = _loading[0];
        var setLoading = _loading[1];

        var _selected = useState([]);
        var selected = _selected[0];
        var setSelected = _selected[1];

        var _ageFilter = useState('');
        var ageFilter = _ageFilter[0];
        var setAgeFilter = _ageFilter[1];

        var _subscriptionFilter = useState('');
        var subscriptionFilter = _subscriptionFilter[0];
        var setSubscriptionFilter = _subscriptionFilter[1];

        var searchTimeoutRef = useRef(null);

        // Reset à l'ouverture et charger tous les membres
        useEffect(function () {
            if (isOpen) {
                setSearch('');
                setSelected([]);
                setAgeFilter('');
                setSubscriptionFilter('');
                // Charger tous les membres disponibles à l'ouverture
                setLoading(true);
                searchMembers({
                    search: '',
                    eventId: event ? event.id : 0,
                    ageRange: '',
                    subscriptionFilter: '',
                })
                    .then(function (data) {
                        setResults(data.members || []);
                        setLoading(false);
                    })
                    .catch(function () {
                        setLoading(false);
                    });
            }
        }, [isOpen, event, searchMembers]);

        // Recherche avec debounce
        var doSearch = useCallback(function () {
            if (!search && !ageFilter && !subscriptionFilter) {
                setResults([]);
                return;
            }

            setLoading(true);
            searchMembers({
                search: search,
                eventId: event ? event.id : 0,
                ageRange: ageFilter,
                subscriptionFilter: subscriptionFilter,
            })
                .then(function (data) {
                    setResults(data.members || []);
                    setLoading(false);
                })
                .catch(function () {
                    setLoading(false);
                });
        }, [search, ageFilter, subscriptionFilter, event, searchMembers]);

        useEffect(function () {
            if (searchTimeoutRef.current) {
                clearTimeout(searchTimeoutRef.current);
            }
            searchTimeoutRef.current = setTimeout(doSearch, 300);
            return function () {
                if (searchTimeoutRef.current) {
                    clearTimeout(searchTimeoutRef.current);
                }
            };
        }, [doSearch]);

        // Toggle sélection
        var toggleSelect = useCallback(function (member) {
            setSelected(function (prev) {
                var index = prev.findIndex(function (m) { return m.id === member.id; });
                if (index === -1) {
                    return prev.concat([member]);
                }
                return prev.filter(function (m) { return m.id !== member.id; });
            });
        }, []);

        var isSelected = useCallback(function (memberId) {
            return selected.some(function (m) { return m.id === memberId; });
        }, [selected]);

        // Confirmer l'ajout
        var handleConfirm = useCallback(function () {
            if (selected.length === 0) return;
            var memberIds = selected.map(function (m) { return m.id; });
            onAdd(memberIds);
        }, [selected, onAdd]);

        var ageRanges = config.ageRanges || {};

        var footer = h(Fragment, null, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary',
                onClick: onClose,
            }, getString(strings, 'cancel', 'Annuler')),
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--primary',
                onClick: handleConfirm,
                disabled: selected.length === 0,
            }, [
                getString(strings, 'addSelectedParticipants', 'Ajouter les membres sélectionnés'),
                selected.length > 0 && ' (' + selected.length + ')',
            ]),
        ]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: getString(strings, 'addParticipant', 'Ajouter des participants'),
            size: 'large',
            footer: footer,
        }, [
            // Barre de recherche
            h('div', { class: 'mj-regmgr-add-member__search' }, [
                h('div', { class: 'mj-regmgr-search-input mj-regmgr-search-input--large' }, [
                    h('svg', {
                        class: 'mj-regmgr-search-input__icon',
                        width: 20,
                        height: 20,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 2,
                    }, [
                        h('circle', { cx: 11, cy: 11, r: 8 }),
                        h('line', { x1: 21, y1: 21, x2: 16.65, y2: 16.65 }),
                    ]),
                    h('input', {
                        type: 'text',
                        class: 'mj-regmgr-search-input__field',
                        placeholder: getString(strings, 'searchMember', 'Rechercher un membre...'),
                        value: search,
                        onInput: function (e) { setSearch(e.target.value); },
                        autoFocus: true,
                    }),
                ]),
            ]),

            // Filtres
            h('div', { class: 'mj-regmgr-add-member__filters' }, [
                h('div', { class: 'mj-regmgr-filter-group' }, [
                    h('label', null, getString(strings, 'filterByAge', 'Filtrer par âge')),
                    h('select', {
                        class: 'mj-regmgr-select',
                        value: ageFilter,
                        onChange: function (e) { setAgeFilter(e.target.value); },
                    }, [
                        h('option', { value: '' }, 'Tous les âges'),
                        Object.keys(ageRanges).map(function (key) {
                            return h('option', { key: key, value: key }, ageRanges[key]);
                        }),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-filter-group' }, [
                    h('label', null, getString(strings, 'filterBySubscription', 'Filtrer par cotisation')),
                    h('select', {
                        class: 'mj-regmgr-select',
                        value: subscriptionFilter,
                        onChange: function (e) { setSubscriptionFilter(e.target.value); },
                    }, [
                        h('option', { value: '' }, 'Toutes'),
                        h('option', { value: 'active' }, getString(strings, 'subscriptionActive', 'Cotisation active')),
                        h('option', { value: 'expired' }, getString(strings, 'subscriptionExpired', 'Cotisation expirée')),
                        h('option', { value: 'none' }, getString(strings, 'subscriptionNone', 'Aucune cotisation')),
                    ]),
                ]),
            ]),

            // Bouton créer nouveau membre
            config.allowCreateMember && h('div', { class: 'mj-regmgr-add-member__create' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary mj-btn--small',
                    onClick: onCreateMember,
                }, [
                    h('svg', {
                        width: 16,
                        height: 16,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 2,
                    }, [
                        h('line', { x1: 12, y1: 5, x2: 12, y2: 19 }),
                        h('line', { x1: 5, y1: 12, x2: 19, y2: 12 }),
                    ]),
                    getString(strings, 'createNewMember', 'Créer un nouveau membre'),
                ]),
            ]),

            // Liste des résultats
            h('div', { class: 'mj-regmgr-add-member__results' }, [
                loading && h('div', { class: 'mj-regmgr-add-member__loading' }, [
                    h('div', { class: 'mj-regmgr-spinner' }),
                ]),

                !loading && results.length === 0 && (search || ageFilter || subscriptionFilter) && h('div', { class: 'mj-regmgr-add-member__empty' }, [
                    h('p', null, getString(strings, 'noResults', 'Aucun résultat')),
                ]),

                !loading && results.length > 0 && h('div', { class: 'mj-regmgr-member-list' },
                    results.map(function (member) {
                        var memberName = (member.firstName + ' ' + member.lastName).trim();
                        var isDisabled = member.alreadyRegistered || member.ageRestriction || member.roleRestriction;
                        var restriction = member.ageRestriction || member.roleRestriction || 
                            (member.alreadyRegistered ? getString(strings, 'alreadyRegistered', 'Déjà inscrit') : null);

                        return h('div', {
                            key: member.id,
                            class: classNames('mj-regmgr-member-item', {
                                'mj-regmgr-member-item--selected': isSelected(member.id),
                                'mj-regmgr-member-item--disabled': isDisabled,
                            }),
                            onClick: isDisabled ? null : function () { toggleSelect(member); },
                        }, [
                            // Checkbox
                            h('div', { class: 'mj-regmgr-member-item__checkbox' }, [
                                h('input', {
                                    type: 'checkbox',
                                    checked: isSelected(member.id),
                                    disabled: isDisabled,
                                    onChange: function () { },
                                }),
                            ]),

                            // Avatar
                            h(MemberAvatar, { member: member, showStatus: true }),

                            // Infos
                            h('div', { class: 'mj-regmgr-member-item__info' }, [
                                h('div', { class: 'mj-regmgr-member-item__name' }, memberName),
                                h('div', { class: 'mj-regmgr-member-item__meta' }, [
                                    h('span', null, member.roleLabel),
                                    member.age !== null && h('span', null, ' • ' + member.age + ' ans'),
                                ]),
                                restriction && h('div', { class: 'mj-regmgr-member-item__restriction' }, restriction),
                            ]),

                            // Badge cotisation
                            h('div', { class: 'mj-regmgr-member-item__subscription' }, [
                                h('span', {
                                    class: classNames('mj-regmgr-badge', 'mj-regmgr-badge--subscription-' + member.subscriptionStatus),
                                }, member.subscriptionStatus === 'active' ? '✓' : member.subscriptionStatus === 'expired' ? '!' : '?'),
                            ]),
                        ]);
                    })
                ),
            ]),
        ]);
    }

    // ============================================
    // CREATE MEMBER MODAL
    // ============================================

    function CreateMemberModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var onCreate = props.onCreate;
        var strings = props.strings;
        var config = props.config;

        var _firstName = useState('');
        var firstName = _firstName[0];
        var setFirstName = _firstName[1];

        var _lastName = useState('');
        var lastName = _lastName[0];
        var setLastName = _lastName[1];

        var _email = useState('');
        var email = _email[0];
        var setEmail = _email[1];

        var _role = useState('jeune');
        var role = _role[0];
        var setRole = _role[1];

        var _birthDate = useState('');
        var birthDate = _birthDate[0];
        var setBirthDate = _birthDate[1];

        var _loading = useState(false);
        var loading = _loading[0];
        var setLoading = _loading[1];

        var _error = useState('');
        var error = _error[0];
        var setError = _error[1];

        // Reset à l'ouverture
        useEffect(function () {
            if (isOpen) {
                setFirstName('');
                setLastName('');
                setEmail('');
                setRole('jeune');
                setBirthDate('');
                setError('');
            }
        }, [isOpen]);

        var handleSubmit = useCallback(function (e) {
            e.preventDefault();
            
            if (!firstName.trim() || !lastName.trim()) {
                setError('Prénom et nom sont requis.');
                return;
            }

            setLoading(true);
            setError('');

            onCreate(firstName.trim(), lastName.trim(), email.trim(), role, birthDate)
                .then(function () {
                    setLoading(false);
                    onClose();
                })
                .catch(function (err) {
                    setLoading(false);
                    setError(err.message || 'Erreur lors de la création');
                });
        }, [firstName, lastName, email, role, birthDate, onCreate, onClose]);

        var roleLabels = config.roleLabels || {};

        var footer = h(Fragment, null, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary',
                onClick: onClose,
                disabled: loading,
            }, getString(strings, 'cancel', 'Annuler')),
            h('button', {
                type: 'submit',
                form: 'create-member-form',
                class: 'mj-btn mj-btn--primary',
                disabled: loading || !firstName.trim() || !lastName.trim(),
            }, loading ? getString(strings, 'loading', 'Chargement...') : getString(strings, 'save', 'Créer')),
        ]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: getString(strings, 'createNewMember', 'Créer un nouveau membre'),
            size: 'small',
            footer: footer,
        }, [
            h('form', {
                id: 'create-member-form',
                class: 'mj-regmgr-form',
                onSubmit: handleSubmit,
            }, [
                error && h('div', { class: 'mj-regmgr-alert mj-regmgr-alert--error' }, error),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'create-firstName' }, getString(strings, 'firstName', 'Prénom') + ' *'),
                    h('input', {
                        type: 'text',
                        id: 'create-firstName',
                        class: 'mj-regmgr-input',
                        value: firstName,
                        onInput: function (e) { setFirstName(e.target.value); },
                        required: true,
                        autoFocus: true,
                    }),
                ]),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'create-lastName' }, getString(strings, 'lastName', 'Nom') + ' *'),
                    h('input', {
                        type: 'text',
                        id: 'create-lastName',
                        class: 'mj-regmgr-input',
                        value: lastName,
                        onInput: function (e) { setLastName(e.target.value); },
                        required: true,
                    }),
                ]),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'create-email' }, getString(strings, 'email', 'Email') + ' (optionnel)'),
                    h('input', {
                        type: 'email',
                        id: 'create-email',
                        class: 'mj-regmgr-input',
                        value: email,
                        onInput: function (e) { setEmail(e.target.value); },
                    }),
                ]),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'create-birthDate' }, getString(strings, 'birthDate', 'Date de naissance')),
                    h('input', {
                        type: 'date',
                        id: 'create-birthDate',
                        class: 'mj-regmgr-input',
                        value: birthDate,
                        onInput: function (e) { setBirthDate(e.target.value); },
                    }),
                ]),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'create-role' }, getString(strings, 'memberRole', 'Rôle')),
                    h('select', {
                        id: 'create-role',
                        class: 'mj-regmgr-select',
                        value: role,
                        onChange: function (e) { setRole(e.target.value); },
                    }, Object.keys(roleLabels).map(function (key) {
                        return h('option', { key: key, value: key }, roleLabels[key]);
                    })),
                ]),
            ]),
        ]);
    }

    // ============================================
    // MEMBER NOTES MODAL
    // ============================================

    function MemberNotesModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var member = props.member;
        var notes = props.notes || [];
        var onSave = props.onSave;
        var onDelete = props.onDelete;
        var strings = props.strings;
        var loading = props.loading;
        var currentMemberId = props.currentMemberId;

        var _newNote = useState('');
        var newNote = _newNote[0];
        var setNewNote = _newNote[1];

        var _editingId = useState(null);
        var editingId = _editingId[0];
        var setEditingId = _editingId[1];

        var _editContent = useState('');
        var editContent = _editContent[0];
        var setEditContent = _editContent[1];

        var _saving = useState(false);
        var saving = _saving[0];
        var setSaving = _saving[1];

        // Reset à l'ouverture
        useEffect(function () {
            if (isOpen) {
                setNewNote('');
                setEditingId(null);
                setEditContent('');
            }
        }, [isOpen]);

        var memberName = member 
            ? (member.firstName + ' ' + member.lastName).trim() 
            : 'Membre';

        var handleAddNote = useCallback(function () {
            if (!newNote.trim() || saving) return;

            setSaving(true);
            onSave(member.id, newNote.trim(), null)
                .then(function () {
                    setNewNote('');
                    setSaving(false);
                })
                .catch(function () {
                    setSaving(false);
                });
        }, [newNote, member, onSave, saving]);

        var handleEditNote = useCallback(function (note) {
            setEditingId(note.id);
            setEditContent(note.content);
        }, []);

        var handleSaveEdit = useCallback(function () {
            if (!editContent.trim() || saving) return;

            setSaving(true);
            onSave(member.id, editContent.trim(), editingId)
                .then(function () {
                    setEditingId(null);
                    setEditContent('');
                    setSaving(false);
                })
                .catch(function () {
                    setSaving(false);
                });
        }, [editContent, editingId, member, onSave, saving]);

        var handleDeleteNote = useCallback(function (noteId) {
            if (!confirm('Supprimer cette note ?')) return;
            onDelete(noteId);
        }, [onDelete]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: getString(strings, 'notePrivate', 'Notes sur') + ' ' + memberName,
            size: 'medium',
        }, [
            // Ajout de nouvelle note
            h('div', { class: 'mj-regmgr-notes__add' }, [
                h('textarea', {
                    class: 'mj-regmgr-textarea',
                    placeholder: getString(strings, 'notePlaceholder', 'Saisissez votre note ici...'),
                    value: newNote,
                    onInput: function (e) { setNewNote(e.target.value); },
                    rows: 3,
                }),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--primary',
                    onClick: handleAddNote,
                    disabled: !newNote.trim() || saving,
                }, getString(strings, 'addNote', 'Ajouter une note')),
            ]),

            // Liste des notes
            h('div', { class: 'mj-regmgr-notes__list' }, [
                loading && h('div', { class: 'mj-regmgr-notes__loading' }, [
                    h('div', { class: 'mj-regmgr-spinner' }),
                ]),

                !loading && notes.length === 0 && h('div', { class: 'mj-regmgr-notes__empty' }, [
                    h('p', null, getString(strings, 'noNotes', 'Aucune note')),
                ]),

                !loading && notes.map(function (note) {
                    var isEditing = editingId === note.id;

                    return h('div', { key: note.id, class: 'mj-regmgr-note' }, [
                        h('div', { class: 'mj-regmgr-note__header' }, [
                            h('span', { class: 'mj-regmgr-note__author' }, note.authorName || 'Anonyme'),
                            h('span', { class: 'mj-regmgr-note__date' }, note.createdAtFormatted),
                        ]),

                        isEditing ? h('div', { class: 'mj-regmgr-note__edit' }, [
                            h('textarea', {
                                class: 'mj-regmgr-textarea',
                                value: editContent,
                                onInput: function (e) { setEditContent(e.target.value); },
                                rows: 3,
                            }),
                            h('div', { class: 'mj-regmgr-note__edit-actions' }, [
                                h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--small mj-btn--secondary',
                                    onClick: function () { setEditingId(null); },
                                }, getString(strings, 'cancel', 'Annuler')),
                                h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--small mj-btn--primary',
                                    onClick: handleSaveEdit,
                                    disabled: saving,
                                }, getString(strings, 'save', 'Enregistrer')),
                            ]),
                        ]) : h('div', { class: 'mj-regmgr-note__content' }, note.content),

                        !isEditing && note.canEdit && h('div', { class: 'mj-regmgr-note__actions' }, [
                            h('button', {
                                type: 'button',
                                class: 'mj-regmgr-icon-btn mj-regmgr-icon-btn--small',
                                onClick: function () { handleEditNote(note); },
                                title: getString(strings, 'editNote', 'Modifier'),
                            }, [
                                h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                    h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                ]),
                            ]),
                            h('button', {
                                type: 'button',
                                class: 'mj-regmgr-icon-btn mj-regmgr-icon-btn--small mj-regmgr-icon-btn--danger',
                                onClick: function () { handleDeleteNote(note.id); },
                                title: getString(strings, 'deleteNote', 'Supprimer'),
                            }, [
                                h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('polyline', { points: '3 6 5 6 21 6' }),
                                    h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }),
                                ]),
                            ]),
                        ]),
                    ]);
                }),
            ]),
        ]);
    }

    // ============================================
    // QR CODE MODAL
    // ============================================

    function QRCodeModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var qrData = props.qrData;
        var strings = props.strings;
        var loading = props.loading;

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: getString(strings, 'paymentQRCode', 'QR Code de paiement'),
            size: 'small',
        }, [
            loading && h('div', { class: 'mj-regmgr-qr__loading' }, [
                h('div', { class: 'mj-regmgr-spinner' }),
            ]),

            !loading && qrData && h('div', { class: 'mj-regmgr-qr' }, [
                h('div', { class: 'mj-regmgr-qr__info' }, [
                    h('p', { class: 'mj-regmgr-qr__event' }, qrData.eventTitle),
                    h('p', { class: 'mj-regmgr-qr__amount' }, qrData.amount.toFixed(2) + ' €'),
                ]),
                h('div', { class: 'mj-regmgr-qr__image' }, [
                    h('img', {
                        src: qrData.qrUrl,
                        alt: 'QR Code',
                        width: 200,
                        height: 200,
                    }),
                ]),
                h('p', { class: 'mj-regmgr-qr__hint' }, 
                    'Scannez ce QR code pour effectuer le paiement'
                ),
            ]),
        ]);
    }

    // ============================================
    // OCCURRENCES SELECTOR MODAL
    // ============================================

    function formatOccDateForModal(dateStr) {
        var d = new Date(dateStr);
        var days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        var months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return {
            dayName: days[d.getDay()],
            date: d.getDate(),
            month: months[d.getMonth()],
            year: d.getFullYear(),
            time: d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
            full: days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear(),
        };
    }

    function OccurrencesModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var registration = props.registration;
        var occurrences = props.occurrences || [];
        var selectedOccurrences = props.selectedOccurrences || [];
        var onSave = props.onSave;
        var strings = props.strings;
        var loading = props.loading;

        var _localSelected = useState([]);
        var localSelected = _localSelected[0];
        var setLocalSelected = _localSelected[1];

        var _viewMode = useState('grid'); // 'grid' ou 'list'
        var viewMode = _viewMode[0];
        var setViewMode = _viewMode[1];

        // Initialiser la sélection locale quand la modal s'ouvre
        useEffect(function () {
            if (isOpen && selectedOccurrences) {
                setLocalSelected(selectedOccurrences.slice());
            }
        }, [isOpen, selectedOccurrences]);

        var memberName = registration && registration.member 
            ? (registration.member.firstName + ' ' + registration.member.lastName).trim() 
            : 'ce participant';

        var toggleOccurrence = useCallback(function (occKey) {
            setLocalSelected(function (prev) {
                var index = prev.indexOf(occKey);
                if (index > -1) {
                    var next = prev.slice();
                    next.splice(index, 1);
                    return next;
                } else {
                    return prev.concat([occKey]);
                }
            });
        }, []);

        var selectAll = useCallback(function () {
            setLocalSelected(occurrences.map(function (occ) {
                return typeof occ === 'string' ? occ : (occ.start || occ.date || '');
            }));
        }, [occurrences]);

        var selectNone = useCallback(function () {
            setLocalSelected([]);
        }, []);

        var handleSave = useCallback(function () {
            onSave(localSelected);
        }, [onSave, localSelected]);

        // Grouper par mois
        var groupedOccurrences = useMemo(function () {
            var groups = {};
            occurrences.forEach(function (occ) {
                var occDate = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                var d = new Date(occDate);
                var monthKey = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                if (!groups[monthKey]) {
                    groups[monthKey] = {
                        label: formatOccDateForModal(occDate).month + ' ' + d.getFullYear(),
                        items: [],
                    };
                }
                groups[monthKey].items.push(occ);
            });
            return Object.keys(groups).sort().map(function (key) { return groups[key]; });
        }, [occurrences]);

        var footer = h(Fragment, null, [
            h('div', { class: 'mj-occ-modal__footer-left' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--small',
                    onClick: selectAll,
                    disabled: loading,
                }, 'Tout sélectionner'),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--small',
                    onClick: selectNone,
                    disabled: loading,
                }, 'Tout désélectionner'),
            ]),
            h('div', { class: 'mj-occ-modal__footer-right' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary',
                    onClick: onClose,
                }, getString(strings, 'cancel', 'Annuler')),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--primary',
                    onClick: handleSave,
                    disabled: loading,
                }, [
                    loading && h('span', { class: 'mj-regmgr-spinner mj-regmgr-spinner--small' }),
                    getString(strings, 'save', 'Enregistrer'),
                ]),
            ]),
        ]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: 'Séances de ' + memberName,
            size: 'large',
            footer: footer,
        }, [
            h('div', { class: 'mj-occ-modal' }, [
                // Info header
                h('div', { class: 'mj-occ-modal__info' }, [
                    h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('circle', { cx: 12, cy: 12, r: 10 }),
                        h('line', { x1: 12, y1: 16, x2: 12, y2: 12 }),
                        h('line', { x1: 12, y1: 8, x2: 12.01, y2: 8 }),
                    ]),
                    h('span', null, localSelected.length + ' séance' + (localSelected.length > 1 ? 's' : '') + ' sélectionnée' + (localSelected.length > 1 ? 's' : '') + ' sur ' + occurrences.length),
                ]),

                // Toggle view mode
                h('div', { class: 'mj-occ-modal__view-toggle' }, [
                    h('button', {
                        type: 'button',
                        class: classNames('mj-occ-modal__view-btn', { 'mj-occ-modal__view-btn--active': viewMode === 'grid' }),
                        onClick: function () { setViewMode('grid'); },
                        title: 'Vue grille',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 3, y: 3, width: 7, height: 7 }),
                            h('rect', { x: 14, y: 3, width: 7, height: 7 }),
                            h('rect', { x: 3, y: 14, width: 7, height: 7 }),
                            h('rect', { x: 14, y: 14, width: 7, height: 7 }),
                        ]),
                    ]),
                    h('button', {
                        type: 'button',
                        class: classNames('mj-occ-modal__view-btn', { 'mj-occ-modal__view-btn--active': viewMode === 'list' }),
                        onClick: function () { setViewMode('list'); },
                        title: 'Vue liste',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('line', { x1: 8, y1: 6, x2: 21, y2: 6 }),
                            h('line', { x1: 8, y1: 12, x2: 21, y2: 12 }),
                            h('line', { x1: 8, y1: 18, x2: 21, y2: 18 }),
                            h('line', { x1: 3, y1: 6, x2: 3.01, y2: 6 }),
                            h('line', { x1: 3, y1: 12, x2: 3.01, y2: 12 }),
                            h('line', { x1: 3, y1: 18, x2: 3.01, y2: 18 }),
                        ]),
                    ]),
                ]),

                // Occurrences list
                viewMode === 'grid' && h('div', { class: 'mj-occ-modal__groups' },
                    groupedOccurrences.map(function (group) {
                        return h('div', { key: group.label, class: 'mj-occ-modal__group' }, [
                            h('h4', { class: 'mj-occ-modal__group-title' }, group.label),
                            h('div', { class: 'mj-occ-modal__grid' },
                                group.items.map(function (occ, index) {
                                    var occKey = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                                    var occDate = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                                    var isSelected = localSelected.indexOf(occKey) > -1;
                                    var isPast = new Date(occDate) < new Date();
                                    var dateInfo = formatOccDateForModal(occDate);

                                    return h('button', {
                                        key: occKey || index,
                                        type: 'button',
                                        class: classNames('mj-occ-card', {
                                            'mj-occ-card--selected': isSelected,
                                            'mj-occ-card--past': isPast,
                                        }),
                                        onClick: function () { toggleOccurrence(occKey); },
                                    }, [
                                        h('div', { class: 'mj-occ-card__check' }, [
                                            isSelected && h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 3 }, [
                                                h('polyline', { points: '20 6 9 17 4 12' }),
                                            ]),
                                        ]),
                                        h('div', { class: 'mj-occ-card__content' }, [
                                            h('span', { class: 'mj-occ-card__day' }, dateInfo.dayName.substring(0, 3)),
                                            h('span', { class: 'mj-occ-card__date' }, dateInfo.date),
                                            h('span', { class: 'mj-occ-card__time' }, dateInfo.time),
                                        ]),
                                    ]);
                                })
                            ),
                        ]);
                    })
                ),

                // Vue liste
                viewMode === 'list' && h('div', { class: 'mj-occ-modal__list' },
                    occurrences.map(function (occ, index) {
                        var occKey = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                        var occDate = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                        var isSelected = localSelected.indexOf(occKey) > -1;
                        var isPast = new Date(occDate) < new Date();
                        var dateInfo = formatOccDateForModal(occDate);

                        return h('label', {
                            key: occKey || index,
                            class: classNames('mj-occ-list-item', {
                                'mj-occ-list-item--selected': isSelected,
                                'mj-occ-list-item--past': isPast,
                            }),
                        }, [
                            h('input', {
                                type: 'checkbox',
                                checked: isSelected,
                                onChange: function () { toggleOccurrence(occKey); },
                                class: 'mj-occ-list-item__checkbox',
                            }),
                            h('div', { class: 'mj-occ-list-item__content' }, [
                                h('span', { class: 'mj-occ-list-item__date' }, dateInfo.full),
                                h('span', { class: 'mj-occ-list-item__time' }, dateInfo.time),
                            ]),
                            isPast && h('span', { class: 'mj-occ-list-item__badge' }, 'Passée'),
                        ]);
                    })
                ),

                occurrences.length === 0 && h('div', { class: 'mj-occ-modal__empty' }, [
                    h('svg', { width: 48, height: 48, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.5 }, [
                        h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                        h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                        h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                    ]),
                    h('p', null, 'Aucune séance disponible pour cet événement.'),
                ]),
            ]),
        ]);
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrModals = {
        Modal: Modal,
        AddParticipantModal: AddParticipantModal,
        CreateMemberModal: CreateMemberModal,
        MemberNotesModal: MemberNotesModal,
        QRCodeModal: QRCodeModal,
        OccurrencesModal: OccurrencesModal,
    };

})(window);
