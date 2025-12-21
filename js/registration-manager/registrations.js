/**
 * Registration Manager - Registrations Components
 * Composants pour la gestion des inscriptions
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour registrations.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useCallback = hooks.useCallback;

    var formatDate = Utils.formatDate;
    var getInitials = Utils.getInitials;
    var stringToColor = Utils.stringToColor;
    var classNames = Utils.classNames;
    var getString = Utils.getString;

    // ============================================
    // MEMBER AVATAR
    // ============================================

    function MemberAvatar(props) {
        var member = props.member;
        var size = props.size || 'medium';
        var showStatus = props.showStatus;

        var name = member ? (member.firstName + ' ' + member.lastName).trim() : '';
        var initials = getInitials(name);
        var bgColor = stringToColor(name);

        var subscriptionClass = '';
        if (showStatus && member) {
            subscriptionClass = 'mj-regmgr-avatar--subscription-' + (member.subscriptionStatus || 'none');
        }

        // Support both photoUrl and avatarUrl
        var imageUrl = member ? (member.photoUrl || member.avatarUrl) : null;

        if (member && imageUrl) {
            return h('div', {
                class: classNames('mj-regmgr-avatar', 'mj-regmgr-avatar--' + size, subscriptionClass),
            }, [
                h('img', {
                    src: imageUrl,
                    alt: name,
                    class: 'mj-regmgr-avatar__image',
                }),
            ]);
        }

        return h('div', {
            class: classNames('mj-regmgr-avatar', 'mj-regmgr-avatar--' + size, subscriptionClass),
            style: { backgroundColor: bgColor },
        }, [
            h('span', { class: 'mj-regmgr-avatar__initials' }, initials),
        ]);
    }

    // ============================================
    // REGISTRATION CARD
    // ============================================

    function RegistrationCard(props) {
        var registration = props.registration;
        var onValidate = props.onValidate;
        var onCancel = props.onCancel;
        var onDelete = props.onDelete;
        var onValidatePayment = props.onValidatePayment;
        var onCancelPayment = props.onCancelPayment;
        var onShowQR = props.onShowQR;
        var onShowNotes = props.onShowNotes;
        var onChangeOccurrences = props.onChangeOccurrences;
        var strings = props.strings;
        var config = props.config;
        var eventRequiresPayment = props.eventRequiresPayment;
        var allowOccurrenceSelection = props.allowOccurrenceSelection !== false;

        var _menuOpen = useState(false);
        var menuOpen = _menuOpen[0];
        var setMenuOpen = _menuOpen[1];

        var member = registration.member;
        var memberName = member 
            ? (member.firstName + ' ' + member.lastName).trim() 
            : 'Membre inconnu';

        var statusLabels = config.registrationStatuses || {};
        var paymentLabels = config.paymentStatuses || {};

        var handleMenuToggle = useCallback(function (e) {
            e.stopPropagation();
            setMenuOpen(!menuOpen);
        }, [menuOpen]);

        var handleAction = useCallback(function (action) {
            setMenuOpen(false);
            action();
        }, []);

        var needsValidation = registration.status === 'en_attente';
        var needsPayment = eventRequiresPayment && registration.paymentStatus === 'unpaid';
        var isPaid = eventRequiresPayment && registration.paymentStatus === 'paid';
        var isCancelled = registration.status === 'annule';

        return h('div', {
            class: classNames('mj-regmgr-registration-card', {
                'mj-regmgr-registration-card--pending': needsValidation,
                'mj-regmgr-registration-card--unpaid': needsPayment,
                'mj-regmgr-registration-card--cancelled': isCancelled,
            }),
        }, [
            // Avatar et infos membre
            h('div', { class: 'mj-regmgr-registration-card__member' }, [
                h(MemberAvatar, { member: member, showStatus: true }),
                h('div', { class: 'mj-regmgr-registration-card__info' }, [
                    h('div', { class: 'mj-regmgr-registration-card__name' }, memberName),
                    member && h('div', { class: 'mj-regmgr-registration-card__meta' }, [
                        h('span', { class: 'mj-regmgr-registration-card__role' }, member.roleLabel),
                        member.age !== null && h('span', { class: 'mj-regmgr-registration-card__age' }, 
                            ' • ' + member.age + ' ans'
                        ),
                    ]),
                ]),
            ]),

            // Statuts
            h('div', { class: 'mj-regmgr-registration-card__statuses' }, [
                // Statut inscription
                h('span', {
                    class: classNames('mj-regmgr-badge', 'mj-regmgr-badge--status-' + registration.status),
                }, statusLabels[registration.status] || registration.status),

                // Statut paiement
                eventRequiresPayment && h('span', {
                    class: classNames('mj-regmgr-badge', 'mj-regmgr-badge--payment-' + registration.paymentStatus),
                }, paymentLabels[registration.paymentStatus] || registration.paymentStatus),

                // Indicateur cotisation
                member && member.subscriptionStatus && h('span', {
                    class: classNames('mj-regmgr-badge', 'mj-regmgr-badge--subscription-' + member.subscriptionStatus),
                    title: member.subscriptionStatus === 'active' 
                        ? getString(strings, 'subscriptionActive', 'Cotisation active')
                        : member.subscriptionStatus === 'expired'
                            ? getString(strings, 'subscriptionExpired', 'Cotisation expirée')
                            : getString(strings, 'subscriptionNone', 'Aucune cotisation'),
                }, [
                    h('svg', {
                        width: 12,
                        height: 12,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 2,
                    }, member.subscriptionStatus === 'active' 
                        ? [h('polyline', { points: '20 6 9 17 4 12' })]
                        : [h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }), h('line', { x1: 6, y1: 6, x2: 18, y2: 18 })]
                    ),
                ]),
            ]),

            // Actions - Boutons texte visibles en desktop
            h('div', { class: 'mj-regmgr-registration-card__actions' }, [
                // Actions principales (toujours visibles)
                h('div', { class: 'mj-regmgr-registration-card__actions-main' }, [
                    // Valider inscription
                    needsValidation && h('button', {
                        type: 'button',
                        class: 'mj-regmgr-action-btn mj-regmgr-action-btn--success',
                        onClick: function () { onValidate(registration); },
                        title: getString(strings, 'validateRegistration', 'Valider'),
                    }, [
                        h('svg', { class: 'mj-regmgr-action-btn__icon', width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '20 6 9 17 4 12' }),
                        ]),
                        h('span', { class: 'mj-regmgr-action-btn__label' }, getString(strings, 'validate', 'Valider')),
                    ]),

                    // Valider paiement
                    needsPayment && config.allowManualPayment && h('button', {
                        type: 'button',
                        class: 'mj-regmgr-action-btn mj-regmgr-action-btn--primary',
                        onClick: function () { onValidatePayment(registration); },
                        title: getString(strings, 'validatePayment', 'Valider paiement'),
                    }, [
                        h('svg', { class: 'mj-regmgr-action-btn__icon', width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                            h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                        ]),
                        h('span', { class: 'mj-regmgr-action-btn__label' }, getString(strings, 'markPaid', 'Payé')),
                    ]),

                    // Annuler paiement
                    isPaid && config.allowManualPayment && h('button', {
                        type: 'button',
                        class: 'mj-regmgr-action-btn mj-regmgr-action-btn--warning',
                        onClick: function () { onCancelPayment(registration); },
                        title: getString(strings, 'cancelPayment', 'Annuler paiement'),
                    }, [
                        h('svg', { class: 'mj-regmgr-action-btn__icon', width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                            h('line', { x1: 4, y1: 7, x2: 20, y2: 17 }),
                        ]),
                        h('span', { class: 'mj-regmgr-action-btn__label' }, getString(strings, 'unpay', 'Annuler')),
                    ]),

                    // QR Code
                    needsPayment && h('button', {
                        type: 'button',
                        class: 'mj-regmgr-action-btn',
                        onClick: function () { onShowQR(registration); },
                        title: getString(strings, 'showQRCode', 'QR Code'),
                    }, [
                        h('svg', { class: 'mj-regmgr-action-btn__icon', width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 3, y: 3, width: 7, height: 7 }),
                            h('rect', { x: 14, y: 3, width: 7, height: 7 }),
                            h('rect', { x: 3, y: 14, width: 7, height: 7 }),
                            h('rect', { x: 14, y: 14, width: 7, height: 7 }),
                        ]),
                        h('span', { class: 'mj-regmgr-action-btn__label' }, 'QR'),
                    ]),

                    // Séances
                    allowOccurrenceSelection && onChangeOccurrences && h('button', {
                        type: 'button',
                        class: 'mj-regmgr-action-btn',
                        onClick: function () { onChangeOccurrences(registration); },
                        title: getString(strings, 'changeOccurrences', 'Séances'),
                    }, [
                        h('svg', { class: 'mj-regmgr-action-btn__icon', width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                            h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                            h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                        ]),
                        h('span', { class: 'mj-regmgr-action-btn__label' }, getString(strings, 'sessions', 'Séances')),
                    ]),

                    // Notes
                    h('button', {
                        type: 'button',
                        class: classNames('mj-regmgr-action-btn', {
                            'mj-regmgr-action-btn--has-badge': registration.notesCount > 0,
                        }),
                        onClick: function () { onShowNotes(registration); },
                        title: getString(strings, 'addNote', 'Notes'),
                    }, [
                        h('svg', { class: 'mj-regmgr-action-btn__icon', width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                            h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                        ]),
                        h('span', { class: 'mj-regmgr-action-btn__label' }, getString(strings, 'notes', 'Notes')),
                        registration.notesCount > 0 && h('span', { class: 'mj-regmgr-action-btn__badge' }, registration.notesCount),
                    ]),
                ]),

                // Actions secondaires (dropdown pour actions destructives)
                h('div', { class: 'mj-regmgr-dropdown' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-icon-btn',
                        onClick: handleMenuToggle,
                        'aria-expanded': menuOpen ? 'true' : 'false',
                        title: getString(strings, 'moreActions', 'Plus d\'actions'),
                    }, [
                        h('svg', {
                            width: 16,
                            height: 16,
                            viewBox: '0 0 24 24',
                            fill: 'currentColor',
                        }, [
                            h('circle', { cx: 12, cy: 5, r: 1.5 }),
                            h('circle', { cx: 12, cy: 12, r: 1.5 }),
                            h('circle', { cx: 12, cy: 19, r: 1.5 }),
                        ]),
                    ]),

                    menuOpen && h('div', { class: 'mj-regmgr-dropdown__menu' }, [
                        // Annuler inscription
                        !isCancelled && h('button', {
                            type: 'button',
                            class: 'mj-regmgr-dropdown__item mj-regmgr-dropdown__item--warning',
                            onClick: function () { handleAction(function () { onCancel(registration); }); },
                        }, [
                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                h('circle', { cx: 12, cy: 12, r: 10 }),
                                h('line', { x1: 15, y1: 9, x2: 9, y2: 15 }),
                                h('line', { x1: 9, y1: 9, x2: 15, y2: 15 }),
                            ]),
                            getString(strings, 'cancelRegistration', 'Annuler l\'inscription'),
                        ]),

                        // Supprimer
                        config.allowDeleteRegistration && h('button', {
                            type: 'button',
                            class: 'mj-regmgr-dropdown__item mj-regmgr-dropdown__item--danger',
                            onClick: function () { handleAction(function () { onDelete(registration); }); },
                        }, [
                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                h('polyline', { points: '3 6 5 6 21 6' }),
                                h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }),
                            ]),
                            getString(strings, 'deleteRegistration', 'Supprimer'),
                        ]),
                    ]),
                ]),
            ]),

            // Overlay pour fermer le menu
            menuOpen && h('div', {
                class: 'mj-regmgr-dropdown__overlay',
                onClick: function () { setMenuOpen(false); },
            }),
        ]);
    }

    // ============================================
    // REGISTRATIONS LIST
    // ============================================

    function RegistrationsList(props) {
        var registrations = props.registrations || [];
        var loading = props.loading;
        var onAddParticipant = props.onAddParticipant;
        var onValidate = props.onValidate;
        var onCancel = props.onCancel;
        var onDelete = props.onDelete;
        var onValidatePayment = props.onValidatePayment;
        var onCancelPayment = props.onCancelPayment;
        var onShowQR = props.onShowQR;
        var onShowNotes = props.onShowNotes;
        var onChangeOccurrences = props.onChangeOccurrences;
        var strings = props.strings;
        var config = props.config;
        var eventRequiresPayment = props.eventRequiresPayment;
        var allowOccurrenceSelection = props.allowOccurrenceSelection !== false;

        // Stats rapides
        var stats = {
            total: registrations.length,
            pending: registrations.filter(function (r) { return r.status === 'en_attente'; }).length,
            confirmed: registrations.filter(function (r) { return r.status === 'valide'; }).length,
            cancelled: registrations.filter(function (r) { return r.status === 'annule'; }).length,
            unpaid: eventRequiresPayment ? registrations.filter(function (r) { return r.paymentStatus === 'unpaid' && r.status !== 'annule'; }).length : 0,
            paid: eventRequiresPayment ? registrations.filter(function (r) { return r.paymentStatus === 'paid'; }).length : 0,
        };

        return h('div', { class: 'mj-regmgr-registrations' }, [
            // Header
            h('div', { class: 'mj-regmgr-registrations__header' }, [
                h('h2', { class: 'mj-regmgr-registrations__title' }, [
                    getString(strings, 'registrationList', 'Liste des inscrits'),
                    h('span', { class: 'mj-regmgr-registrations__count' }, '(' + stats.total + ')'),
                ]),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--primary',
                    onClick: onAddParticipant,
                }, [
                    h('svg', {
                        width: 16,
                        height: 16,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 2,
                    }, [
                        h('path', { d: 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                        h('circle', { cx: 8.5, cy: 7, r: 4 }),
                        h('line', { x1: 20, y1: 8, x2: 20, y2: 14 }),
                        h('line', { x1: 23, y1: 11, x2: 17, y2: 11 }),
                    ]),
                    getString(strings, 'addParticipant', 'Ajouter un participant'),
                ]),
            ]),

            // Stats complètes
            h('div', { class: 'mj-regmgr-registrations__stats' }, [
                // Inscrits confirmés
                h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--success' }, [
                    h('div', { class: 'mj-regmgr-stat-card__icon' }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '20 6 9 17 4 12' }),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-stat-card__content' }, [
                        h('span', { class: 'mj-regmgr-stat-card__value' }, stats.confirmed),
                        h('span', { class: 'mj-regmgr-stat-card__label' }, getString(strings, 'confirmed', 'Confirmés')),
                    ]),
                ]),

                // En attente
                stats.pending > 0 && h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--warning' }, [
                    h('div', { class: 'mj-regmgr-stat-card__icon' }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('circle', { cx: 12, cy: 12, r: 10 }),
                            h('polyline', { points: '12 6 12 12 16 14' }),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-stat-card__content' }, [
                        h('span', { class: 'mj-regmgr-stat-card__value' }, stats.pending),
                        h('span', { class: 'mj-regmgr-stat-card__label' }, getString(strings, 'pending', 'En attente')),
                    ]),
                ]),

                // À payer
                eventRequiresPayment && stats.unpaid > 0 && h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--danger' }, [
                    h('div', { class: 'mj-regmgr-stat-card__icon' }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                            h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-stat-card__content' }, [
                        h('span', { class: 'mj-regmgr-stat-card__value' }, stats.unpaid),
                        h('span', { class: 'mj-regmgr-stat-card__label' }, getString(strings, 'awaitingPayment', 'À payer')),
                    ]),
                ]),

                // Payés
                eventRequiresPayment && stats.paid > 0 && h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--paid' }, [
                    h('div', { class: 'mj-regmgr-stat-card__icon' }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('line', { x1: 12, y1: 1, x2: 12, y2: 23 }),
                            h('path', { d: 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6' }),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-stat-card__content' }, [
                        h('span', { class: 'mj-regmgr-stat-card__value' }, stats.paid),
                        h('span', { class: 'mj-regmgr-stat-card__label' }, getString(strings, 'paid', 'Payés')),
                    ]),
                ]),

                // Annulés
                stats.cancelled > 0 && h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--muted' }, [
                    h('div', { class: 'mj-regmgr-stat-card__icon' }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('circle', { cx: 12, cy: 12, r: 10 }),
                            h('line', { x1: 15, y1: 9, x2: 9, y2: 15 }),
                            h('line', { x1: 9, y1: 9, x2: 15, y2: 15 }),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-stat-card__content' }, [
                        h('span', { class: 'mj-regmgr-stat-card__value' }, stats.cancelled),
                        h('span', { class: 'mj-regmgr-stat-card__label' }, getString(strings, 'cancelled', 'Annulés')),
                    ]),
                ]),
            ]),

            // Liste
            loading && h('div', { class: 'mj-regmgr-registrations__loading' }, [
                h('div', { class: 'mj-regmgr-spinner' }),
            ]),

            !loading && registrations.length === 0 && h('div', { class: 'mj-regmgr-registrations__empty' }, [
                h('p', null, getString(strings, 'noRegistrations', 'Aucun inscrit pour le moment.')),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary',
                    onClick: onAddParticipant,
                }, getString(strings, 'addParticipant', 'Ajouter un participant')),
            ]),

            !loading && registrations.length > 0 && h('div', { class: 'mj-regmgr-registrations__list' },
                registrations.map(function (reg) {
                    return h(RegistrationCard, {
                        key: reg.id,
                        registration: reg,
                        onValidate: onValidate,
                        onCancel: onCancel,
                        onDelete: onDelete,
                        onValidatePayment: onValidatePayment,
                        onCancelPayment: onCancelPayment,
                        onShowQR: onShowQR,
                        onShowNotes: onShowNotes,
                        onChangeOccurrences: onChangeOccurrences,
                        allowOccurrenceSelection: allowOccurrenceSelection,
                        strings: strings,
                        config: config,
                        eventRequiresPayment: eventRequiresPayment,
                    });
                })
            ),
        ]);
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrRegistrations = {
        MemberAvatar: MemberAvatar,
        RegistrationCard: RegistrationCard,
        RegistrationsList: RegistrationsList,
    };

})(window);
