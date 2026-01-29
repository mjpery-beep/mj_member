/**
 * Registration Manager - Attendance Components
 * Composants pour la feuille de présence - UI moderne et intuitive
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var RegComps = global.MjRegMgrRegistrations;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour attendance.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;

    var formatDate = Utils.formatDate;
    var formatShortDate = Utils.formatShortDate;
    var classNames = Utils.classNames;
    var getString = Utils.getString;
    var buildWhatsAppLink = Utils.buildWhatsAppLink;

    var MemberAvatar = RegComps ? RegComps.MemberAvatar : function () { return null; };

    // ============================================
    // OCCURRENCE PILL SELECTOR
    // ============================================

    /**
     * Formatter la date pour l'affichage en pill
     */
    function formatOccDate(dateStr) {
        var d = new Date(dateStr);
        var days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        var months = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];
        return {
            day: days[d.getDay()],
            date: d.getDate(),
            month: months[d.getMonth()],
            time: d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
        };
    }

    /**
     * Sélecteur de séance/occurrence en format pill avec pagination
     */
    function OccurrenceSelector(props) {
        var occurrences = props.occurrences || [];
        var selectedOccurrence = props.selectedOccurrence;
        var onSelect = props.onSelect;
        var strings = props.strings;

        // Pagination state
        var ITEMS_PER_PAGE = 7;
        var _page = useState(0);
        var page = _page[0];
        var setPage = _page[1];

        var totalPages = Math.ceil(occurrences.length / ITEMS_PER_PAGE);
        var showPagination = occurrences.length > ITEMS_PER_PAGE;

        // Get visible occurrences for current page
        var visibleOccurrences = useMemo(function () {
            var start = page * ITEMS_PER_PAGE;
            return occurrences.slice(start, start + ITEMS_PER_PAGE);
        }, [occurrences, page]);

        // Auto-navigate to page containing selected occurrence
        useEffect(function () {
            if (selectedOccurrence && occurrences.length > 0) {
                var selectedIndex = occurrences.findIndex(function (occ) {
                    var occKey = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                    return occKey === selectedOccurrence;
                });
                if (selectedIndex !== -1) {
                    var targetPage = Math.floor(selectedIndex / ITEMS_PER_PAGE);
                    if (targetPage !== page) {
                        setPage(targetPage);
                    }
                }
            }
        }, [selectedOccurrence, occurrences]);

        var handlePrevPage = function () {
            setPage(function (p) { return Math.max(0, p - 1); });
        };

        var handleNextPage = function () {
            setPage(function (p) { return Math.min(totalPages - 1, p + 1); });
        };

        if (occurrences.length === 0) {
            return h('div', { class: 'mj-att-empty' }, [
                h('svg', { width: 48, height: 48, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.5 }, [
                    h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                    h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                    h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                ]),
                h('p', null, getString(strings, 'noOccurrences', 'Aucune séance disponible')),
            ]);
        }

        return h('div', { class: 'mj-att-occurrence-selector' }, [
            h('div', { class: 'mj-att-occurrence-selector__header' }, [
                h('div', { class: 'mj-att-occurrence-selector__label' }, [
                    h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                        h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                        h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                        h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                    ]),
                    h('span', null, getString(strings, 'selectSession', 'Sélectionnez une séance')),
                    showPagination && h('span', { class: 'mj-att-occurrence-selector__count' }, 
                        '(' + occurrences.length + ' séances)'
                    ),
                ]),

                // Pagination controls
                showPagination && h('div', { class: 'mj-att-occurrence-paging' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-att-occurrence-paging__btn',
                        onClick: handlePrevPage,
                        disabled: page === 0,
                        'aria-label': 'Page précédente',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '15 18 9 12 15 6' }),
                        ]),
                    ]),
                    h('span', { class: 'mj-att-occurrence-paging__info' }, 
                        (page + 1) + ' / ' + totalPages
                    ),
                    h('button', {
                        type: 'button',
                        class: 'mj-att-occurrence-paging__btn',
                        onClick: handleNextPage,
                        disabled: page >= totalPages - 1,
                        'aria-label': 'Page suivante',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '9 18 15 12 9 6' }),
                        ]),
                    ]),
                ]),
            ]),

            h('div', { class: 'mj-att-occurrence-pills' },
                visibleOccurrences.map(function (occ, index) {
                    var occKey = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                    var occDate = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                    var isSelected = selectedOccurrence === occKey;
                    var isPast = new Date(occDate) < new Date();
                    var dateInfo = formatOccDate(occDate);

                    return h('button', {
                        key: occKey || index,
                        type: 'button',
                        class: classNames('mj-att-pill', {
                            'mj-att-pill--selected': isSelected,
                            'mj-att-pill--past': isPast && !isSelected,
                        }),
                        onClick: function () { onSelect(occKey); },
                    }, [
                        h('span', { class: 'mj-att-pill__day' }, dateInfo.day),
                        h('span', { class: 'mj-att-pill__date' }, dateInfo.date),
                        h('span', { class: 'mj-att-pill__month' }, dateInfo.month),
                    ]);
                })
            ),
        ]);
    }

    // ============================================
    // ATTENDANCE QUICK STATS
    // ============================================

    function AttendanceStats(props) {
        var stats = props.stats;
        var total = props.total;

        return h('div', { class: 'mj-att-stats' }, [
            // Barre de progression
            h('div', { class: 'mj-att-stats__progress' }, [
                h('div', { 
                    class: 'mj-att-stats__progress-bar mj-att-stats__progress-bar--present',
                    style: { width: (total > 0 ? (stats.present / total * 100) : 0) + '%' },
                }),
                h('div', { 
                    class: 'mj-att-stats__progress-bar mj-att-stats__progress-bar--absent',
                    style: { width: (total > 0 ? (stats.absent / total * 100) : 0) + '%' },
                }),
            ]),
            
            // Compteurs
            h('div', { class: 'mj-att-stats__counters' }, [
                h('div', { class: 'mj-att-stats__counter mj-att-stats__counter--present' }, [
                    h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.5 }, [
                        h('polyline', { points: '20 6 9 17 4 12' }),
                    ]),
                    h('span', null, stats.present + ' présent' + (stats.present > 1 ? 's' : '')),
                ]),
                h('div', { class: 'mj-att-stats__counter mj-att-stats__counter--absent' }, [
                    h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.5 }, [
                        h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                        h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                    ]),
                    h('span', null, stats.absent + ' absent' + (stats.absent > 1 ? 's' : '')),
                ]),
                stats.undefined > 0 && h('div', { class: 'mj-att-stats__counter mj-att-stats__counter--undefined' }, [
                    h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('circle', { cx: 12, cy: 12, r: 10 }),
                        h('line', { x1: 12, y1: 16, x2: 12, y2: 12 }),
                        h('line', { x1: 12, y1: 8, x2: 12.01, y2: 8 }),
                    ]),
                    h('span', null, stats.undefined + ' non défini' + (stats.undefined > 1 ? 's' : '')),
                ]),
            ]),
        ]);
    }

    // ============================================
    // ATTENDANCE MEMBER CARD (extended for irregular)
    // ============================================

    function AttendanceMemberCard(props) {
        var registration = props.registration;
        var attendanceStatus = props.attendanceStatus;
        var onStatusChange = props.onStatusChange;
        var loading = props.loading;
        var irregular = props.irregular; // 'unpaid' | 'not-registered' | null
        var onRegularize = props.onRegularize;
        var strings = props.strings;
        var requiresPayment = props.requiresPayment === true;
        var requiresValidation = props.requiresValidation !== false;
        var onViewMember = props.onViewMember;
        var hideOccurrenceIrregular = props.hideOccurrenceIrregular === true;

        var member = registration.member;
        var memberName = member 
            ? (member.firstName + ' ' + member.lastName).trim() 
            : 'Membre inconnu';
        var memberPhone = member && typeof member.phone === 'string' ? member.phone : '';
        var memberWhatsappOptIn = member ? (typeof member.whatsappOptIn === 'undefined' ? true : !!member.whatsappOptIn) : false;
        var whatsappLink = '';
        if (memberWhatsappOptIn && memberPhone) {
            whatsappLink = buildWhatsAppLink(memberPhone);
        }
        var volunteerLabel = getString(strings, 'volunteerLabel', 'Bénévole');
        var isAttendanceOnly = registration && registration.attendanceOnly;

        var handleClick = function (status) {
            if (loading) return;
            // Toggle: cliquer sur le même statut le retire
            var newStatus = attendanceStatus === status ? '' : status;
            onStatusChange(newStatus);
        };

        var paymentStatus = registration.paymentStatus || 'unpaid';
        var irregularAction = null;
        var canValidateRegistration = requiresValidation && registration.status === 'en_attente';
        var shouldValidateRegistration = canValidateRegistration
            || (requiresValidation && (!requiresPayment || paymentStatus !== 'unpaid'));

        var irregularLabel = null;
        if (irregular === 'unpaid') {
            irregularLabel = shouldValidateRegistration
                ? getString(strings, 'registrationPending', "Inscription à valider")
                : getString(strings, 'paymentPending', 'Non payé');
        } else if (irregular === 'not-registered' && !hideOccurrenceIrregular) {
            irregularLabel = getString(strings, 'occurrenceMissing', 'Non inscrit à cette séance');
        }

        if (irregular === 'unpaid') {
            if (shouldValidateRegistration) {
                irregularAction = getString(strings, 'validateRegistration', "Valider l'inscription");
            } else {
                irregularAction = getString(strings, 'validatePayment', "Valider le paiement");
            }
        } else if (irregular === 'not-registered' && !hideOccurrenceIrregular) {
            irregularAction = getString(strings, 'validateRegistration', "Valider l'inscription");
        }

        return h('div', {
            class: classNames('mj-att-member', {
                'mj-att-member--present': attendanceStatus === 'present',
                'mj-att-member--absent': attendanceStatus === 'absent',
                'mj-att-member--loading': loading,
                'mj-att-member--unpaid': irregular === 'unpaid',
                'mj-att-member--not-registered': irregular === 'not-registered' && !hideOccurrenceIrregular,
            }),
        }, [
            // Info membre
            h('div', { class: 'mj-att-member__info' }, [
                h(MemberAvatar, { member: member, size: 'medium' }),
                h('div', { class: 'mj-att-member__details' }, [
                    h('div', { class: 'mj-att-member__name-row' }, [
                        h('span', { class: 'mj-att-member__name' }, memberName),
                        onViewMember && member && member.id && h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--ghost mj-btn--small mj-att-member__view-member',
                            onClick: function () { onViewMember(member); },
                            title: getString(strings, 'viewMemberProfile', 'Ouvrir la fiche membre'),
                        }, [
                            h('svg', {
                                width: 12,
                                height: 12,
                                viewBox: '0 0 24 24',
                                fill: 'none',
                                stroke: 'currentColor',
                                'stroke-width': 2,
                            }, [
                                h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                                h('polyline', { points: '15 3 21 3 21 9' }),
                                h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                            ]),
                            h('span', { class: 'mj-att-member__view-member-label' }, getString(strings, 'openMember', 'Fiche')),
                        ]),
                    ]),
                    h('div', { class: 'mj-att-member__meta-row' }, [
                        member && h('span', { class: 'mj-att-member__meta' }, [
                            member.roleLabel,
                            member.age !== null && ' • ' + member.age + ' ans',
                        ]),
                        member && member.isVolunteer && h('span', {
                            class: classNames('mj-att-member__badge', 'mj-att-member__badge--volunteer'),
                        }, volunteerLabel),
                        irregularLabel && h('span', { class: 'mj-att-member__badge mj-att-member__badge--' + irregular }, irregularLabel),
                    ]),
                ]),
            ]),

            // Actions
            h('div', { class: 'mj-att-member__actions' }, [
                // Bouton de régularisation si nécessaire
                irregular && onRegularize && !isAttendanceOnly && !(hideOccurrenceIrregular && irregular === 'not-registered') && h('button', {
                    type: 'button',
                    class: 'mj-att-member__regularize-btn',
                    onClick: function () { onRegularize(registration, irregular); },
                    disabled: loading,
                    title: irregularAction,
                }, [
                    irregular === 'unpaid' && h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                        h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                    ]),
                    irregular === 'not-registered' && h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('circle', { cx: 12, cy: 12, r: 10 }),
                        h('line', { x1: 12, y1: 8, x2: 12, y2: 16 }),
                        h('line', { x1: 8, y1: 12, x2: 16, y2: 12 }),
                    ]),
                    irregularAction,
                ]),

                whatsappLink && h('a', {
                    href: whatsappLink,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'mj-att-member__contact',
                    title: getString(strings, 'contactWhatsapp', 'WhatsApp'),
                    'aria-label': getString(strings, 'contactWhatsapp', 'WhatsApp'),
                }, [
                    h('svg', { class: 'mj-att-member__contact-icon', width: 28, height: 28, viewBox: '0 0 32 32', fill: 'none' }, [
                        h('path', {
                            d: 'M26.576 5.363c-2.69-2.69-6.406-4.354-10.511-4.354-8.209 0-14.865 6.655-14.865 14.865 0 2.732 0.737 5.291 2.022 7.491l-0.038-0.070-2.109 7.702 7.879-2.067c2.051 1.139 4.498 1.809 7.102 1.809h0.006c8.209-0.003 14.862-6.659 14.862-14.868 0-4.103-1.662-7.817-4.349-10.507l0 0zM16.062 28.228h-0.005c-0 0-0.001 0-0.001 0-2.319 0-4.489-0.64-6.342-1.753l0.056 0.031-0.451-0.267-4.675 1.227 1.247-4.559-0.294-0.467c-1.185-1.862-1.889-4.131-1.889-6.565 0-6.822 5.531-12.353 12.353-12.353s12.353 5.531 12.353 12.353c0 6.822-5.53 12.353-12.353 12.353h-0zM22.838 18.977c-0.371-0.186-2.197-1.083-2.537-1.208-0.341-0.124-0.589-0.185-0.837 0.187-0.246 0.371-0.958 1.207-1.175 1.455-0.216 0.249-0.434 0.279-0.805 0.094-1.15-0.466-2.138-1.087-2.997-1.852l0.010 0.009c-0.799-0.74-1.484-1.587-2.037-2.521l-0.028-0.052c-0.216-0.371-0.023-0.572 0.162-0.757 0.167-0.166 0.372-0.434 0.557-0.65 0.146-0.179 0.271-0.384 0.366-0.604l0.006-0.017c0.043-0.087 0.068-0.188 0.068-0.296 0-0.131-0.037-0.253-0.101-0.357l0.002 0.003c-0.094-0.186-0.836-2.014-1.145-2.758-0.302-0.724-0.609-0.625-0.836-0.637-0.216-0.010-0.464-0.012-0.712-0.012-0.395 0.010-0.746 0.188-0.988 0.463l-0.001 0.002c-0.802 0.761-1.3 1.834-1.3 3.023 0 0.026 0 0.053 0.001 0.079l-0-0.004c0.131 1.467 0.681 2.784 1.527 3.857l-0.012-0.015c1.604 2.379 3.742 4.282 6.251 5.564l0.094 0.043c0.548 0.248 1.25 0.513 1.968 0.74l0.149 0.041c0.442 0.14 0.951 0.221 1.479 0.221 0.303 0 0.601-0.027 0.889-0.078l-0.031 0.004c1.069-0.223 1.956-0.868 2.497-1.749l0.009-0.017c0.165-0.366 0.261-0.793 0.261-1.242 0-0.185-0.016-0.366-0.047-0.542l0.003 0.019c-0.092-0.155-0.34-0.247-0.712-0.434z',
                            fill: 'currentColor',
                        }),
                    ]),
                ]),

                // Boutons de présence stylisés
                h('button', {
                    type: 'button',
                    class: classNames('mj-att-btn mj-att-btn--present', {
                        'mj-att-btn--active': attendanceStatus === 'present',
                    }),
                    onClick: function () { handleClick('present'); },
                    disabled: loading,
                    'aria-pressed': attendanceStatus === 'present' ? 'true' : 'false',
                    'aria-label': 'Marquer présent',
                }, [
                    h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none' }, [
                        h('polyline', {
                            points: '20 6 9 17 4 12',
                            stroke: 'currentColor',
                            'stroke-width': 2.5,
                            'stroke-linecap': 'round',
                            'stroke-linejoin': 'round',
                        }),
                    ]),
                ]),
                h('button', {
                    type: 'button',
                    class: classNames('mj-att-btn mj-att-btn--absent', {
                        'mj-att-btn--active': attendanceStatus === 'absent',
                    }),
                    onClick: function () { handleClick('absent'); },
                    disabled: loading,
                    'aria-pressed': attendanceStatus === 'absent' ? 'true' : 'false',
                    'aria-label': 'Marquer absent',
                }, [
                    h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none' }, [
                        h('line', {
                            x1: 18,
                            y1: 6,
                            x2: 6,
                            y2: 18,
                            stroke: 'currentColor',
                            'stroke-width': 2.5,
                            'stroke-linecap': 'round',
                        }),
                        h('line', {
                            x1: 6,
                            y1: 6,
                            x2: 18,
                            y2: 18,
                            stroke: 'currentColor',
                            'stroke-width': 2.5,
                            'stroke-linecap': 'round',
                        }),
                    ]),
                ]),
            ]),
        ]);
    }

    // ============================================
    // ATTENDANCE SHEET (Main Component)
    // ============================================

    function AttendanceSheet(props) {
        var event = props.event;
        var registrations = props.registrations || [];
        var attendanceMembers = props.attendanceMembers || [];
        var occurrences = props.occurrences || [];
        var attendanceMap = props.attendanceMap || {};
        var onUpdateAttendance = props.onUpdateAttendance;
        var onBulkAttendance = props.onBulkAttendance;
        var onValidatePayment = props.onValidatePayment;
        var onValidateRegistration = props.onValidateRegistration;
        var onChangeOccurrences = props.onChangeOccurrences;
        var onViewMember = props.onViewMember;
        var strings = props.strings;
        var loading = props.loading;
        var loadingMembers = props.loadingMembers || {};
        var requiresValidation = props.eventRequiresValidation !== false;
        var allowAttendanceAll = useMemo(function () {
            if (event && (event.attendanceShowAllMembers || event.attendance_show_all_members)) {
                return true;
            }
            return Array.isArray(attendanceMembers) && attendanceMembers.length > 0;
        }, [event, attendanceMembers]);

        var _selectedOccurrence = useState('');
        var selectedOccurrence = _selectedOccurrence[0];
        var setSelectedOccurrence = _selectedOccurrence[1];

        // Sélectionner automatiquement la prochaine séance ou la première
        useEffect(function () {
            if (occurrences.length > 0 && !selectedOccurrence) {
                var now = new Date();
                var nextOcc = occurrences.find(function (occ) {
                    var occDate = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                    return new Date(occDate) >= now;
                });
                
                if (nextOcc) {
                    var key = typeof nextOcc === 'string' ? nextOcc : (nextOcc.start || nextOcc.date || '');
                    setSelectedOccurrence(key);
                } else if (occurrences.length > 0) {
                    // Si toutes passées, prendre la dernière
                    var lastOcc = occurrences[occurrences.length - 1];
                    var key = typeof lastOcc === 'string' ? lastOcc : (lastOcc.start || lastOcc.date || '');
                    setSelectedOccurrence(key);
                }
            }
        }, [occurrences, selectedOccurrence]);

        // Helper: normaliser une date pour comparaison
        var normalizeOccurrenceKey = function (occ) {
            if (!occ) return '';
            if (typeof occ === 'string') return occ;
            return occ.start || occ.date || '';
        };

        var requiresPayment = useMemo(function () {
            if (!event) {
                return false;
            }
            var rawPrice = typeof event.prix !== 'undefined' ? event.prix : event.price;
            var priceValue = parseFloat(rawPrice);
            if (!isFinite(priceValue)) {
                priceValue = 0;
            }
            var freeParticipation = !!event.freeParticipation;
            return priceValue > 0 && !freeParticipation;
        }, [event]);

        var mergedRegistrations = useMemo(function () {
            if (!attendanceMembers || attendanceMembers.length === 0) {
                return registrations;
            }

            var seen = {};
            registrations.forEach(function (reg) {
                if (reg && reg.memberId) {
                    seen[reg.memberId] = true;
                }
            });

            var extras = attendanceMembers.map(function (member) {
                var memberId = member && member.id ? member.id : null;
                if (!memberId || seen[memberId]) {
                    return null;
                }
                return {
                    id: 'attendance-only-' + memberId,
                    eventId: event ? event.id : null,
                    memberId: memberId,
                    status: 'valide',
                    paymentStatus: 'paid',
                    member: member,
                    attendanceOnly: true,
                    occurrences: [],
                    assignedOccurrences: [],
                };
            }).filter(function (entry) { return entry !== null; });

            if (extras.length === 0) {
                return registrations;
            }

            return registrations.concat(extras);
        }, [registrations, attendanceMembers, event]);

        // Filtrer les inscriptions par catégorie
        var categorizedRegistrations = useMemo(function () {
            var valid = [];        // Validé + inscrit à cette séance
            var unpaid = [];       // Non payé mais inscrit à cette séance  
            var notRegistered = []; // Validé mais pas inscrit à cette séance

            var selectedOccNormalized = normalizeOccurrenceKey(selectedOccurrence);
            var hasMultipleOccurrences = occurrences.length > 1;

            mergedRegistrations.forEach(function (reg) {
                if (!reg) {
                    return;
                }
                var isValidated = reg.status === 'valide'
                    || (!requiresValidation && reg.status === 'en_attente');
                var isAttendanceOnly = reg.attendanceOnly === true;
                var registrationOccurrences = Array.isArray(reg.occurrences) ? reg.occurrences : [];
                if (registrationOccurrences.length === 0 && Array.isArray(reg.assignedOccurrences)) {
                    registrationOccurrences = reg.assignedOccurrences;
                }
                
                // Vérifier si inscrit à cette occurrence
                var isRegisteredToOccurrence = true;
                if (isAttendanceOnly) {
                    isRegisteredToOccurrence = false;
                } else if (hasMultipleOccurrences && selectedOccNormalized) {
                    if (registrationOccurrences.length > 0) {
                        isRegisteredToOccurrence = registrationOccurrences.some(function (occ) {
                            var occKey = normalizeOccurrenceKey(occ);
                            return occKey === selectedOccNormalized;
                        });
                    } else {
                        isRegisteredToOccurrence = false;
                    }
                } else if (selectedOccNormalized && registrationOccurrences.length > 0) {
                    isRegisteredToOccurrence = registrationOccurrences.some(function (occ) {
                        var occKey = normalizeOccurrenceKey(occ);
                        return occKey === selectedOccNormalized;
                    });
                } else if (selectedOccNormalized && registrationOccurrences.length === 0 && hasMultipleOccurrences) {
                    isRegisteredToOccurrence = false;
                }

                if (allowAttendanceAll) {
                    isRegisteredToOccurrence = true;
                }

                if (isValidated && isRegisteredToOccurrence) {
                    valid.push(reg);
                } else if (!isValidated && isRegisteredToOccurrence) {
                    unpaid.push(reg);
                } else if (isValidated && !isRegisteredToOccurrence) {
                    notRegistered.push(reg);
                }
                // Ignore: non validé ET non inscrit à cette séance
            });

            return { valid: valid, unpaid: unpaid, notRegistered: notRegistered };
        }, [mergedRegistrations, selectedOccurrence, occurrences, requiresValidation, allowAttendanceAll]);

        // Obtenir le statut de présence
        var getAttendanceStatus = useCallback(function (memberId) {
            if (!selectedOccurrence || !attendanceMap[selectedOccurrence]) {
                return '';
            }
            var entry = attendanceMap[selectedOccurrence][memberId];
            return entry ? entry.status : '';
        }, [attendanceMap, selectedOccurrence]);

        // Calculer les stats (uniquement pour les validés inscrits)
        var stats = useMemo(function () {
            var result = { present: 0, absent: 0, pending: 0, undefined: 0 };
            categorizedRegistrations.valid.forEach(function (reg) {
                var status = getAttendanceStatus(reg.memberId);
                if (status === 'present') result.present++;
                else if (status === 'absent') result.absent++;
                else result.undefined++;
            });
            return result;
        }, [categorizedRegistrations.valid, getAttendanceStatus]);

        // Actions en masse (uniquement pour les validés inscrits)
        var handleBulkAction = useCallback(function (status) {
            if (!selectedOccurrence || loading) return;
            var updates = categorizedRegistrations.valid.map(function (reg) {
                return { memberId: reg.memberId, status: status };
            });
            onBulkAttendance(selectedOccurrence, updates);
        }, [selectedOccurrence, categorizedRegistrations.valid, onBulkAttendance, loading]);

        // Changement de statut individuel
        var handleStatusChange = useCallback(function (registration, status) {
            onUpdateAttendance(registration.memberId, selectedOccurrence, status);
        }, [onUpdateAttendance, selectedOccurrence]);

        // Régulariser un membre
        var handleRegularize = useCallback(function (registration, irregularType) {
            if (registration && registration.attendanceOnly) {
                return;
            }
            if (irregularType === 'unpaid') {
                var paymentStatus = registration.paymentStatus || 'unpaid';
                var canValidateRegistration = requiresValidation && registration.status === 'en_attente';
                var shouldValidateRegistration = canValidateRegistration
                    || (requiresValidation && (!requiresPayment || paymentStatus !== 'unpaid'));

                if (shouldValidateRegistration && typeof onValidateRegistration === 'function') {
                    onValidateRegistration(registration);
                } else if (typeof onValidatePayment === 'function') {
                    onValidatePayment(registration);
                }
            } else if (irregularType === 'not-registered' && onChangeOccurrences) {
                onChangeOccurrences(registration);
            }
        }, [onValidatePayment, onValidateRegistration, onChangeOccurrences, requiresPayment, requiresValidation]);

        var totalValid = categorizedRegistrations.valid.length;
        var totalIrregular = categorizedRegistrations.unpaid.length + categorizedRegistrations.notRegistered.length;

        return h('div', { class: 'mj-att' }, [
            // Header
            h('div', { class: 'mj-att__header' }, [
                h('h2', { class: 'mj-att__title' }, [
                    h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('path', { d: 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                        h('circle', { cx: 8.5, cy: 7, r: 4 }),
                        h('polyline', { points: '17 11 19 13 23 9' }),
                    ]),
                    getString(strings, 'attendanceSheet', 'Feuille de présence'),
                ]),
            ]),

            // Sélecteur de séance
            h(OccurrenceSelector, {
                occurrences: occurrences,
                selectedOccurrence: selectedOccurrence,
                onSelect: setSelectedOccurrence,
                strings: strings,
            }),

            // Contenu principal si une séance est sélectionnée
            selectedOccurrence && h(Fragment, null, [
                // Stats
                h(AttendanceStats, {
                    stats: stats,
                    total: totalValid,
                }),

                // Actions rapides
                h('div', { class: 'mj-att__bulk-actions' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-att__bulk-btn mj-att__bulk-btn--present',
                        onClick: function () { handleBulkAction('present'); },
                        disabled: loading || totalValid === 0,
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.5 }, [
                            h('polyline', { points: '20 6 9 17 4 12' }),
                        ]),
                        'Tous présents',
                    ]),
                    h('button', {
                        type: 'button',
                        class: 'mj-att__bulk-btn mj-att__bulk-btn--absent',
                        onClick: function () { handleBulkAction('absent'); },
                        disabled: loading || totalValid === 0,
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.5 }, [
                            h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                            h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                        ]),
                        'Tous absents',
                    ]),
                    h('button', {
                        type: 'button',
                        class: 'mj-att__bulk-btn mj-att__bulk-btn--reset',
                        onClick: function () { handleBulkAction(''); },
                        disabled: loading || totalValid === 0,
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8' }),
                            h('path', { d: 'M3 3v5h5' }),
                        ]),
                        'Réinitialiser',
                    ]),
                ]),

                // Liste des participants validés
                h('div', { class: 'mj-att__list' }, [
                    totalValid === 0 && totalIrregular === 0 && h('div', { class: 'mj-att__empty' }, [
                        h('svg', { width: 48, height: 48, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.5 }, [
                            h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                            h('circle', { cx: 9, cy: 7, r: 4 }),
                            h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                            h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                        ]),
                        h('p', null, getString(strings, 'noValidRegistrations', 'Aucun inscrit pour cette séance.')),
                    ]),

                    // Section: Inscrits validés
                    categorizedRegistrations.valid.map(function (reg) {
                        var isLoading = loadingMembers[reg.memberId];
                        return h(AttendanceMemberCard, {
                            key: reg.id,
                            registration: reg,
                            attendanceStatus: getAttendanceStatus(reg.memberId),
                            onStatusChange: function (status) { handleStatusChange(reg, status); },
                            loading: isLoading,
                            strings: strings,
                            requiresPayment: requiresPayment,
                            requiresValidation: requiresValidation,
                            onViewMember: onViewMember,
                            hideOccurrenceIrregular: allowAttendanceAll,
                        });
                    }),
                ]),

                // Section: Membres irréguliers (non payés ou non inscrits à la séance)
                !allowAttendanceAll && totalIrregular > 0 && h('div', { class: 'mj-att__irregular-section' }, [
                    h('h2', { class: 'mj-att__irregular-title' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z' }),
                            h('line', { x1: 12, y1: 9, x2: 12, y2: 13 }),
                            h('line', { x1: 12, y1: 17, x2: 12.01, y2: 17 }),
                        ]),
                        'Situations irrégulières (' + totalIrregular + ')',
                    ]),

                    h('div', { class: 'mj-att__irregular-list' }, [
                        // Non payés
                        categorizedRegistrations.unpaid.map(function (reg) {
                            var isLoading = loadingMembers[reg.memberId];
                            return h(AttendanceMemberCard, {
                                key: 'unpaid-' + reg.id,
                                registration: reg,
                                attendanceStatus: getAttendanceStatus(reg.memberId),
                                onStatusChange: function (status) { handleStatusChange(reg, status); },
                                loading: isLoading,
                                irregular: 'unpaid',
                                onRegularize: handleRegularize,
                                strings: strings,
                                requiresPayment: requiresPayment,
                                requiresValidation: requiresValidation,
                                onViewMember: onViewMember,
                                hideOccurrenceIrregular: allowAttendanceAll,
                            });
                        }),

                        // Non inscrits à cette séance
                        categorizedRegistrations.notRegistered.map(function (reg) {
                            var isLoading = loadingMembers[reg.memberId];
                            return h(AttendanceMemberCard, {
                                key: 'notreg-' + reg.id,
                                registration: reg,
                                attendanceStatus: getAttendanceStatus(reg.memberId),
                                onStatusChange: function (status) { handleStatusChange(reg, status); },
                                loading: isLoading,
                                irregular: 'not-registered',
                                onRegularize: handleRegularize,
                                strings: strings,
                                requiresPayment: requiresPayment,
                                requiresValidation: requiresValidation,
                                onViewMember: onViewMember,
                                hideOccurrenceIrregular: allowAttendanceAll,
                            });
                        }),
                    ]),
                ]),
            ]),

            // Message si pas de séance
            !selectedOccurrence && occurrences.length > 0 && h('div', { class: 'mj-att__empty' }, [
                h('p', null, getString(strings, 'selectOccurrence', 'Sélectionnez une séance ci-dessus.')),
            ]),
        ]);
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrAttendance = {
        OccurrenceSelector: OccurrenceSelector,
        AttendanceStats: AttendanceStats,
        AttendanceMemberCard: AttendanceMemberCard,
        AttendanceSheet: AttendanceSheet,
    };

})(window);
