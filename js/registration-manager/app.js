/**
 * Registration Manager - Main Application
 * Point d'entrée principal et assemblage des composants
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;

    if (!preact || !hooks) {
        console.warn('[MjRegMgr] Preact must be loaded before the registration manager.');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var render = preact.render;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;
    var useRef = hooks.useRef;

    // Import des modules
    var Services = global.MjRegMgrServices;
    var Utils = global.MjRegMgrUtils;
    var EventsComps = global.MjRegMgrEvents;
    var RegComps = global.MjRegMgrRegistrations;
    var AttendanceComps = global.MjRegMgrAttendance;
    var Modals = global.MjRegMgrModals;
    var EventEditorModule = global.MjRegMgrEventEditor;
    var EventEditor = EventEditorModule ? EventEditorModule.EventEditor : null;

    if (!Services || !Utils || !EventsComps || !RegComps || !AttendanceComps || !Modals) {
        console.warn('[MjRegMgr] Modules manquants');
        return;
    }

    var getString = Utils.getString;
    var classNames = Utils.classNames;
    var useToasts = Utils.useToasts;
    var useModal = Utils.useModal;

    var EventsSidebar = EventsComps.EventsSidebar;
    var RegistrationsList = RegComps.RegistrationsList;
    var AttendanceSheet = AttendanceComps.AttendanceSheet;
    var AddParticipantModal = Modals.AddParticipantModal;
    var CreateMemberModal = Modals.CreateMemberModal;
    var MemberNotesModal = Modals.MemberNotesModal;
    var QRCodeModal = Modals.QRCodeModal;
    var OccurrencesModal = Modals.OccurrencesModal;

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
                    h('h2', { class: 'mj-regmgr-event-detail__title' }, event.title || 'Sans titre'),
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
                    // Lien vers la page front
                    event.frontUrl && h('a', {
                        href: event.frontUrl,
                        target: '_blank',
                        class: 'mj-btn mj-btn--primary',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                            h('polyline', { points: '15 3 21 3 21 9' }),
                            h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                        ]),
                        'Voir sur le site',
                    ]),

                    // Bouton modifier
                    config.adminEditUrl && h('a', {
                        href: config.adminEditUrl + event.id,
                        target: '_blank',
                        class: 'mj-btn mj-btn--secondary',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                            h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                        ]),
                        getString(strings, 'editEvent', 'Modifier'),
                    ]),
                ]),
            ]),
        ]);
    }

    // ============================================
    // TABS
    // ============================================

    function Tabs(props) {
        var tabs = props.tabs;
        var activeTab = props.activeTab;
        var onChange = props.onChange;

        return h('div', { class: 'mj-regmgr-tabs', role: 'tablist' },
            tabs.map(function (tab) {
                return h('button', {
                    key: tab.key,
                    type: 'button',
                    class: classNames('mj-regmgr-tab', {
                        'mj-regmgr-tab--active': activeTab === tab.key,
                    }),
                    role: 'tab',
                    'aria-selected': activeTab === tab.key ? 'true' : 'false',
                    onClick: function () { onChange(tab.key); },
                }, [
                    tab.icon && h('span', { class: 'mj-regmgr-tab__icon', dangerouslySetInnerHTML: { __html: tab.icon } }),
                    h('span', null, tab.label),
                    tab.badge !== undefined && h('span', { class: 'mj-regmgr-tab__badge' }, tab.badge),
                ]);
            })
        );
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
    // MAIN APP
    // ============================================

    function RegistrationManagerApp(props) {
        var config = props.config;
        var strings = config.strings || {};

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
                    if (key === 'form' || key === 'meta') {
                        appendNestedValue(formData, key, value);
                        return;
                    }
                    if (Array.isArray(value)) {
                        value.forEach(function (item, index) {
                            formData.append(key + '[' + index + ']', item);
                        });
                        return;
                    }
                    if (typeof value === 'object') {
                        formData.append(key, JSON.stringify(value));
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
        var addParticipantModal = useModal();
        var createMemberModal = useModal();
        var notesModal = useModal();
        var qrModal = useModal();
        var occurrencesModal = useModal();

        // State
        var _events = useState([]);
        var events = _events[0];
        var setEvents = _events[1];

        var _eventsLoading = useState(true);
        var eventsLoading = _eventsLoading[0];
        var setEventsLoading = _eventsLoading[1];

        var _filter = useState(config.defaultFilter || 'assigned');
        var filter = _filter[0];
        var setFilter = _filter[1];

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
        var setActiveTab = _activeTab[1];

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

        // Sidebar mode state (events or members)
        var _sidebarMode = useState('events');
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

        var _selectedMember = useState(null);
        var selectedMember = _selectedMember[0];
        var setSelectedMember = _selectedMember[1];

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
                        try {
                            var savedId = localStorage.getItem(storageKey);
                            if (savedId) {
                                var savedEvent = loadedEvents.find(function (e) { return e.id === parseInt(savedId, 10); });
                                if (savedEvent) {
                                    handleSelectEvent(savedEvent);
                                }
                            }
                        } catch (e) {
                            // localStorage non disponible
                        }
                    }
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message || getString(strings, 'error', 'Erreur'));
                        setEventsLoading(false);
                    }
                });
        }, [api, filter, search, config.perPage, showError, strings, initialEventLoaded, storageKey]);

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
                    }
                });
        }, [api, showError]);

        // Sélectionner un événement
        var handleSelectEvent = useCallback(function (event) {
            setSelectedEvent(event);
            setEventDetails(null);
            setRegistrations([]);
            setAttendanceMap({});
            setEventEditorData(null);
            setEventEditorSummary(null);
            setEventEditorErrors([]);
            setEventEditorLoading(false);
            setEventEditorSaving(false);
            eventEditorLoadedRef.current = null;
            setActiveTab('registrations');
            setMobileShowDetails(true); // Afficher les détails sur mobile
            
            // Mémoriser l'événement sélectionné
            lastSelectedEventIdRef.current = event.id;
            try {
                localStorage.setItem(storageKey, String(event.id));
            } catch (e) {
                // localStorage non disponible
            }
            
            loadEventDetails(event.id);
            loadRegistrations(event.id);
        }, [loadEventDetails, loadRegistrations, storageKey]);

        var handleReloadEventEditor = useCallback(function () {
            if (!selectedEvent || !selectedEvent.id) {
                return Promise.resolve();
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

        // ============================================
        // MEMBERS MODE FUNCTIONS
        // ============================================

        // Charger les membres
        var loadMembers = useCallback(function (page) {
            setMembersLoading(true);
            api.getMembers({
                filter: memberFilter,
                search: memberSearch,
                page: page || 1,
                perPage: config.perPage || 20,
            })
                .then(function (data) {
                    setMembersList(data.members || []);
                    setMembersPagination(data.pagination || { page: 1, totalPages: 1 });
                    setMembersLoading(false);
                })
                .catch(function (err) {
                    if (!err.aborted) {
                        showError(err.message || getString(strings, 'error', 'Erreur'));
                        setMembersLoading(false);
                    }
                });
        }, [api, memberFilter, memberSearch, config.perPage, showError, strings]);

        // Charger les membres quand on bascule en mode membres ou quand les filtres changent
        useEffect(function () {
            if (sidebarMode === 'members') {
                loadMembers(1);
            }
        }, [sidebarMode, memberFilter, memberSearch]);

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

            loadMemberDetails(member.id);
            loadMemberNotesForPanel(member.id);
            loadMemberRegistrationsHistory(member.id);
        }, [loadMemberDetails, loadMemberNotesForPanel, loadMemberRegistrationsHistory, memberStorageKey]);

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
        }, [sidebarMode, membersLoading, selectedMember, membersList, handleSelectMember, memberStorageKey]);

        // Changer de mode sidebar
        var handleSidebarModeChange = useCallback(function (mode) {
            setSidebarMode(mode);
            setMobileShowDetails(false);
            
            if (mode === 'events') {
                setSelectedMember(null);
                setMemberDetails(null);
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

        var handleViewMemberFromRegistration = useCallback(function (member) {
            if (!member || !member.id) {
                return;
            }
            setSidebarMode('members');
            setSelectedEvent(null);
            setEventDetails(null);
            handleSelectMember(member);
        }, [handleSelectMember, setSelectedEvent, setEventDetails]);

        // Mettre à jour un membre
        var handleUpdateMember = useCallback(function (memberId, data) {
            api.updateMember(memberId, data)
                .then(function (result) {
                    showSuccess(result.message || 'Membre mis à jour');
                    loadMemberDetails(memberId);
                    loadMembers(membersPagination.page);
                })
                .catch(function (err) {
                    showError(err.message);
                });
        }, [api, showSuccess, showError, loadMemberDetails, loadMembers, membersPagination.page]);

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
        var handleCreateMember = useCallback(function (firstName, lastName, email, role, birthDate) {
            return api.createQuickMember(firstName, lastName, email, role, birthDate)
                .then(function (data) {
                    showSuccess(data.message);
                    createMemberModal.close();
                    // Optionnel: ajouter directement à l'événement
                    if (selectedEvent && data.member) {
                        return api.addRegistration(selectedEvent.id, [data.member.id], [])
                            .then(function () {
                                loadRegistrations(selectedEvent.id);
                                loadEvents(pagination.page);
                            });
                    }
                });
        }, [api, selectedEvent, showSuccess, createMemberModal, loadRegistrations, loadEvents, pagination.page]);

        // Rechercher membres
        var handleSearchMembers = useCallback(function (params) {
            return api.searchMembers(params);
        }, [api]);

        // Onglets
        var tabs = [
            { 
                key: 'registrations', 
                label: getString(strings, 'tabRegistrations', 'Inscriptions'),
                badge: registrations.length,
            },
            { 
                key: 'attendance', 
                label: getString(strings, 'tabAttendance', 'Présence'),
            },
            { 
                key: 'details', 
                label: getString(strings, 'tabDetails', 'Détails'),
            },
        ];

        if (EventEditor) {
            tabs.push({
                key: 'editor',
                label: getString(strings, 'tabEditor', 'Éditer'),
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
                    onFilterChange: setFilter,
                    search: search,
                    onSearchChange: setSearch,
                    onLoadMore: function () { loadEvents(pagination.page + 1); },
                    hasMore: pagination.page < pagination.totalPages,
                    loadingMore: false,

                    // Members props
                    members: membersList,
                    membersLoading: membersLoading,
                    selectedMemberId: selectedMember ? selectedMember.id : null,
                    onSelectMember: handleSelectMember,
                    memberFilter: memberFilter,
                    onMemberFilterChange: setMemberFilter,
                    memberSearch: memberSearch,
                    onMemberSearchChange: setMemberSearch,
                    onLoadMoreMembers: function () { loadMembers(membersPagination.page + 1); },
                    hasMoreMembers: membersPagination.page < membersPagination.totalPages,
                    membersLoadingMore: false,

                    strings: strings,
                    title: config.title || 'Événements',
                }),

                // Zone principale
                h('main', { class: 'mj-regmgr__main' }, [
                    // Bouton retour mobile (événements)
                    sidebarMode === 'events' && selectedEvent && h('button', {
                        type: 'button',
                        class: 'mj-regmgr__back-btn',
                        onClick: handleBackToEvents,
                    }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '15 18 9 12 15 6' }),
                        ]),
                        h('span', null, 'Événements'),
                    ]),

                    // Bouton retour mobile (membres)
                    sidebarMode === 'members' && selectedMember && h('button', {
                        type: 'button',
                        class: 'mj-regmgr__back-btn',
                        onClick: handleBackToEvents,
                    }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '15 18 9 12 15 6' }),
                        ]),
                        h('span', null, 'Membres'),
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
                        // Onglets
                        h(Tabs, {
                            tabs: tabs,
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
                                strings: strings,
                                config: config,
                                eventRequiresPayment: eventRequiresPayment,
                                allowOccurrenceSelection: allowOccurrenceSelection,
                                eventRequiresValidation: eventRequiresValidation,
                            }),

                            activeTab === 'attendance' && h(AttendanceSheet, {
                                event: eventDetails,
                                registrations: registrations,
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

                            activeTab === 'details' && h(EventDetailPanel, {
                                event: eventDetails,
                                registrations: registrations,
                                attendanceMap: attendanceMap,
                                occurrences: eventDetails ? eventDetails.occurrences : [],
                                strings: strings,
                                config: config,
                                loading: registrationsLoading || !eventDetails,
                            }),

                            activeTab === 'editor' && EventEditor && h(EventEditor, {
                                data: eventEditorData,
                                eventSummary: eventEditorSummary || selectedEvent,
                                loading: eventEditorLoading,
                                saving: eventEditorSaving,
                                errors: eventEditorErrors,
                                onSubmit: handleSubmitEventEditor,
                                onReload: handleReloadEventEditor,
                                strings: strings,
                            }),
                        ]),
                    ]),

                    // Contenu membre sélectionné
                    sidebarMode === 'members' && selectedMember && MemberDetailPanel && h('div', { class: 'mj-regmgr__content mj-regmgr__content--member' }, [
                        h(MemberDetailPanel, {
                            member: memberDetails,
                            loading: !memberDetails,
                            strings: strings,
                            config: config,
                            notes: memberNotes,
                            registrations: memberRegistrations,
                            onSaveNote: handleSaveMemberNote,
                            onDeleteNote: handleDeleteMemberNoteFromPanel,
                            onUpdateMember: handleUpdateMember,
                            onPayMembershipOnline: handlePayMembershipOnline,
                            onMarkMembershipPaid: handleMarkMembershipPaid,
                            onUpdateIdea: handleUpdateMemberIdea,
                            onUpdatePhoto: handleUpdateMemberPhoto,
                            onDeletePhoto: handleDeleteMemberPhoto,
                            onDeleteMessage: handleDeleteMemberMessage,
                        }),
                    ]),
                ]),
            ]),

            // Modals
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

            // Toasts
            h(Toasts, {
                toasts: toasts,
                onRemove: removeToast,
            }),
        ]);
    }

    // ============================================
    // INITIALIZATION
    // ============================================

    function initRegistrationManager() {
        var containers = document.querySelectorAll('[data-mj-registration-manager]');

        containers.forEach(function (container) {
            var configAttr = container.getAttribute('data-config');
            var config = {};

            try {
                config = JSON.parse(configAttr || '{}');
            } catch (e) {
                console.error('[MjRegMgr] Invalid config JSON');
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
