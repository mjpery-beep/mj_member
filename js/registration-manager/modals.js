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

    function buildLocationQuery(data) {
        if (!data) {
            return '';
        }

        var mapQuery = data.map_query || data.mapQuery || '';
        if (typeof mapQuery === 'string') {
            mapQuery = mapQuery.trim();
        } else {
            mapQuery = '';
        }
        if (mapQuery !== '') {
            return mapQuery;
        }

        var latitude = data.latitude !== undefined && data.latitude !== null
            ? String(data.latitude).trim()
            : '';
        var longitude = data.longitude !== undefined && data.longitude !== null
            ? String(data.longitude).trim()
            : '';

        if (latitude !== '' && longitude !== '') {
            return latitude + ',' + longitude;
        }

        var parts = [];
        ['address_line', 'postal_code', 'city', 'country'].forEach(function (key) {
            var value = data[key];
            if (value === undefined || value === null) {
                return;
            }
            var str = typeof value === 'string' ? value : String(value);
            str = str.trim();
            if (str !== '') {
                parts.push(str);
            }
        });

        return parts.join(', ');
    }

    function buildLocationPreviewUrl(data) {
        var query = buildLocationQuery(data);
        if (!query) {
            return '';
        }
        return 'https://maps.google.com/maps?q=' + encodeURIComponent(query) + '&output=embed';
    }

    function buildLocationExternalUrl(data) {
        var query = buildLocationQuery(data);
        if (!query) {
            return '';
        }
        return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query);
    }

    function dataUrlToBlob(dataUrl) {
        if (!dataUrl || typeof dataUrl !== 'string') {
            return null;
        }
        var parts = dataUrl.split(',');
        if (parts.length < 2) {
            return null;
        }
        var mimeMatch = parts[0].match(/:(.*?);/);
        var mime = mimeMatch && mimeMatch[1] ? mimeMatch[1] : 'image/jpeg';
        try {
            var binaryString = global.atob(parts[1]);
            var len = binaryString.length;
            var bytes = new Uint8Array(len);
            for (var i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return new Blob([bytes], { type: mime });
        } catch (e) {
            return null;
        }
    }

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

    function CreateEventModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var onCreate = props.onCreate;
        var strings = props.strings;
        var submitting = !!props.submitting;

        var _title = useState('');
        var title = _title[0];
        var setTitle = _title[1];

        var _error = useState('');
        var error = _error[0];
        var setError = _error[1];

        useEffect(function () {
            if (isOpen) {
                setTitle('');
                setError('');
            }
        }, [isOpen]);

        var handleSubmit = useCallback(function (e) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            if (submitting) {
                return;
            }

            var trimmed = title.trim();
            if (!trimmed) {
                setError(getString(strings, 'createEventTitleRequired', 'Le titre est obligatoire.'));
                return;
            }

            setError('');

            var result = onCreate ? onCreate({ title: trimmed }) : null;
            if (result && typeof result.then === 'function') {
                result.catch(function (err) {
                    var message = err && err.message ? err.message : getString(strings, 'createEventError', 'Impossible de créer cet événement.');
                    setError(message);
                });
            }
        }, [title, submitting, onCreate, strings]);

        var footer = h(Fragment, null, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary',
                onClick: onClose,
                disabled: submitting,
            }, getString(strings, 'cancel', 'Annuler')),
            h('button', {
                type: 'submit',
                form: 'create-event-form',
                class: 'mj-btn mj-btn--primary',
                disabled: submitting || !title.trim(),
            }, submitting
                ? getString(strings, 'creatingEvent', 'Création...')
                : getString(strings, 'createEventSubmit', 'Créer le brouillon')
            ),
        ]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: getString(strings, 'createEventModalTitle', 'Créer un événement'),
            size: 'small',
            footer: footer,
        }, [
            h('form', {
                id: 'create-event-form',
                class: 'mj-regmgr-form',
                onSubmit: handleSubmit,
            }, [
                error && h('div', { class: 'mj-regmgr-alert mj-regmgr-alert--error' }, error),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'create-event-title' }, getString(strings, 'createEventTitleLabel', 'Titre du brouillon') + ' *'),
                    h('input', {
                        type: 'text',
                        id: 'create-event-title',
                        class: 'mj-regmgr-input',
                        value: title,
                        onInput: function (e) {
                            setTitle(e.target.value);
                            if (error) {
                                setError('');
                            }
                        },
                        disabled: submitting,
                        placeholder: getString(strings, 'createEventTitlePlaceholder', 'Ex: Atelier découverte'),
                        autoFocus: true,
                    }),
                ]),
            ]),
        ]);
    }

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
    // MEMBER ACCOUNT MODAL
    // ============================================

    function MemberAccountModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var member = props.member;
        var rolesMap = props.roles || {};
        var onSubmit = props.onSubmit;
        var onResetPassword = props.onResetPassword;
        var strings = props.strings || {};

        var roleKeys = Object.keys(rolesMap);
        var roleKeysKey = roleKeys.join('|');

        var _role = useState('');
        var role = _role[0];
        var setRole = _role[1];

        var _manualLogin = useState('');
        var manualLogin = _manualLogin[0];
        var setManualLogin = _manualLogin[1];

        var _manualPassword = useState('');
        var manualPassword = _manualPassword[0];
        var setManualPassword = _manualPassword[1];

        var _submitting = useState(false);
        var submitting = _submitting[0];
        var setSubmitting = _submitting[1];

        var _error = useState('');
        var error = _error[0];
        var setError = _error[1];

        var _statusMessage = useState('');
        var statusMessage = _statusMessage[0];
        var setStatusMessage = _statusMessage[1];

        var _resultData = useState(null);
        var resultData = _resultData[0];
        var setResultData = _resultData[1];

        var _copyFeedback = useState('');
        var copyFeedback = _copyFeedback[0];
        var setCopyFeedback = _copyFeedback[1];

        var _resetting = useState(false);
        var resetting = _resetting[0];
        var setResetting = _resetting[1];

        var _linkCopyFeedback = useState('');
        var linkCopyFeedback = _linkCopyFeedback[0];
        var setLinkCopyFeedback = _linkCopyFeedback[1];

        var modalTitle = getString(strings, 'memberAccountModalTitle', 'Gestion du compte WordPress');
        var modalDescription = getString(strings, 'memberAccountModalDescription', 'Créez, liez ou mettez à jour le compte WordPress associé à ce membre.');
        var accountLabel = getString(strings, 'memberAccount', 'Compte WordPress');
        var accountLinkedText = getString(strings, 'memberAccountStatusLinked', 'Un compte WordPress est lié à ce membre.');
        var accountUnlinkedText = getString(strings, 'memberAccountStatusUnlinked', 'Aucun compte WordPress n\'est encore lié.');
        var roleLabel = getString(strings, 'memberAccountRoleLabel', 'Rôle WordPress attribué');
        var rolePlaceholder = getString(strings, 'memberAccountRolePlaceholder', 'Sélectionnez un rôle…');
        var loginLabel = getString(strings, 'memberAccountLoginLabel', 'Identifiant WordPress');
        var loginPlaceholder = getString(strings, 'memberAccountLoginPlaceholder', 'ex : prenom.nom');
        var loginHelp = getString(strings, 'memberAccountLoginHelp', 'Laissez vide pour proposer un identifiant automatiquement.');
        var passwordLabel = getString(strings, 'memberAccountPasswordLabel', 'Mot de passe');
        var passwordHelp = getString(strings, 'memberAccountPasswordHelp', 'Laissez vide pour générer un mot de passe sécurisé automatiquement.');
        var generatePasswordLabel = getString(strings, 'memberAccountGeneratePassword', 'Générer un mot de passe sécurisé');
        var copyPasswordLabel = getString(strings, 'memberAccountCopyPassword', 'Copier le mot de passe');
        var copyPasswordSuccess = getString(strings, 'memberAccountPasswordCopied', 'Mot de passe copié dans le presse-papiers.');
        var claimLinkLabel = getString(strings, 'memberAccountClaimLinkLabel', 'Lien de création de compte');
        var claimLinkHelp = getString(strings, 'memberAccountClaimLinkHelp', 'Partagez ce lien avec le membre pour qu\'il crée son accès.');
        var copyLinkLabel = getString(strings, 'memberAccountCopyLink', 'Copier le lien');
        var copyLinkSuccess = getString(strings, 'memberAccountLinkCopied', 'Lien copié dans le presse-papiers.');
        var submitCreateLabel = getString(strings, 'memberAccountSubmitCreate', 'Créer et lier le compte');
        var submitUpdateLabel = getString(strings, 'memberAccountSubmitUpdate', 'Mettre à jour le compte');
        var successCreateLabel = getString(strings, 'memberAccountSuccessCreate', 'Compte WordPress créé et lié avec succès.');
        var successUpdateLabel = getString(strings, 'memberAccountSuccessUpdate', 'Compte WordPress mis à jour avec succès.');
        var resetEmailLabel = getString(strings, 'memberAccountResetEmail', 'Envoyer un email de réinitialisation');
        var resetEmailSuccess = getString(strings, 'memberAccountResetEmailSuccess', 'Email de réinitialisation envoyé.');
        var noRolesMessage = getString(strings, 'memberAccountNoRoles', 'Aucun rôle WordPress n\'est disponible pour votre compte.');

        var memberName = member ? [(member.firstName || ''), (member.lastName || '')].join(' ').trim() : '';
        var claimLink = member && typeof member.cardClaimUrl === 'string' ? member.cardClaimUrl : '';

        var baseLinked = member && member.hasLinkedAccount ? true : false;
        if (resultData && (resultData.created || (resultData.login && resultData.login !== ''))) {
            baseLinked = true;
        }
        var isLinked = baseLinked;

        useEffect(function () {
            if (!isOpen) {
                return;
            }
            var defaultRole = '';
            if (member && member.accountRole && Object.prototype.hasOwnProperty.call(rolesMap, member.accountRole)) {
                defaultRole = member.accountRole;
            } else if (roleKeys.length > 0) {
                defaultRole = roleKeys[0];
            }
            setRole(defaultRole);
            setManualLogin(member && member.accountLogin ? member.accountLogin : '');
            setManualPassword('');
            setError('');
            setStatusMessage('');
            setResultData(null);
            setCopyFeedback('');
            setResetting(false);
            setLinkCopyFeedback('');
        }, [isOpen, member ? member.id : null, roleKeysKey]);

        var generatePassword = useCallback(function () {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@$!?';
            var length = 12;
            var generated = '';
            for (var i = 0; i < length; i += 1) {
                generated += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            setManualPassword(generated);
            setCopyFeedback('');
        }, []);

        var handleCopyPassword = useCallback(function () {
            if (!manualPassword) {
                return;
            }

            var text = manualPassword;

            if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text)
                    .then(function () {
                        setCopyFeedback(copyPasswordSuccess);
                    })
                    .catch(function () {
                        setCopyFeedback(copyPasswordSuccess);
                    });
                return;
            }

            try {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                setCopyFeedback(copyPasswordSuccess);
            } catch (err) {
                setCopyFeedback(copyPasswordSuccess);
            }
        }, [manualPassword, copyPasswordSuccess]);

        var handleCopyClaimLink = useCallback(function () {
            if (!claimLink) {
                return;
            }

            var text = claimLink;

            if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text)
                    .then(function () {
                        setLinkCopyFeedback(copyLinkSuccess);
                    })
                    .catch(function () {
                        setLinkCopyFeedback(copyLinkSuccess);
                    });
                return;
            }

            try {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                setLinkCopyFeedback(copyLinkSuccess);
            } catch (err) {
                setLinkCopyFeedback(copyLinkSuccess);
            }
        }, [claimLink, copyLinkSuccess]);

        var handleSubmit = useCallback(function (event) {
            event.preventDefault();

            if (submitting) {
                return;
            }

            if (!member || !member.id) {
                setError('Membre introuvable.');
                return;
            }

            if (typeof onSubmit !== 'function') {
                setError('Action indisponible.');
                return;
            }

            if (roleKeys.length > 0 && !role) {
                setError(noRolesMessage);
                return;
            }

            setSubmitting(true);
            setError('');
            setStatusMessage('');

            onSubmit(member.id, {
                role: roleKeys.length > 0 ? role : (member && member.accountRole ? member.accountRole : ''),
                manualLogin: manualLogin.trim(),
                manualPassword: manualPassword.trim(),
            })
                .then(function (result) {
                    var message = result && result.message ? result.message : (isLinked ? successUpdateLabel : successCreateLabel);
                    setStatusMessage(message);

                    if (result && result.member_login) {
                        setManualLogin(result.member_login);
                    } else if (result && result.login) {
                        setManualLogin(result.login);
                    }

                    if (result && result.generated_password) {
                        setManualPassword(result.generated_password);
                        setCopyFeedback('');
                    }

                    setResultData({
                        login: result ? (result.member_login || result.login || manualLogin.trim()) : manualLogin.trim(),
                        generatedPassword: result && result.generated_password ? result.generated_password : manualPassword.trim(),
                        created: !!(result && result.user_id) || !!(result && result.created),
                    });

                    setSubmitting(false);
                })
                .catch(function (err) {
                    setSubmitting(false);
                    setError(err && err.message ? err.message : 'Erreur lors de la mise à jour.');
                });
        }, [submitting, member, onSubmit, role, manualLogin, manualPassword, noRolesMessage, isLinked, successUpdateLabel, successCreateLabel]);

        var handleResetEmail = useCallback(function () {
            if (resetting || typeof onResetPassword !== 'function' || !member || !member.id) {
                return;
            }

            setResetting(true);
            setStatusMessage('');

            Promise.resolve(onResetPassword(member.id))
                .then(function () {
                    setStatusMessage(resetEmailSuccess);
                })
                .catch(function (err) {
                    setError(err && err.message ? err.message : 'Erreur lors de l\'envoi de l\'email.');
                })
                .finally(function () {
                    setResetting(false);
                });
        }, [resetting, onResetPassword, member, resetEmailSuccess]);

        var submitLabel = isLinked ? submitUpdateLabel : submitCreateLabel;
        var disableSubmit = submitting || (roleKeys.length > 0 && !role);

        var footer = h(Fragment, null, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary',
                onClick: onClose,
                disabled: submitting,
            }, getString(strings, 'cancel', 'Annuler')),
            h('button', {
                type: 'submit',
                form: 'member-account-form',
                class: 'mj-btn mj-btn--primary',
                disabled: disableSubmit,
            }, submitting ? getString(strings, 'loading', 'Chargement...') : submitLabel),
        ]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: modalTitle,
            size: 'medium',
            footer: footer,
        }, [
            !member && h('p', { class: 'mj-regmgr-modal__description' }, getString(strings, 'loading', 'Chargement...')),

            member && h('form', {
                id: 'member-account-form',
                class: 'mj-regmgr-form',
                onSubmit: handleSubmit,
            }, [
                h('p', { class: 'mj-regmgr-modal__description' }, modalDescription),
                memberName && h('p', { class: 'mj-regmgr-modal__description' }, accountLabel + ' · ' + memberName),
                h('p', { class: 'mj-regmgr-modal__description' }, isLinked ? accountLinkedText : accountUnlinkedText),

                statusMessage && h('p', { class: 'mj-regmgr-modal__description' }, statusMessage),
                error && h('div', { class: 'mj-regmgr-alert mj-regmgr-alert--error' }, error),

                roleKeys.length === 0 && h('p', { class: 'mj-regmgr-modal__description' }, noRolesMessage),

                roleKeys.length > 0 && h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'member-account-role' }, roleLabel + ' *'),
                    h('select', {
                        id: 'member-account-role',
                        class: 'mj-regmgr-select',
                        value: role,
                        onChange: function (e) { setRole(e.target.value); },
                        disabled: submitting,
                    }, [
                        role === '' && h('option', { value: '' }, rolePlaceholder),
                        roleKeys.map(function (key) {
                            return h('option', { key: key, value: key }, rolesMap[key]);
                        }),
                    ]),
                ]),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'member-account-login' }, loginLabel),
                    h('input', {
                        type: 'text',
                        id: 'member-account-login',
                        class: 'mj-regmgr-input',
                        value: manualLogin,
                        onInput: function (e) { setManualLogin(e.target.value); },
                        placeholder: loginPlaceholder,
                        disabled: submitting,
                    }),
                    h('p', { class: 'mj-regmgr-modal__description' }, loginHelp),
                ]),

                h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'member-account-password' }, passwordLabel),
                    h('input', {
                        type: 'text',
                        id: 'member-account-password',
                        class: 'mj-regmgr-input',
                        value: manualPassword,
                        onInput: function (e) {
                            setManualPassword(e.target.value);
                            setCopyFeedback('');
                        },
                        placeholder: '••••••••',
                        disabled: submitting,
                    }),
                    h('p', { class: 'mj-regmgr-modal__description' }, passwordHelp),
                    h('div', { class: 'mj-regmgr-modal__actions' }, [
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--secondary mj-btn--small',
                            onClick: generatePassword,
                            disabled: submitting,
                        }, generatePasswordLabel),
                        manualPassword && h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--ghost mj-btn--small',
                            onClick: handleCopyPassword,
                            disabled: submitting,
                        }, copyPasswordLabel),
                    ]),
                    copyFeedback && h('p', { class: 'mj-regmgr-modal__description' }, copyFeedback),
                ]),

                claimLink && h('div', { class: 'mj-regmgr-form__group' }, [
                    h('label', { for: 'member-account-claim-link' }, claimLinkLabel),
                    h('input', {
                        type: 'text',
                        id: 'member-account-claim-link',
                        class: 'mj-regmgr-input',
                        value: claimLink,
                        readOnly: true,
                        onFocus: function (e) { e.target.select(); },
                    }),
                    h('div', { class: 'mj-regmgr-modal__actions' }, [
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--ghost mj-btn--small',
                            onClick: handleCopyClaimLink,
                        }, copyLinkLabel),
                    ]),
                    h('p', { class: 'mj-regmgr-modal__description' }, claimLinkHelp),
                    linkCopyFeedback && h('p', { class: 'mj-regmgr-modal__description' }, linkCopyFeedback),
                ]),

                resultData && h('div', { class: 'mj-regmgr-form__group' }, [
                    resultData.login && h('p', { class: 'mj-regmgr-modal__description' }, accountLabel + ' · ' + resultData.login),
                    resultData.generatedPassword && h('p', { class: 'mj-regmgr-modal__description' }, passwordLabel + ' · ' + resultData.generatedPassword),
                ]),

                typeof onResetPassword === 'function' && isLinked && h('div', { class: 'mj-regmgr-form__group' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost mj-btn--small',
                        onClick: handleResetEmail,
                        disabled: resetting,
                    }, resetting ? getString(strings, 'loading', 'Chargement...') : resetEmailLabel),
                ]),
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

    function LocationModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose || function () {};
        var mode = props.mode === 'edit' ? 'edit' : 'create';
        var loading = !!props.loading;
        var saving = !!props.saving;
        var location = props.location || null;
        var strings = props.strings || {};
        var onSubmit = props.onSubmit || function () {};

        var formId = useMemo(function () {
            return 'mj-regmgr-location-' + Math.random().toString(36).slice(2);
        }, []);

        var initialState = useMemo(function () {
            var payload = location || {};

            var coverId = 0;
            var rawCoverId = payload.cover_id !== undefined ? payload.cover_id : payload.coverId;
            if (rawCoverId !== undefined && rawCoverId !== null && rawCoverId !== '') {
                var parsedCoverId = parseInt(rawCoverId, 10);
                if (!isNaN(parsedCoverId)) {
                    coverId = parsedCoverId;
                }
            }

            var coverUrl = '';
            if (typeof payload.cover_url === 'string' && payload.cover_url.trim() !== '') {
                coverUrl = payload.cover_url.trim();
            } else if (typeof payload.coverUrl === 'string' && payload.coverUrl.trim() !== '') {
                coverUrl = payload.coverUrl.trim();
            }

            var coverAdminUrl = '';
            if (typeof payload.cover_admin_url === 'string' && payload.cover_admin_url.trim() !== '') {
                coverAdminUrl = payload.cover_admin_url.trim();
            } else if (typeof payload.coverAdminUrl === 'string' && payload.coverAdminUrl.trim() !== '') {
                coverAdminUrl = payload.coverAdminUrl.trim();
            }

            return {
                name: payload.name || '',
                address_line: payload.address_line || '',
                postal_code: payload.postal_code || '',
                city: payload.city || '',
                country: payload.country || '',
                map_query: payload.map_query || '',
                latitude: payload.latitude !== undefined && payload.latitude !== null ? String(payload.latitude) : '',
                longitude: payload.longitude !== undefined && payload.longitude !== null ? String(payload.longitude) : '',
                notes: payload.notes || '',
                cover_id: coverId,
                coverId: coverId,
                cover_url: coverUrl,
                coverUrl: coverUrl,
                cover_admin_url: coverAdminUrl,
                coverAdminUrl: coverAdminUrl,
            };
        }, [location]);

        var _formState = useState(initialState);
        var formState = _formState[0];
        var setFormState = _formState[1];

        var mediaFrameRef = useRef(null);

        useEffect(function () {
            setFormState(initialState);
        }, [initialState, isOpen]);

        var handleFieldChange = useCallback(function (field) {
            return function (event) {
                var value = event && event.target ? event.target.value : '';
                setFormState(function (prev) {
                    var next = Object.assign({}, prev);
                    next[field] = value;
                    return next;
                });
            };
        }, []);

        var handleClose = useCallback(function () {
            if (saving) {
                return;
            }
            onClose();
        }, [saving, onClose]);

        var coverMeta = useMemo(function () {
            var source = formState || {};
            var rawCoverId = source.cover_id !== undefined ? source.cover_id : source.coverId;
            var coverId = 0;
            if (rawCoverId !== undefined && rawCoverId !== null && rawCoverId !== '') {
                var parsedId = parseInt(rawCoverId, 10);
                if (!isNaN(parsedId)) {
                    coverId = parsedId;
                }
            }
            var coverUrl = '';
            if (typeof source.cover_url === 'string' && source.cover_url.trim() !== '') {
                coverUrl = source.cover_url.trim();
            } else if (typeof source.coverUrl === 'string' && source.coverUrl.trim() !== '') {
                coverUrl = source.coverUrl.trim();
            }
            var coverAdminUrl = '';
            if (typeof source.cover_admin_url === 'string' && source.cover_admin_url.trim() !== '') {
                coverAdminUrl = source.cover_admin_url.trim();
            } else if (typeof source.coverAdminUrl === 'string' && source.coverAdminUrl.trim() !== '') {
                coverAdminUrl = source.coverAdminUrl.trim();
            }
            return {
                id: coverId,
                url: coverUrl,
                adminUrl: coverAdminUrl,
            };
        }, [formState]);

        var wpMediaAvailable = !!(global.wp && global.wp.media && typeof global.wp.media === 'function');

        var handleSelectCover = useCallback(function () {
            if (!wpMediaAvailable) {
                return;
            }
            var wpGlobal = global.wp;
            if (!mediaFrameRef.current) {
                mediaFrameRef.current = wpGlobal.media({
                    title: getString(strings, 'locationCoverSelectModalTitle', 'Choisir un visuel pour ce lieu'),
                    button: { text: getString(strings, 'locationCoverSelectButton', 'Choisir un visuel') },
                    multiple: false,
                    library: { type: 'image' },
                });
                mediaFrameRef.current.on('select', function () {
                    var frame = mediaFrameRef.current;
                    if (!frame) {
                        return;
                    }
                    var state = typeof frame.state === 'function' ? frame.state() : frame.state;
                    if (!state || typeof state.get !== 'function') {
                        return;
                    }
                    var selection = state.get('selection');
                    if (!selection || typeof selection.first !== 'function') {
                        return;
                    }
                    var attachment = selection.first();
                    if (!attachment || typeof attachment.toJSON !== 'function') {
                        return;
                    }
                    var details = attachment.toJSON();
                    var id = details && details.id ? parseInt(details.id, 10) || 0 : 0;
                    var url = '';
                    if (details) {
                        if (details.sizes && details.sizes.medium && details.sizes.medium.url) {
                            url = details.sizes.medium.url;
                        } else if (details.url) {
                            url = details.url;
                        }
                    }
                    setFormState(function (prev) {
                        var next = Object.assign({}, prev);
                        next.cover_id = id;
                        next.coverId = id;
                        next.cover_url = url;
                        next.coverUrl = url;
                        return next;
                    });
                });
            }

            var frameInstance = mediaFrameRef.current;
            if (!frameInstance) {
                return;
            }

            var syncSelection = function () {
                var state = typeof frameInstance.state === 'function' ? frameInstance.state() : frameInstance.state;
                if (!state || typeof state.get !== 'function') {
                    return;
                }
                var selection = state.get('selection');
                if (!selection || typeof selection.reset !== 'function') {
                    return;
                }
                selection.reset();

                var currentId = 0;
                if (formState.cover_id !== undefined && formState.cover_id !== null && formState.cover_id !== '') {
                    var parsedExisting = parseInt(formState.cover_id, 10);
                    if (!isNaN(parsedExisting)) {
                        currentId = parsedExisting;
                    }
                } else if (formState.coverId !== undefined && formState.coverId !== null && formState.coverId !== '') {
                    var parsedAlt = parseInt(formState.coverId, 10);
                    if (!isNaN(parsedAlt)) {
                        currentId = parsedAlt;
                    }
                }

                if (currentId <= 0) {
                    return;
                }

                var attachment = wpGlobal.media.attachment(currentId);
                if (!attachment) {
                    return;
                }
                if (typeof attachment.fetch === 'function') {
                    attachment.fetch();
                }
                selection.add(attachment);
            };

            if (typeof frameInstance.once === 'function') {
                frameInstance.once('open', syncSelection);
            } else if (typeof frameInstance.on === 'function') {
                frameInstance.on('open', function handleOpenOnce() {
                    if (typeof frameInstance.off === 'function') {
                        frameInstance.off('open', handleOpenOnce);
                    }
                    syncSelection();
                });
            } else {
                syncSelection();
            }

            frameInstance.open();
        }, [wpMediaAvailable, strings, formState, setFormState]);

        var handleRemoveCover = useCallback(function () {
            setFormState(function (prev) {
                var next = Object.assign({}, prev);
                next.cover_id = 0;
                next.coverId = 0;
                next.cover_url = '';
                next.coverUrl = '';
                return next;
            });
        }, [setFormState]);

        var mapPreviewSrc = useMemo(function () {
            return buildLocationPreviewUrl(formState);
        }, [formState]);

        var mapExternalUrl = useMemo(function () {
            return buildLocationExternalUrl(formState);
        }, [formState]);

        var canSubmit = !loading && !saving && typeof formState.name === 'string' && formState.name.trim() !== '';

        var handleSubmit = useCallback(function (event) {
            if (event) {
                event.preventDefault();
            }
            if (!canSubmit) {
                return;
            }
            onSubmit(Object.assign({}, formState));
        }, [canSubmit, formState, onSubmit]);

        var title = mode === 'edit'
            ? getString(strings, 'locationEditTitle', 'Modifier le lieu')
            : getString(strings, 'locationCreateTitle', 'Nouveau lieu');

        return h(Modal, {
            isOpen: isOpen,
            onClose: handleClose,
            title: title,
            size: 'large',
            footer: h(Fragment, null, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost',
                    onClick: handleClose,
                    disabled: saving,
                }, getString(strings, 'cancel', 'Annuler')),
                h('button', {
                    type: 'submit',
                    form: formId,
                    class: 'mj-btn mj-btn--primary',
                    disabled: !canSubmit,
                }, saving
                    ? getString(strings, 'locationSaving', 'Enregistrement du lieu...')
                    : getString(strings, 'locationSaveButton', 'Enregistrer le lieu')),
            ]),
        }, loading
            ? h('div', { class: 'mj-regmgr-loading mj-location-form__loading' }, [
                h('div', { class: 'mj-regmgr-loading__spinner' }),
                h('p', { class: 'mj-regmgr-loading__text' }, getString(strings, 'locationModalLoading', 'Chargement du lieu...')),
            ])
            : h('form', {
                id: formId,
                class: 'mj-location-form',
                onSubmit: handleSubmit,
            }, [
                h('div', { class: 'mj-location-form__grid' }, [
                    h('div', { class: 'mj-location-form__field mj-location-form__field--full' }, [
                        h('label', null, getString(strings, 'locationNameLabel', 'Nom du lieu') + ' *'),
                        h('input', {
                            type: 'text',
                            value: formState.name,
                            onInput: handleFieldChange('name'),
                            required: true,
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field mj-location-form__field--full' }, [
                        h('label', null, getString(strings, 'locationAddressLabel', 'Adresse')),
                        h('input', {
                            type: 'text',
                            value: formState.address_line,
                            onInput: handleFieldChange('address_line'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field' }, [
                        h('label', null, getString(strings, 'locationPostalCodeLabel', 'Code postal')),
                        h('input', {
                            type: 'text',
                            value: formState.postal_code,
                            onInput: handleFieldChange('postal_code'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field' }, [
                        h('label', null, getString(strings, 'locationCityLabel', 'Ville')),
                        h('input', {
                            type: 'text',
                            value: formState.city,
                            onInput: handleFieldChange('city'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field' }, [
                        h('label', null, getString(strings, 'locationCountryLabel', 'Pays')),
                        h('input', {
                            type: 'text',
                            value: formState.country,
                            onInput: handleFieldChange('country'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field mj-location-form__field--full' }, [
                        h('label', null, getString(strings, 'locationMapQueryLabel', 'Recherche Google Maps')),
                        h('input', {
                            type: 'text',
                            value: formState.map_query,
                            onInput: handleFieldChange('map_query'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field' }, [
                        h('label', null, getString(strings, 'locationLatitudeLabel', 'Latitude')),
                        h('input', {
                            type: 'text',
                            value: formState.latitude,
                            onInput: handleFieldChange('latitude'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field' }, [
                        h('label', null, getString(strings, 'locationLongitudeLabel', 'Longitude')),
                        h('input', {
                            type: 'text',
                            value: formState.longitude,
                            onInput: handleFieldChange('longitude'),
                        }),
                    ]),
                    h('div', { class: 'mj-location-form__field mj-location-form__field--full' }, [
                        h('label', null, getString(strings, 'locationNotesLabel', 'Notes internes')),
                        h('textarea', {
                            rows: 3,
                            value: formState.notes,
                            onInput: handleFieldChange('notes'),
                        }),
                    ]),
                ]),
                h('div', { class: 'mj-location-form__preview' }, [
                    h('div', { class: 'mj-location-form__cover' }, [
                        h('span', { class: 'mj-location-form__cover-label' }, getString(strings, 'locationCoverLabel', 'Visuel du lieu')),
                        coverMeta.url
                            ? h('img', {
                                class: 'mj-location-form__cover-image',
                                src: coverMeta.url,
                                alt: '',
                                loading: 'lazy',
                            })
                            : h('span', { class: 'mj-location-form__cover-empty' }, getString(strings, 'locationCoverEmpty', 'Aucun visuel défini pour ce lieu.')),
                        (function () {
                            var actions = [];
                            if (wpMediaAvailable) {
                                actions.push(h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--secondary mj-btn--small',
                                    onClick: handleSelectCover,
                                    disabled: saving,
                                }, getString(strings, 'locationCoverSelectButton', 'Choisir un visuel')));

                                if (coverMeta.id > 0) {
                                    actions.push(h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--ghost mj-btn--small',
                                        onClick: handleRemoveCover,
                                        disabled: saving,
                                    }, getString(strings, 'locationCoverRemoveButton', 'Retirer le visuel')));
                                }
                            }

                            if (actions.length === 0) {
                                return null;
                            }

                            return h('div', { class: 'mj-location-form__cover-actions' }, actions);
                        })(),
                    ]),
                    h('h2', null, getString(strings, 'locationPreviewLabel', 'Aperçu de la carte')),
                    mapPreviewSrc
                        ? h('iframe', {
                            src: mapPreviewSrc,
                            title: getString(strings, 'locationPreviewLabel', 'Aperçu de la carte'),
                            loading: 'lazy',
                            referrerPolicy: 'no-referrer-when-downgrade',
                        })
                        : h('p', { class: 'mj-location-form__preview-empty' }, getString(strings, 'locationModalEmpty', 'Impossible d\'afficher les détails de ce lieu.')),
                    mapExternalUrl && h('a', {
                        href: mapExternalUrl,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        class: 'mj-location-form__preview-link',
                    }, getString(strings, 'locationOpenExternal', 'Ouvrir dans Google Maps')),
                ]),
            ]));
    }

    // ============================================
    // AVATAR CAPTURE MODAL
    // ============================================

    function AvatarCaptureModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose || function () {};
        var onCapture = typeof props.onCapture === 'function' ? props.onCapture : null;
        var strings = props.strings || {};
        var member = props.member || null;

        var videoRef = useRef(null);
        var canvasRef = useRef(null);
        var streamRef = useRef(null);

        var _initializing = useState(false);
        var initializing = _initializing[0];
        var setInitializing = _initializing[1];

        var _error = useState('');
        var error = _error[0];
        var setError = _error[1];

        var _snapshot = useState(null);
        var snapshot = _snapshot[0];
        var setSnapshot = _snapshot[1];

        var _saving = useState(false);
        var saving = _saving[0];
        var setSaving = _saving[1];

        var title = getString(strings, 'memberAvatarCaptureTitle', 'Prendre une photo');
        var instructions = getString(strings, 'memberAvatarCaptureInstructions', 'Positionnez le membre dans le cadre puis appuyez sur "Capturer"');
        var permissionHint = getString(strings, 'memberAvatarCaptureGrant', 'Autorisez l\'accès à la caméra si demandé.');
        var captureLabel = getString(strings, 'memberAvatarCaptureTake', 'Capturer');
        var retakeLabel = getString(strings, 'memberAvatarCaptureRetake', 'Reprendre');
        var confirmLabel = getString(strings, 'memberAvatarCaptureConfirm', 'Utiliser cette photo');
        var cancelLabel = getString(strings, 'memberAvatarCaptureCancel', 'Annuler');
        var unsupportedMessage = getString(strings, 'memberAvatarCaptureUnsupported', 'La capture photo n\'est pas supportée sur ce navigateur.');
        var genericError = getString(strings, 'memberAvatarCaptureError', 'Impossible d\'accéder à la caméra.');
        var savingLabel = getString(strings, 'memberAvatarCaptureSaving', 'Enregistrement...');

        var memberName = member ? ((member.firstName || '') + ' ' + (member.lastName || '')).trim() : '';

        var stopStream = useCallback(function () {
            var current = streamRef.current;
            if (current && current.getTracks) {
                current.getTracks().forEach(function (track) {
                    try {
                        track.stop();
                    } catch (e) {
                        // ignore
                    }
                });
            }
            streamRef.current = null;
            if (videoRef.current) {
                videoRef.current.srcObject = null;
            }
        }, []);

        var startStream = useCallback(function () {
            stopStream();
            if (!global.navigator || !global.navigator.mediaDevices || typeof global.navigator.mediaDevices.getUserMedia !== 'function') {
                setError(unsupportedMessage);
                return;
            }
            setInitializing(true);
            setError('');
            global.navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
                .then(function (stream) {
                    streamRef.current = stream;
                    if (videoRef.current) {
                        videoRef.current.srcObject = stream;
                        var playPromise = videoRef.current.play();
                        if (playPromise && typeof playPromise.catch === 'function') {
                            playPromise.catch(function (err) {
                                console.warn('[MjRegMgr] Impossible de lancer la vidéo de capture', err);
                            });
                        }
                    }
                })
                .catch(function (err) {
                    var message = genericError;
                    if (err && err.message) {
                        message += ' ' + err.message;
                    }
                    setError(message);
                })
                .finally(function () {
                    setInitializing(false);
                });
        }, [genericError, stopStream, unsupportedMessage]);

        useEffect(function () {
            if (!isOpen) {
                stopStream();
                setSaving(false);
                setInitializing(false);
                setError('');
                setSnapshot(function (prev) {
                    if (prev && prev.url) {
                        global.URL.revokeObjectURL(prev.url);
                    }
                    return null;
                });
                return;
            }

            setSaving(false);
            setError('');
            setSnapshot(function (prev) {
                if (prev && prev.url) {
                    global.URL.revokeObjectURL(prev.url);
                }
                return null;
            });
            startStream();

            return function () {
                stopStream();
            };
        }, [isOpen, startStream, stopStream]);

        useEffect(function () {
            return function () {
                var snap = snapshot;
                if (snap && snap.url) {
                    global.URL.revokeObjectURL(snap.url);
                }
            };
        }, [snapshot]);

        var handleCapture = useCallback(function () {
            if (initializing || saving || snapshot) {
                return;
            }
            var video = videoRef.current;
            var canvas = canvasRef.current;
            if (!video || !canvas) {
                return;
            }
            var width = video.videoWidth;
            var height = video.videoHeight;
            if (!width || !height) {
                setError(genericError);
                return;
            }

            canvas.width = width;
            canvas.height = height;
            var context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, width, height);

            var handleBlob = function (blob) {
                if (!blob) {
                    setError(genericError);
                    return;
                }
                var objectUrl = global.URL.createObjectURL(blob);
                setSnapshot({ blob: blob, url: objectUrl });
                stopStream();
            };

            if (typeof canvas.toBlob === 'function') {
                canvas.toBlob(function (blob) {
                    handleBlob(blob);
                }, 'image/jpeg', 0.92);
            } else {
                var dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                handleBlob(dataUrlToBlob(dataUrl));
            }
        }, [genericError, initializing, saving, snapshot, stopStream]);

        var handleRetake = useCallback(function () {
            if (snapshot && snapshot.url) {
                global.URL.revokeObjectURL(snapshot.url);
            }
            setSnapshot(null);
            setError('');
            if (isOpen) {
                startStream();
            }
        }, [isOpen, snapshot, startStream]);

        var handleConfirm = useCallback(function () {
            if (!snapshot || !snapshot.blob || !onCapture || saving) {
                return;
            }
            setSaving(true);
            Promise.resolve(onCapture(snapshot.blob))
                .then(function () {
                    if (snapshot && snapshot.url) {
                        global.URL.revokeObjectURL(snapshot.url);
                    }
                    setSnapshot(null);
                })
                .catch(function (err) {
                    if (err && err.message) {
                        setError(err.message);
                    } else {
                        setError(genericError);
                    }
                })
                .finally(function () {
                    setSaving(false);
                });
        }, [genericError, onCapture, saving, snapshot]);

        var handleCancel = useCallback(function () {
            if (saving) {
                return;
            }
            onClose();
        }, [onClose, saving]);

        var footer = h('div', { class: 'mj-regmgr-avatar-capture__actions' }, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--ghost',
                onClick: handleCancel,
                disabled: saving,
            }, cancelLabel),
            snapshot ? h(Fragment, null, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary',
                    onClick: handleRetake,
                    disabled: saving,
                }, retakeLabel),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--primary',
                    onClick: handleConfirm,
                    disabled: saving,
                }, saving ? savingLabel : confirmLabel),
            ]) : h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--primary',
                onClick: handleCapture,
                disabled: initializing || saving || !!error,
            }, initializing ? savingLabel : captureLabel),
        ]);

        return h(Modal, {
            isOpen: isOpen,
            onClose: handleCancel,
            title: title,
            size: 'large',
            footer: footer,
        }, [
            h('div', { class: 'mj-regmgr-avatar-capture' }, [
                memberName && h('p', { class: 'mj-regmgr-avatar-capture__member' }, memberName),
                h('div', { class: 'mj-regmgr-avatar-capture__viewer' }, [
                    snapshot
                        ? h('img', {
                            src: snapshot.url,
                            alt: title,
                            class: 'mj-regmgr-avatar-capture__preview',
                        })
                        : h('video', {
                            ref: videoRef,
                            class: 'mj-regmgr-avatar-capture__video',
                            playsInline: true,
                            autoPlay: true,
                            muted: true,
                        }),
                    h('canvas', {
                        ref: canvasRef,
                        class: 'mj-regmgr-avatar-capture__canvas',
                    }),
                ]),
                h('p', { class: 'mj-regmgr-avatar-capture__instructions' }, instructions),
                !snapshot && !error && h('p', { class: 'mj-regmgr-avatar-capture__hint' }, permissionHint),
                error && h('div', { class: 'mj-regmgr-avatar-capture__error' }, error),
            ]),
        ]);
    }

    global.MjRegMgrModals = {
        Modal: Modal,
        AddParticipantModal: AddParticipantModal,
        CreateEventModal: CreateEventModal,
        CreateMemberModal: CreateMemberModal,
        MemberNotesModal: MemberNotesModal,
        MemberAccountModal: MemberAccountModal,
        QRCodeModal: QRCodeModal,
        OccurrencesModal: OccurrencesModal,
        LocationModal: LocationModal,
        AvatarCaptureModal: AvatarCaptureModal,
    };

})(window);
