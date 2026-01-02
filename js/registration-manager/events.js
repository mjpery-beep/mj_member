/**
 * Registration Manager - Events Components
 * Composants pour la liste et les cartes d'événements
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour events.js');
        return;
    }

    var h = preact.h;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;

    var formatDate = Utils.formatDate;
    var formatShortDate = Utils.formatShortDate;
    var classNames = Utils.classNames;
    var getString = Utils.getString;

    function getScheduleModeLabel(mode, strings) {
        var normalized = typeof mode === 'string' ? mode : '';
        switch (normalized) {
            case 'range':
                return getString(strings, 'scheduleModeRangeShort', 'Période continue');
            case 'recurring':
                return getString(strings, 'scheduleModeRecurringShort', 'Récurrence');
            case 'series':
                return getString(strings, 'scheduleModeSeriesShort', 'Série personnalisée');
            case 'fixed':
            default:
                return getString(strings, 'scheduleModeFixedShort', 'Date unique');
        }
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }
        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function formatCompactDate(date) {
        if (!date) {
            return '';
        }
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        return day + '/' + month;
    }

    function formatCompactTime(date) {
        if (!date) {
            return '';
        }
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');
        return hours + ':' + minutes;
    }

    function formatCompactDateTime(date) {
        if (!date) {
            return '';
        }
        var datePart = formatCompactDate(date);
        var timePart = formatCompactTime(date);
        return datePart && timePart ? datePart + ' ' + timePart : datePart || timePart;
    }

    function buildScheduleDetailFallback(event, strings) {
        if (!event) {
            return '';
        }
        var mode = typeof event.scheduleMode === 'string' && event.scheduleMode !== '' ? event.scheduleMode : 'fixed';
        var start = parseDate(event.dateDebut);
        var end = parseDate(event.dateFin);

        if (mode === 'range') {
            var rangeParts = [];
            var startDate = formatCompactDate(start);
            var endDate = formatCompactDate(end);
            if (startDate && endDate) {
                rangeParts.push(startDate + ' → ' + endDate);
            } else if (startDate) {
                rangeParts.push(startDate);
            }
            var startTime = formatCompactTime(start);
            var endTime = formatCompactTime(end);
            if (startTime && endTime) {
                rangeParts.push(startTime + ' → ' + endTime);
            } else if (startTime) {
                rangeParts.push(startTime);
            }
            return rangeParts.join(' · ');
        }

        if (mode === 'recurring' || mode === 'series') {
            return start ? formatCompactDateTime(start) : '';
        }

        var summary = '';
        if (start) {
            summary = formatCompactDateTime(start);
            if (end) {
                var sameDay = formatCompactDate(start) === formatCompactDate(end);
                var endPart = sameDay ? formatCompactTime(end) : formatCompactDateTime(end);
                if (endPart) {
                    summary += ' → ' + endPart;
                }
            }
        }
        return summary;
    }

    // ============================================
    // EVENT CARD
    // ============================================

    /**
     * Carte d'événement dans la sidebar
     */
    function EventCard(props) {
        var event = props.event;
        var isSelected = props.isSelected;
        var onClick = props.onClick;
        var strings = props.strings;

        var statusClass = 'mj-regmgr-event-card__status--' + event.status;
        var typeClass = 'mj-regmgr-event-card__type--' + event.type;

        var capacityText = '';
        if (event.capacityTotal > 0) {
            capacityText = event.registrationsCount + '/' + event.capacityTotal;
        } else {
            capacityText = String(event.registrationsCount);
        }

        var isFull = event.capacityTotal > 0 && event.registrationsCount >= event.capacityTotal;
        var scheduleSummary = event.scheduleSummary || getScheduleModeLabel(event.scheduleMode, strings);
        var scheduleDetail = event.scheduleDetail || buildScheduleDetailFallback(event, strings);
        var freeParticipation = !!event.freeParticipation;

        return h('div', {
            class: classNames('mj-regmgr-event-card', {
                'mj-regmgr-event-card--selected': isSelected,
                'mj-regmgr-event-card--full': isFull,
                'mj-regmgr-event-card--has-cover': event.coverUrl,
            }),
            onClick: function () { onClick(event); },
            role: 'option',
            'aria-selected': isSelected ? 'true' : 'false',
            tabIndex: 0,
            onKeyDown: function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onClick(event);
                }
            },
        }, [
            // Cover image (petite vignette)
            event.coverUrl && h('div', { class: 'mj-regmgr-event-card__cover' }, [
                h('img', { 
                    src: event.coverUrl, 
                    alt: event.title,
                    loading: 'lazy',
                }),
            ]),

            // Barre de couleur accent (seulement si pas de cover)
            !event.coverUrl && event.accentColor && h('div', {
                class: 'mj-regmgr-event-card__accent',
                style: { backgroundColor: event.accentColor },
            }),

            // Contenu principal
            h('div', { class: 'mj-regmgr-event-card__content' }, [
                // Header avec type et statut
                h('div', { class: 'mj-regmgr-event-card__header' }, [
                    h('span', { class: classNames('mj-regmgr-event-card__type', typeClass) },
                        event.typeLabel
                    ),
                    h('span', { class: classNames('mj-regmgr-event-card__status', statusClass) },
                        event.statusLabel
                    ),
                ]),

                // Titre
                h('h2', { class: 'mj-regmgr-event-card__title' }, event.title),

                // Date
                h('div', { class: 'mj-regmgr-event-card__date' }, [
                    h('svg', { 
                        class: 'mj-regmgr-event-card__icon', 
                        width: 14, 
                        height: 14, 
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 2,
                    }, [
                        h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                        h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                        h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                        h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                    ]),
                    h('span', null, formatShortDate(event.dateDebut)),
                ]),

                (scheduleSummary || scheduleDetail) && h('div', { class: 'mj-regmgr-event-card__schedule' }, [
                    scheduleSummary && h('span', { class: 'mj-regmgr-event-card__schedule-summary' }, scheduleSummary),
                    scheduleDetail && h('span', { class: 'mj-regmgr-event-card__schedule-detail' }, scheduleDetail),
                ]),

                // Footer avec inscriptions et prix
                h('div', { class: 'mj-regmgr-event-card__footer' }, [
                    freeParticipation
                        ? h('div', { class: 'mj-regmgr-event-card__free-participation' }, [
                            h('svg', {
                                class: 'mj-regmgr-event-card__icon',
                                width: 14,
                                height: 14,
                                viewBox: '0 0 24 24',
                                fill: 'none',
                                stroke: 'currentColor',
                                'stroke-width': 2,
                            }, [
                                h('path', { d: 'M3 12h18' }),
                                h('path', { d: 'M12 3v18' }),
                            ]),
                            h('span', null, getString(strings, 'eventFreeParticipation', 'Participation libre')),
                        ])
                        : h('div', { class: 'mj-regmgr-event-card__registrations' }, [
                            h('svg', {
                                class: 'mj-regmgr-event-card__icon',
                                width: 14,
                                height: 14,
                                viewBox: '0 0 24 24',
                                fill: 'none',
                                stroke: 'currentColor',
                                'stroke-width': 2,
                            }, [
                                h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                                h('circle', { cx: 9, cy: 7, r: 4 }),
                                h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                                h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                            ]),
                            h('span', null, capacityText),
                        ]),
                    event.prix > 0 && h('div', { class: 'mj-regmgr-event-card__price' },
                        event.prix.toFixed(2) + ' €'
                    ),
                    event.prix === 0 && h('div', { class: 'mj-regmgr-event-card__price mj-regmgr-event-card__price--free' },
                        getString(strings, 'eventFree', 'Gratuit')
                    ),
                ]),
            ]),
        ]);
    }

    // ============================================
    // EVENTS LIST
    // ============================================

    /**
     * Liste des événements dans la sidebar
     */
    function EventsList(props) {
        var events = props.events || [];
        var loading = props.loading;
        var selectedEventId = props.selectedEventId;
        var onSelectEvent = props.onSelectEvent;
        var strings = props.strings;
        var onLoadMore = props.onLoadMore;
        var hasMore = props.hasMore;
        var loadingMore = props.loadingMore;

        if (loading && events.length === 0) {
            return h('div', { class: 'mj-regmgr-events-list mj-regmgr-events-list--loading' }, [
                h('div', { class: 'mj-regmgr-spinner' }),
                h('p', null, getString(strings, 'loading', 'Chargement...')),
            ]);
        }

        if (events.length === 0) {
            return h('div', { class: 'mj-regmgr-events-list mj-regmgr-events-list--empty' }, [
                h('div', { class: 'mj-regmgr-events-list__empty-icon' }, [
                    h('svg', {
                        width: 48,
                        height: 48,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 1.5,
                    }, [
                        h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                        h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                        h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                        h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                    ]),
                ]),
                h('p', null, getString(strings, 'noEvents', 'Aucun événement trouvé.')),
            ]);
        }

        return h('div', { class: 'mj-regmgr-events-list' }, [
            events.map(function (event) {
                return h(EventCard, {
                    key: event.id,
                    event: event,
                    isSelected: event.id === selectedEventId,
                    onClick: onSelectEvent,
                    strings: strings,
                });
            }),

            // Bouton "Charger plus"
            hasMore && h('div', { class: 'mj-regmgr-events-list__load-more' }, [
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
    // EVENTS SIDEBAR
    // ============================================

    /**
     * Sidebar complète avec recherche et filtres
     */
    function EventsSidebar(props) {
        var events = props.events;
        var loading = props.loading;
        var selectedEventId = props.selectedEventId;
        var onSelectEvent = props.onSelectEvent;
        var filter = props.filter;
        var onFilterChange = props.onFilterChange;
        var search = props.search;
        var onSearchChange = props.onSearchChange;
        var strings = props.strings;
        var title = props.title;
        var onLoadMore = props.onLoadMore;
        var hasMore = props.hasMore;
        var loadingMore = props.loadingMore;
        var createEventUrl = props.createEventUrl;
        var onCreateEvent = typeof props.onCreateEvent === 'function' ? props.onCreateEvent : null;
        var createEventLoading = !!props.createEventLoading;
        var canCreateEvent = !!props.canCreateEvent && (onCreateEvent || (typeof createEventUrl === 'string' && createEventUrl !== ''));

        // Props for members mode
        var sidebarMode = props.sidebarMode || 'events';
        var onModeChange = props.onModeChange;
        var members = props.members || [];
        var membersLoading = props.membersLoading;
        var selectedMemberId = props.selectedMemberId;
        var onSelectMember = props.onSelectMember;
        var memberFilter = props.memberFilter || 'all';
        var onMemberFilterChange = props.onMemberFilterChange;
        var memberSearch = props.memberSearch || '';
        var onMemberSearchChange = props.onMemberSearchChange;
        var onLoadMoreMembers = props.onLoadMoreMembers;
        var hasMoreMembers = props.hasMoreMembers;
        var membersLoadingMore = props.membersLoadingMore;

        // Get MembersList component
        var MembersComps = window.MjRegMgrMembers;
        var MembersList = MembersComps ? MembersComps.MembersList : null;

        var eventFilters = [
            { key: 'assigned', label: getString(strings, 'filterAssigned', 'Mes événements') },
            { key: 'upcoming', label: getString(strings, 'filterUpcoming', 'À venir') },
            { key: 'past', label: getString(strings, 'filterPast', 'Passés') },
            { key: 'draft', label: getString(strings, 'filterDraft', 'Brouillons') },
            { key: 'internal', label: getString(strings, 'filterInternal', 'Internes') },
        ];

        var memberFilters = [
            { key: 'all', label: getString(strings, 'filterAll', 'Tous') },
            { key: 'jeune', label: getString(strings, 'filterJeune', 'Jeunes') },
            { key: 'animateur', label: getString(strings, 'filterAnimateur', 'Animateurs') },
            { key: 'parent', label: getString(strings, 'filterParent', 'Parents') },
            { key: 'membership_due', label: getString(strings, 'filterMembershipDue', 'Cotisation à régulariser') },
        ];

        var currentFilters = sidebarMode === 'events' ? eventFilters : memberFilters;
        var currentFilter = sidebarMode === 'events' ? filter : memberFilter;
        var currentFilterChange = sidebarMode === 'events' ? onFilterChange : onMemberFilterChange;
        var currentSearch = sidebarMode === 'events' ? search : memberSearch;
        var currentSearchChange = sidebarMode === 'events' ? onSearchChange : onMemberSearchChange;

        return h('aside', { class: 'mj-regmgr-sidebar' }, [
            // Header with mode tabs
            h('div', { class: 'mj-regmgr-sidebar__header' }, [
                h('div', { class: 'mj-regmgr-sidebar__mode-tabs', role: 'tablist' }, [
                    h('button', {
                        type: 'button',
                        class: classNames('mj-regmgr-sidebar__mode-tab', {
                            'mj-regmgr-sidebar__mode-tab--active': sidebarMode === 'events',
                        }),
                        role: 'tab',
                        'aria-selected': sidebarMode === 'events' ? 'true' : 'false',
                        onClick: function () { onModeChange && onModeChange('events'); },
                    }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                            h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                            h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                            h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                        ]),
                        h('span', null, 'Événements'),
                    ]),
                    h('button', {
                        type: 'button',
                        class: classNames('mj-regmgr-sidebar__mode-tab', {
                            'mj-regmgr-sidebar__mode-tab--active': sidebarMode === 'members',
                        }),
                        role: 'tab',
                        'aria-selected': sidebarMode === 'members' ? 'true' : 'false',
                        onClick: function () { onModeChange && onModeChange('members'); },
                    }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                            h('circle', { cx: 9, cy: 7, r: 4 }),
                            h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                            h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                        ]),
                        h('span', null, 'Membres'),
                    ]),
                ]),
            ]),

            sidebarMode === 'events' && canCreateEvent && h('div', { class: 'mj-regmgr-sidebar__actions' }, [
                onCreateEvent
                    ? h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--primary mj-btn--block',
                        onClick: function () {
                            if (createEventLoading) {
                                return;
                            }
                            onCreateEvent();
                        },
                        disabled: createEventLoading,
                        'aria-busy': createEventLoading ? 'true' : 'false',
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
                        h('span', null, createEventLoading
                            ? getString(strings, 'creatingEvent', 'Création...')
                            : getString(strings, 'addEvent', 'Ajouter un événement')
                        ),
                    ])
                    : h('a', {
                        href: createEventUrl,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        class: 'mj-btn mj-btn--primary mj-btn--block',
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
                        h('span', null, getString(strings, 'addEvent', 'Ajouter un événement')),
                    ]),
            ]),

            // Recherche
            h('div', { class: 'mj-regmgr-sidebar__search' }, [
                h('div', { class: 'mj-regmgr-search-input' }, [
                    h('svg', {
                        class: 'mj-regmgr-search-input__icon',
                        width: 16,
                        height: 16,
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
                        placeholder: sidebarMode === 'events' 
                            ? getString(strings, 'searchEvents', 'Rechercher un événement...') 
                            : getString(strings, 'searchMembers', 'Rechercher un membre...'),
                        value: currentSearch,
                        onInput: function (e) { currentSearchChange(e.target.value); },
                    }),
                    currentSearch && h('button', {
                        type: 'button',
                        class: 'mj-regmgr-search-input__clear',
                        onClick: function () { currentSearchChange(''); },
                        'aria-label': 'Effacer',
                    }, [
                        h('svg', {
                            width: 14,
                            height: 14,
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
            ]),

            // Filtres
            h('div', { class: 'mj-regmgr-sidebar__filters' }, [
                h('div', { class: 'mj-regmgr-filter-tabs', role: 'tablist' }, 
                    currentFilters.map(function (f) {
                        return h('button', {
                            key: f.key,
                            type: 'button',
                            class: classNames('mj-regmgr-filter-tab', {
                                'mj-regmgr-filter-tab--active': currentFilter === f.key,
                            }),
                            role: 'tab',
                            'aria-selected': currentFilter === f.key ? 'true' : 'false',
                            onClick: function () { currentFilterChange(f.key); },
                        }, f.label);
                    })
                ),
            ]),

            // Liste (événements ou membres)
            h('div', { class: 'mj-regmgr-sidebar__list' }, [
                sidebarMode === 'events' ? h(EventsList, {
                    events: events,
                    loading: loading,
                    selectedEventId: selectedEventId,
                    onSelectEvent: onSelectEvent,
                    strings: strings,
                    onLoadMore: onLoadMore,
                    hasMore: hasMore,
                    loadingMore: loadingMore,
                }) : (MembersList ? h(MembersList, {
                    members: members,
                    loading: membersLoading,
                    selectedMemberId: selectedMemberId,
                    onSelectMember: onSelectMember,
                    strings: strings,
                    onLoadMore: onLoadMoreMembers,
                    hasMore: hasMoreMembers,
                    loadingMore: membersLoadingMore,
                }) : h('div', { class: 'mj-regmgr-members-list--loading' }, [
                    h('div', { class: 'mj-regmgr-spinner' }),
                ])),
            ]),
        ]);
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrEvents = {
        EventCard: EventCard,
        EventsList: EventsList,
        EventsSidebar: EventsSidebar,
    };

})(window);