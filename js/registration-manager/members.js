/**
 * Registration Manager - Members Components
 * Composants pour la gestion des membres
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var RegComps = global.MjRegMgrRegistrations;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour members.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;

    var formatDate = Utils.formatDate;
    var classNames = Utils.classNames;
    var getString = Utils.getString;

    var MemberAvatar = RegComps ? RegComps.MemberAvatar : function () { return null; };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    /**
     * Calculate age from birth date
     * @param {string} birthDate - Date string in YYYY-MM-DD format
     * @returns {number|null} Age in years or null if invalid
     */
    function calculateAge(birthDate) {
        if (!birthDate) return null;
        var birth = new Date(birthDate);
        if (isNaN(birth.getTime())) return null;
        
        var today = new Date();
        var age = today.getFullYear() - birth.getFullYear();
        var monthDiff = today.getMonth() - birth.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        
        return age >= 0 ? age : null;
    }

    // ============================================
    // MEMBER CARD (for sidebar list)
    // ============================================

    function MemberCard(props) {
        var member = props.member;
        var isSelected = props.isSelected;
        var onClick = props.onClick;
        var strings = props.strings;

        var roleLabels = {
            'jeune': 'Jeune',
            'animateur': 'Animateur',
            'tuteur': 'Tuteur',
            'benevole': 'Bénévole',
            'coordinateur': 'Coordinateur',
        };

        var membershipLabels = {
            'paid': 'Cotisation OK',
            'expired': 'Cotisation expirée',
            'unpaid': 'Cotisation due',
            'not_required': '', // Ne pas afficher si pas de cotisation requise
        };

        return h('div', {
            class: classNames('mj-regmgr-member-card', {
                'mj-regmgr-member-card--selected': isSelected,
            }),
            onClick: function () { onClick(member); },
            role: 'button',
            tabIndex: 0,
            onKeyDown: function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onClick(member);
                }
            },
        }, [
            h(MemberAvatar, { member: member, size: 'medium' }),
            h('div', { class: 'mj-regmgr-member-card__content' }, [
                h('div', { class: 'mj-regmgr-member-card__name' }, 
                    (member.firstName || '') + ' ' + (member.lastName || '')
                ),
                h('div', { class: 'mj-regmgr-member-card__meta' }, [
                    member.role && h('span', { class: 'mj-regmgr-member-card__role' }, 
                        roleLabels[member.role] || member.role
                    ),
                    member.membershipStatus && member.membershipStatus !== 'not_required' && h('span', { 
                        class: classNames('mj-regmgr-member-card__membership', {
                            'mj-regmgr-member-card__membership--paid': member.membershipStatus === 'paid',
                            'mj-regmgr-member-card__membership--expired': member.membershipStatus === 'expired',
                            'mj-regmgr-member-card__membership--unpaid': member.membershipStatus === 'unpaid',
                        })
                    }, membershipLabels[member.membershipStatus] || ''),
                ]),
            ]),
        ]);
    }

    // ============================================
    // MEMBERS LIST
    // ============================================

    function MembersList(props) {
        var members = props.members || [];
        var loading = props.loading;
        var selectedMemberId = props.selectedMemberId;
        var onSelectMember = props.onSelectMember;
        var strings = props.strings;
        var onLoadMore = props.onLoadMore;
        var hasMore = props.hasMore;
        var loadingMore = props.loadingMore;

        if (loading && members.length === 0) {
            return h('div', { class: 'mj-regmgr-members-list mj-regmgr-members-list--loading' }, [
                h('div', { class: 'mj-regmgr-spinner' }),
                h('p', null, getString(strings, 'loading', 'Chargement...')),
            ]);
        }

        if (members.length === 0) {
            return h('div', { class: 'mj-regmgr-members-list mj-regmgr-members-list--empty' }, [
                h('div', { class: 'mj-regmgr-members-list__empty-icon' }, [
                    h('svg', {
                        width: 48,
                        height: 48,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 1.5,
                    }, [
                        h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                        h('circle', { cx: 9, cy: 7, r: 4 }),
                        h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                        h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                    ]),
                ]),
                h('p', null, getString(strings, 'noMembers', 'Aucun membre trouvé.')),
            ]);
        }

        return h('div', { class: 'mj-regmgr-members-list' }, [
            members.map(function (member) {
                return h(MemberCard, {
                    key: member.id,
                    member: member,
                    isSelected: member.id === selectedMemberId,
                    onClick: onSelectMember,
                    strings: strings,
                });
            }),

            // Bouton "Charger plus"
            hasMore && h('div', { class: 'mj-regmgr-members-list__load-more' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary mj-btn--small',
                    onClick: onLoadMore,
                    disabled: loadingMore,
                }, loadingMore 
                    ? getString(strings, 'loading', 'Chargement...') 
                    : 'Voir plus'
                ),
            ]),
        ]);
    }

    // ============================================
    // MEMBER DETAIL PANEL
    // ============================================

    function MemberDetailPanel(props) {
        var member = props.member;
        var loading = props.loading;
        var strings = props.strings;
        var config = props.config;
        var notes = props.notes || [];
        var registrations = props.registrations || [];
        var onSaveNote = props.onSaveNote;
        var onDeleteNote = props.onDeleteNote;
        var onUpdateMember = props.onUpdateMember;
        var onPayMembershipOnline = props.onPayMembershipOnline;
        var onMarkMembershipPaid = props.onMarkMembershipPaid;

        var _editMode = useState(false);
        var editMode = _editMode[0];
        var setEditMode = _editMode[1];

        var _editData = useState({});
        var editData = _editData[0];
        var setEditData = _editData[1];

        var _newNote = useState('');
        var newNote = _newNote[0];
        var setNewNote = _newNote[1];

        var _savingNote = useState(false);
        var savingNote = _savingNote[0];
        var setSavingNote = _savingNote[1];

        var _showPaymentModal = useState(false);
        var showPaymentModal = _showPaymentModal[0];
        var setShowPaymentModal = _showPaymentModal[1];

        var _paymentProcessing = useState(false);
        var paymentProcessing = _paymentProcessing[0];
        var setPaymentProcessing = _paymentProcessing[1];

        // Reset edit data when member changes
        useEffect(function () {
            if (member) {
                setEditData({
                    firstName: member.firstName || '',
                    lastName: member.lastName || '',
                    email: member.email || '',
                    phone: member.phone || '',
                    birthDate: member.birthDate || '',
                });
                setEditMode(false);
            }
        }, [member ? member.id : null]);

        if (loading) {
            return h('div', { class: 'mj-regmgr-member-detail mj-regmgr-member-detail--loading' }, [
                h('div', { class: 'mj-regmgr-loading' }, [
                    h('div', { class: 'mj-regmgr-loading__spinner' }),
                    h('p', { class: 'mj-regmgr-loading__text' }, 'Chargement...'),
                ]),
            ]);
        }

        if (!member) {
            return null;
        }

        var roleLabels = {
            'jeune': 'Jeune',
            'animateur': 'Animateur',
            'tuteur': 'Tuteur',
            'benevole': 'Bénévole',
            'coordinateur': 'Coordinateur',
        };

        var membershipLabels = {
            'paid': 'Cotisation payée',
            'expired': 'Cotisation expirée',
            'unpaid': 'Cotisation due',
            'not_required': 'Non soumis à cotisation',
        };

        var statusLabels = {
            'active': 'Actif',
            'inactive': 'Inactif',
        };

        var handleFieldChange = function (field) {
            return function (e) {
                var newData = Object.assign({}, editData);
                newData[field] = e.target.value;
                setEditData(newData);
            };
        };

        var handleSave = function () {
            onUpdateMember(member.id, editData);
            setEditMode(false);
        };

        var handleAddNote = function () {
            if (!newNote.trim()) return;
            setSavingNote(true);
            onSaveNote(member.id, newNote.trim())
                .then(function () {
                    setNewNote('');
                })
                .finally(function () {
                    setSavingNote(false);
                });
        };

        // Calculate age
        var age = null;
        if (member.birthDate) {
            var birth = new Date(member.birthDate);
            var today = new Date();
            age = today.getFullYear() - birth.getFullYear();
            var m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
        }

        return h('div', { class: 'mj-regmgr-member-detail' }, [
            // Header avec avatar
            h('div', { class: 'mj-regmgr-member-detail__header' }, [
                h(MemberAvatar, { member: member, size: 'large' }),
                h('div', { class: 'mj-regmgr-member-detail__identity' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__name' }, 
                        (member.firstName || '') + ' ' + (member.lastName || '')
                    ),
                    member.role && h('span', { 
                        class: 'mj-regmgr-badge mj-regmgr-badge--role-' + member.role 
                    }, roleLabels[member.role] || member.role),
                ]),
                !editMode && h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--icon mj-btn--secondary',
                    onClick: function () { setEditMode(true); },
                    title: 'Modifier',
                }, [
                    h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                        h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                    ]),
                ]),
            ]),

            h('div', { class: 'mj-regmgr-member-detail__content' }, [
                // Section informations
                h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 'Informations'),

                    editMode ? [
                        // Mode édition
                        h('div', { class: 'mj-regmgr-form-grid' }, [
                            h('div', { class: 'mj-regmgr-form-group' }, [
                                h('label', null, 'Prénom'),
                                h('input', {
                                    type: 'text',
                                    class: 'mj-regmgr-input',
                                    value: editData.firstName,
                                    onInput: handleFieldChange('firstName'),
                                }),
                            ]),
                            h('div', { class: 'mj-regmgr-form-group' }, [
                                h('label', null, 'Nom'),
                                h('input', {
                                    type: 'text',
                                    class: 'mj-regmgr-input',
                                    value: editData.lastName,
                                    onInput: handleFieldChange('lastName'),
                                }),
                            ]),
                            h('div', { class: 'mj-regmgr-form-group' }, [
                                h('label', null, 'Email'),
                                h('input', {
                                    type: 'email',
                                    class: 'mj-regmgr-input',
                                    value: editData.email,
                                    onInput: handleFieldChange('email'),
                                }),
                            ]),
                            h('div', { class: 'mj-regmgr-form-group' }, [
                                h('label', null, 'Téléphone'),
                                h('input', {
                                    type: 'tel',
                                    class: 'mj-regmgr-input',
                                    value: editData.phone,
                                    onInput: handleFieldChange('phone'),
                                }),
                            ]),
                            h('div', { class: 'mj-regmgr-form-group' }, [
                                h('label', null, 'Date de naissance'),
                                h('input', {
                                    type: 'date',
                                    class: 'mj-regmgr-input',
                                    value: editData.birthDate,
                                    onInput: handleFieldChange('birthDate'),
                                }),
                            ]),
                        ]),
                        h('div', { class: 'mj-regmgr-member-detail__actions' }, [
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--secondary',
                                onClick: function () { setEditMode(false); },
                            }, 'Annuler'),
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--primary',
                                onClick: handleSave,
                            }, 'Enregistrer'),
                        ]),
                    ] : [
                        // Mode lecture
                        h('div', { class: 'mj-regmgr-member-detail__info' }, [
                            member.email && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z' }),
                                    h('polyline', { points: '22,6 12,13 2,6' }),
                                ]),
                                h('span', null, member.email),
                            ]),
                            member.phone && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z' }),
                                ]),
                                h('span', null, member.phone),
                            ]),
                            (member.birthDate || age !== null) && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                                    h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                                    h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                                    h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                                ]),
                                h('span', null, [
                                    member.birthDate && formatDate(member.birthDate),
                                    age !== null && ' (' + age + ' ans)',
                                ]),
                            ]),
                            member.address && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z' }),
                                    h('circle', { cx: 12, cy: 10, r: 3 }),
                                ]),
                                h('span', null, member.address),
                            ]),
                            member.guardianName && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                                    h('circle', { cx: 9, cy: 7, r: 4 }),
                                    h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                                    h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                                ]),
                                h('span', null, 'Tuteur: ' + member.guardianName),
                            ]),
                        ]),
                    ],
                ]),

                // Section Cotisation & Statut
                h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 'Cotisation & Statut'),
                    h('div', { class: 'mj-regmgr-member-detail__status-grid' }, [
                        // Statut du compte
                        h('div', { class: 'mj-regmgr-member-detail__status-card' }, [
                            h('div', { class: 'mj-regmgr-member-detail__status-label' }, 'Statut du compte'),
                            h('span', { 
                                class: classNames('mj-regmgr-badge', {
                                    'mj-regmgr-badge--success': member.status === 'active',
                                    'mj-regmgr-badge--secondary': member.status !== 'active',
                                })
                            }, statusLabels[member.status] || member.status || 'Actif'),
                        ]),
                        // Cotisation
                        h('div', { class: 'mj-regmgr-member-detail__status-card mj-regmgr-member-detail__status-card--wide' }, [
                            h('div', { class: 'mj-regmgr-member-detail__status-label' }, 'Cotisation'),
                            h('div', { class: 'mj-regmgr-member-detail__membership-row' }, [
                                h('span', { 
                                    class: classNames('mj-regmgr-badge', {
                                        'mj-regmgr-badge--success': member.membershipStatus === 'paid',
                                        'mj-regmgr-badge--warning': member.membershipStatus === 'expired',
                                        'mj-regmgr-badge--danger': member.membershipStatus === 'unpaid',
                                        'mj-regmgr-badge--secondary': member.membershipStatus === 'not_required',
                                    })
                                }, [
                                    membershipLabels[member.membershipStatus] || 'Inconnu',
                                    member.membershipYear && member.membershipStatus === 'paid' && ' (' + member.membershipYear + ')',
                                ]),
                                // Boutons de paiement si cotisation requise et non payée
                                member.requiresPayment && member.membershipStatus !== 'paid' && h('div', { class: 'mj-regmgr-member-detail__membership-actions' }, [
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--small mj-btn--primary',
                                        onClick: function () { setShowPaymentModal(true); },
                                        disabled: paymentProcessing,
                                    }, [
                                        h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                            h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                                            h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                                        ]),
                                        ' Payer',
                                    ]),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--small mj-btn--secondary',
                                        onClick: function () {
                                            if (confirm('Confirmer que la cotisation a été payée en main propre ?')) {
                                                setPaymentProcessing(true);
                                                onMarkMembershipPaid(member.id, 'cash')
                                                    .finally(function () { setPaymentProcessing(false); });
                                            }
                                        },
                                        disabled: paymentProcessing,
                                    }, paymentProcessing ? 'Traitement...' : 'Payé en main propre'),
                                ]),
                            ]),
                        ]),
                        // Numéro de membre
                        member.membershipNumber && h('div', { class: 'mj-regmgr-member-detail__status-card' }, [
                            h('div', { class: 'mj-regmgr-member-detail__status-label' }, 'N° de membre'),
                            h('span', { class: 'mj-regmgr-member-detail__status-value' }, member.membershipNumber),
                        ]),
                        // Date d'inscription
                        member.dateInscription && h('div', { class: 'mj-regmgr-member-detail__status-card' }, [
                            h('div', { class: 'mj-regmgr-member-detail__status-label' }, 'Inscrit depuis'),
                            h('span', { class: 'mj-regmgr-member-detail__status-value' }, formatDate(member.dateInscription)),
                        ]),
                        // Bénévole
                        member.isVolunteer && h('div', { class: 'mj-regmgr-member-detail__status-card' }, [
                            h('div', { class: 'mj-regmgr-member-detail__status-label' }, 'Bénévole'),
                            h('span', { class: 'mj-regmgr-badge mj-regmgr-badge--info' }, 'Oui'),
                        ]),
                        // Autonome
                        member.role === 'jeune' && h('div', { class: 'mj-regmgr-member-detail__status-card' }, [
                            h('div', { class: 'mj-regmgr-member-detail__status-label' }, 'Autonome'),
                            h('span', { 
                                class: classNames('mj-regmgr-badge', {
                                    'mj-regmgr-badge--info': member.isAutonomous,
                                    'mj-regmgr-badge--secondary': !member.isAutonomous,
                                })
                            }, member.isAutonomous ? 'Oui' : 'Non'),
                        ]),
                    ]),
                ]),

                // Section notes
                h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 
                        'Notes (' + notes.length + ')'
                    ),

                    // Ajouter une note
                    h('div', { class: 'mj-regmgr-member-detail__add-note' }, [
                        h('textarea', {
                            class: 'mj-regmgr-textarea',
                            placeholder: 'Ajouter une note...',
                            value: newNote,
                            onInput: function (e) { setNewNote(e.target.value); },
                            rows: 3,
                        }),
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--primary mj-btn--small',
                            onClick: handleAddNote,
                            disabled: savingNote || !newNote.trim(),
                        }, savingNote ? 'Enregistrement...' : 'Ajouter'),
                    ]),

                    // Liste des notes
                    notes.length > 0 && h('div', { class: 'mj-regmgr-member-detail__notes' }, 
                        notes.map(function (note) {
                            return h('div', { key: note.id, class: 'mj-regmgr-note-card' }, [
                                h('div', { class: 'mj-regmgr-note-card__header' }, [
                                    h('span', { class: 'mj-regmgr-note-card__author' }, note.authorName || 'Anonyme'),
                                    h('span', { class: 'mj-regmgr-note-card__date' }, formatDate(note.createdAt)),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--icon mj-btn--ghost mj-btn--danger',
                                        onClick: function () { onDeleteNote(note.id); },
                                        title: 'Supprimer',
                                    }, [
                                        h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                            h('polyline', { points: '3 6 5 6 21 6' }),
                                            h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }),
                                        ]),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-note-card__content' }, note.content),
                            ]);
                        })
                    ),
                ]),

                // Section enfants (si le membre est tuteur)
                member.children && member.children.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 
                        'Enfants (' + member.children.length + ')'
                    ),
                    h('div', { class: 'mj-regmgr-member-detail__children' }, 
                        member.children.map(function (child) {
                            var childAge = child.birthDate ? calculateAge(child.birthDate) : null;
                            return h('div', { key: child.id, class: 'mj-regmgr-child-card' }, [
                                h('div', { class: 'mj-regmgr-child-card__avatar' }, [
                                    child.avatarUrl 
                                        ? h('img', { src: child.avatarUrl, alt: '', class: 'mj-regmgr-child-card__img' })
                                        : h('div', { class: 'mj-regmgr-child-card__initials' }, 
                                            ((child.firstName || '')[0] || '') + ((child.lastName || '')[0] || '')
                                        ),
                                ]),
                                h('div', { class: 'mj-regmgr-child-card__info' }, [
                                    h('div', { class: 'mj-regmgr-child-card__name' }, 
                                        (child.firstName || '') + ' ' + (child.lastName || '')
                                    ),
                                    h('div', { class: 'mj-regmgr-child-card__meta' }, [
                                        child.roleLabel && h('span', { class: 'mj-regmgr-badge mj-regmgr-badge--sm' }, child.roleLabel),
                                        childAge !== null && h('span', null, childAge + ' ans'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-child-card__status' }, [
                                    h('span', { 
                                        class: classNames('mj-regmgr-badge mj-regmgr-badge--sm', {
                                            'mj-regmgr-badge--success': child.membershipStatus === 'paid',
                                            'mj-regmgr-badge--warning': child.membershipStatus === 'expired',
                                            'mj-regmgr-badge--danger': child.membershipStatus === 'unpaid',
                                            'mj-regmgr-badge--secondary': child.membershipStatus === 'not_required',
                                        })
                                    }, membershipLabels[child.membershipStatus] || 'N/A'),
                                    child.membershipYear && h('span', { class: 'mj-regmgr-child-card__year' }, child.membershipYear),
                                ]),
                                h('div', { class: 'mj-regmgr-child-card__actions' }, [
                                    config.adminMemberUrl && h('a', {
                                        href: config.adminMemberUrl + child.id,
                                        target: '_blank',
                                        class: 'mj-btn mj-btn--icon mj-btn--ghost',
                                        title: 'Modifier dans l\'admin',
                                    }, [
                                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                            h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                            h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                        ]),
                                    ]),
                                ]),
                            ]);
                        })
                    ),
                ]),

                // Section historique inscriptions
                registrations.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 
                        'Historique inscriptions (' + registrations.length + ')'
                    ),
                    h('div', { class: 'mj-regmgr-member-detail__registrations' }, 
                        registrations.map(function (reg) {
                            var statusClasses = {
                                'valide': 'mj-regmgr-badge--success',
                                'en_attente': 'mj-regmgr-badge--warning',
                                'annule': 'mj-regmgr-badge--danger',
                            };
                            return h('div', { key: reg.id, class: 'mj-regmgr-registration-item' }, [
                                h('div', { class: 'mj-regmgr-registration-item__info' }, [
                                    h('span', { class: 'mj-regmgr-registration-item__event' }, reg.eventTitle || 'Événement'),
                                    h('span', { class: 'mj-regmgr-registration-item__date' }, formatDate(reg.createdAt)),
                                ]),
                                h('span', { 
                                    class: classNames('mj-regmgr-badge', statusClasses[reg.status] || '')
                                }, reg.statusLabel || reg.status),
                            ]);
                        })
                    ),
                ]),
            ]),

            // Bouton admin
            config.adminMemberUrl && h('div', { class: 'mj-regmgr-member-detail__footer' }, [
                h('a', {
                    href: config.adminMemberUrl + member.id,
                    target: '_blank',
                    class: 'mj-btn mj-btn--secondary',
                }, [
                    h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                        h('polyline', { points: '15 3 21 3 21 9' }),
                        h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                    ]),
                    'Voir dans l\'admin',
                ]),
            ]),

            // Modal de paiement cotisation
            showPaymentModal && h(MembershipPaymentModal, {
                member: member,
                config: config,
                onClose: function () { 
                    setShowPaymentModal(false); 
                },
                onPayOnline: function (memberId) {
                    // Retourne la promesse pour que la modal puisse gérer l'état
                    return onPayMembershipOnline(memberId);
                },
                onMarkPaid: function (method) {
                    // Retourne la promesse pour que la modal puisse gérer l'état
                    return onMarkMembershipPaid(member.id, method)
                        .then(function () {
                            setShowPaymentModal(false);
                        });
                },
            }),
        ]);
    }

    // ============================================
    // MEMBERSHIP PAYMENT MODAL
    // ============================================

    function MembershipPaymentModal(props) {
        var member = props.member;
        var config = props.config;
        var onClose = props.onClose;
        var onPayOnline = props.onPayOnline;
        var onMarkPaid = props.onMarkPaid;

        var currentYear = new Date().getFullYear();
        var membershipPrice = parseFloat(config.membershipPrice) || 2;
        var membershipPriceManual = parseFloat(config.membershipPriceManual) || membershipPrice;

        // État local pour le processing et le QR code
        var _processing = useState(false);
        var processing = _processing[0];
        var setProcessing = _processing[1];

        var _paymentData = useState(null);
        var paymentData = _paymentData[0];
        var setPaymentData = _paymentData[1];

        var _error = useState(null);
        var error = _error[0];
        var setError = _error[1];

        var handleBackdropClick = function (e) {
            if (e.target === e.currentTarget && !processing) {
                onClose();
            }
        };

        var handlePayOnline = function () {
            setProcessing(true);
            setError(null);
            onPayOnline(member.id)
                .then(function (result) {
                    if (result && result.checkoutUrl) {
                        setPaymentData(result);
                    }
                })
                .catch(function (err) {
                    setError(err.message || 'Erreur lors de la création du lien de paiement');
                })
                .finally(function () {
                    setProcessing(false);
                });
        };

        var handleMarkPaid = function () {
            setProcessing(true);
            setError(null);
            onMarkPaid('cash')
                .finally(function () {
                    setProcessing(false);
                });
        };

        // Vue avec QR code après création du lien
        if (paymentData && paymentData.checkoutUrl) {
            return h('div', { 
                class: 'mj-regmgr-modal-backdrop',
                onClick: handleBackdropClick,
            }, [
                h('div', { class: 'mj-regmgr-modal mj-regmgr-modal--small' }, [
                    h('div', { class: 'mj-regmgr-modal__header' }, [
                        h('h2', { class: 'mj-regmgr-modal__title' }, 'Lien de paiement'),
                        h('button', {
                            type: 'button',
                            class: 'mj-regmgr-modal__close',
                            onClick: onClose,
                        }, [
                            h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                                h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                            ]),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-modal__body' }, [
                        // Info membre
                        h('div', { class: 'mj-regmgr-payment-info' }, [
                            h('div', { class: 'mj-regmgr-payment-info__member' }, [
                                h(MemberAvatar, { member: member, size: 'medium' }),
                                h('div', null, [
                                    h('strong', null, (member.firstName || '') + ' ' + (member.lastName || '')),
                                    h('div', { class: 'mj-regmgr-payment-info__detail' }, membershipPrice.toFixed(2) + ' €'),
                                ]),
                            ]),
                        ]),

                        // QR Code
                        paymentData.qrUrl && h('div', { class: 'mj-regmgr-payment-qr' }, [
                            h('img', {
                                src: paymentData.qrUrl,
                                alt: 'QR Code paiement',
                                class: 'mj-regmgr-payment-qr__image',
                            }),
                            h('p', { class: 'mj-regmgr-payment-qr__text' }, 'Scanner ce QR code pour payer'),
                        ]),

                        // Lien direct
                        h('div', { style: { marginTop: '16px', textAlign: 'center' } }, [
                            h('a', {
                                href: paymentData.checkoutUrl,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                class: 'mj-btn mj-btn--primary mj-btn--block',
                            }, [
                                'Ouvrir la page de paiement',
                                h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, style: { marginLeft: '8px' } }, [
                                    h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                                    h('polyline', { points: '15 3 21 3 21 9' }),
                                    h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]);
        }

        // Vue initiale - choix du mode de paiement
        return h('div', { 
            class: 'mj-regmgr-modal-backdrop',
            onClick: handleBackdropClick,
        }, [
            h('div', { class: 'mj-regmgr-modal mj-regmgr-modal--small' }, [
                h('div', { class: 'mj-regmgr-modal__header' }, [
                    h('h2', { class: 'mj-regmgr-modal__title' }, 'Paiement cotisation ' + currentYear),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-modal__close',
                        onClick: onClose,
                        disabled: processing,
                    }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                            h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                        ]),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-modal__body' }, [
                    // Erreur éventuelle
                    error && h('div', { 
                        class: 'mj-regmgr-alert mj-regmgr-alert--error',
                        style: { marginBottom: '16px' },
                    }, error),

                    // Info membre
                    h('div', { class: 'mj-regmgr-payment-info' }, [
                        h('div', { class: 'mj-regmgr-payment-info__member' }, [
                            h(MemberAvatar, { member: member, size: 'medium' }),
                            h('div', null, [
                                h('strong', null, (member.firstName || '') + ' ' + (member.lastName || '')),
                                h('div', { class: 'mj-regmgr-payment-info__detail' }, 'Cotisation annuelle ' + currentYear),
                            ]),
                        ]),
                    ]),

                    h('div', { class: 'mj-regmgr-payment-options' }, [
                        // Option 1: Paiement en ligne via Stripe
                        h('div', { class: 'mj-regmgr-payment-option' }, [
                            h('div', { class: 'mj-regmgr-payment-option__header' }, [
                                h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                                    h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                                ]),
                                h('div', null, [
                                    h('strong', null, 'Paiement en ligne'),
                                    h('div', { class: 'mj-regmgr-payment-option__price' }, membershipPrice.toFixed(2) + ' €'),
                                ]),
                            ]),
                            h('p', { class: 'mj-regmgr-payment-option__desc' }, 
                                'Génère un lien Stripe avec QR code pour paiement par carte.'
                            ),
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--primary mj-btn--block',
                                onClick: handlePayOnline,
                                disabled: processing,
                            }, [
                                processing ? h('span', null, [
                                    h('span', { class: 'mj-regmgr-loading__spinner', style: { width: '16px', height: '16px', marginRight: '8px', display: 'inline-block', verticalAlign: 'middle' } }),
                                    'Création du lien...'
                                ]) : 'Générer le lien de paiement',
                            ]),
                        ]),

                        h('div', { class: 'mj-regmgr-payment-separator' }, [
                            h('span', null, 'ou'),
                        ]),

                        // Option 2: Paiement en main propre
                        h('div', { class: 'mj-regmgr-payment-option mj-regmgr-payment-option--secondary' }, [
                            h('div', { class: 'mj-regmgr-payment-option__header' }, [
                                h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('line', { x1: 12, y1: 1, x2: 12, y2: 23 }),
                                    h('path', { d: 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6' }),
                                ]),
                                h('div', null, [
                                    h('strong', null, 'Payé en main propre'),
                                    h('div', { class: 'mj-regmgr-payment-option__price' }, membershipPriceManual.toFixed(2) + ' €'),
                                ]),
                            ]),
                            h('p', { class: 'mj-regmgr-payment-option__desc' }, 
                                'Espèces, chèque ou virement reçu directement.'
                            ),
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--secondary mj-btn--block',
                                onClick: handleMarkPaid,
                                disabled: processing,
                            }, processing ? 'Enregistrement...' : 'Marquer comme payé'),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrMembers = {
        MemberCard: MemberCard,
        MembersList: MembersList,
        MemberDetailPanel: MemberDetailPanel,
        MembershipPaymentModal: MembershipPaymentModal,
    };

})(window);
