/**
 * Registration Manager - Main Application
 * Point d'entrée principal et assemblage des composants
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var render = global.preactRender || (preact && preact.render);

    if (!preact || !hooks || typeof render !== 'function') {
        console.warn('[MjRegMgr] Preact must be loaded before the registration manager.');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;
    var useRef = hooks.useRef;

    // Import des modules
    var Utils = global.MjRegMgrUtils;
    var EventsComps = global.MjRegMgrEvents;
    var RegComps = global.MjRegMgrRegistrations;
    var Modals = global.MjRegMgrModals;
    var Services = global.MjRegMgrServices;
    var AttendanceComps = global.MjRegMgrAttendance;
    var EventEditorModule = global.MjRegMgrEventEditor;
    var EventEditor = EventEditorModule ? EventEditorModule.EventEditor : null;
    var TabsModule = global.MjRegMgrTabs;
    var createOccurrenceTab = TabsModule && typeof TabsModule.createOccurrenceTab === 'function'
        ? TabsModule.createOccurrenceTab
        : null;
    var OCCURRENCE_TAB_KEY = TabsModule && typeof TabsModule.OCCURRENCE_TAB_KEY === 'string'
        ? TabsModule.OCCURRENCE_TAB_KEY
        : 'occurrence-encoder';
    var TabsComponent = TabsModule && typeof TabsModule.Tabs === 'function'
        ? TabsModule.Tabs
        : null;
    var ensureRegistrationsTab = TabsModule && typeof TabsModule.ensureRegistrationsTab === 'function'
        ? TabsModule.ensureRegistrationsTab
        : function (tabs, fallback) {
            var safeTabs = Array.isArray(tabs) ? tabs.filter(function (tab) { return !!tab; }) : [];
            var fallbackLabel = fallback && fallback.label ? fallback.label : 'Inscriptions';
            var fallbackBadge = fallback && typeof fallback.badge === 'number' ? fallback.badge : 0;
            var exists = safeTabs.some(function (tab) { return tab && tab.key === 'registrations'; });
            if (exists) {
                return safeTabs;
            }
            var fallbackTab = {
                key: 'registrations',
                label: fallbackLabel,
                badge: fallbackBadge,
            };
            return [fallbackTab].concat(safeTabs);
        };

    if (!Utils || !EventsComps || !RegComps || !Modals || !Services || !AttendanceComps) {
        console.warn('[MjRegMgr] Modules manquants');
        return;
    }

    var getString = Utils.getString;
    var classNames = Utils.classNames;
    var useModal = Utils.useModal;
    var useToasts = Utils.useToasts;

    var EventsSidebar = EventsComps.EventsSidebar;
    var RegistrationsList = RegComps.RegistrationsList;
    var AttendanceSheet = AttendanceComps.AttendanceSheet;
    var AddParticipantModal = Modals.AddParticipantModal;
    var CreateEventModal = Modals.CreateEventModal;
    var CreateMemberModal = Modals.CreateMemberModal;
    var AttachChildModal = Modals.AttachChildModal;
    var MemberNotesModal = Modals.MemberNotesModal;
    var MemberAccountModal = Modals.MemberAccountModal;
    var QRCodeModal = Modals.QRCodeModal;
    var OccurrencesModal = Modals.OccurrencesModal;
    var LocationModal = Modals.LocationModal;
    var AvatarCaptureModal = Modals.AvatarCaptureModal;

    // ============================================
    // EVENT DETAIL PANEL
    // ============================================

    function EventDetailPanel(props) {
        var event = props.event;
        var strings = props.strings;
        var config = props.config;
        var registrations = props.registrations || [];
        var attendanceMap = props.attendanceMap || {};
        var occurrences = props.occurrences || [];
        var loading = props.loading;
        var deletingEvent = props.deletingEvent;
        var onDeleteEvent = props.onDeleteEvent;
        var canDeleteEvent = props.canDeleteEvent !== undefined ? props.canDeleteEvent : (config && config.canDeleteEvent);

        if (!event || loading) {
            return h('div', { class: 'mj-regmgr-event-detail mj-regmgr-event-detail--loading' }, [
                h('div', { class: 'mj-regmgr-loading' }, [
                    h('div', { class: 'mj-regmgr-loading__spinner' }),
                    h('p', { class: 'mj-regmgr-loading__text' }, getString(strings, 'loading', 'Chargement des détails...')),
                ]),
            ]);
        }

        // Calculer les statistiques d'inscription
        var registrationStats = useMemo(function () {
            var valid = 0;
            var pending = 0;
            var paid = 0;
            var unpaid = 0;

            registrations.forEach(function (reg) {
                if (reg.status === 'valide') {
                    valid++;
                    paid++;
                } else {
                    pending++;
                    unpaid++;
                }
            });

            return {
                total: registrations.length,
                valid: valid,
                pending: pending,
                paid: paid,
                unpaid: unpaid,
            };
        }, [registrations]);

        // Calculer les statistiques de présence globales
        var attendanceStats = useMemo(function () {
            var totalPresent = 0;
            var totalAbsent = 0;
            var totalUndefined = 0;
            var byOccurrence = {};

            // Pour chaque occurrence
            occurrences.forEach(function (occ) {
                var occKey = typeof occ === 'string' ? occ : (occ.start || occ.date || '');
                var occData = attendanceMap[occKey] || {};
                
                var present = 0;
                var absent = 0;
                var undefined_ = 0;

                registrations.forEach(function (reg) {
                    if (reg.status !== 'valide') return;
                    
                    var entry = occData[reg.memberId];
                    if (entry && entry.status === 'present') {
                        present++;
                        totalPresent++;
                    } else if (entry && entry.status === 'absent') {
                        absent++;
                        totalAbsent++;
                    } else {
                        undefined_++;
                        totalUndefined++;
                    }
                });

                byOccurrence[occKey] = {
                    present: present,
                    absent: absent,
                    undefined: undefined_,
                    total: present + absent + undefined_,
                };
            });

            var totalRecorded = totalPresent + totalAbsent;
            var attendanceRate = totalRecorded > 0 
                ? Math.round((totalPresent / totalRecorded) * 100) 
                : 0;

            return {
                totalPresent: totalPresent,
                totalAbsent: totalAbsent,
                totalUndefined: totalUndefined,
                attendanceRate: attendanceRate,
                occurrencesCount: occurrences.length,
                byOccurrence: byOccurrence,
            };
        }, [registrations, attendanceMap, occurrences]);

        var emoji = typeof event.emoji === 'string' ? event.emoji : '';
        var fallbackTitle = getString(strings, 'eventUntitled', 'Sans titre');
        var displayTitle = event.title && event.title !== '' ? event.title : fallbackTitle;
        var detailTitleLabel = emoji ? (emoji + ' ' + displayTitle).trim() : displayTitle;

        return h('div', { class: 'mj-regmgr-event-detail' }, [
            // Header avec image
            event.coverUrl && h('div', { class: 'mj-regmgr-event-detail__cover' }, [
                h('img', { src: event.coverUrl, alt: event.title }),
            ]),

            h('div', { class: 'mj-regmgr-event-detail__content' }, [
                // Titre et type
                h('div', { class: 'mj-regmgr-event-detail__header' }, [
                    event.typeLabel && h('span', { class: 'mj-regmgr-badge mj-regmgr-badge--type-' + event.type },
                        event.typeLabel
                    ),
                    h('h2', {
                        class: 'mj-regmgr-event-detail__title',
                        'aria-label': detailTitleLabel,
                    }, [
                        emoji && h('span', {
                            class: 'mj-regmgr-event-detail__emoji',
                            'aria-hidden': 'true',
                        }, emoji),
                        h('span', { class: 'mj-regmgr-event-detail__title-text' }, displayTitle),
                    ]),
                ]),

                // Infos principales
                h('div', { class: 'mj-regmgr-event-detail__info' }, [
                    // Date
                    h('div', { class: 'mj-regmgr-event-detail__row' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                            h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                            h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                            h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                        ]),
                        h('span', null, event.dateDebutFormatted),
                        event.dateFinFormatted && event.dateFinFormatted !== event.dateDebutFormatted && [
                            ' - ',
                            event.dateFinFormatted,
                        ],
                    ]),

                    // Lieu
                    event.location && h('div', { class: 'mj-regmgr-event-detail__row' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z' }),
                            h('circle', { cx: 12, cy: 10, r: 3 }),
                        ]),
                        h('span', null, event.location.name),
                    ]),

                    // Capacité
                    h('div', { class: 'mj-regmgr-event-detail__row' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                            h('circle', { cx: 9, cy: 7, r: 4 }),
                            h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                            h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                        ]),
                        h('span', null, [
                            event.registrationsCount + ' inscrit' + (event.registrationsCount > 1 ? 's' : ''),
                            event.capacityTotal > 0 && ' / ' + event.capacityTotal + ' places',
                        ]),
                    ]),

                    // Prix
                    h('div', { class: 'mj-regmgr-event-detail__row' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('line', { x1: 12, y1: 1, x2: 12, y2: 23 }),
                            h('path', { d: 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6' }),
                        ]),
                        h('span', null, event.prix > 0 
                            ? event.prix.toFixed(2) + ' €' 
                            : getString(strings, 'eventFree', 'Gratuit')
                        ),
                    ]),

                    // Âge
                    (event.ageMin > 0 || event.ageMax < 99) && h('div', { class: 'mj-regmgr-event-detail__row' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('circle', { cx: 12, cy: 12, r: 10 }),
                            h('polyline', { points: '12 6 12 12 16 14' }),
                        ]),
                        h('span', null, event.ageMin + ' - ' + event.ageMax + ' ans'),
                    ]),
                ]),

                // Section Statistiques
                h('div', { class: 'mj-regmgr-event-detail__stats' }, [
                    h('h2', { class: 'mj-regmgr-event-detail__section-title' }, 'Statistiques'),

                    // Grille de stats
                    h('div', { class: 'mj-regmgr-stats-grid' }, [
                        // Inscriptions validées
                        h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--success' }, [
                            h('div', { class: 'mj-regmgr-stat-card__value' }, registrationStats.valid),
                            h('div', { class: 'mj-regmgr-stat-card__label' }, 'Validées'),
                        ]),
                        // Inscriptions en attente
                        h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--warning' }, [
                            h('div', { class: 'mj-regmgr-stat-card__value' }, registrationStats.pending),
                            h('div', { class: 'mj-regmgr-stat-card__label' }, 'En attente'),
                        ]),
                        // Taux de présence
                        attendanceStats.occurrencesCount > 0 && h('div', { class: 'mj-regmgr-stat-card mj-regmgr-stat-card--info' }, [
                            h('div', { class: 'mj-regmgr-stat-card__value' }, attendanceStats.attendanceRate + '%'),
                            h('div', { class: 'mj-regmgr-stat-card__label' }, 'Taux présence'),
                        ]),
                        // Séances
                        attendanceStats.occurrencesCount > 0 && h('div', { class: 'mj-regmgr-stat-card' }, [
                            h('div', { class: 'mj-regmgr-stat-card__value' }, attendanceStats.occurrencesCount),
                            h('div', { class: 'mj-regmgr-stat-card__label' }, 'Séance' + (attendanceStats.occurrencesCount > 1 ? 's' : '')),
                        ]),
                    ]),

                    // Détail présences si séances multiples
                    attendanceStats.occurrencesCount > 0 && h('div', { class: 'mj-regmgr-event-detail__attendance-summary' }, [
                        h('div', { class: 'mj-regmgr-attendance-bar' }, [
                            h('div', { class: 'mj-regmgr-attendance-bar__segment mj-regmgr-attendance-bar__segment--present', style: { flex: attendanceStats.totalPresent } }),
                            h('div', { class: 'mj-regmgr-attendance-bar__segment mj-regmgr-attendance-bar__segment--absent', style: { flex: attendanceStats.totalAbsent } }),
                            h('div', { class: 'mj-regmgr-attendance-bar__segment mj-regmgr-attendance-bar__segment--undefined', style: { flex: attendanceStats.totalUndefined } }),
                        ]),
                        h('div', { class: 'mj-regmgr-attendance-legend' }, [
                            h('span', { class: 'mj-regmgr-attendance-legend__item mj-regmgr-attendance-legend__item--present' }, [
                                h('span', { class: 'mj-regmgr-attendance-legend__dot' }),
                                attendanceStats.totalPresent + ' présent' + (attendanceStats.totalPresent > 1 ? 's' : ''),
                            ]),
                            h('span', { class: 'mj-regmgr-attendance-legend__item mj-regmgr-attendance-legend__item--absent' }, [
                                h('span', { class: 'mj-regmgr-attendance-legend__dot' }),
                                attendanceStats.totalAbsent + ' absent' + (attendanceStats.totalAbsent > 1 ? 's' : ''),
                            ]),
                            attendanceStats.totalUndefined > 0 && h('span', { class: 'mj-regmgr-attendance-legend__item mj-regmgr-attendance-legend__item--undefined' }, [
                                h('span', { class: 'mj-regmgr-attendance-legend__dot' }),
                                attendanceStats.totalUndefined + ' non défini' + (attendanceStats.totalUndefined > 1 ? 's' : ''),
                            ]),
                        ]),
                    ]),
                ]),

                // Boutons d'action
                h('div', { class: 'mj-regmgr-event-detail__actions' }, [
                    // Lien vers EventPage
                    event.eventPageUrl && h('a', {
                        href: event.eventPageUrl,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        class: 'mj-btn mj-btn--primary',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                            h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                            h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                            h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                        ]),
                        getString(strings, 'openEventPage', 'Voir la page événement'),
                    ]),

                    // Lien vers la page front (article lié)
                    event.frontUrl && h('a', {
                        href: event.frontUrl,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        class: 'mj-btn mj-btn--secondary',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                            h('polyline', { points: '15 3 21 3 21 9' }),
                            h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                        ]),
                        getString(strings, 'viewLinkedArticle', 'Voir sur le site'),
                    ]),

                    // Bouton suppression
                    canDeleteEvent && typeof onDeleteEvent === 'function' && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--danger',
                        onClick: function () { onDeleteEvent(event); },
                        disabled: !!deletingEvent,
                        'aria-disabled': deletingEvent ? 'true' : undefined,
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '3 6 5 6 21 6' }),
                            h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6' }),
                            h('path', { d: 'M10 11v6' }),
                            h('path', { d: 'M14 11v6' }),
                            h('path', { d: 'M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2' }),
                        ]),
                        deletingEvent
                            ? getString(strings, 'deleteEventLoading', 'Suppression...')
                            : getString(strings, 'deleteEvent', 'Supprimer'),
                    ]),
                ]),
            ]),
        ]);
    }

    // ============================================
    // TABS
    // ============================================

    function OccurrenceEncoderPanel(props) {
        var event = props.event || null;
        var occurrencesProp = Array.isArray(props.occurrences) ? props.occurrences : [];
        var strings = props.strings || {};
        var locale = props.locale || 'fr';
        var onPersistOccurrences = typeof props.onPersistOccurrences === 'function' ? props.onPersistOccurrences : null;

        var _isPersisting = useState(false);
        var isPersisting = _isPersisting[0];
        var setIsPersisting = _isPersisting[1];

        var resolvedLocale = useMemo(function () {
            return resolveLocaleTag(locale);
        }, [locale]);

        var normalizedOccurrences = useMemo(function () {
            return occurrencesProp.map(function (occ, index) {
                return normalizeOccurrence(occ, index);
            });
        }, [occurrencesProp]);

        var _localOccurrences = useState(normalizedOccurrences);
        var localOccurrences = _localOccurrences[0];
        var setLocalOccurrences = _localOccurrences[1];

        useEffect(function () {
            setLocalOccurrences(normalizedOccurrences);
        }, [normalizedOccurrences]);

        var _selectedId = useState(normalizedOccurrences.length > 0 ? normalizedOccurrences[0].id : null);
        var selectedOccurrenceId = _selectedId[0];
        var setSelectedOccurrenceId = _selectedId[1];

        useEffect(function () {
            setSelectedOccurrenceId(normalizedOccurrences.length > 0 ? normalizedOccurrences[0].id : null);
        }, [normalizedOccurrences]);

        var initialPivotDate = useMemo(function () {
            return deriveInitialPivotDate(normalizedOccurrences, event);
        }, [normalizedOccurrences, event]);

        var eventGeneratorPlan = useMemo(function () {
            if (!event || !event.occurrenceGenerator) {
                return null;
            }
            return normalizeGeneratorPlan(event.occurrenceGenerator);
        }, [event]);

        var eventGeneratorPlanSignature = useMemo(function () {
            if (!eventGeneratorPlan) {
                return 'none';
            }
            try {
                return JSON.stringify(eventGeneratorPlan);
            } catch (error) {
                return 'none';
            }
        }, [eventGeneratorPlan]);

        var _pivotDate = useState(initialPivotDate);
        var pivotDate = _pivotDate[0];
        var setPivotDate = _pivotDate[1];

        useEffect(function () {
            if (!(initialPivotDate instanceof Date) || Number.isNaN(initialPivotDate.getTime())) {
                return;
            }
            setPivotDate(function () {
                if (viewMode === 'week') {
                    return alignDateToWeekStart(initialPivotDate);
                }
                return new Date(initialPivotDate.getFullYear(), initialPivotDate.getMonth(), 1);
            });
        }, [initialPivotDate, viewMode]);

        var _viewMode = useState('quarter');
        var viewMode = _viewMode[0];
        var setViewMode = _viewMode[1];

        var occurrencesByDate = useMemo(function () {
            var map = {};
            localOccurrences.forEach(function (occ) {
                if (!map[occ.date]) {
                    map[occ.date] = [];
                }
                map[occ.date].push(occ);
            });
            return map;
        }, [localOccurrences]);

        var selectedOccurrence = useMemo(function () {
            if (!selectedOccurrenceId) {
                return null;
            }
            return localOccurrences.find(function (occ) { return occ.id === selectedOccurrenceId; }) || null;
        }, [localOccurrences, selectedOccurrenceId]);

        var _editorState = useState(createEditorState(selectedOccurrence));
        var editorState = _editorState[0];
        var setEditorState = _editorState[1];

        useEffect(function () {
            if (selectedOccurrence) {
                setEditorState(createEditorState(selectedOccurrence));
            }
        }, [selectedOccurrence]);

        var editorHasDate = editorState && typeof editorState.date === 'string' && editorState.date !== '';
        var editorHasExistingId = editorState && !!editorState.id;
        var hasExistingOccurrences = localOccurrences.length > 0;
        var shouldShowEditorCard = !!(selectedOccurrenceId || editorHasExistingId || editorHasDate || !hasExistingOccurrences);
        var isCreatingNewOccurrence = !selectedOccurrenceId && !editorHasExistingId && (editorHasDate || !hasExistingOccurrences);
        var editorCardTitle = isCreatingNewOccurrence
            ? getString(strings, 'occurrenceEditorCreateTitle', 'Ajoute une occurrence')
            : getString(strings, 'occurrenceEditorTitle', "Modifier l'occurrence sélectionnée");

        var statusOptions = useMemo(function () {
            return [
                { value: 'planned', label: getString(strings, 'occurrenceStatusPlanned', 'Prévu') },
                { value: 'confirmed', label: getString(strings, 'occurrenceStatusConfirmed', 'Confirmée') },
                { value: 'cancelled', label: getString(strings, 'occurrenceStatusCancelled', 'Annulé') },
            ];
        }, [strings]);

        var statusLabelMap = useMemo(function () {
            return {
                planned: getString(strings, 'occurrenceStatusPlanned', 'Prévu'),
                confirmed: getString(strings, 'occurrenceStatusConfirmed', 'Confirmée'),
                cancelled: getString(strings, 'occurrenceStatusCancelled', 'Annulé'),
            };
        }, [strings]);

        var weekdayLabels = useMemo(function () {
            return [
                getString(strings, 'occurrenceDayMon', 'Lun'),
                getString(strings, 'occurrenceDayTue', 'Mar'),
                getString(strings, 'occurrenceDayWed', 'Mer'),
                getString(strings, 'occurrenceDayThu', 'Jeu'),
                getString(strings, 'occurrenceDayFri', 'Ven'),
                getString(strings, 'occurrenceDaySat', 'Sam'),
                getString(strings, 'occurrenceDaySun', 'Dim'),
            ];
        }, [strings]);

        var weekdayFullLabels = useMemo(function () {
            return [
                getString(strings, 'occurrenceDayMondayFull', 'Lundi'),
                getString(strings, 'occurrenceDayTuesdayFull', 'Mardi'),
                getString(strings, 'occurrenceDayWednesdayFull', 'Mercredi'),
                getString(strings, 'occurrenceDayThursdayFull', 'Jeudi'),
                getString(strings, 'occurrenceDayFridayFull', 'Vendredi'),
                getString(strings, 'occurrenceDaySaturdayFull', 'Samedi'),
                getString(strings, 'occurrenceDaySundayFull', 'Dimanche'),
            ];
        }, [strings]);

        var monthlyOrdinalOptions = useMemo(function () {
            return [
                { value: 'first', label: getString(strings, 'occurrenceGeneratorOrdinalFirst', '1er') },
                { value: 'second', label: getString(strings, 'occurrenceGeneratorOrdinalSecond', '2e') },
                { value: 'third', label: getString(strings, 'occurrenceGeneratorOrdinalThird', '3e') },
                { value: 'fourth', label: getString(strings, 'occurrenceGeneratorOrdinalFourth', '4e') },
                { value: 'last', label: getString(strings, 'occurrenceGeneratorOrdinalLast', 'Dernier') },
            ];
        }, [strings]);

        var months = useMemo(function () {
            if (viewMode !== 'quarter') {
                return [];
            }
            return buildQuarterMonths(pivotDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId);
        }, [pivotDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId, viewMode]);

        var singleMonthOverview = useMemo(function () {
            if (!(pivotDate instanceof Date) || Number.isNaN(pivotDate.getTime())) {
                return null;
            }
            var monthDate = new Date(pivotDate.getFullYear(), pivotDate.getMonth(), 1);
            return buildMonthOverview(monthDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId);
        }, [pivotDate, resolvedLocale, occurrencesByDate, selectedOccurrenceId]);

        var weekOverview = useMemo(function () {
            if (viewMode !== 'week') {
                return null;
            }
            return buildWeekOverview(pivotDate, occurrencesByDate, selectedOccurrenceId);
        }, [pivotDate, occurrencesByDate, selectedOccurrenceId, viewMode]);

        var calendarMonths = viewMode === 'month'
            ? (singleMonthOverview ? [singleMonthOverview] : [])
            : months;

        var weekTimeScale = useMemo(function () {
            if (!weekOverview) {
                return {
                    min: 9 * 60,
                    max: 17 * 60,
                    range: 8 * 60,
                    ticks: [],
                };
            }
            var minMinutes = weekOverview.minMinutes;
            var maxMinutes = weekOverview.maxMinutes;
            var paddedMin = Math.max(0, Math.floor((minMinutes - 30) / 60) * 60);
            var paddedMax = Math.min(24 * 60, Math.ceil((maxMinutes + 30) / 60) * 60);
            if (paddedMax <= paddedMin) {
                paddedMax = Math.min(24 * 60, paddedMin + 120);
            }
            var ticks = [];
            for (var cursor = paddedMin; cursor <= paddedMax; cursor += 60) {
                ticks.push({
                    minutes: cursor,
                    label: formatPreviewTime(minutesToTime(cursor)),
                });
            }
            return {
                min: paddedMin,
                max: paddedMax,
                range: Math.max(60, paddedMax - paddedMin),
                ticks: ticks,
            };
        }, [weekOverview]);

        var WEEK_VIEW_HEIGHT = 560;
        var WEEK_CREATION_STEP_MINUTES = 15;
        var WEEK_CREATION_DEFAULT_DURATION = 60;
        var weekTimelineRange = Math.max(60, weekTimeScale.range || 0);

        var weekRangeLabel = useMemo(function () {
            if (!weekOverview) {
                return '';
            }
            var start = weekOverview.start;
            var end = weekOverview.end;
            if (!(start instanceof Date) || Number.isNaN(start.getTime()) || !(end instanceof Date) || Number.isNaN(end.getTime())) {
                return '';
            }
            var options = { day: 'numeric', month: 'short' };
            var startLabel;
            var endLabel;
            try {
                startLabel = start.toLocaleDateString(resolvedLocale, options);
            } catch (error) {
                startLabel = start.toLocaleDateString('fr', options);
            }
            try {
                endLabel = end.toLocaleDateString(resolvedLocale, options);
            } catch (error2) {
                endLabel = end.toLocaleDateString('fr', options);
            }
            var template = getString(strings, 'occurrenceWeekRange', 'Semaine du {start} au {end}');
            return template.replace('{start}', startLabel).replace('{end}', endLabel);
        }, [weekOverview, resolvedLocale, strings]);

        var handleWeekColumnBackgroundClick = useCallback(function (day, event) {
            if (!day || !weekOverview || !weekTimeScale || typeof event !== 'object') {
                return;
            }
            var target = event.currentTarget;
            if (!target || typeof target.getBoundingClientRect !== 'function') {
                return;
            }
            var rect = target.getBoundingClientRect();
            var pointerY = typeof event.clientY === 'number' ? event.clientY : rect.top;
            var offsetY = pointerY - rect.top;
            if (offsetY < 0) {
                offsetY = 0;
            }
            var height = rect.height > 0 ? rect.height : 1;
            var ratio = offsetY / height;
            if (!Number.isFinite(ratio)) {
                ratio = 0;
            }
            ratio = Math.min(1, Math.max(0, ratio));
            var rawMinutes = weekTimeScale.min + (ratio * weekTimelineRange);
            var snappedStart = Math.floor(rawMinutes / WEEK_CREATION_STEP_MINUTES) * WEEK_CREATION_STEP_MINUTES;
            var safeMaxStart = weekTimeScale.max - WEEK_CREATION_STEP_MINUTES;
            if (safeMaxStart < weekTimeScale.min) {
                safeMaxStart = weekTimeScale.min;
            }
            if (snappedStart < weekTimeScale.min) {
                snappedStart = weekTimeScale.min;
            }
            if (snappedStart > safeMaxStart) {
                snappedStart = safeMaxStart;
            }
            var snappedEnd = snappedStart + WEEK_CREATION_DEFAULT_DURATION;
            if (snappedEnd > weekTimeScale.max) {
                snappedEnd = weekTimeScale.max;
                snappedStart = Math.max(weekTimeScale.min, snappedEnd - WEEK_CREATION_DEFAULT_DURATION);
            }
            if (snappedEnd <= snappedStart) {
                snappedEnd = Math.min(weekTimeScale.max, snappedStart + WEEK_CREATION_STEP_MINUTES);
            }
            var startTime = minutesToTime(snappedStart);
            var endTime = minutesToTime(snappedEnd);
            setSelectedOccurrenceId(null);
            setEditorState(function () {
                var next = createEditorState(null);
                next.date = day.iso;
                next.startTime = startTime;
                next.endTime = endTime;
                next.status = 'planned';
                next.reason = '';
                return next;
            });
        }, [weekOverview, weekTimeScale, weekTimelineRange, setSelectedOccurrenceId, setEditorState]);

        var _generatorState = useState(createGeneratorState(initialPivotDate));
        var generatorState = _generatorState[0];
        var setGeneratorState = _generatorState[1];

        var safeGeneratorState = generatorState && typeof generatorState === 'object'
            ? generatorState
            : createGeneratorState(initialPivotDate);

        var generatorDays = safeGeneratorState.days && typeof safeGeneratorState.days === 'object'
            ? safeGeneratorState.days
            : {};
        var generatorMode = typeof safeGeneratorState.mode === 'string'
            ? safeGeneratorState.mode
            : 'weekly';
        var generatorFrequency = typeof safeGeneratorState.frequency === 'string'
            ? safeGeneratorState.frequency
            : 'every_week';
        var generatorOverrides = safeGeneratorState.timeOverrides && typeof safeGeneratorState.timeOverrides === 'object'
            ? safeGeneratorState.timeOverrides
            : {};
        var generatorStartDate = typeof safeGeneratorState.startDate === 'string'
            ? safeGeneratorState.startDate
            : '';
        var generatorEndDate = typeof safeGeneratorState.endDate === 'string'
            ? safeGeneratorState.endDate
            : '';
        var generatorStartTime = typeof safeGeneratorState.startTime === 'string'
            ? safeGeneratorState.startTime
            : '';
        var generatorEndTime = typeof safeGeneratorState.endTime === 'string'
            ? safeGeneratorState.endTime
            : '';
        var generatorMonthlyOrdinal = typeof safeGeneratorState.monthlyOrdinal === 'string'
            ? safeGeneratorState.monthlyOrdinal
            : 'first';
        var generatorMonthlyWeekday = typeof safeGeneratorState.monthlyWeekday === 'string'
            ? safeGeneratorState.monthlyWeekday
            : 'mon';
        useEffect(function () {
            if (!eventGeneratorPlan) {
                return;
            }
            setGeneratorState(function () {
                return createGeneratorStateFromPlan(eventGeneratorPlan, initialPivotDate);
            });
        }, [eventGeneratorPlanSignature, initialPivotDate, eventGeneratorPlan]);

        useEffect(function () {
            if (eventGeneratorPlan) {
                return;
            }
            setGeneratorState(function (prev) {
                var next = Object.assign({}, prev);
                if (!prev._explicitStart) {
                    next.startDate = formatISODate(initialPivotDate);
                }
                if (typeof prev.endDate === 'string' && prev.endDate !== '') {
                    var startDateObj = parseISODate(next.startDate);
                    var endDateObj = parseISODate(prev.endDate);
                    if (startDateObj && endDateObj && endDateObj < startDateObj) {
                        next.endDate = next.startDate;
                    }
                } else {
                    next.endDate = '';
                }
                return next;
            });
        }, [initialPivotDate, eventGeneratorPlan]);

        var initialSchedulePreview = useMemo(function () {
            if (event && typeof event.occurrenceScheduleSummary === 'string' && event.occurrenceScheduleSummary !== '') {
                return event.occurrenceScheduleSummary;
            }
            if (event && typeof event.scheduleSummary === 'string' && event.scheduleSummary !== '') {
                return event.scheduleSummary;
            }
            if (event && typeof event.scheduleDetail === 'string' && event.scheduleDetail !== '') {
                return event.scheduleDetail;
            }
            return '';
        }, [event]);

        var _schedulePreview = useState(initialSchedulePreview);
        var schedulePreview = _schedulePreview[0];
        var setSchedulePreview = _schedulePreview[1];

        var _schedulePreviewVisible = useState(initialSchedulePreview !== '');
        var schedulePreviewVisible = _schedulePreviewVisible[0];
        var setSchedulePreviewVisible = _schedulePreviewVisible[1];

        var _schedulePreviewAutoSync = useState(false);
        var schedulePreviewAutoSync = _schedulePreviewAutoSync[0];
        var setSchedulePreviewAutoSync = _schedulePreviewAutoSync[1];

        useEffect(function () {
            setSchedulePreview(initialSchedulePreview);
            setSchedulePreviewVisible(initialSchedulePreview !== '');
            setSchedulePreviewAutoSync(initialSchedulePreview !== '');
        }, [initialSchedulePreview]);

        var selectedDayOccurrences = useMemo(function () {
            if (!selectedOccurrence || !selectedOccurrence.date) {
                return [];
            }
            return occurrencesByDate[selectedOccurrence.date] || [selectedOccurrence];
        }, [selectedOccurrence, occurrencesByDate]);

        var sidebarContent = h('div', { class: 'mj-regmgr-occurrence__sidebar' }, [
            !shouldShowEditorCard && h('p', { class: 'mj-regmgr-occurrence__hint mj-regmgr-occurrence__hint--empty' },
                getString(strings, 'occurrenceEmptySelection', 'Sélectionnez une date dans le calendrier pour commencer.')
            ),
            shouldShowEditorCard && h('div', { class: 'mj-regmgr-occurrence__card' }, [
                h('h2', null, editorCardTitle),
                selectedOccurrence && selectedDayOccurrences.length > 1 && h('div', { class: 'mj-regmgr-occurrence__occurrence-list' }, selectedDayOccurrences.map(function (item) {
                    var chipStatus = item && typeof item.status === 'string' ? normalizeOccurrenceStatus(item.status) : '';
                    return h('button', {
                        key: item.id,
                        type: 'button',
                        class: classNames('mj-regmgr-occurrence__occurrence-chip', {
                            'mj-regmgr-occurrence__occurrence-chip--active': item.id === selectedOccurrenceId,
                        }, chipStatus ? 'mj-regmgr-occurrence__occurrence-chip--status-' + chipStatus : null),
                        onClick: function () { setSelectedOccurrenceId(item.id); },
                    }, item.startTime + ' - ' + item.endTime);
                })),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceDateLabel', 'Date')),
                    h('input', {
                        type: 'date',
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.date,
                        onInput: function (event) { handleEditorChange('date', event.currentTarget.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceTypeLabel', 'Type')),
                    h('select', {
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.status,
                        onInput: function (event) { handleEditorChange('status', event.currentTarget.value); },
                    }, statusOptions.map(function (option) {
                        return h('option', { key: option.value, value: option.value }, option.label);
                    })),
                ]),
                editorState.status === 'cancelled' && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceReasonLabel', 'Motif d\'annulation')),
                    h('input', {
                        type: 'text',
                        class: 'mj-regmgr-occurrence__input',
                        value: editorState.reason,
                        placeholder: getString(strings, 'occurrenceReasonPlaceholder', 'Ex: Problème technique'),
                        onInput: function (event) { handleEditorChange('reason', event.currentTarget.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceStartLabel', 'Heure de début')),
                        h('input', {
                            type: 'time',
                            class: 'mj-regmgr-occurrence__input',
                            value: editorState.startTime,
                            onInput: function (event) { handleEditorChange('startTime', event.currentTarget.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceEndLabel', 'Heure de fin')),
                        h('input', {
                            type: 'time',
                            class: 'mj-regmgr-occurrence__input',
                            value: editorState.endTime,
                            onInput: function (event) { handleEditorChange('endTime', event.currentTarget.value); },
                        }),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__actions' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--primary',
                        onClick: function (e) { handleUpdateOccurrence(e); },
                        disabled: isPersisting,
                    }, editorState.id
                        ? getString(strings, 'occurrenceUpdateButton', 'Modifier cette occurrence')
                        : getString(strings, 'occurrenceCreateButton', 'Créer l\'occurrence')
                    ),
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--secondary',
                        onClick: function (e) { handleCancelEdit(e); },
                    }, getString(strings, 'occurrenceCancelButton', 'Annuler')),
                    selectedOccurrenceId && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--danger',
                        onClick: function (e) { handleDeleteOccurrence(e); },
                        disabled: isPersisting,
                    }, getString(strings, 'occurrenceDeleteButton', 'Supprimer')),
                ]),
                localOccurrences.length > 0 && h('div', { class: 'mj-regmgr-occurrence__delete-all' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--danger',
                        onClick: function (e) { handleDeleteAllOccurrences(e); },
                        disabled: isPersisting,
                    }, getString(strings, 'occurrenceDeleteAllButton', 'Supprimer toutes les occurrences')),
                ]),
            ]),
            h('div', { class: 'mj-regmgr-occurrence__card' }, [
                h('h2', null, getString(strings, 'occurrenceGeneratorTitle', 'Générer des occurrences')),
                h('p', { class: 'mj-regmgr-occurrence__description' },
                    getString(strings, 'occurrenceGeneratorDescription', 'Planifiez la récurrence automatique de cet événement.')
                ),
                h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorModeLabel', 'Mode')),
                    h('select', {
                        class: 'mj-regmgr-occurrence__input',
                        value: generatorMode,
                        onInput: function (event) { handleGeneratorChange('mode', event.currentTarget.value); },
                    }, [
                        h('option', { value: 'weekly' }, getString(strings, 'occurrenceGeneratorModeWeekly', 'Hebdomadaire')),
                        h('option', { value: 'monthly' }, getString(strings, 'occurrenceGeneratorModeMonthly', 'Mensuel')),
                        h('option', { value: 'range' }, getString(strings, 'occurrenceGeneratorModeRange', 'Plage de dates')),
                    ]),
                ]),
                generatorMode === 'weekly' && h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                    h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorFrequencyLabel', 'Fréquence')),
                    h('select', {
                        class: 'mj-regmgr-occurrence__input',
                        value: generatorFrequency,
                        onInput: function (event) { handleGeneratorChange('frequency', event.currentTarget.value); },
                    }, [
                        h('option', { value: 'every_week' }, getString(strings, 'occurrenceGeneratorEveryWeek', 'Chaque semaine')),
                        h('option', { value: 'every_two_weeks' }, getString(strings, 'occurrenceGeneratorEveryTwoWeeks', 'Toutes les deux semaines')),
                    ]),
                ]),
                generatorMode === 'monthly' && h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorMonthlyOrdinalLabel', 'Ordre dans le mois')),
                        h('select', {
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorMonthlyOrdinal,
                            onInput: function (event) { handleGeneratorChange('monthlyOrdinal', event.currentTarget.value); },
                        }, monthlyOrdinalOptions.map(function (option) {
                            return h('option', { key: option.value, value: option.value }, option.label);
                        })),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorMonthlyWeekdayLabel', 'Jour de la semaine')),
                        h('select', {
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorMonthlyWeekday,
                            onInput: function (event) { handleGeneratorChange('monthlyWeekday', event.currentTarget.value); },
                        }, OCCURRENCE_WEEKDAY_KEYS.map(function (dayKey, index) {
                            return h('option', { key: dayKey, value: dayKey }, weekdayFullLabels[index] || weekdayLabels[index]);
                        })),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorStartDate', 'Date de début')),
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorStartDate,
                            onInput: function (event) { handleGeneratorChange('startDate', event.currentTarget.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceGeneratorEndDate', 'Date de fin')),
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorEndDate,
                            min: generatorStartDate || undefined,
                            onInput: function (event) { handleGeneratorChange('endDate', event.currentTarget.value); },
                        }),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__form-row' }, [
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceStartLabel', 'Heure de début')),
                        h('input', {
                            type: 'time',
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorStartTime,
                            onInput: function (event) { handleGeneratorChange('startTime', event.currentTarget.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__form-field' }, [
                        h('label', { class: 'mj-regmgr-occurrence__label' }, getString(strings, 'occurrenceEndLabel', 'Heure de fin')),
                        h('input', {
                            type: 'time',
                            class: 'mj-regmgr-occurrence__input',
                            value: generatorEndTime,
                            onInput: function (event) { handleGeneratorChange('endTime', event.currentTarget.value); },
                        }),
                    ]),
                ]),
                generatorMode === 'weekly' && h('div', { class: 'mj-regmgr-occurrence__days' }, OCCURRENCE_WEEKDAY_KEYS.map(function (dayKey, index) {
                    var isActive = !!generatorDays[dayKey];
                    var override = generatorOverrides[dayKey] || null;
                    var startValue = override && override.start ? override.start : generatorStartTime;
                    var endValue = override && override.end ? override.end : generatorEndTime;
                    var hasOverride = !!(override && (override.start || override.end));
                    return h('label', {
                        key: dayKey,
                        class: classNames('mj-regmgr-occurrence__day-row', {
                            'mj-regmgr-occurrence__day-row--active': isActive,
                            'mj-regmgr-occurrence__day-row--override': hasOverride,
                        }),
                    }, [
                        h('input', {
                            type: 'checkbox',
                            class: 'mj-regmgr-occurrence__day-row-checkbox',
                            checked: isActive,
                            onChange: function () { handleGeneratorDayToggle(dayKey); },
                        }),
                        h('span', { class: 'mj-regmgr-occurrence__day-row-label' }, weekdayLabels[index]),
                        h('span', { class: 'mj-regmgr-occurrence__day-row-times' }, [
                            h('input', {
                                type: 'time',
                                class: classNames('mj-regmgr-occurrence__day-row-input', 'mj-regmgr-occurrence__day-row-input--start', {
                                    'mj-regmgr-occurrence__day-row-input--override': hasOverride && !!(override && override.start),
                                }),
                                value: startValue || '',
                                disabled: !isActive,
                                onInput: function (event) { handleGeneratorTimeChange(dayKey, 'start', event.currentTarget.value); },
                            }),
                            h('span', { class: 'mj-regmgr-occurrence__day-row-separator' }, ' - '),
                            h('input', {
                                type: 'time',
                                class: classNames('mj-regmgr-occurrence__day-row-input', 'mj-regmgr-occurrence__day-row-input--end', {
                                    'mj-regmgr-occurrence__day-row-input--override': hasOverride && !!(override && override.end),
                                }),
                                value: endValue || '',
                                disabled: !isActive,
                                onInput: function (event) { handleGeneratorTimeChange(dayKey, 'end', event.currentTarget.value); },
                            }),
                        ]),
                    ]);
                })),
                h('div', { class: 'mj-regmgr-occurrence__generator-actions' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--primary',
                        onClick: function (e) { handleAddOccurrences(e); },
                        disabled: isPersisting,
                    }, getString(strings, 'occurrenceGeneratorAddButton', 'Ajouter les occurrences')),
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--secondary',
                        onClick: function (e) { handleUpdateSchedulePreview(e); },
                        disabled: isPersisting,
                        style: { marginLeft: '0.75rem' },
                    }, getString(strings, 'occurrenceGeneratorPreviewButton', 'Mettre à jour l\'horaire')),
                ]),
                schedulePreviewVisible && h('div', { class: 'mj-regmgr-occurrence__preview' }, [
                    h('span', { class: 'mj-regmgr-occurrence__preview-label' }, getString(strings, 'occurrenceGeneratorPreviewLabel', 'Aperçu de l\'horaire')),
                    h('p', { class: 'mj-regmgr-occurrence__preview-value' }, schedulePreview && schedulePreview.trim() !== ''
                        ? schedulePreview
                        : getString(strings, 'occurrenceSchedulePreviewEmpty', 'Aucun horaire détecté')
                    ),
                ]),
            ]),
        ]);

        var handleGeneratorTimeChange = useCallback(function (dayKey, field, value) {
            if (!dayKey || (field !== 'start' && field !== 'end')) {
                return;
            }
            setGeneratorState(function (prev) {
                var next = Object.assign({}, prev);
                var overrides = Object.assign({}, prev.timeOverrides || {});
                var current = overrides[dayKey] ? Object.assign({ start: '', end: '' }, overrides[dayKey]) : { start: '', end: '' };
                current[field] = value || '';
                if (!current.start && !current.end) {
                    delete overrides[dayKey];
                } else {
                    overrides[dayKey] = current;
                }
                next.timeOverrides = overrides;
                return next;
            });
        }, []);

        var handleUpdateSchedulePreview = useCallback(function () {
            var planResult = buildGeneratorPlan();
            var previewText = computeSchedulePreview(planResult);
            var trimmed = typeof previewText === 'string' ? previewText.trim() : '';
            setSchedulePreview(trimmed);
            setSchedulePreviewVisible(true);
            setSchedulePreviewAutoSync(true);
        }, [buildGeneratorPlan, computeSchedulePreview]);

        var persistOccurrences = useCallback(function (nextList, rollback, previewOverride) {
            if (!onPersistOccurrences) {
                return Promise.resolve();
            }
            setIsPersisting(true);
            var summaryPayload = typeof previewOverride === 'string' ? previewOverride : schedulePreview;
            var planResult = buildGeneratorPlan();
            var generatorPayload = serializeGeneratorPlan(planResult, generatorState);
            return Promise.resolve(onPersistOccurrences(nextList, summaryPayload, generatorPayload))
                .then(function (result) {
                    setIsPersisting(false);
                    return result;
                })
                .catch(function (error) {
                    setIsPersisting(false);
                    if (typeof rollback === 'function') {
                        rollback();
                    }
                    return Promise.reject(error);
                });
        }, [onPersistOccurrences, schedulePreview, buildGeneratorPlan, generatorState]);

        var handleSelectDay = useCallback(function (day) {
            if (day.hasOccurrences && day.occurrences.length > 0) {
                var target = day.occurrences[0];
                setSelectedOccurrenceId(target.id);
            } else {
                setSelectedOccurrenceId(null);
                var baseState = createEditorState(null);
                baseState.date = day.iso;
                setEditorState(baseState);
            }
        }, []);

        var handleEditorChange = useCallback(function (field, value) {
            setEditorState(function (prev) {
                var next = Object.assign({}, prev);
                next[field] = value;
                return next;
            });
        }, []);

        var handleCancelEdit = useCallback(function () {
            var reset = createEditorState(selectedOccurrence);
            if (!selectedOccurrence && editorState && editorState.date) {
                reset.date = editorState.date;
            }
            setEditorState(reset);
        }, [selectedOccurrence, editorState]);

        var handleUpdateOccurrence = useCallback(function () {
            if (!editorState || !editorState.date) {
                return;
            }
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            if (editorState.id) {
                var updatedList = localOccurrences.map(function (occ) {
                    if (occ.id !== editorState.id) {
                        return occ;
                    }
                    return Object.assign({}, occ, {
                        date: editorState.date,
                        startTime: editorState.startTime,
                        endTime: editorState.endTime,
                        status: editorState.status,
                        reason: editorState.reason,
                    });
                });
                setLocalOccurrences(updatedList);
                persistOccurrences(updatedList, function () {
                    setLocalOccurrences(previousList);
                    setSelectedOccurrenceId(previousSelection);
                }).catch(function () {
                    // Already handled by parent notifications
                });
            } else {
                var newId = generateOccurrenceId(editorState.date, editorState.startTime, localOccurrences.length);
                var newOccurrence = {
                    id: newId,
                    date: editorState.date,
                    startTime: editorState.startTime,
                    endTime: editorState.endTime,
                    status: editorState.status,
                    reason: editorState.reason,
                };
                var updatedList = localOccurrences.concat([newOccurrence]);
                setLocalOccurrences(updatedList);
                setSelectedOccurrenceId(newId);
                setEditorState(createEditorState(newOccurrence));
                persistOccurrences(updatedList, function () {
                    setLocalOccurrences(previousList);
                    setSelectedOccurrenceId(previousSelection);
                }).catch(function () {
                    // Already handled by parent notifications
                });
            }
        }, [editorState, localOccurrences, selectedOccurrenceId, persistOccurrences]);

        var handleDeleteOccurrence = useCallback(function () {
            console.log('Deleting occurrence', selectedOccurrenceId);   
            if (!selectedOccurrenceId) {
                return;
            }
            var confirmMessage = getString(strings, 'occurrenceDeleteConfirm', 'Supprimer cette occurrence ?');
            if (typeof window !== 'undefined' && !window.confirm(confirmMessage)) {
                return;
            }
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            var previousEditorState = editorState ? Object.assign({}, editorState) : createEditorState(null);
            var updatedList = localOccurrences.filter(function (occ) { return occ.id !== selectedOccurrenceId; });
            setLocalOccurrences(updatedList);
            setSelectedOccurrenceId(null);
            var resetState = createEditorState(null);
            if (editorState && editorState.date) {
                resetState.date = editorState.date;
            }
            setEditorState(resetState);
            persistOccurrences(updatedList, function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
                setEditorState(previousEditorState);
            }).catch(function () {
                // Notification already handled upstream
            });
        }, [selectedOccurrenceId, strings, editorState, localOccurrences, persistOccurrences]);

        var handleGeneratorChange = useCallback(function (field, value) {
            setGeneratorState(function (prev) {
                var next = Object.assign({}, prev);
                var normalizedValue = typeof value === 'string' ? value.trim() : value;
                next[field] = normalizedValue;
                if (field === 'startDate') {
                    var startValue = typeof normalizedValue === 'string' ? normalizedValue : '';
                    next._explicitStart = startValue !== '';
                }
                if (field === 'startDate' && typeof normalizedValue === 'string' && normalizedValue !== '' && typeof prev.endDate === 'string' && prev.endDate !== '') {
                    var startDateObj = parseISODate(normalizedValue);
                    var endDateObj = parseISODate(prev.endDate);
                    if (startDateObj && endDateObj && endDateObj < startDateObj) {
                        next.endDate = normalizedValue;
                    }
                } else if (field === 'endDate') {
                    if (typeof normalizedValue !== 'string' || normalizedValue === '') {
                        next.endDate = '';
                    } else {
                        var startObj = parseISODate(next.startDate);
                        var candidateEnd = parseISODate(normalizedValue);
                        if (startObj && candidateEnd && candidateEnd < startObj) {
                            next.endDate = next.startDate;
                        } else {
                            next.endDate = normalizedValue;
                        }
                    }
                }
                return next;
            });
        }, []);

        var handleGeneratorDayToggle = useCallback(function (dayKey) {
            setGeneratorState(function (prev) {
                var nextDays = Object.assign({}, prev && typeof prev.days === 'object' ? prev.days : {});
                var nextOverrides = Object.assign({}, prev.timeOverrides || {});
                var nextValue = !nextDays[dayKey];
                nextDays[dayKey] = nextValue;
                if (!nextValue && nextOverrides[dayKey]) {
                    delete nextOverrides[dayKey];
                }
                return Object.assign({}, prev, { days: nextDays, timeOverrides: nextOverrides });
            });
        }, []);

        var handleAddOccurrences = useCallback(function () {
            var planResult = buildGeneratorPlan();
            var additions = planResult && Array.isArray(planResult.additions) ? planResult.additions : [];
            if (additions.length === 0) {
                return;
            }
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            var previousEditorState = editorState ? Object.assign({}, editorState) : createEditorState(null);
            var updatedList = localOccurrences.concat(additions);
            setLocalOccurrences(updatedList);
            var firstNew = additions[0];
            setSelectedOccurrenceId(firstNew.id);
            setEditorState(createEditorState(firstNew));
            persistOccurrences(updatedList, function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
                setEditorState(previousEditorState);
            }).catch(function () {
                // Parent handles error notification
            });
        }, [buildGeneratorPlan, localOccurrences, selectedOccurrenceId, editorState, persistOccurrences]);

        var handleShiftPivot = useCallback(function (offset) {
            setPivotDate(function (current) {
                var reference = current instanceof Date && !Number.isNaN(current.getTime())
                    ? new Date(current.getTime())
                    : new Date();
                reference.setHours(0, 0, 0, 0);
                if (viewMode === 'week') {
                    var shifted = new Date(reference.getFullYear(), reference.getMonth(), reference.getDate() + (offset * 7));
                    return alignDateToWeekStart(shifted);
                }
                return new Date(reference.getFullYear(), reference.getMonth() + offset, 1);
            });
        }, [viewMode]);

        var handleDeleteAllOccurrences = useCallback(function () {
            console.log('Deleting all occurrences');
            if (localOccurrences.length === 0) {
                return;
            }
            var confirmMessage = getString(strings, 'occurrenceDeleteAllConfirm', 'Voulez-vous vraiment supprimer toutes les occurrences ?');
            if (typeof window !== 'undefined' && !window.confirm(confirmMessage)) {
                return;
            }
            var previousPreviewValue = schedulePreview;
            var previousPreviewVisible = schedulePreviewVisible;
            var previousPreviewAutoSync = schedulePreviewAutoSync;
            var previousList = cloneOccurrenceList(localOccurrences);
            var previousSelection = selectedOccurrenceId;
            var previousEditorState = editorState ? Object.assign({}, editorState) : createEditorState(null);
            setLocalOccurrences([]);
            setSelectedOccurrenceId(null);
            setEditorState(createEditorState(null));
            setSchedulePreview('');
            setSchedulePreviewVisible(false);
            setSchedulePreviewAutoSync(false);
            persistOccurrences([], function () {
                setLocalOccurrences(previousList);
                setSelectedOccurrenceId(previousSelection);
                setEditorState(previousEditorState);
                setSchedulePreview(previousPreviewValue);
                setSchedulePreviewVisible(previousPreviewVisible);
                setSchedulePreviewAutoSync(previousPreviewAutoSync);
            }).catch(function () {
                // Parent handles notification
            });
        }, [localOccurrences, strings, selectedOccurrenceId, editorState, persistOccurrences, schedulePreview, schedulePreviewVisible, schedulePreviewAutoSync]);

        var generatorOverrides = generatorState && generatorState.timeOverrides && typeof generatorState.timeOverrides === 'object'
            ? generatorState.timeOverrides
            : {};
        var WEEKLY_GENERATION_LIMIT = 8;
        var WEEKLY_GENERATION_HARD_CAP = 208; // safeguard (~4 years weekly)
        var MONTHLY_GENERATION_LIMIT = 12;
        var MONTHLY_GENERATION_HARD_CAP = 120; // safeguard (10 years monthly)
        var RANGE_GENERATION_LIMIT = 31; // safeguard (~1 month)
        var RANGE_GENERATION_HARD_CAP = 366; // safeguard (1 year)
        var DAY_IN_MS = 24 * 60 * 60 * 1000;

        var buildGeneratorPlan = useCallback(function () {
            var startDateValue = typeof safeGeneratorState.startDate === 'string'
                ? safeGeneratorState.startDate
                : '';
            var startDate = parseISODate(startDateValue);
            if (!startDate) {
                return { additions: [], plan: null };
            }

            var endDateInput = typeof safeGeneratorState.endDate === 'string'
                ? safeGeneratorState.endDate.trim()
                : '';
            var hasEndDateInput = endDateInput !== '';
            var endDate = hasEndDateInput
                ? parseISODate(endDateInput)
                : null;
            if (endDate && endDate < startDate) {
                endDate = null;
            }
            var allowExtendedCap = hasEndDateInput;
            if (endDate) {
                allowExtendedCap = true;
            }

            var additions = [];
            var plan = {
                mode: generatorMode,
                startDateISO: formatISODate(startDate),
                endDateISO: endDate ? formatISODate(endDate) : '',
                startTime: typeof safeGeneratorState.startTime === 'string'
                    ? safeGeneratorState.startTime
                    : '',
                endTime: typeof safeGeneratorState.endTime === 'string'
                    ? safeGeneratorState.endTime
                    : '',
                frequency: generatorFrequency,
                days: [],
                overrides: generatorOverrides,
                monthlyOrdinal: typeof safeGeneratorState.monthlyOrdinal === 'string'
                    ? safeGeneratorState.monthlyOrdinal
                    : 'first',
                monthlyWeekday: typeof safeGeneratorState.monthlyWeekday === 'string'
                    ? safeGeneratorState.monthlyWeekday
                    : 'mon',
            };

            if (generatorMode === 'range') {
                var rangeEndDate = endDate && endDate >= startDate ? endDate : startDate;
                var rangeStartTime = plan.startTime;
                var rangeEndTime = plan.endTime;
                if (!rangeStartTime || !rangeEndTime) {
                    return { additions: [], plan: plan };
                }

                plan.endDateISO = rangeEndDate ? formatISODate(rangeEndDate) : plan.endDateISO;
                additions = [];

                var rangeIterations = 0;
                var rangeCap = allowExtendedCap ? RANGE_GENERATION_HARD_CAP : RANGE_GENERATION_LIMIT;
                var cursor = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                while (cursor <= rangeEndDate && rangeIterations < rangeCap) {
                    var cursorIso = formatISODate(cursor);
                    var additionSeed = localOccurrences.length + additions.length;
                    additions.push({
                        id: generateOccurrenceId(cursorIso, rangeStartTime, additionSeed),
                        date: cursorIso,
                        startTime: rangeStartTime,
                        endTime: rangeEndTime,
                        status: 'planned',
                        reason: '',
                    });
                    rangeIterations += 1;
                    cursor = addDays(cursor, 1);
                }

                return { additions: additions, plan: plan };
            }

            if (generatorMode === 'monthly') {
                var ordinalKey = plan.monthlyOrdinal;
                var weekdayKey = plan.monthlyWeekday;
                var monthWeekdayIndex = OCCURRENCE_WEEKDAY_TO_JS_INDEX[weekdayKey];
                if (monthWeekdayIndex === undefined) {
                    monthWeekdayIndex = 1;
                }

                var monthlyCap = allowExtendedCap ? MONTHLY_GENERATION_HARD_CAP : MONTHLY_GENERATION_LIMIT;
                var monthCursor = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
                var monthIterations = 0;

                while (monthIterations < monthlyCap) {
                    var candidate = findNthWeekdayOfMonth(monthCursor, monthWeekdayIndex, ordinalKey);
                    if (candidate && candidate >= startDate && (!endDate || candidate <= endDate)) {
                        var timeStart = plan.startTime;
                        var timeEnd = plan.endTime;
                        if (timeStart && timeEnd) {
                            var candidateIso = formatISODate(candidate);
                            var additionSeed = localOccurrences.length + additions.length;
                            additions.push({
                                id: generateOccurrenceId(candidateIso, timeStart, additionSeed),
                                date: candidateIso,
                                startTime: timeStart,
                                endTime: timeEnd,
                                status: 'planned',
                                reason: '',
                            });
                        }
                    }

                    if (endDate && monthCursor > endDate) {
                        break;
                    }

                    monthIterations += 1;
                    monthCursor = new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 1);
                }

                plan.days = [weekdayKey];

                if (startDate || endDate) {
                    additions = additions.filter(function (occurrence) {
                        var occurrenceDate = parseISODate(occurrence.date);
                        if (!occurrenceDate) {
                            return false;
                        }
                        if (startDate && occurrenceDate < startDate) {
                            return false;
                        }
                        if (endDate && occurrenceDate > endDate) {
                            return false;
                        }
                        return true;
                    });
                }

                return { additions: additions, plan: plan };
            }

            var selectedKeys = OCCURRENCE_WEEKDAY_KEYS.filter(function (key) {
                return !!generatorDays[key];
            });
            if (selectedKeys.length === 0) {
                return { additions: [], plan: plan };
            }

            var interval = generatorFrequency === 'every_two_weeks' ? 14 : 7;
            selectedKeys.forEach(function (dayKey) {
                var targetIndex = OCCURRENCE_WEEKDAY_TO_INDEX[dayKey];
                var firstDate = findNextWeekday(startDate, targetIndex);
                if (endDate && firstDate > endDate) {
                    return;
                }

                var iterationCap = allowExtendedCap ? WEEKLY_GENERATION_HARD_CAP : WEEKLY_GENERATION_LIMIT;
                var occurrencesGenerated = 0;
                var candidateDate = new Date(firstDate.getFullYear(), firstDate.getMonth(), firstDate.getDate());

                while (occurrencesGenerated < iterationCap) {
                    if (endDate && candidateDate > endDate) {
                        break;
                    }
                    var iso = formatISODate(candidateDate);
                    var override = generatorOverrides[dayKey] || null;
                    var dayStart = override && override.start ? override.start : plan.startTime;
                    var dayEnd = override && override.end ? override.end : plan.endTime;
                    if (dayStart && dayEnd) {
                        var additionSeed = localOccurrences.length + additions.length;
                        additions.push({
                            id: generateOccurrenceId(iso, dayStart, additionSeed),
                            date: iso,
                            startTime: dayStart,
                            endTime: dayEnd,
                            status: 'planned',
                            reason: '',
                        });
                    }

                    occurrencesGenerated += 1;
                    candidateDate = addDays(candidateDate, interval);
                }
            });

            plan.days = selectedKeys;

            if (startDate || endDate) {
                additions = additions.filter(function (occurrence) {
                    var occurrenceDate = parseISODate(occurrence.date);
                    if (!occurrenceDate) {
                        return false;
                    }
                    if (startDate && occurrenceDate < startDate) {
                        return false;
                    }
                    if (endDate && occurrenceDate > endDate) {
                        return false;
                    }
                    return true;
                });
            }

            return { additions: additions, plan: plan };
        }, [generatorState, generatorOverrides, generatorDays, generatorMode, generatorFrequency, localOccurrences]);

        var computeSchedulePreview = useCallback(function (planResult) {
            var plan = planResult && planResult.plan ? planResult.plan : null;
            var additions = planResult && Array.isArray(planResult.additions) ? planResult.additions : [];
            return deriveSchedulePreviewText({
                plan: plan,
                additions: additions,
                occurrences: localOccurrences,
                weekdayFullLabels: weekdayFullLabels,
                monthlyOrdinalOptions: monthlyOrdinalOptions,
                locale: resolvedLocale,
                strings: strings,
            });
        }, [localOccurrences, weekdayFullLabels, monthlyOrdinalOptions, resolvedLocale, strings]);

        useEffect(function () {
            if (!schedulePreviewAutoSync) {
                return;
            }
            var planResult = buildGeneratorPlan();
            var previewText = computeSchedulePreview(planResult);
            var trimmed = typeof previewText === 'string' ? previewText.trim() : '';
            if (trimmed !== schedulePreview) {
                setSchedulePreview(trimmed);
            }
        }, [schedulePreviewAutoSync, buildGeneratorPlan, computeSchedulePreview, schedulePreview]);

        var headingSubLabel = null;
        if (viewMode === 'week' && weekRangeLabel) {
            headingSubLabel = weekRangeLabel;
        } else if (pivotDate instanceof Date && !Number.isNaN(pivotDate.getTime())) {
            if (viewMode === 'quarter') {
                headingSubLabel = getString(strings, 'occurrenceQuarterRange', 'Vue trimestrielle');
            } else if (viewMode === 'month') {
                headingSubLabel = singleMonthOverview
                    ? singleMonthOverview.label
                    : getString(strings, 'occurrenceMonthRange', 'Vue mensuelle');
            }
        }

        return h('div', { class: 'mj-regmgr-occurrence' }, [
            h('div', { class: 'mj-regmgr-occurrence__header' }, [
                h('div', { class: 'mj-regmgr-occurrence__heading' }, [
                    h('h2', null, getString(strings, 'occurrencePanelTitle', 'Gestionnaire d\'occurrences')),
                    headingSubLabel && h('span', { class: 'mj-regmgr-occurrence__subheading' }, headingSubLabel),
                ]),
                h('div', { class: 'mj-regmgr-occurrence__header-controls' }, [
                    h('div', { class: 'mj-regmgr-occurrence__nav' }, [
                        h('button', {
                            type: 'button',
                            class: 'mj-regmgr-occurrence__nav-button',
                            'aria-label': getString(strings, 'occurrenceNavPrevious', 'Mois précédent'),
                            onClick: function () { handleShiftPivot(-1); },
                        }, [
                            h('span', { class: 'mj-regmgr-occurrence__nav-icon', 'aria-hidden': true }, '‹'),
                        ]),
                        h('button', {
                            type: 'button',
                            class: 'mj-regmgr-occurrence__nav-button',
                            'aria-label': getString(strings, 'occurrenceNavNext', 'Mois suivant'),
                            onClick: function () { handleShiftPivot(1); },
                        }, [
                            h('span', { class: 'mj-regmgr-occurrence__nav-icon', 'aria-hidden': true }, '›'),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-occurrence__view-toggle' }, [
                        h('button', {
                            type: 'button',
                            class: classNames('mj-regmgr-occurrence__view-button', {
                                'mj-regmgr-occurrence__view-button--active': viewMode === 'quarter',
                            }),
                            onClick: function () { setViewMode('quarter'); },
                        }, getString(strings, 'occurrenceViewQuarter', '4 mois')),
                        h('button', {
                            type: 'button',
                            class: classNames('mj-regmgr-occurrence__view-button', {
                                'mj-regmgr-occurrence__view-button--active': viewMode === 'month',
                            }),
                            onClick: function () { setViewMode('month'); },
                        }, getString(strings, 'occurrenceViewMonth', 'Mois')),
                        h('button', {
                            type: 'button',
                            class: classNames('mj-regmgr-occurrence__view-button', {
                                'mj-regmgr-occurrence__view-button--active': viewMode === 'week',
                            }),
                            onClick: function () { setViewMode('week'); },
                        }, getString(strings, 'occurrenceViewWeek', 'Semaine')),
                    ]),
                ]),
            ]),

            (viewMode === 'quarter' || viewMode === 'month') && h('div', { class: 'mj-regmgr-occurrence__body' }, [
                h('div', { class: 'mj-regmgr-occurrence__calendar' }, [
                    h('div', { class: 'mj-regmgr-occurrence__months' }, calendarMonths.map(function (month) {
                        return h('div', { key: month.key, class: 'mj-regmgr-occurrence__month' }, [
                            h('div', { class: 'mj-regmgr-occurrence__month-header' }, month.label),
                            h('div', { class: 'mj-regmgr-occurrence__weekdays' }, weekdayLabels.map(function (label, index) {
                                return h('div', { key: month.key + '-weekday-' + index, class: 'mj-regmgr-occurrence__weekday' }, label);
                            })),
                            month.weeks.map(function (week, weekIndex) {
                                return h('div', { key: month.key + '-week-' + weekIndex, class: 'mj-regmgr-occurrence__week' }, week.map(function (day) {
                                    return h('button', {
                                        key: day.iso,
                                        type: 'button',
                                        class: classNames('mj-regmgr-occurrence__day', {
                                            'mj-regmgr-occurrence__day--muted': !day.isCurrentMonth,
                                            'mj-regmgr-occurrence__day--selected': day.isSelected,
                                            'mj-regmgr-occurrence__day--today': day.isToday,
                                            'mj-regmgr-occurrence__day--with-occurrence': day.hasOccurrences,
                                        }, day.status ? 'mj-regmgr-occurrence__day--status-' + day.status : null),
                                        onClick: function () { handleSelectDay(day); },
                                    }, [
                                        h('span', { class: 'mj-regmgr-occurrence__day-number' }, day.label),
                                        day.timeSummary && h('span', { class: 'mj-regmgr-occurrence__day-time' }, day.timeSummary),
                                        day.hasOccurrences && h('span', {
                                            class: classNames('mj-regmgr-occurrence__day-indicator', day.status ? 'mj-regmgr-occurrence__day-indicator--' + day.status : null),
                                        }, day.occurrences.length),
                                    ]);
                                }));
                            }),
                        ]);
                    })),
                ]),
                sidebarContent,
            ]),

            viewMode === 'week' && h('div', { class: 'mj-regmgr-occurrence__body' }, [
                weekOverview
                    ? h('div', { class: 'mj-regmgr-occurrence__week-view' }, [
                        h('div', { class: 'mj-regmgr-occurrence__week-grid' }, [
                            h('div', { class: 'mj-regmgr-occurrence__week-time' }, [
                                h('div', { class: 'mj-regmgr-occurrence__week-time-header' },
                                    getString(strings, 'occurrenceWeekTimeColumn', 'Horaires')
                                ),
                                h('div', {
                                    class: 'mj-regmgr-occurrence__week-time-scale',
                                    style: { height: WEEK_VIEW_HEIGHT + 'px' },
                                }, weekTimeScale.ticks.map(function (tick, index) {
                                    var topRatio = (tick.minutes - weekTimeScale.min) / weekTimelineRange;
                                    var top = Math.max(0, Math.min(1, topRatio)) * WEEK_VIEW_HEIGHT;
                                    return h('div', {
                                        key: 'time-' + tick.minutes,
                                        class: classNames('mj-regmgr-occurrence__week-time-marker', {
                                            'mj-regmgr-occurrence__week-time-marker--first': index === 0,
                                        }),
                                        style: { top: top + 'px' },
                                    }, [
                                        h('span', { class: 'mj-regmgr-occurrence__week-time-label' }, tick.label),
                                    ]);
                                })),
                            ]),
                            weekOverview.days.map(function (day) {
                                var weekdayIndex = typeof day.weekdayIndex === 'number' ? day.weekdayIndex : 0;
                                var weekdayLabel = weekdayLabels[weekdayIndex] || '';
                                var dateLabel = '';
                                if (day.date instanceof Date && !Number.isNaN(day.date.getTime())) {
                                    try {
                                        dateLabel = capitalizeLabel(day.date.toLocaleDateString(resolvedLocale, { day: 'numeric', month: 'short' }));
                                    } catch (error) {
                                        dateLabel = capitalizeLabel(day.date.toLocaleDateString('fr', { day: 'numeric', month: 'short' }));
                                    }
                                }
                                var combinedLabel = weekdayLabel ? (weekdayLabel + ' ' + dateLabel) : dateLabel;
                                var hasOccurrences = Array.isArray(day.occurrences) && day.occurrences.length > 0;
                                return h('div', {
                                    key: day.iso,
                                    class: classNames('mj-regmgr-occurrence__week-column', {
                                        'mj-regmgr-occurrence__week-column--today': !!day.isToday,
                                        'mj-regmgr-occurrence__week-column--selected': !!day.isSelected,
                                    }),
                                }, [
                                    h('div', { class: 'mj-regmgr-occurrence__week-column-header' }, [
                                        combinedLabel && h('span', { class: 'mj-regmgr-occurrence__week-column-title' }, combinedLabel),
                                        day.timeSummary && h('span', { class: 'mj-regmgr-occurrence__week-column-summary' }, day.timeSummary),
                                        hasOccurrences && h('span', { class: 'mj-regmgr-occurrence__week-column-count' }, day.occurrences.length),
                                    ]),
                                    h('div', {
                                        class: 'mj-regmgr-occurrence__week-column-body',
                                        style: { height: WEEK_VIEW_HEIGHT + 'px' },
                                        onClick: function (event) {
                                            if (event.target === event.currentTarget) {
                                                handleSelectDay(day);
                                            }
                                        },
                                    }, [
                                        h('div', { class: 'mj-regmgr-occurrence__week-guides' }, weekTimeScale.ticks.map(function (tick, index) {
                                            var guideRatio = (tick.minutes - weekTimeScale.min) / weekTimelineRange;
                                            var guideTop = Math.max(0, Math.min(1, guideRatio)) * WEEK_VIEW_HEIGHT;
                                            return h('div', {
                                                key: day.iso + '-guide-' + tick.minutes,
                                                class: classNames('mj-regmgr-occurrence__week-guide', {
                                                    'mj-regmgr-occurrence__week-guide--first': index === 0,
                                                }),
                                                style: { top: guideTop + 'px' },
                                            });
                                        })),
                                        !hasOccurrences && h('div', { class: 'mj-regmgr-occurrence__week-empty' },
                                            getString(strings, 'occurrenceWeekEmptyDay', 'Aucune occurrence planifiée')
                                        ),
                                        hasOccurrences && day.occurrences.map(function (occurrence) {
                                            var startMinutes = typeof occurrence.startMinutes === 'number'
                                                ? occurrence.startMinutes
                                                : parseTimeToMinutes(occurrence.startTime);
                                            var endMinutes = typeof occurrence.endMinutes === 'number'
                                                ? occurrence.endMinutes
                                                : parseTimeToMinutes(occurrence.endTime);
                                            if (startMinutes === null) {
                                                startMinutes = weekTimeScale.min;
                                            }
                                            if (endMinutes === null) {
                                                endMinutes = startMinutes + 60;
                                            }
                                            var effectiveStart = Math.max(weekTimeScale.min, startMinutes);
                                            var effectiveEnd = Math.min(weekTimeScale.max, Math.max(endMinutes, effectiveStart + 30));
                                            var blockTop = ((effectiveStart - weekTimeScale.min) / weekTimelineRange) * WEEK_VIEW_HEIGHT;
                                            var blockHeight = ((effectiveEnd - effectiveStart) / weekTimelineRange) * WEEK_VIEW_HEIGHT;
                                            if (blockHeight < 24) {
                                                blockHeight = 24;
                                            }
                                            var statusKey = occurrence.status ? normalizeOccurrenceStatus(occurrence.status) : 'planned';
                                            var isSelectedBlock = selectedOccurrenceId === occurrence.id;
                                            var ariaLabel = formatTimeRange(occurrence.startTime, occurrence.endTime);
                                            return h('button', {
                                                key: occurrence.id,
                                                type: 'button',
                                                class: classNames('mj-regmgr-occurrence__week-block',
                                                    statusKey ? 'mj-regmgr-occurrence__week-block--status-' + statusKey : null,
                                                    {
                                                        'mj-regmgr-occurrence__week-block--selected': isSelectedBlock,
                                                    }
                                                ),
                                                style: {
                                                    top: blockTop + 'px',
                                                    height: blockHeight + 'px',
                                                },
                                                onClick: function (event) {
                                                    event.stopPropagation();
                                                    setSelectedOccurrenceId(occurrence.id);
                                                },
                                                'aria-label': ariaLabel,
                                            }, [
                                                h('span', { class: 'mj-regmgr-occurrence__week-block-time' }, formatPreviewRange(occurrence.startTime, occurrence.endTime)),
                                                statusLabelMap[statusKey] && h('span', { class: 'mj-regmgr-occurrence__week-block-status' }, statusLabelMap[statusKey]),
                                                occurrence.status === 'cancelled' && occurrence.reason && h('span', { class: 'mj-regmgr-occurrence__week-block-reason' }, occurrence.reason),
                                            ]);
                                        }),
                                    ]),
                                ]);
                            }),
                        ]),
                    ])
                    : h('div', { class: 'mj-regmgr-occurrence__week-view mj-regmgr-occurrence__week-view--empty' }, [
                        h('div', { class: 'mj-regmgr-occurrence__placeholder' },
                            getString(strings, 'occurrenceWeekPlaceholder', 'Aucune occurrence à afficher.')
                        ),
                    ]),
                sidebarContent,
            ]),
        ]);
    }

    var OCCURRENCE_WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    var OCCURRENCE_WEEKDAY_TO_INDEX = {
        mon: 1,
        tue: 2,
        wed: 3,
        thu: 4,
        fri: 5,
        sat: 6,
        sun: 0,
    };
    var OCCURRENCE_WEEKDAY_TO_JS_INDEX = {
        mon: 1,
        tue: 2,
        wed: 3,
        thu: 4,
        fri: 5,
        sat: 6,
        sun: 0,
    };

    function padNumber(value) {
        var str = String(value);
        return str.length < 2 ? '0' + str : str;
    }

    function formatISODate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        return date.getFullYear() + '-' + padNumber(date.getMonth() + 1) + '-' + padNumber(date.getDate());
    }

    function parseISODate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return null;
        }
        var parts = trimmed.split('-');
        if (parts.length !== 3) {
            return null;
        }
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10) - 1;
        var day = parseInt(parts[2], 10);
        if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
            return null;
        }
        var date = new Date(year, month, day, 0, 0, 0, 0);
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function parseISODateTime(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return null;
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            trimmed += 'T00:00:00';
        } else if (trimmed.indexOf('T') === -1) {
            trimmed = trimmed.replace(' ', 'T');
        }
        var date = new Date(trimmed);
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function formatTimeFromDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '09:00';
        }
        return padNumber(date.getHours()) + ':' + padNumber(date.getMinutes());
    }

    function addMinutesToTime(time, minutes) {
        if (!time || typeof time !== 'string') {
            return '';
        }
        var parts = time.split(':');
        if (parts.length < 2) {
            return time;
        }
        var total = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10) + minutes;
        if (Number.isNaN(total)) {
            return time;
        }
        total = ((total % (24 * 60)) + (24 * 60)) % (24 * 60);
        var hours = Math.floor(total / 60);
        var mins = total % 60;
        return padNumber(hours) + ':' + padNumber(mins);
    }

    function formatTimeRange(start, end) {
        var startValue = typeof start === 'string' ? start.trim() : '';
        var endValue = typeof end === 'string' ? end.trim() : '';
        if (!startValue && !endValue) {
            return '';
        }
        if (!startValue) {
            return endValue;
        }
        if (!endValue) {
            return startValue;
        }
        if (startValue === endValue) {
            return startValue;
        }
        return startValue + ' - ' + endValue;
    }

    function formatPreviewTime(time) {
        if (!time || typeof time !== 'string') {
            return '';
        }
        var trimmed = time.trim();
        if (!/^[0-9]{2}:[0-9]{2}$/.test(trimmed)) {
            return trimmed;
        }
        var parts = trimmed.split(':');
        return parts[0] + 'h' + parts[1];
    }

    function formatPreviewRange(start, end) {
        var startLabel = formatPreviewTime(start);
        var endLabel = formatPreviewTime(end);
        if (startLabel && endLabel) {
            return startLabel + ' > ' + endLabel;
        }
        return startLabel || endLabel || '';
    }

    function resolveWeekdayKeyFromDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        var jsIndex = date.getDay();
        switch (jsIndex) {
            case 0: return 'sun';
            case 1: return 'mon';
            case 2: return 'tue';
            case 3: return 'wed';
            case 4: return 'thu';
            case 5: return 'fri';
            case 6: return 'sat';
            default: return '';
        }
    }

    function resolveWeekdayLabel(dayKey, weekdayFullLabels) {
        if (typeof dayKey !== 'string') {
            return '';
        }
        var index = OCCURRENCE_WEEKDAY_KEYS.indexOf(dayKey);
        if (index === -1) {
            return '';
        }
        return weekdayFullLabels && weekdayFullLabels[index] ? weekdayFullLabels[index] : '';
    }

    function normaliseOccurrencesForPreview(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        var normalised = [];
        list.forEach(function (occurrence) {
            if (!occurrence) {
                return;
            }
            var dateIso = '';
            if (typeof occurrence.date === 'string' && occurrence.date !== '') {
                dateIso = occurrence.date;
            } else if (typeof occurrence.start === 'string' && occurrence.start !== '') {
                dateIso = occurrence.start.slice(0, 10);
            }
            if (dateIso === '') {
                return;
            }
            var dateObj = parseISODate(dateIso);
            if (!dateObj) {
                return;
            }
            var startTime = '';
            if (typeof occurrence.startTime === 'string' && occurrence.startTime !== '') {
                startTime = occurrence.startTime;
            } else if (typeof occurrence.start === 'string' && occurrence.start !== '') {
                var startDate = parseISODateTime(occurrence.start);
                if (startDate) {
                    startTime = formatTimeFromDate(startDate);
                }
            }
            var endTime = '';
            if (typeof occurrence.endTime === 'string' && occurrence.endTime !== '') {
                endTime = occurrence.endTime;
            } else if (typeof occurrence.end === 'string' && occurrence.end !== '') {
                var endDate = parseISODateTime(occurrence.end);
                if (endDate) {
                    endTime = formatTimeFromDate(endDate);
                }
            }
            normalised.push({
                date: dateIso,
                dateObj: dateObj,
                startTime: startTime,
                endTime: endTime,
                weekdayKey: resolveWeekdayKeyFromDate(dateObj),
            });
        });
        normalised.sort(function (a, b) {
            if (a.dateObj && b.dateObj && a.dateObj.getTime() !== b.dateObj.getTime()) {
                return a.dateObj.getTime() - b.dateObj.getTime();
            }
            if (a.startTime && b.startTime) {
                return a.startTime < b.startTime ? -1 : (a.startTime > b.startTime ? 1 : 0);
            }
            return 0;
        });
        return normalised;
    }

    function formatDateForLocale(date, locale, includeMonth, includeYear) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        var options = { weekday: 'long', day: 'numeric' };
        if (includeMonth) {
            options.month = 'long';
        }
        if (includeYear) {
            options.year = 'numeric';
        }
        try {
            return capitalizeLabel(date.toLocaleDateString(locale || 'fr', options));
        } catch (error) {
            return capitalizeLabel(date.toLocaleDateString('fr', options));
        }
    }

    function buildWeeklyPreviewFromPlan(plan, weekdayFullLabels, strings) {
        if (!plan || !Array.isArray(plan.days) || plan.days.length === 0) {
            return '';
        }
        var overrides = plan.overrides || {};
        var segments = [];
        plan.days.forEach(function (dayKey) {
            var label = resolveWeekdayLabel(dayKey, weekdayFullLabels);
            if (!label) {
                return;
            }
            var override = overrides[dayKey] || {};
            var startTime = override.start || plan.startTime || '';
            var endTime = override.end || plan.endTime || '';
            var range = formatPreviewRange(startTime, endTime);
            if (!range) {
                return;
            }
            segments.push(label + ' ' + range);
        });
        if (segments.length === 0) {
            return '';
        }
        var prefix = '';
        if (plan.frequency === 'every_two_weeks') {
            prefix = getString(strings, 'occurrencePreviewBiweeklyPrefix', 'Toutes les deux semaines : ');
        }
        return prefix + segments.join(', ');
    }

    function buildMonthlyPreview(plan, weekdayFullLabels, monthlyOrdinalOptions, strings) {
        if (!plan) {
            return '';
        }
        var weekdayLabel = resolveWeekdayLabel(plan.monthlyWeekday, weekdayFullLabels);
        if (!weekdayLabel) {
            return '';
        }
        var ordinalMap = {};
        if (Array.isArray(monthlyOrdinalOptions)) {
            monthlyOrdinalOptions.forEach(function (option) {
                ordinalMap[option.value] = option.label;
            });
        }
        var ordinalLabel = ordinalMap[plan.monthlyOrdinal] || '';
        if (!ordinalLabel) {
            return '';
        }
        var pattern = getString(strings, 'occurrencePreviewMonthlyPattern', 'Tous les {{ordinal}} {{weekday}} du mois');
        var summary = pattern.replace('{{ordinal}}', ordinalLabel).replace('{{weekday}}', weekdayLabel);
        var range = formatPreviewRange(plan.startTime, plan.endTime);
        if (range) {
            summary += ' · ' + range;
        }
        if (summary.charAt(summary.length - 1) !== '.') {
            summary += '.';
        }
        return summary;
    }

    function buildRangePreview(plan, locale, strings) {
        if (!plan) {
            return '';
        }

        var startISO = typeof plan.startDateISO === 'string' && plan.startDateISO !== ''
            ? plan.startDateISO
            : (typeof plan.startDate === 'string' ? plan.startDate : '');
        if (!startISO) {
            return '';
        }
        var endISO = typeof plan.endDateISO === 'string' && plan.endDateISO !== ''
            ? plan.endDateISO
            : (typeof plan.endDate === 'string' ? plan.endDate : startISO);

        var startDate = parseISODate(startISO);
        if (!startDate) {
            return '';
        }
        var endDate = parseISODate(endISO);
        if (!endDate || endDate < startDate) {
            endDate = startDate;
        }

        var includeStartYear = startDate.getFullYear() !== endDate.getFullYear();
        var includeEndMonth = includeStartYear || startDate.getMonth() !== endDate.getMonth();
        var includeEndYear = includeStartYear;

        var startLabel = formatDateForLocale(startDate, locale, true, includeStartYear);
        var endLabel = formatDateForLocale(endDate, locale, includeEndMonth, includeEndYear);
        if (!startLabel || !endLabel) {
            return '';
        }

        var startTimeLabel = formatPreviewTime(plan.startTime);
        var endTimeLabel = formatPreviewTime(plan.endTime);

        if (startTimeLabel && endTimeLabel) {
            var pattern = getString(strings, 'occurrencePreviewRangePattern', 'Du {{startDay}} à {{endDay}} de {{startTime}} à {{endTime}}');
            return pattern
                .replace('{{startDay}}', startLabel)
                .replace('{{endDay}}', endLabel)
                .replace('{{startTime}}', startTimeLabel)
                .replace('{{endTime}}', endTimeLabel);
        }

        var fallbackPattern = getString(strings, 'occurrencePreviewRangeDatesOnly', 'Du {{startDay}} à {{endDay}}');
        return fallbackPattern
            .replace('{{startDay}}', startLabel)
            .replace('{{endDay}}', endLabel);
    }

    function buildSingleDatePreview(occurrences, locale) {
        if (!occurrences || occurrences.length === 0) {
            return '';
        }
        var first = occurrences[0];
        var label = formatDateForLocale(first.dateObj, locale, true, true);
        if (!label) {
            return '';
        }
        var range = formatPreviewRange(first.startTime, first.endTime);
        return range ? label + ' · ' + range : label;
    }

    function buildConsecutiveRangePreview(occurrences, locale) {
        if (!occurrences || occurrences.length < 2) {
            return '';
        }
        var uniqueDates = [];
        occurrences.forEach(function (occ) {
            if (uniqueDates.length === 0 || uniqueDates[uniqueDates.length - 1].date !== occ.date) {
                uniqueDates.push({ date: occ.date, dateObj: occ.dateObj });
            }
        });
        if (uniqueDates.length < 2) {
            return '';
        }
        for (var index = 1; index < uniqueDates.length; index++) {
            var previous = uniqueDates[index - 1];
            var current = uniqueDates[index];
            if (!(previous.dateObj instanceof Date) || !(current.dateObj instanceof Date)) {
                return '';
            }
            var diff = Math.round((current.dateObj.getTime() - previous.dateObj.getTime()) / (24 * 60 * 60 * 1000));
            if (diff !== 1) {
                return '';
            }
        }
        var startDate = uniqueDates[0].dateObj;
        var endDate = uniqueDates[uniqueDates.length - 1].dateObj;
        var includeYear = startDate.getFullYear() !== endDate.getFullYear();
        var includeMonthOnEnd = includeYear || startDate.getMonth() !== endDate.getMonth();
        var startLabel = formatDateForLocale(startDate, locale, true, true);
        var endLabel = formatDateForLocale(endDate, locale, includeMonthOnEnd, includeYear);
        if (!startLabel || !endLabel) {
            return '';
        }
        return startLabel + ' au ' + endLabel;
    }

    function buildWeeklyPreviewFromOccurrences(occurrences, weekdayFullLabels) {
        if (!occurrences || occurrences.length === 0) {
            return '';
        }
        var grouped = {};
        occurrences.forEach(function (occ) {
            if (!occ.weekdayKey) {
                return;
            }
            if (!grouped[occ.weekdayKey]) {
                grouped[occ.weekdayKey] = [];
            }
            grouped[occ.weekdayKey].push(occ);
        });
        var weekdayKeys = Object.keys(grouped);
        if (weekdayKeys.length === 0) {
            return '';
        }
        weekdayKeys.forEach(function (key) {
            grouped[key].sort(function (a, b) {
                return a.dateObj.getTime() - b.dateObj.getTime();
            });
        });
        var segments = [];
        var hasWeeklyPattern = true;
        weekdayKeys.forEach(function (key) {
            var items = grouped[key];
            if (!items || items.length === 0) {
                hasWeeklyPattern = false;
                return;
            }
            var referenceStart = items[0].startTime;
            var referenceEnd = items[0].endTime;
            for (var i = 0; i < items.length; i++) {
                if (items[i].startTime !== referenceStart || items[i].endTime !== referenceEnd) {
                    hasWeeklyPattern = false;
                    break;
                }
                if (i > 0) {
                    var deltaDays = Math.round((items[i].dateObj.getTime() - items[i - 1].dateObj.getTime()) / (24 * 60 * 60 * 1000));
                    if (deltaDays % 7 !== 0) {
                        hasWeeklyPattern = false;
                        break;
                    }
                }
            }
            if (!hasWeeklyPattern) {
                return;
            }
            var weekdayLabel = resolveWeekdayLabel(key, weekdayFullLabels);
            var range = formatPreviewRange(referenceStart, referenceEnd);
            if (!weekdayLabel || !range) {
                hasWeeklyPattern = false;
                return;
            }
            segments.push({ key: key, label: weekdayLabel + ' ' + range });
        });
        if (!hasWeeklyPattern || segments.length === 0) {
            return '';
        }
        segments.sort(function (a, b) {
            return OCCURRENCE_WEEKDAY_KEYS.indexOf(a.key) - OCCURRENCE_WEEKDAY_KEYS.indexOf(b.key);
        });
        return segments.map(function (entry) { return entry.label; }).join(', ');
    }

    function buildOccurrenceListPreview(occurrences, locale) {
        if (!occurrences || occurrences.length === 0) {
            return '';
        }
        var segments = [];
        var limit = Math.min(occurrences.length, 3);
        for (var index = 0; index < limit; index++) {
            var occ = occurrences[index];
            var label = formatDateForLocale(occ.dateObj, locale, true, true);
            if (!label) {
                continue;
            }
            var range = formatPreviewRange(occ.startTime, occ.endTime);
            segments.push(range ? label + ' · ' + range : label);
        }
        if (segments.length === 0) {
            return '';
        }
        var result = segments.join(', ');
        if (occurrences.length > limit) {
            result += '…';
        }
        return result;
    }

    function deriveSchedulePreviewText(context) {
        var plan = context && context.plan ? context.plan : null;
        var additions = context && Array.isArray(context.additions) ? context.additions : [];
        var occurrences = context && Array.isArray(context.occurrences) ? context.occurrences : [];
        var weekdayFullLabels = context && context.weekdayFullLabels ? context.weekdayFullLabels : [];
        var monthlyOrdinalOptions = context && context.monthlyOrdinalOptions ? context.monthlyOrdinalOptions : [];
        var locale = context && context.locale ? context.locale : 'fr';
        var strings = context && context.strings ? context.strings : {};

        if (plan && plan.mode === 'range') {
            var rangePreview = buildRangePreview(plan, locale, strings);
            if (rangePreview) {
                return rangePreview;
            }
        }

        if (plan && plan.mode === 'weekly') {
            var weeklyPreview = buildWeeklyPreviewFromPlan(plan, weekdayFullLabels, strings);
            if (weeklyPreview) {
                return weeklyPreview;
            }
        }

        if (plan && plan.mode === 'monthly') {
            var monthlyPreview = buildMonthlyPreview(plan, weekdayFullLabels, monthlyOrdinalOptions, strings);
            if (monthlyPreview) {
                return monthlyPreview;
            }
        }

        var normalizedOccurrences = normaliseOccurrencesForPreview(occurrences);
        if (normalizedOccurrences.length === 0) {
            normalizedOccurrences = normaliseOccurrencesForPreview(additions);
        }
        if (normalizedOccurrences.length === 0) {
            return '';
        }

        if (normalizedOccurrences.length === 1) {
            var singlePreview = buildSingleDatePreview(normalizedOccurrences, locale);
            if (singlePreview) {
                return singlePreview;
            }
        }

        var rangePreview = buildConsecutiveRangePreview(normalizedOccurrences, locale);
        if (rangePreview) {
            return rangePreview;
        }

        var weeklyFromOccurrences = buildWeeklyPreviewFromOccurrences(normalizedOccurrences, weekdayFullLabels);
        if (weeklyFromOccurrences) {
            return weeklyFromOccurrences;
        }

        return buildOccurrenceListPreview(normalizedOccurrences, locale);
    }

    function parseTimeToMinutes(value) {
        if (typeof value !== 'string') {
            return null;
        }
        var parts = value.split(':');
        if (parts.length < 2) {
            return null;
        }
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return null;
        }
        return (hours * 60) + minutes;
    }

    function minutesToTime(totalMinutes) {
        if (typeof totalMinutes !== 'number' || Number.isNaN(totalMinutes)) {
            return '';
        }
        var normalized = ((totalMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
        var hours = Math.floor(normalized / 60);
        var minutes = normalized % 60;
        return padNumber(hours) + ':' + padNumber(minutes);
    }

    function deriveOccurrenceTimeSummary(list) {
        if (!Array.isArray(list) || list.length === 0) {
            return '';
        }
        var minStart = null;
        var maxEnd = null;
        var fallbackStart = '';
        var fallbackEnd = '';
        list.forEach(function (occurrence) {
            if (!occurrence) {
                return;
            }
            var startStr = typeof occurrence.startTime === 'string' ? occurrence.startTime : '';
            var endStr = typeof occurrence.endTime === 'string' ? occurrence.endTime : '';
            if (!fallbackStart && startStr) {
                fallbackStart = startStr;
            }
            if (!fallbackEnd && endStr) {
                fallbackEnd = endStr;
            }
            var startMinutes = parseTimeToMinutes(startStr);
            if (startMinutes !== null && (minStart === null || startMinutes < minStart)) {
                minStart = startMinutes;
            }
            var endMinutes = parseTimeToMinutes(endStr);
            if (endMinutes !== null && (maxEnd === null || endMinutes > maxEnd)) {
                maxEnd = endMinutes;
            }
        });
        var startText = minStart !== null ? minutesToTime(minStart) : fallbackStart;
        var endText = maxEnd !== null ? minutesToTime(maxEnd) : fallbackEnd;
        return formatTimeRange(startText, endText);
    }

    function deriveDayStatus(list) {
        if (!Array.isArray(list) || list.length === 0) {
            return '';
        }
        var priority = {
            cancelled: 3,
            confirmed: 2,
            planned: 1,
        };
        var resolved = '';
        list.forEach(function (occurrence) {
            if (!occurrence || typeof occurrence.status !== 'string') {
                return;
            }
            var candidate = normalizeOccurrenceStatus(occurrence.status);
            var candidatePriority = priority[candidate] || 0;
            var resolvedPriority = priority[resolved] || 0;
            if (!resolved || candidatePriority > resolvedPriority) {
                resolved = candidate;
            }
        });
        return resolved;
    }

    function isSameDate(a, b) {
        if (!(a instanceof Date) || !(b instanceof Date)) {
            return false;
        }
        return a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    function capitalizeLabel(label) {
        if (!label || typeof label !== 'string') {
            return '';
        }
        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function normalizeOccurrenceStatus(status) {
        if (typeof status !== 'string') {
            return 'planned';
        }
        var value = status.trim().toLowerCase();
        if (value === 'confirmed' || value === 'active') {
            return 'confirmed';
        }
        if (value === 'cancelled' || value === 'annule') {
            return 'cancelled';
        }
        if (value === 'postponed' || value === 'reporté' || value === 'reporte') {
            return 'planned';
        }
        if (value === 'pending' || value === 'a_confirmer' || value === 'planned') {
            return 'planned';
        }
        return 'planned';
    }

    function cloneOccurrenceList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(function (occ) {
            return Object.assign({}, occ);
        });
    }

    function normalizeOccurrence(occurrence, index) {
        var start = parseISODateTime(occurrence && (occurrence.start || occurrence.start_time || occurrence.date));
        var end = parseISODateTime(occurrence && occurrence.end);
        var dateValue = start ? formatISODate(start) : (occurrence && typeof occurrence.date === 'string' ? occurrence.date : formatISODate(new Date()));
        var startTime = start ? formatTimeFromDate(start) : (occurrence && typeof occurrence.startTime === 'string' ? occurrence.startTime : '09:00');
        var endTime = end ? formatTimeFromDate(end) : (occurrence && typeof occurrence.endTime === 'string' ? occurrence.endTime : addMinutesToTime(startTime, 60));
        var statusValue = normalizeOccurrenceStatus(occurrence && occurrence.status);
        var reasonValue = '';
        if (occurrence && typeof occurrence.reason === 'string') {
            reasonValue = occurrence.reason;
        } else if (occurrence && occurrence.meta && typeof occurrence.meta.reason === 'string') {
            reasonValue = occurrence.meta.reason;
        } else if (occurrence && typeof occurrence.cancelReason === 'string') {
            reasonValue = occurrence.cancelReason;
        }

        return {
            id: occurrence && occurrence.id ? String(occurrence.id) : 'occurrence-' + index,
            date: dateValue,
            startTime: startTime || '09:00',
            endTime: endTime || addMinutesToTime(startTime || '09:00', 60),
            status: statusValue,
            reason: reasonValue,
        };
    }

    function deriveInitialPivotDate(occurrences, event) {
        var candidate = null;
        if (occurrences && occurrences.length > 0) {
            candidate = parseISODate(occurrences[0].date);
        }
        if (!candidate && event && typeof event.dateDebut === 'string') {
            candidate = parseISODateTime(event.dateDebut);
        }
        if (!candidate && event && typeof event.dateFin === 'string') {
            candidate = parseISODateTime(event.dateFin);
        }
        if (!candidate) {
            candidate = new Date();
        }
        candidate.setHours(0, 0, 0, 0);
        return candidate;
    }

    function resolveLocaleTag(locale) {
        if (typeof locale !== 'string') {
            return 'fr';
        }
        var trimmed = locale.trim();
        if (trimmed === '') {
            return 'fr';
        }
        var normalised = trimmed.replace(/_/g, '-');
        try {
            new Intl.DateTimeFormat(normalised);
            return normalised;
        } catch (error) {
            var primary = normalised.split('-')[0];
            if (primary) {
                try {
                    new Intl.DateTimeFormat(primary);
                    return primary;
                } catch (error2) {
                    // Ignore and fallback below
                }
            }
        }
        return 'fr';
    }

    function buildQuarterMonths(pivotDate, locale, occurrencesByDate, selectedId) {
        if (!(pivotDate instanceof Date) || Number.isNaN(pivotDate.getTime())) {
            return [];
        }
        var months = [];
        var base = new Date(pivotDate.getFullYear(), pivotDate.getMonth(), 1);
        for (var offset = 0; offset < 4; offset++) {
            var monthDate = new Date(base.getFullYear(), base.getMonth() + offset, 1);
            months.push(buildMonthOverview(monthDate, locale, occurrencesByDate, selectedId));
        }
        return months;
    }

    function alignDateToWeekStart(date) {
        var reference = date instanceof Date && !Number.isNaN(date.getTime())
            ? new Date(date.getTime())
            : new Date();
        reference.setHours(0, 0, 0, 0);
        var jsIndex = reference.getDay();
        var diff = (jsIndex + 6) % 7;
        reference.setDate(reference.getDate() - diff);
        reference.setHours(0, 0, 0, 0);
        return reference;
    }

    function buildWeekOverview(pivotDate, occurrencesByDate, selectedId) {
        if (!(pivotDate instanceof Date) || Number.isNaN(pivotDate.getTime())) {
            return null;
        }
        var start = alignDateToWeekStart(pivotDate);
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var days = [];
        var minMinutes = null;
        var maxMinutes = null;
        for (var offset = 0; offset < 7; offset++) {
            var current = new Date(start.getFullYear(), start.getMonth(), start.getDate() + offset);
            current.setHours(0, 0, 0, 0);
            var iso = formatISODate(current);
            var sourceList = occurrencesByDate[iso] || [];
            var occurrences = [];
            sourceList.forEach(function (occ, index) {
                if (!occ) {
                    return;
                }
                var normalized = Object.assign({}, occ);
                normalized.id = occ.id ? String(occ.id) : ('occurrence-' + iso + '-' + index);
                normalized.status = normalizeOccurrenceStatus(occ.status);
                var startValue = typeof occ.startMinutes === 'number'
                    ? occ.startMinutes
                    : parseTimeToMinutes(occ.startTime);
                var endValue = typeof occ.endMinutes === 'number'
                    ? occ.endMinutes
                    : parseTimeToMinutes(occ.endTime);
                if (startValue === null) {
                    startValue = 9 * 60;
                }
                if (endValue === null || endValue <= startValue) {
                    endValue = startValue + 60;
                }
                normalized.startMinutes = startValue;
                normalized.endMinutes = endValue;
                if (minMinutes === null || startValue < minMinutes) {
                    minMinutes = startValue;
                }
                if (maxMinutes === null || endValue > maxMinutes) {
                    maxMinutes = endValue;
                }
                occurrences.push(normalized);
            });
            var weekdayIndex = (current.getDay() + 6) % 7;
            var dayStatus = deriveDayStatus(occurrences);
            var normalizedSelectedId = selectedId ? String(selectedId) : '';
            var isSelected = !!(normalizedSelectedId && occurrences.some(function (item) {
                return item && item.id === normalizedSelectedId;
            }));
            days.push({
                key: iso,
                iso: iso,
                date: current,
                weekdayIndex: weekdayIndex,
                dayNumber: current.getDate(),
                occurrences: occurrences,
                timeSummary: deriveOccurrenceTimeSummary(occurrences),
                status: dayStatus,
                isSelected: isSelected,
                isToday: isSameDate(current, today),
            });
        }
        if (minMinutes === null || maxMinutes === null) {
            minMinutes = 9 * 60;
            maxMinutes = 17 * 60;
        }
        var end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
        end.setHours(0, 0, 0, 0);
        return {
            key: 'week-' + formatISODate(start),
            start: start,
            end: end,
            days: days,
            minMinutes: minMinutes,
            maxMinutes: maxMinutes,
        };
    }

    function buildMonthOverview(monthDate, locale, occurrencesByDate, selectedId) {
        var month = monthDate.getMonth();
        var year = monthDate.getFullYear();
        var monthLabel;
        try {
            monthLabel = monthDate.toLocaleDateString(locale || 'fr', { month: 'long', year: 'numeric' });
        } catch (error) {
            monthLabel = monthDate.toLocaleDateString('fr', { month: 'long', year: 'numeric' });
        }
        var label = capitalizeLabel(monthLabel);
        var firstDay = new Date(year, month, 1);
        var startOffset = (firstDay.getDay() + 6) % 7;
        var cursor = new Date(year, month, 1 - startOffset);
        var today = new Date();
        var weeks = [];
        for (var weekIndex = 0; weekIndex < 6; weekIndex++) {
            var days = [];
            for (var dayIndex = 0; dayIndex < 7; dayIndex++) {
                var current = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + (weekIndex * 7) + dayIndex);
                var iso = formatISODate(current);
                var list = occurrencesByDate[iso] || [];
                var dayStatus = deriveDayStatus(list);
                var isSelected = selectedId ? list.some(function (item) { return item.id === selectedId; }) : false;
                var timeSummary = deriveOccurrenceTimeSummary(list);
                days.push({
                    iso: iso,
                    label: current.getDate(),
                    isCurrentMonth: current.getMonth() === month,
                    isToday: isSameDate(current, today),
                    hasOccurrences: list.length > 0,
                    occurrences: list,
                    isSelected: isSelected,
                    timeSummary: timeSummary,
                    status: dayStatus,
                });
            }
            weeks.push(days);
        }
        return {
            key: 'month-' + year + '-' + (month + 1),
            label: label,
            weeks: weeks,
        };
    }

    function createEditorState(occurrence) {
        if (!occurrence) {
            return {
                id: null,
                date: '',
                startTime: '09:00',
                endTime: '10:00',
                status: 'planned',
                reason: '',
            };
        }
        return {
            id: occurrence.id,
            date: occurrence.date,
            startTime: occurrence.startTime || '09:00',
            endTime: occurrence.endTime || '10:00',
            status: occurrence.status || 'planned',
            reason: occurrence.reason || '',
        };
    }

    function createGeneratorState(pivotDate) {
        var reference = pivotDate instanceof Date ? new Date(pivotDate.getTime()) : new Date();
        reference.setHours(0, 0, 0, 0);
        return {
            mode: 'weekly',
            frequency: 'every_week',
            startDate: formatISODate(reference),
            endDate: '',
            startTime: '09:00',
            endTime: '11:00',
            days: {
                mon: true,
                tue: true,
                wed: false,
                thu: false,
                fri: false,
                sat: false,
                sun: false,
            },
            timeOverrides: {},
            monthlyOrdinal: 'first',
            monthlyWeekday: 'mon',
        };
    }

    function sanitizeTimeValue(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (!/^\d{2}:\d{2}$/.test(trimmed)) {
            return '';
        }
        var parts = trimmed.split(':');
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return '';
        }
        if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
            return '';
        }
        return padNumber(hours) + ':' + padNumber(minutes);
    }

    function sanitizeDateValue(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }
        var parsed = parseISODate(trimmed);
        if (!parsed) {
            return '';
        }
        return formatISODate(parsed);
    }

    function normalizeGeneratorPlan(plan) {
        if (!plan || typeof plan !== 'object') {
            return null;
        }

        var normalized = {
            version: 'occurrence-editor',
            mode: 'weekly',
            frequency: 'every_week',
            startDate: '',
            endDate: '',
            startTime: '',
            endTime: '',
            days: {},
            overrides: {},
            monthlyOrdinal: 'first',
            monthlyWeekday: 'mon',
            explicitStart: false,
        };

        var mode = typeof plan.mode === 'string' ? plan.mode.trim().toLowerCase() : '';
        if (mode === 'monthly') {
            normalized.mode = 'monthly';
        } else if (mode === 'range') {
            normalized.mode = 'range';
        } else if (mode === 'custom') {
            normalized.mode = 'custom';
        } else {
            normalized.mode = 'weekly';
        }

        var frequency = typeof plan.frequency === 'string' ? plan.frequency.trim() : '';
        if (frequency === 'every_two_weeks') {
            normalized.frequency = 'every_two_weeks';
        } else {
            normalized.frequency = 'every_week';
        }

        var startCandidate = '';
        if (typeof plan.startDateISO === 'string' && plan.startDateISO !== '') {
            startCandidate = plan.startDateISO;
        } else if (typeof plan.startDate === 'string') {
            startCandidate = plan.startDate;
        }
        normalized.startDate = sanitizeDateValue(startCandidate);

        var endCandidate = '';
        if (typeof plan.endDateISO === 'string' && plan.endDateISO !== '') {
            endCandidate = plan.endDateISO;
        } else if (typeof plan.endDate === 'string') {
            endCandidate = plan.endDate;
        }
        normalized.endDate = sanitizeDateValue(endCandidate);

        normalized.startTime = sanitizeTimeValue(plan.startTime);
        normalized.endTime = sanitizeTimeValue(plan.endTime);

        var daysSource = plan.days;
        var hasSelectedDay = false;
        OCCURRENCE_WEEKDAY_KEYS.forEach(function (key) {
            var value = false;
            if (Array.isArray(daysSource)) {
                value = daysSource.indexOf(key) !== -1;
            } else if (daysSource && typeof daysSource === 'object') {
                value = !!daysSource[key];
            }
            normalized.days[key] = value;
            if (value) {
                hasSelectedDay = true;
            }
        });

        var overridesSource = plan.overrides || plan.timeOverrides || {};
        if (overridesSource && typeof overridesSource === 'object') {
            var overrides = {};
            OCCURRENCE_WEEKDAY_KEYS.forEach(function (key) {
                var overrideValue = overridesSource[key];
                if (!overrideValue || typeof overrideValue !== 'object') {
                    return;
                }
                var overrideEntry = {};
                var overrideStart = sanitizeTimeValue(overrideValue.start);
                var overrideEnd = sanitizeTimeValue(overrideValue.end);
                if (overrideStart) {
                    overrideEntry.start = overrideStart;
                }
                if (overrideEnd) {
                    overrideEntry.end = overrideEnd;
                }
                if (Object.keys(overrideEntry).length > 0) {
                    overrides[key] = overrideEntry;
                }
            });
            normalized.overrides = overrides;
        }

        var ordinal = typeof plan.monthlyOrdinal === 'string' ? plan.monthlyOrdinal.trim().toLowerCase() : '';
        if (['first', 'second', 'third', 'fourth', 'last'].indexOf(ordinal) === -1) {
            ordinal = 'first';
        }
        normalized.monthlyOrdinal = ordinal;

        var weekday = typeof plan.monthlyWeekday === 'string' ? plan.monthlyWeekday.trim().toLowerCase() : '';
        if (OCCURRENCE_WEEKDAY_KEYS.indexOf(weekday) === -1) {
            weekday = 'mon';
        }
        normalized.monthlyWeekday = weekday;

        var explicitStart = false;
        if (plan.explicitStart !== undefined) {
            explicitStart = !!plan.explicitStart;
        } else if (plan._explicitStart !== undefined) {
            explicitStart = !!plan._explicitStart;
        }
        if (normalized.startDate !== '') {
            explicitStart = true;
        }
        normalized.explicitStart = explicitStart;

        normalized.hasSelectedDay = hasSelectedDay;

        return normalized;
    }

    function createGeneratorStateFromPlan(plan, pivotDate) {
        var normalized = normalizeGeneratorPlan(plan);
        var base = createGeneratorState(pivotDate);
        if (!normalized) {
            return base;
        }

        var next = Object.assign({}, base);
        next.mode = normalized.mode;
        next.frequency = normalized.frequency;
        if (normalized.startDate !== '') {
            next.startDate = normalized.startDate;
            next._explicitStart = true;
        } else {
            next.startDate = formatISODate(pivotDate);
            next._explicitStart = false;
        }
        next.endDate = normalized.endDate !== '' ? normalized.endDate : '';
        next.startTime = normalized.startTime || base.startTime;
        next.endTime = normalized.endTime || base.endTime;

        var days = {};
        var hasDay = false;
        OCCURRENCE_WEEKDAY_KEYS.forEach(function (key) {
            var value = normalized.days && normalized.days.hasOwnProperty(key) ? !!normalized.days[key] : false;
            days[key] = value;
            if (value) {
                hasDay = true;
            }
        });
        if (!hasDay) {
            days = Object.assign({}, base.days);
        }
        next.days = days;

        next.timeOverrides = normalized.overrides ? Object.assign({}, normalized.overrides) : {};
        next.monthlyOrdinal = normalized.monthlyOrdinal || base.monthlyOrdinal;
        next.monthlyWeekday = normalized.monthlyWeekday || base.monthlyWeekday;

        return next;
    }

    function serializeGeneratorPlan(planResult, generatorState) {
        var plan = planResult && planResult.plan ? planResult.plan : null;
        var candidate = {};

        if (generatorState && typeof generatorState === 'object') {
            candidate.mode = generatorState.mode;
            candidate.frequency = generatorState.frequency;
            candidate.startDate = generatorState.startDate;
            candidate.endDate = generatorState.endDate;
            candidate.startTime = generatorState.startTime;
            candidate.endTime = generatorState.endTime;
            candidate.days = generatorState.days;
            candidate.overrides = generatorState.timeOverrides;
            candidate.monthlyOrdinal = generatorState.monthlyOrdinal;
            candidate.monthlyWeekday = generatorState.monthlyWeekday;
            candidate.explicitStart = !!generatorState._explicitStart;
        }

        if (plan) {
            if (plan.mode !== undefined) {
                candidate.mode = plan.mode;
            }
            if (plan.frequency !== undefined) {
                candidate.frequency = plan.frequency;
            }
            if (plan.startDateISO !== undefined) {
                candidate.startDate = plan.startDateISO;
            }
            if (plan.endDateISO !== undefined) {
                candidate.endDate = plan.endDateISO;
            }
            if (plan.startTime !== undefined) {
                candidate.startTime = plan.startTime;
            }
            if (plan.endTime !== undefined) {
                candidate.endTime = plan.endTime;
            }
            if (plan.days !== undefined) {
                candidate.days = plan.days;
            }
            if (plan.overrides !== undefined) {
                candidate.overrides = plan.overrides;
            }
            if (plan.monthlyOrdinal !== undefined) {
                candidate.monthlyOrdinal = plan.monthlyOrdinal;
            }
            if (plan.monthlyWeekday !== undefined) {
                candidate.monthlyWeekday = plan.monthlyWeekday;
            }
        }

        if (!candidate.mode && generatorState) {
            candidate.mode = generatorState.mode;
        }

        var normalized = normalizeGeneratorPlan(candidate);
        if (!normalized) {
            return null;
        }

        if (generatorState && generatorState._explicitStart && normalized.startDate === '') {
            normalized.startDate = sanitizeDateValue(generatorState.startDate);
            normalized.explicitStart = normalized.startDate !== '';
        }

        normalized.version = 'occurrence-editor';

        return normalized;
    }

    function findNextWeekday(startDate, targetIndex) {
        var base = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
        var delta = (targetIndex - base.getDay() + 7) % 7;
        if (delta !== 0) {
            base = addDays(base, delta);
        }
        return base;
    }

    function resolveMonthlyOrdinalValue(key) {
        switch (key) {
            case 'second':
                return 2;
            case 'third':
                return 3;
            case 'fourth':
                return 4;
            case 'last':
                return 'last';
            case 'first':
            default:
                return 1;
        }
    }

    function findNthWeekdayOfMonth(baseMonthDate, weekdayIndex, ordinalKey) {
        if (!(baseMonthDate instanceof Date) || Number.isNaN(baseMonthDate.getTime())) {
            return null;
        }

        var year = baseMonthDate.getFullYear();
        var month = baseMonthDate.getMonth();
        var ordinalValue = resolveMonthlyOrdinalValue(ordinalKey);
        if (ordinalValue !== 'last' && (typeof ordinalValue !== 'number' || ordinalValue <= 0)) {
            ordinalValue = 1;
        }

        if (ordinalValue === 'last') {
            var lastDay = new Date(year, month + 1, 0);
            var adjustment = (lastDay.getDay() - weekdayIndex + 7) % 7;
            return new Date(year, month + 1, 0 - adjustment);
        }

        var firstOfMonth = new Date(year, month, 1);
        var offset = (weekdayIndex - firstOfMonth.getDay() + 7) % 7;
        var day = 1 + offset + 7 * (ordinalValue - 1);
        var candidate = new Date(year, month, day);

        if (candidate.getMonth() !== month) {
            return null;
        }

        return candidate;
    }

    function addDays(base, count) {
        return new Date(base.getFullYear(), base.getMonth(), base.getDate() + count);
    }

    function generateOccurrenceId(date, time, seed) {
        var cleanDate = (date || '').replace(/[^0-9]/g, '');
        var cleanTime = (time || '').replace(':', '');
        return 'occ-' + cleanDate + '-' + cleanTime + '-' + seed;
    }

    // ============================================
    // TOASTS
    // ============================================

    function Toasts(props) {
        var toasts = props.toasts;
        var onRemove = props.onRemove;

        return h('div', { class: 'mj-regmgr-toasts', 'aria-live': 'polite' },
            toasts.map(function (toast) {
                return h('div', {
                    key: toast.id,
                    class: classNames('mj-regmgr-toast', 'mj-regmgr-toast--' + toast.type),
                }, [
                    h('span', { class: 'mj-regmgr-toast__message' }, toast.message),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-toast__close',
                        onClick: function () { onRemove(toast.id); },
                    }, '×'),
                ]);
            })
        );
    }

    // ============================================
    // DESCRIPTION EDITOR FIELD
    // ============================================

    function DescriptionEditorField(props) {
        var value = typeof props.value === 'string' ? props.value : '';
        var onChange = typeof props.onChange === 'function' ? props.onChange : function () {};
        var rows = props.rows || 10;
        var className = props.className || '';
        var placeholder = typeof props.placeholder === 'string' ? props.placeholder : '';
        var editorAvailable = !!(global.wp && global.wp.editor && typeof global.wp.editor.initialize === 'function');

        var idRef = useRef(null);
        if (!idRef.current) {
            if (props.id && typeof props.id === 'string' && props.id !== '') {
                idRef.current = props.id;
            } else {
                idRef.current = 'mj-regmgr-description-' + Math.random().toString(36).slice(2);
            }
        }
        var editorId = idRef.current;

        var textareaRef = useRef(null);
        var editorRef = useRef(null);
        var syncingRef = useRef(false);
        var valueRef = useRef(value);
        valueRef.current = value;

        useEffect(function () {
            if (!editorAvailable) {
                return undefined;
            }

            var textarea = textareaRef.current;
            if (!textarea) {
                return undefined;
            }

            if (global.wp && global.wp.editor && typeof global.wp.editor.remove === 'function') {
                try {
                    global.wp.editor.remove(editorId);
                } catch (removeError) {
                    // ignore cleanup issues
                }
            }

            textarea.value = valueRef.current || '';

            var editorSettings = {
                mediaButtons: false,
                quicktags: false,
                tinymce: {
                    branding: false,
                    menubar: false,
                    statusbar: true,
                    toolbar1: 'bold italic underline | bullist numlist | link unlink | undo redo',
                    plugins: 'lists link paste',
                    wpautop: true,
                },
            };

            try {
                global.wp.editor.initialize(editorId, editorSettings);
            } catch (initError) {
                console.error('[MjRegMgr] Failed to initialise description editor', initError);
                return undefined;
            }

            var editor = global.tinymce ? global.tinymce.get(editorId) : null;
            if (!editor) {
                return undefined;
            }

            editorRef.current = editor;

            var handleContentChange = function () {
                if (syncingRef.current) {
                    return;
                }
                var content = editor.getContent();
                if (content !== valueRef.current) {
                    valueRef.current = content;
                    onChange(content);
                }
            };

            editor.on('Change KeyUp Paste Undo Redo', handleContentChange);

            return function () {
                editor.off('Change KeyUp Paste Undo Redo', handleContentChange);
                editorRef.current = null;
                if (global.wp && global.wp.editor && typeof global.wp.editor.remove === 'function') {
                    try {
                        global.wp.editor.remove(editorId);
                    } catch (cleanupError) {
                        // ignore cleanup issues
                    }
                }
            };
        }, [editorAvailable, editorId, onChange]);

        useEffect(function () {
            if (!editorAvailable) {
                if (textareaRef.current && textareaRef.current.value !== valueRef.current) {
                    textareaRef.current.value = valueRef.current || '';
                }
                return;
            }

            var editor = editorRef.current;
            if (editor && editor.initialized) {
                var targetValue = valueRef.current || '';
                var currentValue = editor.getContent();
                if (targetValue !== currentValue && !editor.hasFocus()) {
                    syncingRef.current = true;
                    editor.setContent(targetValue);
                    editor.save();
                    syncingRef.current = false;
                }
            }
        }, [editorAvailable, editorId, value]);

        if (!editorAvailable) {
            return h('textarea', {
                ref: textareaRef,
                class: className,
                rows: rows,
                value: valueRef.current || '',
                placeholder: placeholder,
                onInput: function (event) {
                    var nextValue = event && event.target ? event.target.value : '';
                    valueRef.current = nextValue;
                    onChange(nextValue);
                },
            });
        }

        var editorClassName = className ? className + ' wp-editor-area' : 'wp-editor-area';

        return h('textarea', {
            id: editorId,
            ref: textareaRef,
            class: editorClassName,
            rows: rows,
            defaultValue: valueRef.current || '',
            placeholder: placeholder,
        });
    }

    // ============================================
    // MAIN APP
    // ============================================

    function RegistrationManagerApp(props) {
        var config = props.config;
        var strings = config.strings || {};

        // Paramètres URL (prioritaires sur prefillEventId)
        var urlEventId = useMemo(function () {
            if (!config || config.urlEventId === undefined || config.urlEventId === null) {
                return null;
            }
            var parsed = parseInt(config.urlEventId, 10);
            if (isNaN(parsed) || parsed <= 0) {
                return null;
            }
            return parsed;
        }, [config.urlEventId]);

        var urlMemberId = useMemo(function () {
            if (!config || config.urlMemberId === undefined || config.urlMemberId === null) {
                return null;
            }
            var parsed = parseInt(config.urlMemberId, 10);
            if (isNaN(parsed) || parsed <= 0) {
                return null;
            }
            return parsed;
        }, [config.urlMemberId]);

        var urlTab = useMemo(function () {
            if (!config || !config.urlTab || typeof config.urlTab !== 'string') {
                return null;
            }
            return config.urlTab;
        }, [config.urlTab]);

        var prefillEventId = useMemo(function () {
            // URL param est prioritaire
            if (urlEventId) {
                return urlEventId;
            }
            if (!config || config.prefillEventId === undefined || config.prefillEventId === null) {
                return null;
            }
            var parsed = parseInt(config.prefillEventId, 10);
            if (isNaN(parsed) || parsed <= 0) {
                return null;
            }
            return parsed;
        }, [config.prefillEventId, urlEventId]);

        var initialFilterValue = useMemo(function () {
            var allowAllFilter = !!config.showAllEvents;
            var fallback = typeof config.defaultFilter === 'string' && config.defaultFilter !== ''
                ? config.defaultFilter
                : 'assigned';

            if (fallback === 'all' && !allowAllFilter) {
                fallback = 'assigned';
            }

            if (prefillEventId && allowAllFilter && fallback !== 'all') {
                return 'all';
            }

            return fallback;
        }, [config.defaultFilter, config.showAllEvents, prefillEventId]);

        // API Service
        var api = useMemo(function () {
            var service = Services.createApiService(config);

            if (!service || typeof service !== 'object') {
                return service;
            }

            // Runtime shim for browsers that still run the legacy services.js snapshot.

            function appendNestedValue(formData, baseKey, value) {
                if (value === undefined || value === null) {
                    return;
                }

                if (Array.isArray(value)) {
                    if (value.length === 0) {
                        formData.append(baseKey + '[]', '');
                        return;
                    }
                    value.forEach(function (item, index) {
                        appendNestedValue(formData, baseKey + '[' + index + ']', item);
                    });
                    return;
                }

                if (typeof value === 'object') {
                    Object.keys(value).forEach(function (subKey) {
                        appendNestedValue(formData, baseKey + '[' + subKey + ']', value[subKey]);
                    });
                    return;
                }

                if (typeof value === 'boolean') {
                    formData.append(baseKey, value ? '1' : '0');
                    return;
                }

                formData.append(baseKey, value);
            }

            function fallbackPost(action, payload) {
                var formData = new FormData();
                formData.append('action', action);
                formData.append('nonce', config.nonce || '');

                Object.keys(payload || {}).forEach(function (key) {
                    var value = payload[key];
                    if (value === undefined || value === null) {
                        return;
                    }
                    if (Array.isArray(value)) {
                        appendNestedValue(formData, key, value);
                        return;
                    }
                    if (typeof value === 'object') {
                        appendNestedValue(formData, key, value);
                        return;
                    }
                    if (typeof value === 'boolean') {
                        formData.append(key, value ? '1' : '0');
                        return;
                    }
                    formData.append(key, value);
                });

                return fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function (result) {
                        if (!result || !result.success) {
                            var message = result && result.data && result.data.message ? result.data.message : 'Erreur inconnue';
                            var error = new Error(message);
                            if (result && result.data) {
                                error.data = result.data;
                            }
                            throw error;
                        }
                        return result.data;
                    });
            }

            if (typeof service.getEventEditor !== 'function') {
                service.getEventEditor = function (eventId) {
                    return fallbackPost('mj_regmgr_get_event_editor', { eventId: eventId });
                };
            }

            if (typeof service.updateEvent !== 'function') {
                service.updateEvent = function (eventId, form, meta) {
                    return fallbackPost('mj_regmgr_update_event', {
                        eventId: eventId,
                        form: form,
                        meta: meta || {},
                    });
                };
            }

            if (typeof service.saveEventOccurrences !== 'function') {
                service.saveEventOccurrences = function (eventId, occurrences, scheduleSummary, generatorPlan) {
                    return fallbackPost('mj_regmgr_save_event_occurrences', {
                        eventId: eventId,
                        occurrences: occurrences,
                        scheduleSummary: typeof scheduleSummary === 'string' ? scheduleSummary : '',
                        generatorPlan: generatorPlan && typeof generatorPlan === 'object' ? generatorPlan : null,
                    });
                };
            }

            if (typeof service.deleteEvent !== 'function') {
                service.deleteEvent = function (eventId) {
                    return fallbackPost('mj_regmgr_delete_event', {
                        eventId: eventId,
                    });
                };
            }

            if (typeof service.getLocation !== 'function') {
                service.getLocation = function (locationId) {
                    return fallbackPost('mj_regmgr_get_location', {
                        locationId: locationId || 0,
                    });
                };
            }

            if (typeof service.saveLocation !== 'function') {
                service.saveLocation = function (locationId, data) {
                    return fallbackPost('mj_regmgr_save_location', {
                        locationId: locationId || 0,
                        data: data || {},
                    });
                };
            }

            return service;
        }, [config.ajaxUrl, config.nonce]);

        // Toast notifications
        var toastsHook = useToasts();
        var toasts = toastsHook.toasts;
        var showSuccess = toastsHook.success;
        var showError = toastsHook.error;
        var removeToast = toastsHook.removeToast;

        function collectErrorMessages(error, fallbackMessage) {
            var messages = [];
            if (!error) {
                if (fallbackMessage) {
                    messages.push(fallbackMessage);
                }
                return messages;
            }
            var data = error.data || null;
            if (data) {
                if (Array.isArray(data.errors)) {
                    messages = data.errors.slice();
                } else if (Array.isArray(data.messages)) {
                    messages = data.messages.slice();
                } else if (typeof data.message === 'string' && data.message !== '') {
                    messages = [data.message];
                }
            }
            if (messages.length === 0) {
                if (Array.isArray(error.errors)) {
                    messages = error.errors.slice();
                } else if (typeof error.message === 'string' && error.message !== '') {
                    messages = [error.message];
                }
            }
            if (messages.length === 0 && fallbackMessage) {
                messages = [fallbackMessage];
            }
            return messages;
        }

        // Modals
        var createEventModal = useModal();
        var addParticipantModal = useModal();
        var createMemberModal = useModal();
        var attachChildModal = useModal();
        var notesModal = useModal();
        var qrModal = useModal();
        var occurrencesModal = useModal();
        var locationModal = useModal();
        var accountModal = useModal();
        var avatarCaptureModal = useModal();

        var memberDetailConfig = useMemo(function () {
            var baseConfig = config ? Object.assign({}, config) : {};

            baseConfig.onCreateChild = function (guardian) {
                if (!guardian) {
                    return;
                }
                createMemberModal.open({
                    context: {
                        type: 'child',
                        guardian: guardian,
                    },
                });
            };

            baseConfig.onAttachChild = function (guardian) {
                if (!guardian) {
                    return;
                }
                attachChildModal.open({
                    guardian: guardian,
                });
            };

            return baseConfig;
        }, [config, createMemberModal, attachChildModal]);

        // State
        var _events = useState([]);
        var events = _events[0];
        var setEvents = _events[1];

        var _creatingEvent = useState(false);
        var creatingEvent = _creatingEvent[0];
        var setCreatingEvent = _creatingEvent[1];

        var _deletingEvent = useState(false);
        var deletingEvent = _deletingEvent[0];
        var setDeletingEvent = _deletingEvent[1];

        var _eventsLoading = useState(true);
        var eventsLoading = _eventsLoading[0];
        var setEventsLoading = _eventsLoading[1];

        var _filter = useState(initialFilterValue);
        var filter = _filter[0];
        var setFilter = _filter[1];
        var allowAllFilter = useMemo(function () {
            return !!config.showAllEvents;
        }, [config.showAllEvents]);

        var setFilterSafe = useCallback(function (nextFilter) {
            if (nextFilter === 'all' && !allowAllFilter) {
                setFilter('assigned');
                return;
            }
            setFilter(nextFilter);
        }, [allowAllFilter, setFilter]);

        useEffect(function () {
            if (!allowAllFilter && filter === 'all') {
                setFilter('assigned');
            }
        }, [allowAllFilter, filter]);

        var _search = useState('');
        var search = _search[0];
        var setSearch = _search[1];

        var _selectedEvent = useState(null);
        var selectedEvent = _selectedEvent[0];
        var setSelectedEvent = _selectedEvent[1];

        var _eventDetails = useState(null);
        var eventDetails = _eventDetails[0];
        var setEventDetails = _eventDetails[1];

        var _registrations = useState([]);
        var registrations = _registrations[0];
        var setRegistrations = _registrations[1];
        var _attendanceMembers = useState([]);
        var attendanceMembers = _attendanceMembers[0];
        var setAttendanceMembers = _attendanceMembers[1];

        var _registrationsLoading = useState(false);
        var registrationsLoading = _registrationsLoading[0];
        var setRegistrationsLoading = _registrationsLoading[1];

        var _attendanceMap = useState({});
        var attendanceMap = _attendanceMap[0];
        var setAttendanceMap = _attendanceMap[1];

        var _loadingMembers = useState({});
        var loadingMembers = _loadingMembers[0];
        var setLoadingMembers = _loadingMembers[1];

        var _activeTab = useState('registrations');
        var activeTab = _activeTab[0];
        var setActiveTabInternal = _activeTab[1];

        // Wrapper pour setActiveTab qui met aussi à jour l'URL
        var setActiveTab = useCallback(function (tab, skipUrlUpdate) {
            setActiveTabInternal(tab);
            if (!skipUrlUpdate) {
                updateUrlParams({ tab: tab });
            }
        }, []);

        var _descriptionState = useState({
            base: '',
            draft: '',
            eventId: null,
        });
        var descriptionState = _descriptionState[0];
        var setDescriptionState = _descriptionState[1];

        var _descriptionSaving = useState(false);
        var descriptionSaving = _descriptionSaving[0];
        var setDescriptionSaving = _descriptionSaving[1];

        var _descriptionError = useState('');
        var descriptionError = _descriptionError[0];
        var setDescriptionError = _descriptionError[1];

        // Registration document state
        var _regDocState = useState({
            base: '',
            draft: '',
            eventId: null,
        });
        var regDocState = _regDocState[0];
        var setRegDocState = _regDocState[1];

        var _regDocSaving = useState(false);
        var regDocSaving = _regDocSaving[0];
        var setRegDocSaving = _regDocSaving[1];

        var _regDocError = useState('');
        var regDocError = _regDocError[0];
        var setRegDocError = _regDocError[1];

        // Event photos state
        var _eventPhotos = useState([]);
        var eventPhotos = _eventPhotos[0];
        var setEventPhotos = _eventPhotos[1];

        var _eventPhotosLoading = useState(false);
        var eventPhotosLoading = _eventPhotosLoading[0];
        var setEventPhotosLoading = _eventPhotosLoading[1];

        var _eventPhotoUpdating = useState(null);
        var eventPhotoUpdating = _eventPhotoUpdating[0];
        var setEventPhotoUpdating = _eventPhotoUpdating[1];

        var _eventPhotoUploading = useState(false);
        var eventPhotoUploading = _eventPhotoUploading[0];
        var setEventPhotoUploading = _eventPhotoUploading[1];

        var eventPhotoInputRef = useRef(null);
        var eventPhotosLoadedRef = useRef(null);

        var regDocFieldIdRef = useRef(null);
        if (!regDocFieldIdRef.current) {
            regDocFieldIdRef.current = 'mj-regmgr-regdoc-' + Math.random().toString(36).slice(2);
        }
        var regDocFieldId = regDocFieldIdRef.current;

        var descriptionFieldIdRef = useRef(null);
        if (!descriptionFieldIdRef.current) {
            descriptionFieldIdRef.current = 'mj-regmgr-description-' + Math.random().toString(36).slice(2);
        }
        var descriptionFieldId = descriptionFieldIdRef.current;

        var _pagination = useState({ page: 1, totalPages: 1 });
        var pagination = _pagination[0];
        var setPagination = _pagination[1];

        var _notes = useState([]);
        var notes = _notes[0];
        var setNotes = _notes[1];

        var _notesLoading = useState(false);
        var notesLoading = _notesLoading[0];
        var setNotesLoading = _notesLoading[1];

        var _qrData = useState(null);
        var qrData = _qrData[0];
        var setQrData = _qrData[1];

        var _qrLoading = useState(false);
        var qrLoading = _qrLoading[0];
        var setQrLoading = _qrLoading[1];

        var _locationModalState = useState({
            mode: 'create',
            loading: false,
            saving: false,
            location: null,
        });
        var locationModalState = _locationModalState[0];
        var setLocationModalState = _locationModalState[1];

        var _eventEditorData = useState(null);
        var eventEditorData = _eventEditorData[0];
        var setEventEditorData = _eventEditorData[1];

        var _eventEditorSummary = useState(null);
        var eventEditorSummary = _eventEditorSummary[0];
        var setEventEditorSummary = _eventEditorSummary[1];

        var _eventEditorLoading = useState(false);
        var eventEditorLoading = _eventEditorLoading[0];
        var setEventEditorLoading = _eventEditorLoading[1];

        var _eventEditorSaving = useState(false);
        var eventEditorSaving = _eventEditorSaving[0];
        var setEventEditorSaving = _eventEditorSaving[1];

        var _eventEditorErrors = useState([]);
        var eventEditorErrors = _eventEditorErrors[0];
        var setEventEditorErrors = _eventEditorErrors[1];

        var _initialEventLoaded = useState(false);
        var initialEventLoaded = _initialEventLoaded[0];
        var setInitialEventLoaded = _initialEventLoaded[1];

        // Mobile view state (show event list or event details)
        var _mobileShowDetails = useState(false);
        var mobileShowDetails = _mobileShowDetails[0];
        var setMobileShowDetails = _mobileShowDetails[1];

        useEffect(function () {
            var eventId = eventDetails && eventDetails.id ? eventDetails.id : null;
            var descriptionValue = eventDetails && typeof eventDetails.description === 'string'
                ? eventDetails.description
                : '';

            setDescriptionState(function (prev) {
                if (eventId === null) {
                    if (prev.eventId === null && prev.base === '' && prev.draft === '') {
                        return prev;
                    }
                    return { base: '', draft: '', eventId: null };
                }

                if (prev.eventId !== eventId) {
                    return { base: descriptionValue, draft: descriptionValue, eventId: eventId };
                }

                if (prev.base === descriptionValue) {
                    return prev;
                }

                if (prev.draft === prev.base) {
                    return { base: descriptionValue, draft: descriptionValue, eventId: eventId };
                }

                return Object.assign({}, prev, {
                    base: descriptionValue,
                    eventId: eventId,
                });
            });
        }, [eventDetails ? eventDetails.id : null, eventDetails ? eventDetails.description : '', setDescriptionState]);

        // Sync registration document state when eventDetails changes
        useEffect(function () {
            var eventId = eventDetails && eventDetails.id ? eventDetails.id : null;
            var regDocValue = eventDetails && typeof eventDetails.registrationDocument === 'string'
                ? eventDetails.registrationDocument
                : '';

            setRegDocState(function (prev) {
                if (eventId === null) {
                    if (prev.eventId === null && prev.base === '' && prev.draft === '') {
                        return prev;
                    }
                    return { base: '', draft: '', eventId: null };
                }

                if (prev.eventId !== eventId) {
                    return { base: regDocValue, draft: regDocValue, eventId: eventId };
                }

                if (prev.base === regDocValue) {
                    return prev;
                }

                if (prev.draft === prev.base) {
                    return { base: regDocValue, draft: regDocValue, eventId: eventId };
                }

                return Object.assign({}, prev, {
                    base: regDocValue,
                    eventId: eventId,
                });
            });
        }, [eventDetails ? eventDetails.id : null, eventDetails ? eventDetails.registrationDocument : '', setRegDocState]);

        useEffect(function () {
            if (!locationModal.isOpen) {
                setLocationModalState({
                    mode: 'create',
                    loading: false,
                    saving: false,
                    location: null,
                });
            }
        }, [locationModal.isOpen, setLocationModalState]);

        // Sidebar mode state (events or members)
        // Si urlMemberId est présent, démarrer en mode membres
        var initialSidebarMode = useMemo(function () {
            return urlMemberId ? 'members' : 'events';
        }, []);
        var _sidebarMode = useState(initialSidebarMode);
        var sidebarMode = _sidebarMode[0];
        var setSidebarMode = _sidebarMode[1];

        // Members state
        var _membersList = useState([]);
        var membersList = _membersList[0];
        var setMembersList = _membersList[1];

        var _membersLoading = useState(false);
        var membersLoading = _membersLoading[0];
        var setMembersLoading = _membersLoading[1];

        var _memberFilter = useState('all');
        var memberFilter = _memberFilter[0];
        var setMemberFilter = _memberFilter[1];

        var _memberSearch = useState('');
        var memberSearch = _memberSearch[0];
        var setMemberSearch = _memberSearch[1];

        var _memberSort = useState('name');
        var memberSort = _memberSort[0];
        var setMemberSort = _memberSort[1];

        var _selectedMember = useState(null);
        var selectedMember = _selectedMember[0];
        var setSelectedMember = _selectedMember[1];
        var _pendingMemberSelection = useState(null);
        var pendingMemberSelection = _pendingMemberSelection[0];
        var setPendingMemberSelection = _pendingMemberSelection[1];
        var _pendingMemberEdit = useState(null);
        var pendingMemberEdit = _pendingMemberEdit[0];
        var setPendingMemberEdit = _pendingMemberEdit[1];

        var _memberDetails = useState(null);
        var memberDetails = _memberDetails[0];
        var setMemberDetails = _memberDetails[1];

        var _memberNotes = useState([]);
        var memberNotes = _memberNotes[0];
        var setMemberNotes = _memberNotes[1];

        var _memberRegistrations = useState([]);
        var memberRegistrations = _memberRegistrations[0];
        var setMemberRegistrations = _memberRegistrations[1];

        var _membersPagination = useState({ page: 1, totalPages: 1 });
        var membersPagination = _membersPagination[0];
        var setMembersPagination = _membersPagination[1];

        // Clés localStorage pour mémoriser les sélections, uniques par widget pour éviter les collisions
        var storageKey = useMemo(function () {
            var suffix = config && config.widgetId ? config.widgetId : 'default';
            return 'mj_regmgr_selected_event_' + suffix;
        }, [config.widgetId]);

        var memberStorageKey = useMemo(function () {
            var suffix = config && config.widgetId ? config.widgetId : 'default';
            return 'mj_regmgr_selected_member_' + suffix;
        }, [config.widgetId]);

        var prefillHandledRef = useRef(prefillEventId === null);
        var urlMemberHandledRef = useRef(urlMemberId === null);
        var urlTabHandledRef = useRef(urlTab === null);
        var lastSelectedEventIdRef = useRef(null);
        var lastSelectedMemberIdRef = useRef(null);
        var eventEditorLoadedRef = useRef(null);

        // Charger les événements
        var loadEvents = useCallback(function (page) {
            setEventsLoading(true);
            api.getEvents({
                filter: filter,
                search: search,
                page: page || 1,
                perPage: config.perPage || 20,
            })
                .then(function (data) {
                    var loadedEvents = data.events || [];
                    setEvents(loadedEvents);
                    setPagination(data.pagination || { page: 1, totalPages: 1 });
                    setEventsLoading(false);

                    // Restaurer l'événement mémorisé au premier chargement
                    if (!initialEventLoaded && loadedEvents.length > 0) {
                        setInitialEventLoaded(true);
                        var restored = false;

                        if (!prefillHandledRef.current && prefillEventId) {
                            var targetEvent = loadedEvents.find(function (evt) {
                                return evt && evt.id === prefillEventId;
                            });
                            prefillHandledRef.current = true;
                            if (targetEvent) {
                                handleSelectEvent(targetEvent);
                                restored = true;
                            }
                        }

                        if (!restored) {
                            try {
                                var savedId = localStorage.getItem(storageKey);
                                if (savedId) {
                                    var savedParsed = parseInt(savedId, 10);
                                    if (!isNaN(savedParsed)) {
                                        var savedEvent = loadedEvents.find(function (e) {
                                            return e && e.id === savedParsed;
                                        });
                                        if (savedEvent) {
                                            handleSelectEvent(savedEvent);
                                            restored = true;
                                        }
                                    }
                                }
                            } catch (e) {
                                // localStorage non disponible
                            }
                        }
                    }
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message || getString(strings, 'error', 'Erreur'));
                        setEventsLoading(false);
                    }
                });
        }, [api, filter, search, config.perPage, showError, strings, initialEventLoaded, storageKey, prefillEventId]);

        // Charger au démarrage et quand les filtres changent
        useEffect(function () {
            loadEvents(1);
        }, [filter, search]);

        // Charger les détails de l'événement sélectionné
        var loadEventDetails = useCallback(function (eventId) {
            api.getEventDetails(eventId)
                .then(function (data) {
                    setEventDetails(data.event);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message);
                    }
                });
        }, [api, showError]);

        // Charger les photos de l'événement sélectionné
        var loadEventPhotos = useCallback(function (eventId) {
            if (!eventId) {
                setEventPhotos([]);
                return;
            }
            setEventPhotosLoading(true);
            api.getEventPhotos(eventId)
                .then(function (data) {
                    setEventPhotos(data.photos || []);
                    setEventPhotosLoading(false);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message);
                    }
                    setEventPhotosLoading(false);
                });
        }, [api, showError]);

        // Upload une photo pour l'événement
        var handleUploadEventPhoto = useCallback(function (file) {
            if (!selectedEvent || !selectedEvent.id || !file) {
                return;
            }
            setEventPhotoUploading(true);
            api.uploadEventPhoto(selectedEvent.id, file, '')
                .then(function (result) {
                    if (result.photo) {
                        setEventPhotos(function (prev) {
                            return [result.photo].concat(prev);
                        });
                    }
                    showSuccess(result.message || 'Photo ajoutée');
                    setEventPhotoUploading(false);
                    // Reset input
                    if (eventPhotoInputRef.current) {
                        eventPhotoInputRef.current.value = '';
                    }
                })
                .catch(function (err) {
                    showError(err.message || 'Erreur lors de l\'upload');
                    setEventPhotoUploading(false);
                });
        }, [api, selectedEvent, showSuccess, showError]);

        var handleDescriptionChange = useCallback(function (nextValue) {
            var safeValue = typeof nextValue === 'string' ? nextValue : '';
            setDescriptionState(function (prev) {
                if (prev.draft === safeValue) {
                    return prev;
                }
                return Object.assign({}, prev, { draft: safeValue });
            });
            if (descriptionError) {
                setDescriptionError('');
            }
        }, [setDescriptionState, descriptionError, setDescriptionError]);

        var handleResetDescription = useCallback(function () {
            setDescriptionState(function (prev) {
                if (prev.draft === prev.base) {
                    return prev;
                }
                return Object.assign({}, prev, { draft: prev.base });
            });
            setDescriptionError('');
        }, [setDescriptionState, setDescriptionError]);

        var handleSaveDescription = useCallback(function () {
            if (!selectedEvent || !selectedEvent.id) {
                return Promise.resolve();
            }

            if (!descriptionState || descriptionState.draft === descriptionState.base) {
                return Promise.resolve();
            }

            var eventId = selectedEvent.id;
            var draftValue = descriptionState.draft || '';

            setDescriptionSaving(true);
            setDescriptionError('');

            return api.updateEvent(eventId, { event_description: draftValue }, {})
                .then(function (data) {
                    setDescriptionSaving(false);
                    setDescriptionState(function (prev) {
                        if (prev.eventId !== eventId) {
                            return prev;
                        }
                        return Object.assign({}, prev, {
                            base: draftValue,
                            draft: draftValue,
                        });
                    });
                    setDescriptionError('');
                    showSuccess(data && data.message ? data.message : getString(strings, 'descriptionSaved', 'Description mise à jour.'));
                    loadEventDetails(eventId);
                    if (data && data.event) {
                        setSelectedEvent(function (prev) {
                            if (!prev || prev.id !== data.event.id) {
                                return prev;
                            }
                            return Object.assign({}, prev, data.event);
                        });
                        setEvents(function (prevEvents) {
                            if (!Array.isArray(prevEvents) || prevEvents.length === 0) {
                                return prevEvents;
                            }
                            return prevEvents.map(function (evt) {
                                if (evt && evt.id === data.event.id) {
                                    return Object.assign({}, evt, data.event);
                                }
                                return evt;
                            });
                        });
                    }
                    return data;
                })
                .catch(function (error) {
                    setDescriptionSaving(false);
                    if (error && error.aborted) {
                        return Promise.reject(error);
                    }
                    var fallback = getString(strings, 'descriptionSaveError', "Impossible d'enregistrer la description.");
                    var messages = collectErrorMessages(error, fallback);
                    var message = messages && messages.length > 0 ? messages[0] : fallback;
                    setDescriptionError(message);
                    showError(message);
                    return Promise.reject(error);
                });
        }, [selectedEvent, descriptionState, api, showSuccess, strings, loadEventDetails, setSelectedEvent, setEvents, collectErrorMessages, showError]);

        // Registration document handlers
        var handleRegDocChange = useCallback(function (nextValue) {
            var safeValue = typeof nextValue === 'string' ? nextValue : '';
            setRegDocState(function (prev) {
                if (prev.draft === safeValue) {
                    return prev;
                }
                return Object.assign({}, prev, { draft: safeValue });
            });
            if (regDocError) {
                setRegDocError('');
            }
        }, [setRegDocState, regDocError, setRegDocError]);

        var handleResetRegDoc = useCallback(function () {
            setRegDocState(function (prev) {
                if (prev.draft === prev.base) {
                    return prev;
                }
                return Object.assign({}, prev, { draft: prev.base });
            });
            setRegDocError('');
        }, [setRegDocState, setRegDocError]);

        var handleSaveRegDoc = useCallback(function (overrideContent) {
            if (!selectedEvent || !selectedEvent.id) {
                return Promise.resolve();
            }

            var draftValue = typeof overrideContent === 'string' ? overrideContent : (regDocState.draft || '');
            
            // Skip if no changes (unless overrideContent is explicitly provided)
            if (typeof overrideContent !== 'string' && regDocState && draftValue === regDocState.base) {
                return Promise.resolve();
            }

            var eventId = selectedEvent.id;

            // DEBUG
            console.log('[MjRegMgr] Saving registration document:', {
                eventId: eventId,
                draftValue: draftValue,
                draftLength: draftValue.length,
                base: regDocState.base,
            });

            setRegDocSaving(true);
            setRegDocError('');

            return api.updateEvent(eventId, { event_registration_document: draftValue }, {})
                .then(function (data) {
                    setRegDocSaving(false);
                    setRegDocState(function (prev) {
                        if (prev.eventId !== eventId) {
                            return prev;
                        }
                        return Object.assign({}, prev, {
                            base: draftValue,
                            draft: draftValue,
                        });
                    });
                    setRegDocError('');
                    showSuccess(data && data.message ? data.message : getString(strings, 'regDocSaved', 'Document d\'inscription mis à jour.'));
                    loadEventDetails(eventId);
                    if (data && data.event) {
                        setSelectedEvent(function (prev) {
                            if (!prev || prev.id !== data.event.id) {
                                return prev;
                            }
                            return Object.assign({}, prev, data.event);
                        });
                        setEvents(function (prevEvents) {
                            if (!Array.isArray(prevEvents) || prevEvents.length === 0) {
                                return prevEvents;
                            }
                            return prevEvents.map(function (evt) {
                                if (evt && evt.id === data.event.id) {
                                    return Object.assign({}, evt, data.event);
                                }
                                return evt;
                            });
                        });
                    }
                    return data;
                })
                .catch(function (error) {
                    setRegDocSaving(false);
                    if (error && error.aborted) {
                        return Promise.reject(error);
                    }
                    var fallback = getString(strings, 'regDocSaveError', "Impossible d'enregistrer le document d'inscription.");
                    var messages = collectErrorMessages(error, fallback);
                    var message = messages && messages.length > 0 ? messages[0] : fallback;
                    setRegDocError(message);
                    showError(message);
                    return Promise.reject(error);
                });
        }, [selectedEvent, regDocState, api, showSuccess, strings, loadEventDetails, setSelectedEvent, setEvents, collectErrorMessages, showError]);

        // Registration document download as PDF using print dialog
        var handleDownloadRegDoc = useCallback(function () {
            if (!selectedEvent || !eventDetails) {
                return;
            }

            var content = regDocState.draft || '';
            if (!content) {
                showError(getString(strings, 'regDocEmpty', 'Aucun contenu à télécharger.'));
                return;
            }

            // Base variables from event
            var baseVariables = {
                event_name: eventDetails.title || '',
                event_type: eventDetails.typeLabel || eventDetails.type || '',
                event_status: eventDetails.statusLabel || eventDetails.status || '',
                event_date_start: eventDetails.dateDebutFormatted || eventDetails.dateDebut || '',
                event_date_end: eventDetails.dateFinFormatted || eventDetails.dateFin || '',
                event_date_deadline: eventDetails.dateFinInscription || '',
                event_price: (eventDetails.prix || 0) + ' €',
                event_location: eventDetails.location ? eventDetails.location.name : '',
                event_location_address: eventDetails.location ? eventDetails.location.address : '',
                event_age_min: (eventDetails.ageMin || '').toString(),
                event_age_max: (eventDetails.ageMax || '').toString(),
                event_capacity: (eventDetails.capacityTotal || '').toString(),
                site_name: config.siteName || '',
                site_url: config.siteUrl || '',
                current_date: new Date().toLocaleDateString('fr-FR'),
                current_year: new Date().getFullYear().toString(),
            };

            // Helper function to replace placeholders
            var interpolateVariables = function (text, variables) {
                if (!text) return '';
                var result = text;
                Object.keys(variables).forEach(function (key) {
                    var regex = new RegExp('\\[' + key + '\\]', 'gi');
                    result = result.replace(regex, variables[key] || '');
                });
                return result;
            };

            // Helper to get member variables
            var getMemberVariables = function (member, registrationGuardian) {
                if (!member) return {};
                var fullName = ((member.firstName || '') + ' ' + (member.lastName || '')).trim();
                var fullAddress = [
                    member.address || '',
                    ((member.postalCode || '') + ' ' + (member.city || '')).trim()
                ].filter(function (line) { return line; }).join(', ');

                // Get guardian info - prefer registration guardian, fallback to member guardian
                var guardian = registrationGuardian || member.guardian || member.guardianData || null;
                var guardianName = '';
                var guardianFirstName = '';
                var guardianLastName = '';
                var guardianEmail = '';
                var guardianPhone = '';
                var guardianAddressLine = '';
                var guardianPostalCode = '';
                var guardianCity = '';

                if (guardian) {
                    guardianFirstName = guardian.firstName || '';
                    guardianLastName = guardian.lastName || '';
                    guardianName = ((guardianFirstName) + ' ' + (guardianLastName)).trim();
                    if (!guardianName && guardian.displayName) {
                        guardianName = guardian.displayName;
                    }
                    guardianEmail = guardian.email || '';
                    guardianPhone = guardian.phone || '';
                    guardianAddressLine = guardian.address || '';
                    guardianPostalCode = guardian.postalCode || '';
                    guardianCity = guardian.city || '';
                } else {
                    // No guardian - use member info as fallback
                    guardianFirstName = member.firstName || '';
                    guardianLastName = member.lastName || '';
                    guardianName = ((guardianFirstName) + ' ' + (guardianLastName)).trim();
                    guardianEmail = member.email || '';
                    guardianPhone = member.phone || '';
                    guardianAddressLine = member.address || '';
                    guardianPostalCode = member.postalCode || '';
                    guardianCity = member.city || '';
                }

                var guardianFullAddress = [
                    guardianAddressLine,
                    ((guardianPostalCode) + ' ' + (guardianCity)).trim()
                ].filter(function (line) { return line; }).join(', ');

                return {
                    member_name: fullName,
                    member_first_name: member.firstName || '',
                    member_last_name: member.lastName || '',
                    member_email: member.email || '',
                    member_phone: member.phone || '',
                    member_birth_date: member.birthDate || '',
                    member_address: fullAddress,
                    member_address_line: member.address || '',
                    member_postal_code: member.postalCode || '',
                    member_city: member.city || '',
                    guardian_name: guardianName,
                    guardian_first_name: guardianFirstName,
                    guardian_last_name: guardianLastName,
                    guardian_email: guardianEmail,
                    guardian_phone: guardianPhone,
                    guardian_address: guardianFullAddress,
                    guardian_address_line: guardianAddressLine,
                    guardian_postal_code: guardianPostalCode,
                    guardian_city: guardianCity,
                };
            };

            // Get valid registrations with members
            var validRegistrations = (registrations || []).filter(function (reg) {
                return reg && reg.member && reg.status !== 'cancelled';
            });

            // Build document pages
            var pages = [];
            var processedHeader = interpolateVariables(config.regDocHeader || '', baseVariables);
            var processedFooter = interpolateVariables(config.regDocFooter || '', baseVariables);

            if (validRegistrations.length > 0) {
                // Generate one page per member
                validRegistrations.forEach(function (reg) {
                    var memberVars = getMemberVariables(reg.member, reg.guardian);
                    var allVariables = Object.assign({}, baseVariables, memberVars);
                    var processedContent = interpolateVariables(content, allVariables);
                    pages.push(processedContent);
                });
            } else {
                // No registrations - generate single page without member variables
                var processedContent = interpolateVariables(content, baseVariables);
                pages.push(processedContent);
            }

            // Create HTML document for PDF
            var htmlDoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Document d\'inscription - ' + 
                (eventDetails.title || 'Événement') + '</title><style>' +
                '@page{size:A4;margin:20mm;}' +
                'body{font-family:Arial,Helvetica,sans-serif;font-size:12pt;line-height:1.6;color:#333;max-width:100%;margin:0;padding:0;}' +
                'h1{font-size:18pt;margin:0 0 0.75em 0;}h2{font-size:14pt;margin:1em 0 0.5em 0;}h3{font-size:12pt;margin:0.8em 0 0.4em 0;}' +
                'p{margin:0 0 0.75em 0;}p:last-child{margin-bottom:0;}' +
                'ul,ol{margin:0 0 0.75em 1.5em;padding:0;}' +
                'li{margin-bottom:0.4em;}' +
                'br{display:block;content:"";margin-top:0.5em;}' +
                'table{width:100%;border-collapse:collapse;margin:0.75em 0;border:none;}' +
                'th,td{border:none;padding:6pt 8pt;text-align:left;}' +
                'th{background:transparent;font-weight:bold;}' +
                'img{max-width:100%;height:auto;}' +
                'a{color:#2563eb;text-decoration:underline;}' +
                '.regdoc-header{}' +
                '.regdoc-content{min-height:150px;}' +
                '.regdoc-footer{border-top:1px solid #999;padding-top:15pt;margin-top:20pt;font-size:10pt;color:#666;}' +
                '.regdoc-page{page-break-after:always;}' +
                '.regdoc-page:last-child{page-break-after:auto;}' +
                '</style></head><body>';

            pages.forEach(function (pageContent) {
                htmlDoc += '<div class="regdoc-page">' +
                    (processedHeader ? '<div class="regdoc-header">' + processedHeader + '</div>' : '') +
                    '<div class="regdoc-content">' + pageContent + '</div>' +
                    (processedFooter ? '<div class="regdoc-footer">' + processedFooter + '</div>' : '') +
                    '</div>';
            });

            htmlDoc += '</body></html>';

            // Open print dialog in new window (user can save as PDF)
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            if (printWindow) {
                printWindow.document.write(htmlDoc);
                printWindow.document.close();
                printWindow.focus();
                // Wait for content to load then trigger print
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            } else {
                showError('Impossible d\'ouvrir la fenêtre d\'impression. Vérifiez que les popups ne sont pas bloqués.');
            }
        }, [selectedEvent, eventDetails, regDocState, config, strings, showError, registrations]);

        // Download document for a single member
        var handleDownloadMemberDoc = useCallback(function (registration) {
            if (!selectedEvent || !eventDetails || !registration || !registration.member) {
                return;
            }

            var content = regDocState.draft || eventDetails.registrationDocument || '';
            if (!content) {
                showError(getString(strings, 'regDocEmpty', 'Aucun contenu de document configuré pour cet événement.'));
                return;
            }

            var member = registration.member;

            // Base variables from event
            var baseVariables = {
                event_name: eventDetails.title || '',
                event_type: eventDetails.typeLabel || eventDetails.type || '',
                event_status: eventDetails.statusLabel || eventDetails.status || '',
                event_date_start: eventDetails.dateDebutFormatted || eventDetails.dateDebut || '',
                event_date_end: eventDetails.dateFinFormatted || eventDetails.dateFin || '',
                event_date_deadline: eventDetails.dateFinInscription || '',
                event_price: (eventDetails.prix || 0) + ' €',
                event_location: eventDetails.location ? eventDetails.location.name : '',
                event_location_address: eventDetails.location ? eventDetails.location.address : '',
                event_age_min: (eventDetails.ageMin || '').toString(),
                event_age_max: (eventDetails.ageMax || '').toString(),
                event_capacity: (eventDetails.capacityTotal || '').toString(),
                site_name: config.siteName || '',
                site_url: config.siteUrl || '',
                current_date: new Date().toLocaleDateString('fr-FR'),
                current_year: new Date().getFullYear().toString(),
            };

            // Helper function to replace placeholders
            var interpolateVariables = function (text, variables) {
                if (!text) return '';
                var result = text;
                Object.keys(variables).forEach(function (key) {
                    var regex = new RegExp('\\[' + key + '\\]', 'gi');
                    result = result.replace(regex, variables[key] || '');
                });
                return result;
            };

            // Get member variables
            var fullName = ((member.firstName || '') + ' ' + (member.lastName || '')).trim();
            var fullAddress = [
                member.address || '',
                ((member.postalCode || '') + ' ' + (member.city || '')).trim()
            ].filter(function (line) { return line; }).join(', ');

            // Get guardian info - guardian is on registration, not member
            var guardian = registration.guardian || member.guardian || member.guardianData || null;
            var guardianName = '';
            var guardianFirstName = '';
            var guardianLastName = '';
            var guardianEmail = '';
            var guardianPhone = '';
            var guardianAddressLine = '';
            var guardianPostalCode = '';
            var guardianCity = '';

            if (guardian) {
                guardianFirstName = guardian.firstName || '';
                guardianLastName = guardian.lastName || '';
                guardianName = ((guardianFirstName) + ' ' + (guardianLastName)).trim();
                if (!guardianName && guardian.displayName) {
                    guardianName = guardian.displayName;
                }
                guardianEmail = guardian.email || '';
                guardianPhone = guardian.phone || '';
                guardianAddressLine = guardian.address || '';
                guardianPostalCode = guardian.postalCode || '';
                guardianCity = guardian.city || '';
            } else {
                // No guardian - use member info as fallback
                guardianFirstName = member.firstName || '';
                guardianLastName = member.lastName || '';
                guardianName = ((guardianFirstName) + ' ' + (guardianLastName)).trim();
                guardianEmail = member.email || '';
                guardianPhone = member.phone || '';
                guardianAddressLine = member.address || '';
                guardianPostalCode = member.postalCode || '';
                guardianCity = member.city || '';
            }

            var guardianFullAddress = [
                guardianAddressLine,
                ((guardianPostalCode) + ' ' + (guardianCity)).trim()
            ].filter(function (line) { return line; }).join(', ');

            var memberVars = {
                member_name: fullName,
                member_first_name: member.firstName || '',
                member_last_name: member.lastName || '',
                member_email: member.email || '',
                member_phone: member.phone || '',
                member_birth_date: member.birthDate || '',
                member_address: fullAddress,
                member_address_line: member.address || '',
                member_postal_code: member.postalCode || '',
                member_city: member.city || '',
                guardian_name: guardianName,
                guardian_first_name: guardianFirstName,
                guardian_last_name: guardianLastName,
                guardian_email: guardianEmail,
                guardian_phone: guardianPhone,
                guardian_address: guardianFullAddress,
                guardian_address_line: guardianAddressLine,
                guardian_postal_code: guardianPostalCode,
                guardian_city: guardianCity,
            };

            var allVariables = Object.assign({}, baseVariables, memberVars);

            // Process content
            var processedHeader = interpolateVariables(config.regDocHeader || '', allVariables);
            var processedContent = interpolateVariables(content, allVariables);
            var processedFooter = interpolateVariables(config.regDocFooter || '', allVariables);

            // Create HTML document for PDF
            var htmlDoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Document d\'inscription - ' + 
                fullName + ' - ' + (eventDetails.title || 'Événement') + '</title><style>' +
                '@page{size:A4;margin:20mm;}' +
                'body{font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#333;max-width:100%;margin:0;padding:0;}' +
                'h1{font-size:18pt;margin:0 0 0.75em 0;}h2{font-size:14pt;margin:1em 0 0.5em 0;}h3{font-size:12pt;margin:0.8em 0 0.4em 0;}' +
                'p{margin:0 0 0.75em 0;}p:last-child{margin-bottom:0;}' +
                'ul,ol{margin:0 0 0.75em 1.5em;padding:0;}' +
                'li{margin-bottom:0.4em;}' +
                'br{display:block;content:"";margin-top:0.5em;}' +
                'table{width:100%;border-collapse:collapse;margin:0.75em 0;border:none;}' +
                'th,td{border:none;padding:6pt 8pt;text-align:left;}' +
                'th{background:transparent;font-weight:bold;}' +
                'img{max-width:100%;height:auto;}' +
                'a{color:#2563eb;text-decoration:underline;}' +
                '.regdoc-header{}' +
                '.regdoc-content{min-height:150px;}' +
                '.regdoc-footer{border-top:1px solid #999;padding-top:15pt;margin-top:20pt;font-size:10pt;color:#666;}' +
                '</style></head><body>' +
                (processedHeader ? '<div class="regdoc-header">' + processedHeader + '</div>' : '') +
                '<div class="regdoc-content">' + processedContent + '</div>' +
                (processedFooter ? '<div class="regdoc-footer">' + processedFooter + '</div>' : '') +
                '</body></html>';

            // Open print dialog in new window
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            if (printWindow) {
                printWindow.document.write(htmlDoc);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            } else {
                showError('Impossible d\'ouvrir la fenêtre d\'impression. Vérifiez que les popups ne sont pas bloqués.');
            }
        }, [selectedEvent, eventDetails, regDocState, config, strings, showError]);

        var loadEventEditor = useCallback(function (eventId) {
            if (!EventEditor) {
                return Promise.resolve();
            }
            setEventEditorLoading(true);
            setEventEditorErrors([]);
            return api.getEventEditor(eventId)
                .then(function (data) {
                    setEventEditorLoading(false);
                    setEventEditorErrors([]);
                    if (lastSelectedEventIdRef.current !== eventId) {
                        return data;
                    }
                    if (data && data.form) {
                        setEventEditorData({
                            values: data.form.values || {},
                            options: data.form.options || {},
                            meta: data.form.meta || {},
                        });
                    } else {
                        setEventEditorData(null);
                    }
                    if (data && data.event) {
                        setEventEditorSummary(data.event);
                        setSelectedEvent(function (prev) {
                            if (!prev) {
                                return data.event;
                            }
                            if (prev.id !== data.event.id) {
                                return prev;
                            }
                            return Object.assign({}, prev, data.event);
                        });
                        setEvents(function (prev) {
                            if (!prev) {
                                return prev;
                            }
                            return prev.map(function (evt) {
                                if (evt.id === data.event.id) {
                                    return Object.assign({}, evt, data.event);
                                }
                                return evt;
                            });
                        });
                    } else {
                        setEventEditorSummary(null);
                    }
                    eventEditorLoadedRef.current = eventId;
                    return data;
                })
                .catch(function (err) {
                    setEventEditorLoading(false);
                    if (lastSelectedEventIdRef.current !== eventId) {
                        return null;
                    }
                    if (err && err.aborted) {
                        return null;
                    }
                    var fallback = getString(strings, 'error', 'Erreur');
                    var messages = collectErrorMessages(err, fallback);
                    setEventEditorErrors(messages);
                    showError(err && err.message ? err.message : fallback);
                    eventEditorLoadedRef.current = null;
                    return null;
                });
            }, [api, showError, strings]);

        // Charger les inscriptions
        var loadRegistrations = useCallback(function (eventId) {
            setRegistrationsLoading(true);
            api.getRegistrations(eventId)
                .then(function (data) {
                    setRegistrations(data.registrations || []);
                    setAttendanceMembers(data.attendanceMembers || []);
                    
                    // Construire la map de présence
                    var attMap = {};
                    (data.registrations || []).forEach(function (reg) {
                        if (reg.attendance) {
                            Object.keys(reg.attendance).forEach(function (occ) {
                                if (!attMap[occ]) attMap[occ] = {};
                                attMap[occ][reg.memberId] = reg.attendance[occ];
                            });
                        }
                    });
                    setAttendanceMap(attMap);
                    setRegistrationsLoading(false);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message);
                        setRegistrationsLoading(false);
                        setAttendanceMembers([]);
                    }
                });
        }, [api, showError]);

        // Sélectionner un événement
        var handleSelectEvent = useCallback(function (event) {
            setSelectedEvent(event);
            setEventDetails(null);
            setRegistrations([]);
            setAttendanceMembers([]);
            setAttendanceMembers([]);
            setAttendanceMap({});
            setEventEditorData(null);
            setEventEditorSummary(null);
            setEventEditorErrors([]);
            setEventEditorLoading(false);
            setEventEditorSaving(false);
            setDescriptionSaving(false);
            setDescriptionError('');
            setDescriptionState({ base: '', draft: '', eventId: null });
            setRegDocSaving(false);
            setRegDocError('');
            setRegDocState({ base: '', draft: '', eventId: null });
            setEventPhotos([]);
            setEventPhotosLoading(false);
            setEventPhotoUpdating(null);
            eventPhotosLoadedRef.current = null;
            eventEditorLoadedRef.current = null;
            var defaultTab = 'registrations';
            if (event) {
                if (event.freeParticipation) {
                    defaultTab = 'attendance';
                }
            }
            setActiveTab(defaultTab, true); // Skip URL update, on le fait après
            setMobileShowDetails(true); // Afficher les détails sur mobile
            
            // Mémoriser l'événement sélectionné
            lastSelectedEventIdRef.current = event.id;
            try {
                localStorage.setItem(storageKey, String(event.id));
            } catch (e) {
                // localStorage non disponible
            }
            
            // Mettre à jour l'URL avec l'événement et l'onglet
            updateUrlParams({ event: event.id, tab: defaultTab });
            
            loadEventDetails(event.id);
            loadRegistrations(event.id);
        }, [loadEventDetails, loadRegistrations, storageKey, setDescriptionState, setDescriptionSaving, setDescriptionError, setRegDocState, setRegDocSaving, setRegDocError, setActiveTab]);

        var openCreateEventModal = useCallback(function () {
            if (creatingEvent) {
                return;
            }
            createEventModal.open();
        }, [creatingEvent, createEventModal]);

        var handleCloseCreateEventModal = useCallback(function () {
            if (creatingEvent) {
                return;
            }
            createEventModal.close();
        }, [creatingEvent, createEventModal]);

        var handleCreateEvent = useCallback(function (payload) {
            var safePayload = payload || {};
            var title = typeof safePayload.title === 'string' ? safePayload.title.trim() : '';
            if (!title) {
                return Promise.reject(new Error(getString(strings, 'createEventTitleRequired', 'Le titre est obligatoire.')));
            }

            var type = typeof safePayload.type === 'string' ? safePayload.type : '';
            setCreatingEvent(true);

            return api.createEvent({
                title: title,
                type: type,
            })
                .then(function (data) {
                    setCreatingEvent(false);

                    var createdEvent = data && data.event ? data.event : null;
                    if (createdEvent) {
                        setEvents(function (prev) {
                            if (!Array.isArray(prev)) {
                                return [createdEvent];
                            }
                            var without = prev.filter(function (evt) {
                                return evt && evt.id !== createdEvent.id;
                            });
                            without.unshift(createdEvent);
                            return without;
                        });

                        var shouldReload = filter === 'draft' && search === '';
                        if (search !== '') {
                            setSearch('');
                            shouldReload = false;
                        }
                        if (filter !== 'draft') {
                            setFilter('draft');
                            shouldReload = false;
                        }
                        if (shouldReload) {
                            loadEvents(1);
                        }

                        handleSelectEvent(createdEvent);
                        setActiveTab('editor');
                    }

                    showSuccess(data && data.message ? data.message : getString(strings, 'createEventSuccess', 'Brouillon créé.'));
                    createEventModal.close();
                    return data;
                })
                .catch(function (error) {
                    setCreatingEvent(false);
                    throw error;
                });
        }, [api, filter, search, loadEvents, handleSelectEvent, setActiveTab, showSuccess, createEventModal, strings, setEvents, setFilter, setSearch]);

        var handleDeleteEvent = useCallback(function (target) {
            if (deletingEvent) {
                return;
            }

            var eventId = 0;
            if (target && typeof target === 'object') {
                eventId = target.id || 0;
            } else {
                eventId = target;
            }

            eventId = parseInt(eventId, 10);
            if (!eventId || eventId <= 0) {
                return;
            }

            var confirmMessage = getString(strings, 'deleteEventConfirm', 'Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.');
            if (typeof window !== 'undefined' && !window.confirm(confirmMessage)) {
                return;
            }

            setDeletingEvent(true);

            api.deleteEvent(eventId)
                .then(function () {
                    showSuccess(getString(strings, 'eventDeleted', 'Événement supprimé.'));

                    var nextEvent = null;
                    setEvents(function (prev) {
                        if (!Array.isArray(prev) || prev.length === 0) {
                            return prev;
                        }
                        var updated = prev.filter(function (evt) {
                            return evt && evt.id !== eventId;
                        });
                        if (!nextEvent && updated.length > 0) {
                            nextEvent = updated[0];
                        }
                        return updated;
                    });

                    if (selectedEvent && selectedEvent.id === eventId) {
                        setSelectedEvent(null);
                        setEventDetails(null);
                        setRegistrations([]);
                        setAttendanceMembers([]);
                        setAttendanceMap({});
                        setEventEditorData(null);
                        setEventEditorSummary(null);
                        setEventEditorErrors([]);
                        setEventEditorLoading(false);
                        setEventEditorSaving(false);
                        eventEditorLoadedRef.current = null;
                        setActiveTab('registrations');
                        setMobileShowDetails(false);
                        try {
                            localStorage.removeItem(storageKey);
                        } catch (e) {
                            // localStorage non disponible
                        }
                        if (nextEvent) {
                            handleSelectEvent(nextEvent);
                        }
                    }

                    loadEvents(Math.max(1, pagination.page || 1));
                    setDeletingEvent(false);
                })
                .catch(function (err) {
                    if (err && err.aborted) {
                        setDeletingEvent(false);
                        return;
                    }
                    showError(err && err.message ? err.message : getString(strings, 'error', 'Erreur'));
                    setDeletingEvent(false);
                });
        }, [deletingEvent, strings, api, showSuccess, setEvents, selectedEvent, handleSelectEvent, loadEvents, pagination.page, storageKey, showError]);

        var handleCloseLocationModal = useCallback(function () {
            if (locationModalState.saving) {
                return;
            }
            locationModal.close();
        }, [locationModal, locationModalState.saving]);

        var handleRequestLocationModal = useCallback(function (request) {
            if (!config.canManageLocations) {
                showError(getString(strings, 'locationPermissionError', 'Vous ne pouvez pas gérer les lieux.'));
                return;
            }
            var payload = request && typeof request === 'object' ? request : {};
            var mode = payload.mode === 'edit' ? 'edit' : 'create';
            var locationId = mode === 'edit' && payload.locationId ? parseInt(payload.locationId, 10) || 0 : 0;

            locationModal.open({
                mode: mode,
                locationId: locationId,
                onComplete: typeof payload.onComplete === 'function' ? payload.onComplete : null,
            });

            setLocationModalState({
                mode: mode,
                loading: true,
                saving: false,
                location: null,
            });

            api.getLocation(locationId)
                .then(function (result) {
                    setLocationModalState({
                        mode: mode,
                        loading: false,
                        saving: false,
                        location: result && result.location ? result.location : null,
                    });
                })
                .catch(function (error) {
                    setLocationModalState({
                        mode: mode,
                        loading: false,
                        saving: false,
                        location: null,
                    });
                    locationModal.close();
                    var messages = collectErrorMessages(error, getString(strings, 'locationLoadError', 'Impossible de charger ce lieu.'));
                    if (messages.length > 0) {
                        showError(messages[0]);
                    }
                });
        }, [config.canManageLocations, showError, strings, locationModal, setLocationModalState, api, collectErrorMessages]);

        var handleSubmitLocationModal = useCallback(function (payload) {
            var modalData = locationModal.data || {};
            var locationId = modalData.locationId || 0;

            setLocationModalState(function (prev) {
                return Object.assign({}, prev, { saving: true });
            });

            return api.saveLocation(locationId, payload)
                .then(function (result) {
                    setLocationModalState(function (prev) {
                        return Object.assign({}, prev, {
                            saving: false,
                            location: result && result.location ? result.location : prev.location,
                        });
                    });
                    var message = result && result.message ? result.message : getString(strings, 'locationSaved', 'Lieu enregistré.');
                    showSuccess(message);
                    if (modalData.onComplete && typeof modalData.onComplete === 'function') {
                        modalData.onComplete(result);
                    }
                    locationModal.close();
                    return result;
                })
                .catch(function (error) {
                    setLocationModalState(function (prev) {
                        return Object.assign({}, prev, { saving: false });
                    });
                    var messages = collectErrorMessages(error, getString(strings, 'locationSaveError', 'Impossible d\'enregistrer ce lieu.'));
                    if (messages.length > 0) {
                        showError(messages[0]);
                    }
                    throw error;
                });
        }, [api, locationModal, setLocationModalState, getString, strings, showSuccess, collectErrorMessages, showError]);

        var handleReloadEventEditor = useCallback(function () {
            if (!selectedEvent || !selectedEvent.id) {
                return;
            }
            return loadEventEditor(selectedEvent.id);
        }, [selectedEvent, loadEventEditor]);

        var handleSubmitEventEditor = useCallback(function (form, meta) {
            if (!selectedEvent || !selectedEvent.id) {
                return Promise.resolve();
            }
            var targetEventId = selectedEvent.id;
            setEventEditorSaving(true);
            setEventEditorErrors([]);
            return api.updateEvent(targetEventId, form, meta || {})
                .then(function (data) {
                    setEventEditorSaving(false);
                    setEventEditorErrors([]);
                    if (lastSelectedEventIdRef.current !== targetEventId) {
                        return data;
                    }
                    if (data && data.form) {
                        setEventEditorData({
                            values: data.form.values || {},
                            options: data.form.options || {},
                            meta: data.form.meta || {},
                        });
                    }
                    if (data && data.event) {
                        setEventEditorSummary(data.event);
                        setSelectedEvent(function (prev) {
                            if (!prev) {
                                return data.event;
                            }
                            if (prev.id !== data.event.id) {
                                return prev;
                            }
                            return Object.assign({}, prev, data.event);
                        });
                        setEvents(function (prev) {
                            if (!prev) {
                                return prev;
                            }
                            return prev.map(function (evt) {
                                if (evt.id === data.event.id) {
                                    return Object.assign({}, evt, data.event);
                                }
                                return evt;
                            });
                        });
                    }
                    eventEditorLoadedRef.current = targetEventId;
                    showSuccess(data && data.message ? data.message : getString(strings, 'success', 'Opération réussie'));
                    loadEventDetails(targetEventId);
                    return data;
                })
                .catch(function (err) {
                    setEventEditorSaving(false);
                    if (lastSelectedEventIdRef.current !== targetEventId) {
                        return Promise.reject(err);
                    }
                    if (err && err.aborted) {
                        return Promise.reject(err);
                    }
                    var fallback = getString(strings, 'error', 'Erreur');
                    var messages = collectErrorMessages(err, fallback);
                    setEventEditorErrors(messages);
                    if (err && err.message) {
                        showError(err.message);
                    } else {
                        showError(fallback);
                    }
                    return Promise.reject(err);
                });
        }, [selectedEvent, api, showSuccess, showError, strings, loadEventDetails]);

        // Retour à la liste des événements (mobile)
        var handleBackToEvents = useCallback(function () {
            setMobileShowDetails(false);
        }, []);

        useEffect(function () {
            if (sidebarMode !== 'events') {
                return;
            }
            if (eventsLoading) {
                return;
            }
            if (selectedEvent && selectedEvent.id) {
                return;
            }

            try {
                var parsedId = lastSelectedEventIdRef.current;
                if (!parsedId || isNaN(parsedId)) {
                    var savedId = localStorage.getItem(storageKey);
                    if (!savedId) {
                        return;
                    }
                    parsedId = parseInt(savedId, 10);
                    if (!parsedId || isNaN(parsedId)) {
                        return;
                    }
                    lastSelectedEventIdRef.current = parsedId;
                }

                var matchingEvent = events.find(function (evt) { return evt.id === parsedId; });
                if (matchingEvent) {
                    handleSelectEvent(matchingEvent);
                }
            } catch (e) {
                // localStorage non disponible
            }
        }, [sidebarMode, eventsLoading, selectedEvent, events, handleSelectEvent, storageKey]);

        useEffect(function () {
            if (sidebarMode !== 'events') {
                return;
            }
            if (activeTab !== 'editor') {
                return;
            }
            if (!EventEditor) {
                return;
            }
            if (!selectedEvent || !selectedEvent.id) {
                return;
            }
            if (eventEditorLoading || eventEditorSaving) {
                return;
            }
            if (eventEditorLoadedRef.current === selectedEvent.id && eventEditorData) {
                return;
            }
            loadEventEditor(selectedEvent.id);
        }, [sidebarMode, activeTab, selectedEvent, eventEditorLoading, eventEditorSaving, eventEditorData, loadEventEditor]);

        // Charger les photos de l'événement quand l'onglet photos est actif
        useEffect(function () {
            if (sidebarMode !== 'events') {
                return;
            }
            if (activeTab !== 'photos') {
                return;
            }
            if (!selectedEvent || !selectedEvent.id) {
                return;
            }
            if (eventPhotosLoading) {
                return;
            }
            // Déjà chargé pour cet événement
            if (eventPhotosLoadedRef.current === selectedEvent.id) {
                return;
            }
            eventPhotosLoadedRef.current = selectedEvent.id;
            loadEventPhotos(selectedEvent.id);
        }, [sidebarMode, activeTab, selectedEvent, eventPhotosLoading, loadEventPhotos]);

        // Appliquer l'onglet depuis l'URL après le chargement initial de l'événement
        useEffect(function () {
            if (urlTabHandledRef.current) {
                return;
            }
            if (!urlTab) {
                urlTabHandledRef.current = true;
                return;
            }
            // Attendre qu'un événement ou membre soit sélectionné
            if (sidebarMode === 'events' && (!selectedEvent || !selectedEvent.id)) {
                return;
            }
            if (sidebarMode === 'members' && (!selectedMember || !selectedMember.id)) {
                return;
            }

            urlTabHandledRef.current = true;

            // Liste des onglets valides
            var validTabs = ['registrations', 'attendance', 'description', 'regdoc', 'photos', 'details', 'editor', OCCURRENCE_TAB_KEY];
            if (validTabs.indexOf(urlTab) !== -1) {
                setActiveTab(urlTab);
            }
        }, [urlTab, sidebarMode, selectedEvent, selectedMember, setActiveTab]);

        // ============================================
        // MEMBERS MODE FUNCTIONS
        // ============================================

        // Charger les membres
        var loadMembers = useCallback(function (page) {
            setMembersLoading(true);
            return api.getMembers({
                filter: memberFilter,
                search: memberSearch,
                sort: memberSort,
                page: page || 1,
                perPage: config.perPage || 20,
            })
                .then(function (data) {
                    var fetchedMembers = Array.isArray(data.members) ? data.members.slice() : [];
                    if (pendingMemberSelection && pendingMemberSelection.id && pendingMemberSelection.member) {
                        var alreadyPresent = fetchedMembers.some(function (item) {
                            return item.id === pendingMemberSelection.id;
                        });
                        if (!alreadyPresent) {
                            fetchedMembers.unshift(pendingMemberSelection.member);
                        }
                    }
                    setMembersList(fetchedMembers);
                    setMembersPagination(data.pagination || { page: 1, totalPages: 1 });
                    setMembersLoading(false);
                    return data;
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message || getString(strings, 'error', 'Erreur'));
                        setMembersLoading(false);
                    }
                    return null;
                });
        }, [api, memberFilter, memberSearch, memberSort, config.perPage, pendingMemberSelection, showError, strings]);

        // Charger les membres quand on bascule en mode membres ou quand les filtres changent
        useEffect(function () {
            if (sidebarMode === 'members') {
                loadMembers(1);
            }
        }, [sidebarMode, memberFilter, memberSearch, memberSort]);

        // Charger les détails d'un membre
        var loadMemberDetails = useCallback(function (memberId) {
            api.getMemberDetails(memberId)
                .then(function (data) {
                    setMemberDetails(data.member);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message);
                    }
                });
        }, [api, showError]);

        // Charger les notes d'un membre (mode membres)
        var loadMemberNotesForPanel = useCallback(function (memberId) {
            api.getMemberNotes(memberId)
                .then(function (data) {
                    setMemberNotes(data.notes || []);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message);
                    }
                });
        }, [api, showError]);

        // Charger l'historique des inscriptions d'un membre
        var loadMemberRegistrationsHistory = useCallback(function (memberId) {
            api.getMemberRegistrations(memberId)
                .then(function (data) {
                    setMemberRegistrations(data.registrations || []);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message);
                    }
                });
        }, [api, showError]);

        // Sélectionner un membre
        var handleSelectMember = useCallback(function (member) {
            setSelectedMember(member);
            setMemberDetails(null);
            setMemberNotes([]);
            setMemberRegistrations([]);
            setMobileShowDetails(true);

            lastSelectedMemberIdRef.current = member.id;
            try {
                localStorage.setItem(memberStorageKey, String(member.id));
            } catch (e) {
                // localStorage non disponible
            }

            // Mettre à jour l'URL avec le membre (onglet par défaut: information)
            updateUrlParams({ member: member.id, tab: 'information' });

            loadMemberDetails(member.id);
            loadMemberNotesForPanel(member.id);
            loadMemberRegistrationsHistory(member.id);
        }, [loadMemberDetails, loadMemberNotesForPanel, loadMemberRegistrationsHistory, memberStorageKey]);

        // Charger le membre depuis l'URL au démarrage
        useEffect(function () {
            if (urlMemberHandledRef.current) {
                return;
            }
            if (sidebarMode !== 'members') {
                return;
            }
            if (membersLoading) {
                return;
            }
            if (!urlMemberId) {
                urlMemberHandledRef.current = true;
                return;
            }

            urlMemberHandledRef.current = true;

            // Chercher dans la liste déjà chargée
            var memberFromList = membersList.find(function (m) { return m.id === urlMemberId; });
            if (memberFromList) {
                handleSelectMember(memberFromList);
                return;
            }

            // Si pas trouvé, charger directement les détails du membre
            api.getMemberDetails(urlMemberId)
                .then(function (data) {
                    if (data && data.member) {
                        // Injecter dans la liste et sélectionner
                        var memberData = data.member;
                        setMembersList(function (prev) {
                            var exists = prev.some(function (m) { return m.id === memberData.id; });
                            if (exists) {
                                return prev;
                            }
                            return [memberData].concat(prev);
                        });
                        handleSelectMember(memberData);
                    }
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message || getString(strings, 'memberNotFound', 'Membre introuvable'));
                    }
                });
        }, [sidebarMode, membersLoading, urlMemberId, membersList, handleSelectMember, api, showError, strings]);

        useEffect(function () {
            if (sidebarMode !== 'members') {
                return;
            }
            if (membersLoading) {
                return;
            }
            if (selectedMember && selectedMember.id) {
                return;
            }
            // Ne pas restaurer depuis localStorage si on a un urlMemberId
            if (urlMemberId && !urlMemberHandledRef.current) {
                return;
            }

            try {
                var parsedId = lastSelectedMemberIdRef.current;
                if (!parsedId || isNaN(parsedId)) {
                    var savedId = localStorage.getItem(memberStorageKey);
                    if (!savedId) {
                        return;
                    }
                    parsedId = parseInt(savedId, 10);
                    if (!parsedId || isNaN(parsedId)) {
                        return;
                    }
                    lastSelectedMemberIdRef.current = parsedId;
                }

                var matchingMember = membersList.find(function (m) { return m.id === parsedId; });
                if (matchingMember) {
                    handleSelectMember(matchingMember);
                }
            } catch (e) {
                // localStorage non disponible
            }
        }, [sidebarMode, membersLoading, selectedMember, membersList, handleSelectMember, memberStorageKey, urlMemberId]);

        useEffect(function () {
            if (!pendingMemberSelection) {
                return;
            }
            if (sidebarMode !== 'members') {
                return;
            }
            if (membersLoading) {
                return;
            }

            var targetId = pendingMemberSelection.id;
            if (!targetId) {
                setPendingMemberSelection(null);
                return;
            }

            var memberFromList = membersList.find(function (memberItem) {
                return memberItem.id === targetId;
            });

            if (memberFromList) {
                setPendingMemberSelection(null);
            }
        }, [pendingMemberSelection, sidebarMode, membersLoading, membersList, setPendingMemberSelection]);

        // Changer de mode sidebar
        var handleSidebarModeChange = useCallback(function (mode) {
            setSidebarMode(mode);
            setMobileShowDetails(false);
            setPendingMemberEdit(null);
            setPendingMemberSelection(null);
            
            if (mode === 'events') {
                setSelectedMember(null);
                setMemberDetails(null);
                // Nettoyer l'URL - enlever member et tab
                updateUrlParams({ member: null, tab: null, event: null });
            } else {
                setSelectedEvent(null);
                setEventDetails(null);
                lastSelectedEventIdRef.current = null;
                eventEditorLoadedRef.current = null;
                setEventEditorData(null);
                setEventEditorSummary(null);
                setEventEditorErrors([]);
                setEventEditorLoading(false);
                setEventEditorSaving(false);
                // Nettoyer l'URL - enlever event et tab
                updateUrlParams({ event: null, tab: null, member: null });
            }
        }, []);

        // Sauvegarder une note de membre (mode membres)
        var handleSaveMemberNote = useCallback(function (memberId, content, noteId) {
            return api.saveMemberNote(memberId, content, noteId)
                .then(function (data) {
                    showSuccess(data.message || 'Note enregistrée');
                    loadMemberNotesForPanel(memberId);
                    return data;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberNotesForPanel]);

        // Supprimer une note de membre (mode membres)
        var handleDeleteMemberNoteFromPanel = useCallback(function (noteId) {
            if (!confirm('Voulez-vous vraiment supprimer cette note ?')) {
                return;
            }
            api.deleteMemberNote(noteId)
                .then(function () {
                    showSuccess('Note supprimée');
                    if (selectedMember) {
                        loadMemberNotesForPanel(selectedMember.id);
                    }
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, showSuccess, showError, selectedMember, loadMemberNotesForPanel]);

        var handleUpdateMemberIdea = useCallback(function (memberId, ideaId, data) {
            return api.updateMemberIdea(ideaId, memberId, data)
                .then(function (result) {
                    showSuccess(result.message || 'Idée mise à jour');
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails]);

        var handleDeleteMemberIdea = useCallback(function (memberId, ideaId) {
            return api.deleteMemberIdea(ideaId, memberId)
                .then(function (result) {
                    showSuccess(result.message || 'Idée supprimée');
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails]);

        var handleUpdateMemberPhoto = useCallback(function (memberId, photoId, data) {
            return api.updateMemberPhoto(photoId, memberId, data)
                .then(function (result) {
                    showSuccess(result.message || 'Photo mise à jour');
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails]);

        var handleDeleteMemberPhoto = useCallback(function (memberId, photoId) {
            return api.deleteMemberPhoto(photoId, memberId)
                .then(function (result) {
                    showSuccess(result.message || 'Photo supprimée');
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails]);

        var handleDeleteMemberMessage = useCallback(function (memberId, messageId) {
            return api.deleteMemberMessage(messageId, memberId)
                .then(function (result) {
                    showSuccess(result.message || 'Message supprimé');
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails]);

        var handleDeleteMemberTestimonial = useCallback(function (testimonialId) {
            var memberId = selectedMember && selectedMember.id ? selectedMember.id : null;
            return api.deleteMemberTestimonial(testimonialId)
                .then(function (result) {
                    showSuccess(result.message || 'Témoignage supprimé');
                    if (memberId) {
                        loadMemberDetails(memberId);
                    }
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, selectedMember]);

        var handleUpdateMemberTestimonialStatus = useCallback(function (testimonialId, status, rejectionReason) {
            var memberId = selectedMember && selectedMember.id ? selectedMember.id : null;
            return api.updateMemberTestimonialStatus(testimonialId, status, rejectionReason)
                .then(function (result) {
                    showSuccess(result.message || 'Statut mis à jour');
                    if (memberId) {
                        loadMemberDetails(memberId);
                    }
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, selectedMember]);

        var handleSyncMemberBadge = useCallback(function (memberId, badgeId, criterionIds) {
            return api.syncMemberBadge(memberId, badgeId, criterionIds)
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : getString(strings, 'memberBadgeUpdated', 'Progression mise à jour.');
                    showSuccess(successMessage);
                    // Update xpTotal, coinsTotal and levelProgression immediately from sync response
                    if (result && typeof result.xpTotal === 'number') {
                        setMemberDetails(function (prev) {
                            if (!prev) return prev;
                            var updates = { xpTotal: result.xpTotal };
                            if (typeof result.coinsTotal === 'number') {
                                updates.coinsTotal = result.coinsTotal;
                            }
                            if (result.levelProgression) {
                                updates.levelProgression = result.levelProgression;
                            }
                            return Object.assign({}, prev, updates);
                        });
                    }
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    var message = err && err.message
                        ? err.message
                        : getString(strings, 'memberBadgeUpdateError', 'Impossible de mettre à jour ce badge.');
                    showError(message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, strings]);

        var handleAdjustMemberXp = useCallback(function (memberId, amount) {
            return api.adjustMemberXp(memberId, amount)
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : (amount > 0 ? '+' : '') + amount + ' XP';
                    showSuccess(successMessage);
                    // Update xpTotal, coinsTotal and levelProgression immediately from response
                    if (result && typeof result.xpTotal === 'number') {
                        setMemberDetails(function (prev) {
                            if (!prev) return prev;
                            var updates = { xpTotal: result.xpTotal };
                            if (typeof result.coinsTotal === 'number') {
                                updates.coinsTotal = result.coinsTotal;
                            }
                            if (result.levelProgression) {
                                updates.levelProgression = result.levelProgression;
                            }
                            return Object.assign({}, prev, updates);
                        });
                    }
                    return result;
                })
                .catch(function (err) {
                    var message = err && err.message
                        ? err.message
                        : 'Impossible d\'ajuster les XP.';
                    showError(message);
                    throw err;
                });
        }, [api, showSuccess, showError]);

        var handleToggleMemberTrophy = useCallback(function (memberId, trophyId, awarded) {
            return api.toggleMemberTrophy(memberId, trophyId, awarded)
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : (awarded
                            ? getString(strings, 'memberTrophyAwarded', 'Trophée attribué.')
                            : getString(strings, 'memberTrophyRevoked', 'Trophée retiré.'));
                    showSuccess(successMessage);
                    loadMemberDetails(memberId);
                    return result;
                })
                .catch(function (err) {
                    var message = err && err.message
                        ? err.message
                        : getString(strings, 'memberTrophyToggleError', 'Impossible de modifier ce trophée.');
                    showError(message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, strings]);

        var handleUpdateMemberAvatar = useCallback(function (memberId, photoId) {
            var targetId = parseInt(memberId, 10);
            if (!targetId || targetId <= 0) {
                return Promise.resolve();
            }
            var attachmentId = parseInt(photoId, 10);
            if (!attachmentId || attachmentId <= 0) {
                return Promise.resolve();
            }
            return api.updateMember(targetId, { photoId: attachmentId })
                .then(function (result) {
                    showSuccess(getString(strings, 'memberAvatarUpdated', 'Photo de profil mise à jour.'));
                    loadMemberDetails(targetId);
                    loadMembers(membersPagination.page);
                    return result;
                })
                .catch(function (err) {
                    var message = err && err.message
                        ? err.message
                        : getString(strings, 'memberAvatarUpdateError', 'Impossible de mettre à jour la photo de profil.');
                    showError(message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, loadMembers, membersPagination.page, strings]);

        var handleCaptureMemberAvatar = useCallback(function (memberData, lifecycle) {
            if (!memberData || !memberData.id) {
                return;
            }
            avatarCaptureModal.open({
                member: memberData,
                lifecycle: lifecycle || {},
            });
        }, [avatarCaptureModal]);

        var handleConfirmAvatarCapture = useCallback(function (blob) {
            var modalData = avatarCaptureModal.data || {};
            var targetMember = modalData.member || null;
            var lifecycle = modalData.lifecycle || {};
            var targetId = targetMember && targetMember.id ? parseInt(targetMember.id, 10) : 0;
            if (!targetId || targetId <= 0 || !blob) {
                return Promise.reject(new Error('Membre introuvable.'));
            }

            if (lifecycle && typeof lifecycle.onUploadStart === 'function') {
                try { lifecycle.onUploadStart(); } catch (e) { /* ignore */ }
            }

            return api.captureMemberAvatar(targetId, blob)
                .then(function (result) {
                    if (lifecycle && typeof lifecycle.onUploadEnd === 'function') {
                        try { lifecycle.onUploadEnd(); } catch (e) { /* ignore */ }
                    }
                    var successMessage = result && result.message
                        ? result.message
                        : getString(strings, 'memberAvatarUpdated', 'Photo de profil mise à jour.');
                    showSuccess(successMessage);
                    loadMemberDetails(targetId);
                    loadMembers(membersPagination.page);
                    avatarCaptureModal.close();
                    return result;
                })
                .catch(function (err) {
                    if (lifecycle && typeof lifecycle.onUploadError === 'function') {
                        try { lifecycle.onUploadError(err); } catch (e) { /* ignore */ }
                    }
                    var message = err && err.message
                        ? err.message
                        : getString(strings, 'memberAvatarUpdateError', 'Impossible de mettre à jour la photo de profil.');
                    showError(message);
                    throw err;
                });
        }, [api, avatarCaptureModal, showSuccess, loadMemberDetails, loadMembers, membersPagination.page, strings, showError]);

        var handleRemoveMemberAvatar = useCallback(function (memberId) {
            var targetId = parseInt(memberId, 10);
            if (!targetId || targetId <= 0) {
                return Promise.resolve();
            }
            return api.updateMember(targetId, { photoId: 0 })
                .then(function (result) {
                    showSuccess(getString(strings, 'memberAvatarRemoved', 'Photo de profil retirée.'));
                    loadMemberDetails(targetId);
                    loadMembers(membersPagination.page);
                    return result;
                })
                .catch(function (err) {
                    var message = err && err.message
                        ? err.message
                        : getString(strings, 'memberAvatarUpdateError', 'Impossible de mettre à jour la photo de profil.');
                    showError(message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, loadMembers, membersPagination.page, strings]);

        var handleDeleteMemberRegistration = useCallback(function (registration) {
            if (!registration || !registration.id) {
                return;
            }

            if (!confirm(getString(strings, 'confirmDeleteRegistration', 'Voulez-vous vraiment supprimer cette inscription ?'))) {
                return;
            }

            api.deleteRegistration(registration.id)
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : getString(strings, 'success', 'Opération réussie');
                    showSuccess(successMessage);

                    setMemberRegistrations(function (current) {
                        if (!Array.isArray(current)) {
                            return current;
                        }
                        return current.filter(function (item) { return item.id !== registration.id; });
                    });

                    if (selectedMember && selectedMember.id) {
                        loadMemberRegistrationsHistory(selectedMember.id);
                        loadMemberDetails(selectedMember.id);
                    }

                    if (registration.eventId && selectedEvent && selectedEvent.id === registration.eventId) {
                        loadRegistrations(selectedEvent.id);
                    }

                    loadEvents(pagination.page);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [
            api,
            showSuccess,
            strings,
            setMemberRegistrations,
            selectedMember,
            loadMemberRegistrationsHistory,
            loadMemberDetails,
            selectedEvent,
            loadRegistrations,
            loadEvents,
            pagination.page,
            showError,
        ]);

        var handleUpdateRegistrationOccurrences = useCallback(function (registrationId, mode, occurrences) {
            if (!registrationId) {
                return Promise.reject(new Error('Registration ID required'));
            }

            return api.updateRegistrationOccurrences(registrationId, mode, occurrences)
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : getString(strings, 'success', 'Opération réussie');
                    showSuccess(successMessage);

                    // Refresh member registrations
                    if (selectedMember && selectedMember.id) {
                        loadMemberRegistrationsHistory(selectedMember.id);
                    }

                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [
            api,
            showSuccess,
            showError,
            strings,
            selectedMember,
            loadMemberRegistrationsHistory,
        ]);

        var handleDeleteMember = useCallback(function (memberId) {
            if (!memberId) {
                return Promise.resolve();
            }

            var targetId = parseInt(memberId, 10);
            if (!targetId || targetId <= 0) {
                return Promise.resolve();
            }

            return api.deleteMember(targetId)
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : getString(strings, 'memberDeleted', 'Membre supprimé.');
                    showSuccess(successMessage);

                    setMembersList(function (current) {
                        if (!Array.isArray(current)) {
                            return current;
                        }
                        return current.filter(function (item) {
                            return item && item.id !== targetId;
                        });
                    });

                    setAttendanceMembers(function (current) {
                        if (!Array.isArray(current)) {
                            return current;
                        }
                        return current.filter(function (item) {
                            return item && item.id !== targetId;
                        });
                    });

                    setRegistrations(function (current) {
                        if (!Array.isArray(current)) {
                            return current;
                        }
                        return current.filter(function (registration) {
                            return registration && registration.memberId !== targetId;
                        });
                    });

                    setAttendanceMap(function (current) {
                        if (!current || typeof current !== 'object') {
                            return current;
                        }
                        var mutated = false;
                        var next = {};
                        Object.keys(current).forEach(function (occurrenceKey) {
                            var occurrenceMap = current[occurrenceKey];
                            if (occurrenceMap && typeof occurrenceMap === 'object' && Object.prototype.hasOwnProperty.call(occurrenceMap, targetId)) {
                                var copy = Object.assign({}, occurrenceMap);
                                delete copy[targetId];
                                next[occurrenceKey] = copy;
                                mutated = true;
                            } else {
                                next[occurrenceKey] = occurrenceMap;
                            }
                        });
                        return mutated ? next : current;
                    });

                    setPendingMemberEdit(function (current) {
                        if (current && current.memberId === targetId) {
                            return null;
                        }
                        return current;
                    });

                    setPendingMemberSelection(function (current) {
                        if (current && current.id === targetId) {
                            return null;
                        }
                        return current;
                    });

                    if (selectedMember && selectedMember.id === targetId) {
                        setSelectedMember(null);
                        setMemberDetails(null);
                        setMemberNotes([]);
                        setMemberRegistrations([]);
                        setMobileShowDetails(false);
                        lastSelectedMemberIdRef.current = null;
                        try {
                            localStorage.removeItem(memberStorageKey);
                        } catch (e) {
                            // localStorage peut être indisponible (mode navigation privée)
                        }
                    }

                    if (selectedEvent && selectedEvent.id) {
                        loadRegistrations(selectedEvent.id);
                    }

                    var reloadPage = Math.max(1, membersPagination.page || 1);
                    var reloadPromise = loadMembers(reloadPage);
                    if (reloadPromise && typeof reloadPromise.catch === 'function') {
                        reloadPromise.catch(function () {});
                    }

                    return result;
                })
                .catch(function (err) {
                    showError(err && err.message ? err.message : getString(strings, 'error', 'Erreur'));
                    throw err;
                });
        }, [
            api,
            showSuccess,
            strings,
            setMembersList,
            setAttendanceMembers,
            setRegistrations,
            setAttendanceMap,
            setPendingMemberEdit,
            setPendingMemberSelection,
            selectedMember,
            setSelectedMember,
            setMemberDetails,
            setMemberNotes,
            setMemberRegistrations,
            setMobileShowDetails,
            memberStorageKey,
            selectedEvent,
            loadRegistrations,
            loadMembers,
            membersPagination.page,
            showError,
        ]);

        var handleConsumePendingMemberEdit = useCallback(function () {
            setPendingMemberEdit(null);
        }, []);

        var handleViewMemberFromRegistration = useCallback(function (member, options) {
            if (!member || !member.id) {
                return;
            }
            setSidebarMode('members');
            setSelectedEvent(null);
            setEventDetails(null);
            if (options && options.edit === true) {
                setPendingMemberEdit({
                    memberId: member.id,
                    requestId: Date.now(),
                });
            } else {
                setPendingMemberEdit(null);
            }
            handleSelectMember(member);
        }, [handleSelectMember, setSelectedEvent, setEventDetails, setSidebarMode, setPendingMemberEdit]);

        // Naviguer vers un événement depuis la fiche membre (ex: photo)
        var handleViewEventById = useCallback(function (eventId) {
            if (!eventId) {
                return;
            }
            // Chercher l'événement dans la liste
            var found = events.find(function (e) { return e.id === eventId; });
            if (found) {
                setSidebarMode('events');
                setSelectedMember(null);
                setMemberDetails(null);
                handleSelectEvent(found);
            } else {
                // L'événement n'est pas dans la liste actuelle, on l'affiche quand même
                // en créant un objet minimal
                setSidebarMode('events');
                setSelectedMember(null);
                setMemberDetails(null);
                handleSelectEvent({ id: eventId });
            }
        }, [events, handleSelectEvent, setSidebarMode, setSelectedMember, setMemberDetails]);

        // Mettre à jour un membre
        var handleUpdateMember = useCallback(function (memberId, data) {
            return api.updateMember(memberId, data)
                .then(function (result) {
                    showSuccess(result.message || 'Membre mis à jour');
                    loadMemberDetails(memberId);
                    loadMembers(membersPagination.page);
                    return result;
                })
                .catch(function (err) {
                    showError(err && err.message ? err.message : getString(strings, 'error', 'Une erreur est survenue.'));
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, loadMembers, membersPagination.page, strings]);

        // Marquer la cotisation comme payée
        var handleMarkMembershipPaid = useCallback(function (memberId, paymentMethod) {
            return api.markMembershipPaid(memberId, paymentMethod)
                .then(function (result) {
                    showSuccess(result.message || 'Cotisation enregistrée');
                    loadMemberDetails(memberId);
                    loadMembers(membersPagination.page);
                    loadMemberNotesForPanel(memberId);
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, loadMembers, membersPagination.page, loadMemberNotesForPanel]);

        // Réinitialiser le mot de passe d'un membre (envoi mail WordPress)
        var handleResetMemberPassword = useCallback(function (memberId) {
            return api.resetMemberPassword(memberId)
                .then(function (result) {
                    showSuccess(result.message || 'Email de réinitialisation envoyé');
                    return result;
                })
                .catch(function (err) {
                    showError(err.message);
                    throw err;
                });
        }, [api, showSuccess, showError]);

        var handleLinkMemberAccount = useCallback(function (memberId, payload) {
            if (!memberId) {
                return Promise.reject(new Error('memberId is required'));
            }

            if (!config.canManageAccounts) {
                return Promise.reject(new Error('Accès refusé.'));
            }

            return api.linkMemberAccount(memberId, payload || {})
                .then(function (result) {
                    loadMemberDetails(memberId);
                    loadMembers(membersPagination.page);

                    var successMessage = getString(strings, 'memberAccountSuccessUpdate', 'Compte WordPress mis à jour avec succès.');
                    if (result && result.created) {
                        successMessage = getString(strings, 'memberAccountSuccessCreate', 'Compte WordPress créé et lié avec succès.');
                    }
                    if (result && result.message) {
                        successMessage = result.message;
                    }

                    showSuccess(successMessage);
                    return result;
                })
                .catch(function (err) {
                    var errorMessage = err && err.message ? err.message : 'Erreur lors de la mise à jour du compte WordPress.';
                    showError(errorMessage);
                    throw err;
                });
        }, [api, loadMemberDetails, loadMembers, membersPagination.page, showSuccess, showError, strings, config.canManageAccounts]);

        var handleOpenAccountModal = useCallback(function (memberData) {
            if (!config.canManageAccounts) {
                return;
            }

            var targetMember = memberData && memberData.id ? memberData : memberDetails;
            if (!targetMember) {
                return;
            }

            accountModal.open({ member: targetMember });
        }, [config.canManageAccounts, memberDetails, accountModal]);

        // Payer la cotisation via Stripe - retourne le résultat complet avec qrUrl
        var handlePayMembershipOnline = useCallback(function (memberId) {
            return api.createMembershipPaymentLink(memberId)
                .then(function (result) {
                    if (result.checkoutUrl) {
                        // Ne pas ouvrir automatiquement - la modal affichera le QR code
                        return result;
                    } else {
                        var err = new Error('Impossible de créer le lien de paiement');
                        showError(err.message);
                        throw err;
                    }
                })
                .catch(function (err) {
                    showError(err.message || 'Erreur lors de la création du lien de paiement');
                    throw err;
                });
        }, [api, showError]);

        // ============================================
        // EVENT MODE FUNCTIONS
        // ============================================

        // Ajouter des participants
        var handleAddParticipants = useCallback(function (memberIds) {
            if (!selectedEvent) return;

            api.addRegistration(selectedEvent.id, memberIds, [])
                .then(function (data) {
                    showSuccess(data.message);
                    addParticipantModal.close();
                    loadRegistrations(selectedEvent.id);
                    loadEvents(pagination.page);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, addParticipantModal, loadRegistrations, loadEvents, pagination.page]);

        // Valider inscription
        var handleValidateRegistration = useCallback(function (registration) {
            api.updateRegistration(registration.id, 'valide')
                .then(function () {
                    showSuccess(getString(strings, 'success', 'Opération réussie'));
                    loadRegistrations(selectedEvent.id);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, loadRegistrations, strings]);

        // Annuler inscription
        var handleCancelRegistration = useCallback(function (registration) {
            api.updateRegistration(registration.id, 'annule')
                .then(function () {
                    showSuccess(getString(strings, 'success', 'Opération réussie'));
                    loadRegistrations(selectedEvent.id);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, loadRegistrations, strings]);

        // Supprimer inscription
        var handleDeleteRegistration = useCallback(function (registration) {
            if (!confirm(getString(strings, 'confirmDeleteRegistration', 'Voulez-vous vraiment supprimer cette inscription ?'))) {
                return;
            }

            api.deleteRegistration(registration.id)
                .then(function () {
                    showSuccess(getString(strings, 'success', 'Opération réussie'));
                    loadRegistrations(selectedEvent.id);
                    loadEvents(pagination.page);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, loadRegistrations, loadEvents, pagination.page, strings]);

        // Valider paiement
        var handleValidatePayment = useCallback(function (registration) {
            api.validatePayment(registration.id, 'manual', '')
                .then(function () {
                    showSuccess(getString(strings, 'success', 'Paiement validé'));
                    loadRegistrations(selectedEvent.id);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, loadRegistrations, strings]);

        // Annuler paiement
        var handleCancelPayment = useCallback(function (registration) {
            if (!confirm(getString(strings, 'confirmCancelPayment', 'Êtes-vous sûr de vouloir annuler ce paiement ?'))) {
                return;
            }
            api.cancelPayment(registration.id)
                .then(function () {
                    showSuccess(getString(strings, 'success', 'Paiement annulé'));
                    loadRegistrations(selectedEvent.id);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, loadRegistrations, strings]);

        // Afficher QR Code
        var handleShowQR = useCallback(function (registration) {
            setQrLoading(true);
            setQrData(null);
            qrModal.open();

            api.getPaymentQR(registration.id)
                .then(function (data) {
                    setQrData(data);
                    setQrLoading(false);
                })
                .catch(function (err) {
                    showError(err.message);
                    setQrLoading(false);
                    qrModal.close();
                });
        }, [api, qrModal, showError]);

        // Afficher notes
        var handleShowNotes = useCallback(function (registration) {
            if (!registration.member) return;

            notesModal.open({ member: registration.member });
            setNotesLoading(true);
            setNotes([]);

            api.getMemberNotes(registration.member.id)
                .then(function (data) {
                    setNotes(data.notes || []);
                    setNotesLoading(false);
                })
                .catch(function (err) {
                    showError(err.message);
                    setNotesLoading(false);
                });
        }, [api, notesModal, showError]);

        // Sauvegarder note
        var handleSaveNote = useCallback(function (memberId, content, noteId) {
            return api.saveMemberNote(memberId, content, noteId)
                .then(function () {
                    // Recharger les notes
                    return api.getMemberNotes(memberId);
                })
                .then(function (data) {
                    setNotes(data.notes || []);
                });
        }, [api]);

        // Supprimer note
        var handleDeleteNote = useCallback(function (noteId) {
            api.deleteMemberNote(noteId)
                .then(function () {
                    showSuccess(getString(strings, 'success', 'Note supprimée'));
                    if (notesModal.data && notesModal.data.member) {
                        api.getMemberNotes(notesModal.data.member.id)
                            .then(function (data) {
                                setNotes(data.notes || []);
                            });
                    }
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, notesModal.data, showSuccess, showError, strings]);

        // Modifier les séances d'un participant
        var handleChangeOccurrences = useCallback(function (registration) {
            if (!selectedEvent || !eventDetails) return;
            
            // Ouvrir le modal des séances
            occurrencesModal.open({
                registration: registration,
                selectedOccurrences: registration.occurrences || [],
            });
        }, [selectedEvent, eventDetails, occurrencesModal]);

        // Sauvegarder les séances d'un participant
        var handleSaveOccurrences = useCallback(function (selectedOccs) {
            if (!occurrencesModal.data || !selectedEvent) return;

            var registration = occurrencesModal.data.registration;
            
            api.updateOccurrences(registration.id, selectedOccs)
                .then(function (data) {
                    showSuccess(data.message || 'Séances mises à jour');
                    occurrencesModal.close();
                    loadRegistrations(selectedEvent.id);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, occurrencesModal, showSuccess, showError, loadRegistrations]);

        var handlePersistEventOccurrences = useCallback(function (nextOccurrences, scheduleSummary, generatorPlan) {
            var eventIdSource = null;
            if (eventDetails && eventDetails.id !== undefined && eventDetails.id !== null) {
                eventIdSource = eventDetails.id;
            } else if (selectedEvent && selectedEvent.id !== undefined && selectedEvent.id !== null) {
                eventIdSource = selectedEvent.id;
            }
            if (eventIdSource === null) {
                return Promise.reject(new Error(getString(strings, 'occurrenceNoEventSelected', 'Sélectionnez un événement pour gérer ses occurrences.')));
            }

            var eventIdKey = String(eventIdSource);
            var scheduleSummaryValue = typeof scheduleSummary === 'string' ? scheduleSummary : '';

            var payload = Array.isArray(nextOccurrences) ? nextOccurrences.map(function (occ) {
                return {
                    id: occ && occ.id ? occ.id : null,
                    date: occ && typeof occ.date === 'string' ? occ.date : '',
                    startTime: occ && typeof occ.startTime === 'string' ? occ.startTime : '',
                    endTime: occ && typeof occ.endTime === 'string' ? occ.endTime : '',
                    status: occ && typeof occ.status === 'string' ? occ.status : 'planned',
                    reason: occ && typeof occ.reason === 'string' ? occ.reason : '',
                };
            }) : [];

            var generatorPayload = generatorPlan && typeof generatorPlan === 'object' ? generatorPlan : null;

            return api.saveEventOccurrences(eventIdSource, payload, scheduleSummaryValue, generatorPayload)
                .then(function (response) {
                    var responseEventId = response && response.event && response.event.id !== undefined && response.event.id !== null
                        ? String(response.event.id)
                        : eventIdKey;
                    if (response && response.event) {
                        var responseEvent = response.event;
                        setEventDetails(function (prev) {
                            var base = prev;
                            if (!base || base.id === undefined || base.id === null) {
                                if (responseEventId !== eventIdKey) {
                                    return base;
                                }
                                base = { id: eventIdSource };
                            }
                            if (String(base.id) !== responseEventId) {
                                return base;
                            }
                            var nextEvent = Object.assign({}, base);
                            if (Array.isArray(responseEvent.occurrences)) {
                                nextEvent.occurrences = responseEvent.occurrences;
                            }
                            if (typeof responseEvent.dateDebut === 'string') {
                                nextEvent.dateDebut = responseEvent.dateDebut;
                            }
                            if (typeof responseEvent.dateFin === 'string') {
                                nextEvent.dateFin = responseEvent.dateFin;
                            }
                            if (typeof responseEvent.dateDebutFormatted === 'string') {
                                nextEvent.dateDebutFormatted = responseEvent.dateDebutFormatted;
                            }
                            if (typeof responseEvent.dateFinFormatted === 'string') {
                                nextEvent.dateFinFormatted = responseEvent.dateFinFormatted;
                            }
                            if (typeof responseEvent.scheduleSummary === 'string') {
                                nextEvent.scheduleSummary = responseEvent.scheduleSummary;
                            }
                            if (typeof responseEvent.scheduleDetail === 'string') {
                                nextEvent.scheduleDetail = responseEvent.scheduleDetail;
                            }
                            if (typeof responseEvent.occurrenceScheduleSummary === 'string') {
                                nextEvent.occurrenceScheduleSummary = responseEvent.occurrenceScheduleSummary;
                            }
                            if (responseEvent.occurrenceGenerator && typeof responseEvent.occurrenceGenerator === 'object') {
                                nextEvent.occurrenceGenerator = responseEvent.occurrenceGenerator;
                            }
                            return nextEvent;
                        });
                    }
                    if (response && response.eventSummary) {
                        var responseSummaryId = response.eventSummary.id !== undefined && response.eventSummary.id !== null
                            ? String(response.eventSummary.id)
                            : responseEventId;
                        setSelectedEvent(function (prev) {
                            var base = prev;
                            if (!base || base.id === undefined || base.id === null) {
                                if (responseSummaryId !== eventIdKey) {
                                    return base;
                                }
                                base = { id: eventIdSource };
                            }
                            if (String(base.id) !== responseSummaryId) {
                                return base;
                            }
                            var nextEvent = Object.assign({}, base, response.eventSummary || {});
                            if (response && response.event && response.event.occurrenceGenerator && typeof response.event.occurrenceGenerator === 'object') {
                                nextEvent.occurrenceGenerator = response.event.occurrenceGenerator;
                            }
                            return nextEvent;
                        });
                        setEvents(function (prev) {
                            if (!Array.isArray(prev)) {
                                return prev;
                            }
                            return prev.map(function (evt) {
                                if (!evt || evt.id === undefined || evt.id === null) {
                                    return evt;
                                }
                                if (String(evt.id) === responseSummaryId) {
                                    var nextEvent = Object.assign({}, evt, response.eventSummary || {});
                                    if (response && response.event && response.event.occurrenceGenerator && typeof response.event.occurrenceGenerator === 'object') {
                                        nextEvent.occurrenceGenerator = response.event.occurrenceGenerator;
                                    }
                                    return nextEvent;
                                }
                                return evt;
                            });
                        });
                    }
                    showSuccess(response && response.message ? response.message : getString(strings, 'occurrenceSaveSuccess', 'Occurrences mises à jour.'));
                    return response;
                })
                .catch(function (error) {
                    showError(error && error.message ? error.message : getString(strings, 'occurrenceSaveError', 'Impossible d\'enregistrer les occurrences.'));
                    throw error;
                });
        }, [api, eventDetails, selectedEvent, setEventDetails, setSelectedEvent, setEvents, showSuccess, showError, strings]);

        // Mettre à jour présence
        var handleUpdateAttendance = useCallback(function (memberId, occurrence, status) {
            if (!selectedEvent) return;

            setLoadingMembers(function (prev) {
                var next = Object.assign({}, prev);
                next[memberId] = true;
                return next;
            });

            api.updateAttendance(selectedEvent.id, memberId, occurrence, status)
                .then(function () {
                    // Mettre à jour localement
                    setAttendanceMap(function (prev) {
                        var next = Object.assign({}, prev);
                        if (!next[occurrence]) next[occurrence] = {};
                        if (status) {
                            next[occurrence][memberId] = { status: status };
                        } else {
                            delete next[occurrence][memberId];
                        }
                        return next;
                    });
                    setLoadingMembers(function (prev) {
                        var next = Object.assign({}, prev);
                        delete next[memberId];
                        return next;
                    });
                })
                .catch(function (err) {
                    showError(err.message);
                    setLoadingMembers(function (prev) {
                        var next = Object.assign({}, prev);
                        delete next[memberId];
                        return next;
                    });
                });
        }, [api, selectedEvent, showError]);

        // Présence en masse
        var handleBulkAttendance = useCallback(function (occurrence, updates) {
            if (!selectedEvent) return;

            api.bulkAttendance(selectedEvent.id, occurrence, updates)
                .then(function (data) {
                    showSuccess(data.message);
                    loadRegistrations(selectedEvent.id);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, selectedEvent, showSuccess, showError, loadRegistrations]);

        // Créer membre
        var handleCreateMember = useCallback(function (firstName, lastName, email, role, birthDate, options) {
            var opts = options || {};
            var guardianId = null;
            if (typeof opts.guardianId === 'number') {
                guardianId = opts.guardianId;
            } else if (typeof opts.guardianId === 'string' && opts.guardianId !== '') {
                var parsedGuardian = parseInt(opts.guardianId, 10);
                guardianId = isNaN(parsedGuardian) ? null : parsedGuardian;
            }

            var context = opts.context || null;
            var isChildFlow = !!(context && context.type === 'child' && guardianId);

            return api.createQuickMember(firstName, lastName, email, role, birthDate, {
                guardianId: guardianId,
            })
                .then(function (data) {
                    var successMessage = data && data.message
                        ? data.message
                        : isChildFlow
                            ? getString(strings, 'guardianChildCreated', 'Enfant ajouté et rattaché avec succès.')
                            : getString(strings, 'success', 'Opération réussie.');
                    showSuccess(successMessage);
                    createMemberModal.close();

                    var createdMember = data.member || null;

                    if (isChildFlow) {
                        if (guardianId) {
                            loadMemberDetails(guardianId);
                        }
                        loadMembers(membersPagination.page);
                        return data;
                    }

                    if (createdMember && createdMember.id) {
                        var lightweightMember = Object.assign({
                            membershipStatus: 'not_required',
                            requiresPayment: false,
                            isVolunteer: false,
                            status: 'active',
                        }, createdMember);

                        if (sidebarMode === 'members') {
                            setMembersList(function (current) {
                                var list = Array.isArray(current) ? current.slice() : [];
                                var filtered = list.filter(function (item) { return item.id !== lightweightMember.id; });
                                filtered.unshift(lightweightMember);
                                return filtered;
                            });

                            handleSelectMember(lightweightMember);
                            setPendingMemberSelection({
                                id: lightweightMember.id,
                                member: lightweightMember,
                            });

                            loadMembers(membersPagination.page);
                        } else {
                            loadMembers(membersPagination.page);
                        }
                    } else {
                        loadMembers(membersPagination.page);
                    }

                    if (selectedEvent && createdMember) {
                        return api.addRegistration(selectedEvent.id, [createdMember.id], [])
                            .then(function () {
                                loadRegistrations(selectedEvent.id);
                                loadEvents(pagination.page);
                            });
                    }

                    return data;
                });
        }, [
            api,
            selectedEvent,
            showSuccess,
            createMemberModal,
            setMembersList,
            setPendingMemberSelection,
            loadMembers,
            membersPagination.page,
            sidebarMode,
            handleSelectMember,
            loadRegistrations,
            loadEvents,
            pagination.page,
            loadMemberDetails,
            strings,
        ]);

        var handleAttachChild = useCallback(function (guardian, child) {
            if (!guardian || !guardian.id || !child || !child.id) {
                return Promise.reject(new Error(getString(strings, 'error', 'Une erreur est survenue.')));
            }

            return api.updateMember(child.id, {
                guardianId: guardian.id,
            })
                .then(function (result) {
                    var successMessage = result && result.message
                        ? result.message
                        : getString(strings, 'guardianChildAttached', 'Enfant rattaché avec succès.');
                    showSuccess(successMessage);
                    loadMemberDetails(guardian.id);
                    loadMembers(membersPagination.page);
                    return result;
                })
                .catch(function (err) {
                    showError(err && err.message ? err.message : getString(strings, 'error', 'Une erreur est survenue.'));
                    throw err;
                });
        }, [api, showSuccess, showError, loadMemberDetails, loadMembers, membersPagination.page, strings]);

        // Rechercher membres
        var handleSearchMembers = useCallback(function (params) {
            return api.searchMembers(params);
        }, [api]);

        // Onglets
        var registrationsCount = Array.isArray(registrations) ? registrations.length : 0;
        var registrationsTabLabel = getString(strings, 'tabRegistrations', 'Inscriptions');
        var descriptionDirty = descriptionState.draft !== descriptionState.base;
        var regDocDirty = regDocState.draft !== regDocState.base;

        var occurrenceTab = createOccurrenceTab
            ? createOccurrenceTab(strings)
            : {
                key: OCCURRENCE_TAB_KEY,
                label: getString(strings, 'tabOccurrenceEncoder', 'Occurence de date'),
            };
        var occurrenceTabKey = occurrenceTab.key || OCCURRENCE_TAB_KEY;
        var shouldShowOccurrenceTab = !!selectedEvent;

        var tabIcons = {
            registrations: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
            attendance: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M9 12l2 2 4-4"></path><line x1="7" y1="8" x2="17" y2="8"></line></svg>',
            description: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="13" y2="17"></line></svg>',
            regdoc: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>',
            details: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><circle cx="12" cy="8" r="1"></circle></svg>',
            occurrences: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
            editor: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5l4 4-11 11H5.5v-6.5z"></path></svg>',
            photos: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
        };

        // Compteur de photos en attente pour l'événement
        var eventPendingPhotosCount = useMemo(function () {
            return eventPhotos.filter(function (p) { return p.status === 'pending'; }).length;
        }, [eventPhotos]);

        var tabs = [
            { 
                key: 'registrations', 
                label: registrationsTabLabel,
                badge: registrationsCount,
                icon: tabIcons.registrations,
            },
            { 
                key: 'attendance', 
                label: getString(strings, 'tabAttendance', 'Présence'),
                icon: tabIcons.attendance,
            },
            { 
                key: 'description', 
                label: getString(strings, 'tabDescription', 'Description'),
                icon: tabIcons.description,
            },
            {
                key: 'regdoc',
                label: getString(strings, 'tabRegDoc', 'Document'),
                icon: tabIcons.regdoc,
            },
            {
                key: 'photos',
                label: getString(strings, 'tabPhotos', 'Photos'),
                icon: tabIcons.photos,
                badge: eventPendingPhotosCount > 0 ? eventPendingPhotosCount : null,
                badgeClass: eventPendingPhotosCount > 0 ? 'mj-regmgr-tab__badge--warning' : '',
            },
            { 
                key: 'details', 
                label: getString(strings, 'tabDetails', 'Détails'),
                icon: tabIcons.details,
            },
        ];

        if (shouldShowOccurrenceTab) {
            var occurrenceTabWithIcon = Object.assign({}, occurrenceTab);
            if (!occurrenceTabWithIcon.icon) {
                occurrenceTabWithIcon.icon = tabIcons.occurrences;
            }
            tabs.push(occurrenceTabWithIcon);
        }

        if (EventEditor) {
            tabs.push({
                key: 'editor',
                label: getString(strings, 'tabEditor', 'Éditer'),
                icon: tabIcons.editor,
            });
        }

        var eventHasFreeParticipation = false;
        if (eventDetails && typeof eventDetails.freeParticipation !== 'undefined') {
            eventHasFreeParticipation = !!eventDetails.freeParticipation;
        } else if (selectedEvent && typeof selectedEvent.freeParticipation !== 'undefined') {
            eventHasFreeParticipation = !!selectedEvent.freeParticipation;
        }

        if (eventHasFreeParticipation) {
            tabs = tabs.filter(function (tab) { return tab && tab.key !== 'registrations'; });
        } else {
            tabs = ensureRegistrationsTab(tabs, {
                label: registrationsTabLabel,
                badge: registrationsCount,
                icon: tabIcons.registrations,
            });
        }

        var occurrenceSelectionMode = 'member_choice';
        if (eventDetails && typeof eventDetails.occurrenceSelectionMode === 'string' && eventDetails.occurrenceSelectionMode !== '') {
            occurrenceSelectionMode = eventDetails.occurrenceSelectionMode;
        } else if (selectedEvent && typeof selectedEvent.occurrenceSelectionMode === 'string' && selectedEvent.occurrenceSelectionMode !== '') {
            occurrenceSelectionMode = selectedEvent.occurrenceSelectionMode;
        }

        var allowOccurrenceSelection = occurrenceSelectionMode !== 'all_occurrences';
        var eventRequiresPayment = eventDetails && eventDetails.prix > 0 && !eventDetails.freeParticipation;
        var eventRequiresValidation = true;
        if (eventDetails && typeof eventDetails.requiresValidation !== 'undefined') {
            eventRequiresValidation = !!eventDetails.requiresValidation;
        } else if (selectedEvent && typeof selectedEvent.requiresValidation !== 'undefined') {
            eventRequiresValidation = !!selectedEvent.requiresValidation;
        }

        var editorHeadingSource = eventEditorSummary || eventDetails || selectedEvent || null;
        var editorHeadingTitle = '';
        if (editorHeadingSource && typeof editorHeadingSource.title === 'string' && editorHeadingSource.title !== '') {
            editorHeadingTitle = editorHeadingSource.title;
        } else if (selectedEvent && typeof selectedEvent.title === 'string' && selectedEvent.title !== '') {
            editorHeadingTitle = selectedEvent.title;
        }
        if (!editorHeadingTitle) {
            editorHeadingTitle = getString(strings, 'editorTitleFallback', "Modifier l'événement");
        }
        var editorHeadingMeta = '';
        var statusSource = editorHeadingSource || selectedEvent || null;
        if (statusSource) {
            var sourceId = statusSource.id || (selectedEvent && selectedEvent.id);
            var statusLabel = typeof statusSource.statusLabel === 'string' && statusSource.statusLabel !== ''
                ? statusSource.statusLabel
                : (typeof statusSource.status === 'string' ? statusSource.status : '');
            if (sourceId) {
                editorHeadingMeta = '#' + sourceId + (statusLabel ? ' · ' + statusLabel : '');
            } else if (statusLabel) {
                editorHeadingMeta = statusLabel;
            }
        }

        // Get MemberDetailPanel component
        var MembersComps = window.MjRegMgrMembers;
        var MemberDetailPanel = MembersComps ? MembersComps.MemberDetailPanel : null;

        // Classes pour la navigation mobile
        var layoutClasses = classNames('mj-regmgr__layout', {
            'mj-regmgr__layout--mobile-details': mobileShowDetails && (selectedEvent || selectedMember),
        });

        // Afficher un loading global au premier chargement
        if (eventsLoading && !initialEventLoaded && events.length === 0 && sidebarMode === 'events') {
            return h('div', { class: 'mj-regmgr' }, [
                h('div', { class: 'mj-regmgr__initial-loading' }, [
                    h('div', { class: 'mj-regmgr-loading__spinner' }),
                    h('p', null, getString(strings, 'loading', 'Chargement...')),
                ]),
            ]);
        }

        return h('div', { class: 'mj-regmgr' }, [
            h('div', { class: layoutClasses }, [
                // Sidebar
                h(EventsSidebar, {
                    // Mode
                    sidebarMode: sidebarMode,
                    onModeChange: handleSidebarModeChange,

                    // Events props
                    events: events,
                    loading: eventsLoading,
                    selectedEventId: selectedEvent ? selectedEvent.id : null,
                    onSelectEvent: handleSelectEvent,
                    filter: filter,
                    onFilterChange: setFilterSafe,
                    search: search,
                    onSearchChange: setSearch,
                    onLoadMore: function () { loadEvents(pagination.page + 1); },
                    hasMore: pagination.page < pagination.totalPages,
                    loadingMore: false,
                    createEventUrl: config.adminAddEventUrl || '',
                    canCreateEvent: !!config.canCreateEvent,
                    onCreateEvent: openCreateEventModal,
                    createEventLoading: creatingEvent,

                    // Members props
                    members: membersList,
                    membersLoading: membersLoading,
                    selectedMemberId: selectedMember ? selectedMember.id : null,
                    onSelectMember: handleSelectMember,
                    memberFilter: memberFilter,
                    onMemberFilterChange: setMemberFilter,
                    memberSearch: memberSearch,
                    onMemberSearchChange: setMemberSearch,
                    memberSort: memberSort,
                    onMemberSortChange: setMemberSort,
                    onLoadMoreMembers: function () { loadMembers(membersPagination.page + 1); },
                    hasMoreMembers: membersPagination.page < membersPagination.totalPages,
                    membersLoadingMore: false,
                    canCreateMember: !!config.allowCreateMember,
                    onCreateMember: config.allowCreateMember ? createMemberModal.open : null,

                    strings: strings,
                    showAllEvents: allowAllFilter,
                    title: config.title || 'Événements',
                }),

                // Zone principale
                h('main', { class: 'mj-regmgr__main' }, [
                    // Bouton retour mobile (événements)
                    sidebarMode === 'events' && selectedEvent && h('button', {
                        type: 'button',
                        class: 'mj-regmgr__back-btn',
                        onClick: function (e) { handleBackToEvents(e); },
                    }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.5 }, [
                            h('polyline', { points: '15 18 9 12 15 6' }),
                        ]),
                        h('span', null, getString(strings, 'backToEvents', 'Retour aux événements')),
                    ]),

                    // Bouton retour mobile (membres)
                    sidebarMode === 'members' && selectedMember && h('button', {
                        type: 'button',
                        class: 'mj-regmgr__back-btn',
                        onClick: function (e) { handleBackToEvents(e); },
                    }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.5 }, [
                            h('polyline', { points: '15 18 9 12 15 6' }),
                        ]),
                        h('span', null, getString(strings, 'backToMembers', 'Retour à la liste')),
                    ]),

                    // Empty state pour événements
                    sidebarMode === 'events' && !selectedEvent && h('div', { class: 'mj-regmgr__empty-state' }, [
                        h('div', { class: 'mj-regmgr__empty-icon' }, [
                            h('svg', { width: 64, height: 64, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.5 }, [
                                h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                                h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                                h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                                h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                            ]),
                        ]),
                        h('h2', null, 'Sélectionnez un événement'),
                        h('p', null, 'Choisissez un événement dans la liste pour gérer les inscriptions et la présence.'),
                    ]),

                    // Empty state pour membres
                    sidebarMode === 'members' && !selectedMember && h('div', { class: 'mj-regmgr__empty-state' }, [
                        h('div', { class: 'mj-regmgr__empty-icon' }, [
                            h('svg', { width: 64, height: 64, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.5 }, [
                                h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                                h('circle', { cx: 9, cy: 7, r: 4 }),
                                h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                                h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                            ]),
                        ]),
                        h('h2', null, 'Sélectionnez un membre'),
                        h('p', null, 'Choisissez un membre dans la liste pour voir ses informations et ses notes.'),
                    ]),

                    // Contenu événement sélectionné
                    sidebarMode === 'events' && selectedEvent && h('div', { class: 'mj-regmgr__content' }, [
                        editorHeadingTitle && h('div', { class: 'mj-regmgr__event-heading' }, [
                            h('h2', { class: 'mj-regmgr__event-heading-title' }, editorHeadingTitle),
                            editorHeadingMeta && h('p', { class: 'mj-regmgr__event-heading-meta' }, editorHeadingMeta),
                        ]),
                        // Onglets
                        TabsComponent && h(TabsComponent, {
                            tabs: tabs,
                            fallbackRegistrationsTab: {
                                key: 'registrations',
                                label: registrationsTabLabel,
                                badge: registrationsCount,
                            },
                            ensureRegistrationsTab: !eventHasFreeParticipation,
                            activeTab: activeTab,
                            onChange: setActiveTab,
                        }),

                        // Contenu de l'onglet
                        h('div', { class: 'mj-regmgr__tab-content' }, [
                            activeTab === 'registrations' && h(RegistrationsList, {
                                registrations: registrations,
                                loading: registrationsLoading,
                                onAddParticipant: function () { addParticipantModal.open(); },
                                onValidate: eventRequiresValidation ? handleValidateRegistration : null,
                                onCancel: handleCancelRegistration,
                                onDelete: handleDeleteRegistration,
                                onValidatePayment: handleValidatePayment,
                                onCancelPayment: handleCancelPayment,
                                onShowQR: handleShowQR,
                                onShowNotes: handleShowNotes,
                                onChangeOccurrences: handleChangeOccurrences,
                                onViewMember: handleViewMemberFromRegistration,
                                onDownloadDoc: (regDocState.draft || (eventDetails && eventDetails.registrationDocument)) ? handleDownloadMemberDoc : null,
                                strings: strings,
                                config: config,
                                eventRequiresPayment: eventRequiresPayment,
                                allowOccurrenceSelection: allowOccurrenceSelection,
                                eventRequiresValidation: eventRequiresValidation,
                            }),

                            activeTab === 'attendance' && h(AttendanceSheet, {
                                event: eventDetails,
                                registrations: registrations,
                                attendanceMembers: attendanceMembers,
                                occurrences: eventDetails ? eventDetails.occurrences : [],
                                attendanceMap: attendanceMap,
                                onUpdateAttendance: handleUpdateAttendance,
                                onBulkAttendance: handleBulkAttendance,
                                onValidatePayment: handleValidatePayment,
                                onValidateRegistration: eventRequiresValidation ? handleValidateRegistration : null,
                                onChangeOccurrences: allowOccurrenceSelection ? handleChangeOccurrences : null,
                                onViewMember: handleViewMemberFromRegistration,
                                strings: strings,
                                loading: registrationsLoading,
                                loadingMembers: loadingMembers,
                                eventRequiresValidation: eventRequiresValidation,
                            }),

                            activeTab === 'description' && h('div', { class: 'mj-regmgr-description-tab' }, [
                                (!eventDetails || registrationsLoading)
                                    ? h('div', { class: 'mj-regmgr-loading' }, [
                                        h('div', { class: 'mj-regmgr-loading__spinner' }),
                                        h('p', { class: 'mj-regmgr-loading__text' }, getString(strings, 'loading', 'Chargement des détails...')),
                                    ])
                                    : h(Fragment, null, [
                                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                                            h('label', { class: 'mj-regmgr-form-label', htmlFor: descriptionFieldId }, getString(strings, 'descriptionLabel', "Description de l'événement")),
                                            h(DescriptionEditorField, {
                                                id: descriptionFieldId,
                                                className: 'mj-regmgr-richtext',
                                                rows: 12,
                                                value: descriptionState.draft,
                                                onChange: handleDescriptionChange,
                                                placeholder: getString(strings, 'descriptionPlaceholder', 'Décrivez le contenu de cet événement...'),
                                            }),
                                        ]),
                                        h('div', { class: 'mj-regmgr-description-actions' }, [
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--primary',
                                                disabled: descriptionSaving || !descriptionDirty,
                                                onClick: function () { handleSaveDescription(); },
                                            }, descriptionSaving
                                                ? getString(strings, 'descriptionSaving', 'Enregistrement de la description...')
                                                : getString(strings, 'descriptionSaveButton', 'Enregistrer la description')),
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--secondary',
                                                disabled: descriptionSaving || !descriptionDirty,
                                                onClick: function () { handleResetDescription(); },
                                            }, getString(strings, 'descriptionResetButton', 'Annuler les modifications')),
                                            descriptionDirty && !descriptionSaving && h('span', { class: 'mj-regmgr-description-status' }, getString(strings, 'descriptionUnsavedChanges', 'Modifications non enregistrées')),
                                            descriptionError && h('p', { class: 'mj-regmgr-description-error' }, descriptionError),
                                        ]),
                                    ]),
                            ]),

                            activeTab === 'regdoc' && h('div', { class: 'mj-regmgr-regdoc-tab' }, [
                                (!eventDetails || registrationsLoading)
                                    ? h('div', { class: 'mj-regmgr-loading' }, [
                                        h('div', { class: 'mj-regmgr-loading__spinner' }),
                                        h('p', { class: 'mj-regmgr-loading__text' }, getString(strings, 'loading', 'Chargement des détails...')),
                                    ])
                                    : h(Fragment, null, [
                                        // Variables disponibles
                                        h('div', { class: 'mj-regmgr-regdoc-variables' }, [
                                            h('h4', { class: 'mj-regmgr-regdoc-variables__title' }, getString(strings, 'regDocVariablesTitle', 'Variables disponibles')),
                                            h('p', { class: 'mj-regmgr-regdoc-variables__hint' }, getString(strings, 'regDocVariablesHint', 'Utilisez ces variables dans le contenu du document. Elles seront remplacées par les valeurs réelles lors du téléchargement.')),
                                            h('div', { class: 'mj-regmgr-regdoc-variables__list' }, [
                                                h('div', { class: 'mj-regmgr-regdoc-variables__group' }, [
                                                    h('h5', null, 'Événement'),
                                                    h('ul', null, [
                                                        h('li', null, [h('code', null, '[event_name]'), ' - Nom de l\'événement']),
                                                        h('li', null, [h('code', null, '[event_type]'), ' - Type d\'événement']),
                                                        h('li', null, [h('code', null, '[event_status]'), ' - Statut']),
                                                        h('li', null, [h('code', null, '[event_date_start]'), ' - Date de début']),
                                                        h('li', null, [h('code', null, '[event_date_end]'), ' - Date de fin']),
                                                        h('li', null, [h('code', null, '[event_date_deadline]'), ' - Date limite d\'inscription']),
                                                        h('li', null, [h('code', null, '[event_price]'), ' - Tarif']),
                                                        h('li', null, [h('code', null, '[event_location]'), ' - Lieu']),
                                                        h('li', null, [h('code', null, '[event_location_address]'), ' - Adresse du lieu']),
                                                        h('li', null, [h('code', null, '[event_age_min]'), ' - Âge minimum']),
                                                        h('li', null, [h('code', null, '[event_age_max]'), ' - Âge maximum']),
                                                        h('li', null, [h('code', null, '[event_capacity]'), ' - Capacité totale']),
                                                    ]),
                                                ]),
                                                h('div', { class: 'mj-regmgr-regdoc-variables__group' }, [
                                                    h('h5', null, 'Membre'),
                                                    h('ul', null, [
                                                        h('li', null, [h('code', null, '[member_name]'), ' - Nom complet']),
                                                        h('li', null, [h('code', null, '[member_first_name]'), ' - Prénom']),
                                                        h('li', null, [h('code', null, '[member_last_name]'), ' - Nom de famille']),
                                                        h('li', null, [h('code', null, '[member_email]'), ' - Email']),
                                                        h('li', null, [h('code', null, '[member_phone]'), ' - Téléphone']),
                                                        h('li', null, [h('code', null, '[member_birth_date]'), ' - Date de naissance']),
                                                        h('li', null, [h('code', null, '[member_address]'), ' - Adresse complète']),
                                                        h('li', null, [h('code', null, '[member_address_line]'), ' - Rue']),
                                                        h('li', null, [h('code', null, '[member_postal_code]'), ' - Code postal']),
                                                        h('li', null, [h('code', null, '[member_city]'), ' - Ville']),
                                                    ]),
                                                ]),
                                                h('div', { class: 'mj-regmgr-regdoc-variables__group' }, [
                                                    h('h5', null, 'Tuteur'),
                                                    h('ul', null, [
                                                        h('li', null, [h('code', null, '[guardian_name]'), ' - Nom complet']),
                                                        h('li', null, [h('code', null, '[guardian_first_name]'), ' - Prénom']),
                                                        h('li', null, [h('code', null, '[guardian_last_name]'), ' - Nom de famille']),
                                                        h('li', null, [h('code', null, '[guardian_email]'), ' - Email']),
                                                        h('li', null, [h('code', null, '[guardian_phone]'), ' - Téléphone']),
                                                        h('li', null, [h('code', null, '[guardian_address]'), ' - Adresse complète']),
                                                        h('li', null, [h('code', null, '[guardian_address_line]'), ' - Rue']),
                                                        h('li', null, [h('code', null, '[guardian_postal_code]'), ' - Code postal']),
                                                        h('li', null, [h('code', null, '[guardian_city]'), ' - Ville']),
                                                    ]),
                                                ]),
                                                h('div', { class: 'mj-regmgr-regdoc-variables__group' }, [
                                                    h('h5', null, 'Site'),
                                                    h('ul', null, [
                                                        h('li', null, [h('code', null, '[site_name]'), ' - Nom du site']),
                                                        h('li', null, [h('code', null, '[site_url]'), ' - URL du site']),
                                                        h('li', null, [h('code', null, '[current_date]'), ' - Date actuelle']),
                                                        h('li', null, [h('code', null, '[current_year]'), ' - Année actuelle']),
                                                    ]),
                                                ]),
                                            ]),
                                        ]),
                                        // Éditeur du document
                                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                                            h('label', { class: 'mj-regmgr-form-label', htmlFor: regDocFieldId }, getString(strings, 'regDocLabel', 'Contenu du document d\'inscription')),
                                            h(DescriptionEditorField, {
                                                id: regDocFieldId,
                                                className: 'mj-regmgr-richtext',
                                                rows: 12,
                                                value: regDocState.draft,
                                                onChange: handleRegDocChange,
                                                placeholder: getString(strings, 'regDocPlaceholder', 'Rédigez le document d\'inscription avec les informations à transmettre aux participants...'),
                                            }),
                                        ]),
                                        // Actions
                                        h('div', { class: 'mj-regmgr-regdoc-actions' }, [
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--primary',
                                                disabled: regDocSaving || !regDocDirty,
                                                onClick: function () { handleSaveRegDoc(); },
                                            }, regDocSaving
                                                ? getString(strings, 'regDocSaving', 'Enregistrement...')
                                                : getString(strings, 'regDocSaveButton', 'Enregistrer le document')),
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--secondary',
                                                disabled: regDocSaving || !regDocDirty,
                                                onClick: function () { handleResetRegDoc(); },
                                            }, getString(strings, 'regDocResetButton', 'Annuler les modifications')),
                                            regDocState.draft && h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--danger-outline',
                                                disabled: regDocSaving,
                                                onClick: function () {
                                                    if (window.confirm(getString(strings, 'regDocDisableConfirm', 'Êtes-vous sûr de vouloir désactiver le document d\'inscription ? Le contenu sera supprimé.'))) {
                                                        setRegDocState(function (prev) {
                                                            return Object.assign({}, prev, { draft: '' });
                                                        });
                                                        handleSaveRegDoc('');
                                                    }
                                                },
                                            }, getString(strings, 'regDocDisableButton', 'Désactiver le document')),
                                            regDocDirty && !regDocSaving && h('span', { class: 'mj-regmgr-regdoc-status' }, getString(strings, 'regDocUnsavedChanges', 'Modifications non enregistrées')),
                                            regDocError && h('p', { class: 'mj-regmgr-regdoc-error' }, regDocError),
                                        ]),
                                    ]),
                            ]),

                            activeTab === 'photos' && h('div', { class: 'mj-regmgr-photos-tab' }, [
                                // Header avec bouton d'upload
                                h('div', { class: 'mj-regmgr-event-photos__header' }, [
                                    h('input', {
                                        ref: eventPhotoInputRef,
                                        type: 'file',
                                        accept: 'image/*',
                                        style: 'display: none;',
                                        onChange: function (e) {
                                            var file = e.target.files && e.target.files[0];
                                            if (file) {
                                                handleUploadEventPhoto(file);
                                            }
                                        },
                                    }),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--primary',
                                        onClick: function () {
                                            if (eventPhotoInputRef.current) {
                                                eventPhotoInputRef.current.click();
                                            }
                                        },
                                        disabled: eventPhotoUploading,
                                    }, [
                                        eventPhotoUploading
                                            ? getString(strings, 'uploading', 'Envoi en cours...')
                                            : h(Fragment, null, [
                                                h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, style: 'margin-right: 6px; vertical-align: middle;' }, [
                                                    h('path', { d: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4' }),
                                                    h('polyline', { points: '17 8 12 3 7 8' }),
                                                    h('line', { x1: 12, y1: 3, x2: 12, y2: 15 }),
                                                ]),
                                                getString(strings, 'addPhoto', 'Ajouter une photo'),
                                            ]),
                                    ]),
                                ]),
                                eventPhotosLoading
                                    ? h('div', { class: 'mj-regmgr-loading' }, [
                                        h('div', { class: 'mj-regmgr-loading__spinner' }),
                                        h('p', { class: 'mj-regmgr-loading__text' }, getString(strings, 'loading', 'Chargement des photos...')),
                                    ])
                                    : eventPhotos.length === 0
                                        ? h('div', { class: 'mj-regmgr-empty-state' }, [
                                            h('div', { class: 'mj-regmgr-empty-state__icon', dangerouslySetInnerHTML: { __html: tabIcons.photos } }),
                                            h('h3', { class: 'mj-regmgr-empty-state__title' }, getString(strings, 'eventNoPhotos', 'Aucune photo')),
                                            h('p', { class: 'mj-regmgr-empty-state__text' }, getString(strings, 'eventNoPhotosText', 'Les participants n\'ont pas encore partagé de photos pour cet événement.')),
                                        ])
                                        : h('div', { class: 'mj-regmgr-event-photos' }, [
                                            eventPendingPhotosCount > 0 && h('div', { class: 'mj-regmgr-event-photos__notice mj-regmgr-event-photos__notice--warning' }, [
                                                h('span', null, eventPendingPhotosCount + ' photo' + (eventPendingPhotosCount > 1 ? 's' : '') + ' en attente de validation'),
                                            ]),
                                            h('div', { class: 'mj-regmgr-event-photos__grid' },
                                                eventPhotos.map(function (photo) {
                                                    var isPending = photo.status === 'pending';
                                                    var isUpdating = eventPhotoUpdating === photo.id;
                                                    var photoStatusClasses = {
                                                        pending: 'mj-regmgr-badge--warning',
                                                        approved: 'mj-regmgr-badge--success',
                                                        rejected: 'mj-regmgr-badge--error',
                                                    };
                                                    return h('div', {
                                                        key: photo.id,
                                                        class: classNames('mj-regmgr-event-photo', {
                                                            'mj-regmgr-event-photo--pending': isPending,
                                                        }),
                                                    }, [
                                                        h('figure', { class: 'mj-regmgr-event-photo__figure' }, [
                                                            h('a', {
                                                                href: photo.fullUrl || photo.thumbnailUrl,
                                                                target: '_blank',
                                                                rel: 'noreferrer',
                                                                class: 'mj-regmgr-event-photo__link',
                                                            }, [
                                                                h('img', { src: photo.thumbnailUrl, alt: photo.caption || '', loading: 'lazy' }),
                                                            ]),
                                                            h('span', {
                                                                class: classNames('mj-regmgr-badge mj-regmgr-event-photo__status-badge', photoStatusClasses[photo.status] || ''),
                                                            }, photo.statusLabel),
                                                        ]),
                                                        h('div', { class: 'mj-regmgr-event-photo__body' }, [
                                                            photo.memberName && h('a', {
                                                                href: '#',
                                                                class: 'mj-regmgr-event-photo__member',
                                                                onClick: function (e) {
                                                                    e.preventDefault();
                                                                    if (photo.memberId) {
                                                                        handleViewMemberFromRegistration({ id: photo.memberId });
                                                                    }
                                                                },
                                                                title: 'Voir le membre',
                                                            }, [
                                                                photo.memberAvatar && h('img', {
                                                                    src: photo.memberAvatar,
                                                                    alt: '',
                                                                    class: 'mj-regmgr-event-photo__member-avatar',
                                                                }),
                                                                h('span', null, photo.memberName),
                                                            ]),
                                                            photo.caption && h('span', { class: 'mj-regmgr-event-photo__caption-text' }, photo.caption),
                                                            h('div', { class: 'mj-regmgr-event-photo__actions' }, [
                                                                isPending && h('button', {
                                                                    type: 'button',
                                                                    class: 'mj-btn mj-btn--success mj-btn--small',
                                                                    onClick: function () {
                                                                        setEventPhotoUpdating(photo.id);
                                                                        api.updateMemberPhoto(photo.id, photo.memberId, { status: 'approved' })
                                                                            .then(function (result) {
                                                                                setEventPhotos(function (prev) {
                                                                                    return prev.map(function (p) {
                                                                                        if (p.id === photo.id) {
                                                                                            return Object.assign({}, p, {
                                                                                                status: 'approved',
                                                                                                statusLabel: result.photo && result.photo.statusLabel ? result.photo.statusLabel : 'Validée',
                                                                                            });
                                                                                        }
                                                                                        return p;
                                                                                    });
                                                                                });
                                                                                showSuccess('Photo validée');
                                                                                setEventPhotoUpdating(null);
                                                                            })
                                                                            .catch(function (err) {
                                                                                showError(err.message || 'Erreur lors de la validation');
                                                                                setEventPhotoUpdating(null);
                                                                            });
                                                                    },
                                                                    disabled: isUpdating,
                                                                }, isUpdating ? 'Validation...' : 'Valider'),
                                                                h('button', {
                                                                    type: 'button',
                                                                    class: 'mj-btn mj-btn--icon mj-btn--ghost mj-btn--small mj-btn--danger',
                                                                    onClick: function () {
                                                                        if (!confirm('Supprimer cette photo ?')) {
                                                                            return;
                                                                        }
                                                                        setEventPhotoUpdating(photo.id);
                                                                        api.deleteMemberPhoto(photo.id)
                                                                            .then(function () {
                                                                                setEventPhotos(function (prev) {
                                                                                    return prev.filter(function (p) { return p.id !== photo.id; });
                                                                                });
                                                                                showSuccess('Photo supprimée');
                                                                                setEventPhotoUpdating(null);
                                                                            })
                                                                            .catch(function (err) {
                                                                                showError(err.message || 'Erreur lors de la suppression');
                                                                                setEventPhotoUpdating(null);
                                                                            });
                                                                    },
                                                                    title: 'Supprimer',
                                                                    disabled: isUpdating,
                                                                }, [
                                                                    h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                                        h('polyline', { points: '3 6 5 6 21 6' }),
                                                                        h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }),
                                                                    ]),
                                                                ]),
                                                            ]),
                                                        ]),
                                                    ]);
                                                })
                                            ),
                                        ]),
                            ]),

                            activeTab === 'details' && h(EventDetailPanel, {
                                event: eventDetails,
                                registrations: registrations,
                                attendanceMap: attendanceMap,
                                occurrences: eventDetails ? eventDetails.occurrences : [],
                                strings: strings,
                                config: config,
                                loading: registrationsLoading || !eventDetails,
                                onDeleteEvent: handleDeleteEvent,
                                canDeleteEvent: config.canDeleteEvent,
                                deletingEvent: deletingEvent,
                            }),

                            activeTab === occurrenceTabKey && (
                                selectedEvent && eventDetails
                                    ? h(OccurrenceEncoderPanel, {
                                        key: selectedEvent ? (occurrenceTabKey + '-' + selectedEvent.id) : occurrenceTabKey,
                                        event: eventDetails,
                                        occurrences: Array.isArray(eventDetails.occurrences) ? eventDetails.occurrences : [],
                                        strings: strings,
                                        locale: config.locale || 'fr',
                                        onPersistOccurrences: handlePersistEventOccurrences,
                                    })
                                    : h('div', { class: 'mj-regmgr__tab-placeholder' },
                                        getString(strings, 'occurrenceNoEventSelected', 'Sélectionnez un événement pour gérer ses occurrences.')
                                    )
                            ),

                            activeTab === 'editor' && EventEditor && h(EventEditor, {
                                data: eventEditorData,
                                eventSummary: eventEditorSummary || selectedEvent,
                                loading: eventEditorLoading,
                                saving: eventEditorSaving,
                                errors: eventEditorErrors,
                                onSubmit: handleSubmitEventEditor,
                                onReload: handleReloadEventEditor,
                                strings: strings,
                                canManageLocations: !!config.canManageLocations,
                                onManageLocation: handleRequestLocationModal,
                            }),
                        ]),
                    ]),

                    // Contenu membre sélectionné
                    sidebarMode === 'members' && selectedMember && MemberDetailPanel && h('div', { class: 'mj-regmgr__content mj-regmgr__content--member' }, [
                        h(MemberDetailPanel, {
                            member: memberDetails,
                            loading: !memberDetails,
                            strings: strings,
                            config: memberDetailConfig,
                            notes: memberNotes,
                            registrations: memberRegistrations,
                            initialTab: urlMemberId ? urlTab : null,
                            onTabChange: function (tab) {
                                updateUrlParams({ tab: tab });
                            },
                            onSaveNote: handleSaveMemberNote,
                            onDeleteNote: handleDeleteMemberNoteFromPanel,
                            onUpdateMember: handleUpdateMember,
                            onPayMembershipOnline: handlePayMembershipOnline,
                            onMarkMembershipPaid: handleMarkMembershipPaid,
                            onManageAccount: config.canManageAccounts ? handleOpenAccountModal : null,
                            onUpdateIdea: handleUpdateMemberIdea,
                            onDeleteIdea: handleDeleteMemberIdea,
                            onUpdatePhoto: handleUpdateMemberPhoto,
                            onDeletePhoto: handleDeleteMemberPhoto,
                            onCaptureAvatar: handleCaptureMemberAvatar,
                            onUpdateAvatar: handleUpdateMemberAvatar,
                            onRemoveAvatar: handleRemoveMemberAvatar,
                            onDeleteRegistration: handleDeleteMemberRegistration,
                            onUpdateRegistrationOccurrences: handleUpdateRegistrationOccurrences,
                            onOpenMember: handleViewMemberFromRegistration,
                            pendingEditRequest: pendingMemberEdit,
                            onPendingEditHandled: handleConsumePendingMemberEdit,
                            onDeleteMessage: handleDeleteMemberMessage,
                            onDeleteTestimonial: handleDeleteMemberTestimonial,
                            onUpdateTestimonialStatus: handleUpdateMemberTestimonialStatus,
                            onDeleteMember: handleDeleteMember,
                            onSyncBadgeCriteria: handleSyncMemberBadge,
                            onAdjustXp: handleAdjustMemberXp,
                            onToggleTrophy: handleToggleMemberTrophy,
                            onSelectEvent: handleViewEventById,
                        }),
                    ]),
                ]),
            ]),

            // Modals
            h(CreateEventModal, {
                isOpen: createEventModal.isOpen,
                onClose: handleCloseCreateEventModal,
                onCreate: handleCreateEvent,
                strings: strings,
                submitting: creatingEvent,
            }),

            h(AddParticipantModal, {
                isOpen: addParticipantModal.isOpen,
                onClose: addParticipantModal.close,
                onAdd: handleAddParticipants,
                event: eventDetails,
                searchMembers: handleSearchMembers,
                strings: strings,
                config: config,
                onCreateMember: function () {
                    addParticipantModal.close();
                    createMemberModal.open();
                },
            }),

            h(CreateMemberModal, {
                isOpen: createMemberModal.isOpen,
                onClose: createMemberModal.close,
                onCreate: handleCreateMember,
                strings: strings,
                config: config,
                context: createMemberModal.data ? createMemberModal.data.context : null,
            }),

            h(AttachChildModal, {
                isOpen: attachChildModal.isOpen,
                onClose: attachChildModal.close,
                guardian: attachChildModal.data ? attachChildModal.data.guardian : null,
                strings: strings,
                searchMembers: handleSearchMembers,
                onAttach: handleAttachChild,
                config: config,
            }),

            h(MemberNotesModal, {
                isOpen: notesModal.isOpen,
                onClose: notesModal.close,
                member: notesModal.data ? notesModal.data.member : null,
                notes: notes,
                onSave: handleSaveNote,
                onDelete: handleDeleteNote,
                strings: strings,
                loading: notesLoading,
                currentMemberId: config.memberId,
            }),

            MemberAccountModal && config.canManageAccounts && h(MemberAccountModal, {
                isOpen: accountModal.isOpen,
                onClose: accountModal.close,
                member: (function () {
                    if (accountModal.data && accountModal.data.member) {
                        if (memberDetails && accountModal.data.member.id === memberDetails.id) {
                            return memberDetails;
                        }
                        return accountModal.data.member;
                    }
                    return memberDetails;
                })(),
                roles: config.accountRoles || {},
                onSubmit: handleLinkMemberAccount,
                onResetPassword: handleResetMemberPassword,
                strings: strings,
            }),

            h(QRCodeModal, {
                isOpen: qrModal.isOpen,
                onClose: qrModal.close,
                qrData: qrData,
                strings: strings,
                loading: qrLoading,
            }),

            h(OccurrencesModal, {
                isOpen: occurrencesModal.isOpen,
                onClose: occurrencesModal.close,
                registration: occurrencesModal.data ? occurrencesModal.data.registration : null,
                occurrences: eventDetails ? eventDetails.occurrences : [],
                selectedOccurrences: occurrencesModal.data ? occurrencesModal.data.selectedOccurrences : [],
                onSave: handleSaveOccurrences,
                strings: strings,
                loading: false,
            }),

            h(LocationModal, {
                isOpen: locationModal.isOpen,
                onClose: handleCloseLocationModal,
                onSubmit: handleSubmitLocationModal,
                loading: locationModalState.loading,
                saving: locationModalState.saving,
                location: locationModalState.location,
                mode: locationModalState.mode,
                strings: strings,
            }),

            AvatarCaptureModal && h(AvatarCaptureModal, {
                isOpen: avatarCaptureModal.isOpen,
                onClose: avatarCaptureModal.close,
                member: (function () {
                    if (!avatarCaptureModal.data || !avatarCaptureModal.data.member) {
                        return null;
                    }
                    if (memberDetails && avatarCaptureModal.data.member.id === memberDetails.id) {
                        return memberDetails;
                    }
                    return avatarCaptureModal.data.member;
                })(),
                strings: strings,
                onCapture: handleConfirmAvatarCapture,
            }),

            // Toasts
            h(Toasts, {
                toasts: toasts,
                onRemove: removeToast,
            }),
        ]);
    }

    // ============================================
    // URL PARAMETERS
    // ============================================

    /**
     * Parse les paramètres URL pour le gestionnaire
     * Paramètres supportés:
     * - event=ID : ouvre sur un événement spécifique
     * - member=ID : ouvre sur un membre spécifique (active le mode membres)
     * - tab=KEY : ouvre sur un onglet spécifique (registrations, attendance, description, editor, photos, details, occurrence-encoder)
     */
    function parseUrlParams() {
        var params = {
            eventId: null,
            memberId: null,
            tab: null,
        };

        try {
            var searchParams = new URLSearchParams(window.location.search);

            var eventParam = searchParams.get('event');
            if (eventParam) {
                var eventId = parseInt(eventParam, 10);
                if (!isNaN(eventId) && eventId > 0) {
                    params.eventId = eventId;
                }
            }

            var memberParam = searchParams.get('member');
            if (memberParam) {
                var memberId = parseInt(memberParam, 10);
                if (!isNaN(memberId) && memberId > 0) {
                    params.memberId = memberId;
                }
            }

            var tabParam = searchParams.get('tab');
            if (tabParam && typeof tabParam === 'string' && tabParam.trim() !== '') {
                params.tab = tabParam.trim();
            }
        } catch (e) {
            // URLSearchParams non supporté ou erreur de parsing
        }

        return params;
    }

    /**
     * Met à jour les paramètres URL sans recharger la page
     * @param {Object} params - Paramètres à mettre à jour { event, member, tab }
     */
    function updateUrlParams(params) {
        try {
            var url = new URL(window.location.href);
            
            // Gérer le paramètre event
            if (params.event !== undefined) {
                if (params.event) {
                    url.searchParams.set('event', params.event);
                    url.searchParams.delete('member'); // Mutuellement exclusif
                } else {
                    url.searchParams.delete('event');
                }
            }
            
            // Gérer le paramètre member
            if (params.member !== undefined) {
                if (params.member) {
                    url.searchParams.set('member', params.member);
                    url.searchParams.delete('event'); // Mutuellement exclusif
                } else {
                    url.searchParams.delete('member');
                }
            }
            
            // Gérer le paramètre tab
            if (params.tab !== undefined) {
                if (params.tab) {
                    url.searchParams.set('tab', params.tab);
                } else {
                    url.searchParams.delete('tab');
                }
            }
            
            // Mettre à jour l'URL sans recharger
            window.history.replaceState({}, '', url.toString());
        } catch (e) {
            // URL API non supportée ou erreur
        }
    }

    // ============================================
    // INITIALIZATION
    // ============================================

    function initRegistrationManager() {
        var containers = document.querySelectorAll('[data-mj-registration-manager]');
        var urlParams = parseUrlParams();

        containers.forEach(function (container) {
            var configAttr = container.getAttribute('data-config');
            var config = {};

            try {
                config = JSON.parse(configAttr || '{}');
            } catch (e) {
                console.error('[MjRegMgr] Invalid config JSON');
            }

            // Injecter les paramètres URL dans la config
            if (urlParams.eventId) {
                config.urlEventId = urlParams.eventId;
            }
            if (urlParams.memberId) {
                config.urlMemberId = urlParams.memberId;
            }
            if (urlParams.tab) {
                config.urlTab = urlParams.tab;
            }

            render(h(RegistrationManagerApp, { config: config }), container);

            if (container.classList) {
                container.classList.remove('mj-registration-manager--booting');
                container.classList.add('mj-registration-manager--ready');
            }
        });
    }

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRegistrationManager);
    } else {
        initRegistrationManager();
    }

    // Support Elementor
    if (typeof jQuery !== 'undefined') {
        jQuery(window).on('elementor/frontend/init', function () {
            if (typeof elementorFrontend !== 'undefined') {
                elementorFrontend.hooks.addAction('frontend/element_ready/widget', function () {
                    initRegistrationManager();
                });
            }
        });
    }

    // Export
    global.MjRegMgrApp = {
        init: initRegistrationManager,
        App: RegistrationManagerApp,
    };

})(window);
