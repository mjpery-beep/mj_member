/**
 * Agenda widget — Preact shell.
 *
 * Owns the toolbar / filters / legend / preview overlay and drives the
 * Schedule-X calendar (via window.MjAgendaCalendar) and the AJAX service
 * (via window.MjAgendaServices).
 */
(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    if (!preact || !hooks || typeof preact.h !== 'function') {
        if (global.console) { console.warn('[MjAgenda] Preact must load before the agenda widget.'); }
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var render = preact.render;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useRef = hooks.useRef;
    var useMemo = hooks.useMemo;
    var useCallback = hooks.useCallback;

    var Utils = global.MjMemberUtils || {};
    var runtime = global.mjMemberAgenda || {};

    var LAYER_LABELS = {
        event_occurrences: 'Événements',
        todos: 'Tâches',
        leave_requests: 'Congés',
        work_schedules: 'Horaires de travail',
        worked_hours: 'Heures prestées',
        requests: 'Demandes',
        internal_notes: 'Notes internes'
    };

    var VIEW_LABELS = {
        'month-grid': 'Mois',
        'week': 'Semaine',
        'day': 'Jour',
        'month-agenda': 'Agenda',
        'list': 'Liste'
    };

    function i18n(key, fallback) {
        return (runtime.i18n && runtime.i18n[key]) || fallback;
    }

    // --- tiny inline SVG icons (currentColor) ------------------------------
    function svg(children, extra) {
        var attrs = {
            viewBox: '0 0 24 24', width: '18', height: '18',
            fill: 'none', stroke: 'currentColor', 'stroke-width': '2',
            'stroke-linecap': 'round', 'stroke-linejoin': 'round',
            'aria-hidden': 'true', class: 'mj-agenda__svg'
        };
        if (extra) { for (var k in extra) { attrs[k] = extra[k]; } }
        return h('svg', attrs, children);
    }
    function chevron(dir) {
        return svg([h('polyline', { points: dir === 'left' ? '15 18 9 12 15 6' : '9 18 15 12 9 6' })]);
    }
    function icon(name) {
        if (name === 'plus') {
            return svg([h('line', { x1: 12, y1: 5, x2: 12, y2: 19 }), h('line', { x1: 5, y1: 12, x2: 19, y2: 12 })]);
        }
        if (name === 'filter') {
            return svg([h('polygon', { points: '22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3' })]);
        }
        return null;
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function isoDate(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }
    function addMonths(date, delta) {
        var d = new Date(date.getTime());
        d.setMonth(d.getMonth() + delta);
        return d;
    }

    function computeRange(config) {
        var today = new Date();
        var start = new Date(today.getFullYear(), today.getMonth(), 1);
        start = addMonths(start, -(config.range ? config.range.monthsBefore : 1));
        var end = addMonths(new Date(today.getFullYear(), today.getMonth(), 1), (config.range ? config.range.monthsAfter : 3) + 1);
        end.setDate(0);
        return { start: isoDate(start), end: isoDate(end) };
    }

    function parseIso(s) {
        var p = String(s || '').slice(0, 10).split('-');
        if (p.length !== 3) { return null; }
        var d = new Date(+p[0], +p[1] - 1, +p[2]);
        return isNaN(d.getTime()) ? null : d;
    }

    function normLocale(loc) {
        var v = String(loc || 'fr-FR').replace('_', '-');
        return /^[a-z]{2}(-[A-Za-z]{2,4})?$/.test(v) ? v : 'fr-FR';
    }

    // Human label for the currently visible period, given the range + view.
    function formatPeriod(range, view, locale) {
        var loc = normLocale(locale);
        var start = parseIso(range && range.start);
        var end = parseIso(range && range.end);
        if (!start) { return ''; }

        try {
            if (view === 'day') {
                return new Intl.DateTimeFormat(loc, {
                    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
                }).format(start);
            }
            if (view === 'week' || (end && (end - start) <= 8 * 864e5 && view !== 'month-grid' && view !== 'month-agenda')) {
                var e = end || start;
                var sameMonth = start.getMonth() === e.getMonth() && start.getFullYear() === e.getFullYear();
                var dFmt = new Intl.DateTimeFormat(loc, { day: 'numeric' });
                var dmFmt = new Intl.DateTimeFormat(loc, { day: 'numeric', month: 'short' });
                var dmyFmt = new Intl.DateTimeFormat(loc, { day: 'numeric', month: 'short', year: 'numeric' });
                return (sameMonth ? dFmt.format(start) : dmFmt.format(start)) + ' – ' + dmyFmt.format(e);
            }
            // month views: use the range midpoint so the dominant month wins
            var mid = end ? new Date((start.getTime() + end.getTime()) / 2) : start;
            var label = new Intl.DateTimeFormat(loc, { month: 'long', year: 'numeric' }).format(mid);
            return label.charAt(0).toUpperCase() + label.slice(1);
        } catch (err) {
            return range.start + (end ? ' – ' + range.end : '');
        }
    }

    function EntryPreview(props) {
        var entry = props.entry;
        if (!entry) { return null; }
        var p = entry.preview || {};
        var rows = [];

        function row(label, value) {
            if (value === undefined || value === null || value === '') { return; }
            rows.push(h('div', { class: 'mj-agenda-preview__row' }, [
                h('span', { class: 'mj-agenda-preview__label' }, label),
                h('span', { class: 'mj-agenda-preview__value' }, String(value))
            ]));
        }

        row('Couche', LAYER_LABELS[entry.layer] || entry.layer);
        if (entry.layer === 'event_occurrences') {
            row('Titre', p.title);
            row('Type', p.type);
            row('Statut', p.status);
            row('Occurrence', p.occurrence_status);
            row('Lieu', p.location);
            if (p.price) { row('Prix', p.price + ' €'); }
            row('Description', p.description);
        } else if (entry.layer === 'todos') {
            row('Tâche', p.title);
            row('Projet', p.project);
            if (p.priority) { row('Priorité', p.priority); }
            row('Échéance', p.due_date);
            row('Statut', p.status);
            row('Description', p.description);
        } else if (entry.layer === 'leave_requests') {
            row('Membre', p.member_name);
            row('Type', p.type_name);
            row('Statut', p.status);
            row('Motif', p.reason);
        } else if (entry.layer === 'work_schedules') {
            row('Période', (p.period_start || '') + (p.period_end ? ' → ' + p.period_end : ''));
        } else if (entry.layer === 'worked_hours') {
            row('Tâche', p.task_label);
            row('Début', p.start_time);
            row('Fin', p.end_time);
            if (p.duration_minutes) { row('Durée', Math.round(p.duration_minutes / 6) / 10 + ' h'); }
            row('Notes', p.notes);
        } else if (entry.layer === 'requests') {
            row('Titre', p.title);
            row('Type', p.request_type);
            row('Statut', p.status);
            row('Tranche d\'âge', p.age_range);
            row('Description', p.description);
        } else if (entry.layer === 'internal_notes') {
            row('Titre', p.title);
            row('Visibilité', p.visibility);
            row('Auteur', p.author_name);
            rows.push(h('div', { class: 'mj-agenda-preview__body' }, p.content || ''));
        }

        return h('div', { class: 'mj-agenda-preview', role: 'dialog' }, [
            h('button', {
                type: 'button',
                class: 'mj-agenda-preview__close',
                'aria-label': 'Fermer',
                onClick: props.onClose
            }, '×'),
            h('h3', { class: 'mj-agenda-preview__title' }, entry.title || ''),
            h('div', { class: 'mj-agenda-preview__rows' }, rows)
        ]);
    }

    function AgendaApp(props) {
        var config = props.config;
        var acl = props.acl;

        var configuredLayers = useMemo(function () {
            return acl.visibleLayers(config.layers || []);
        }, []);

        var initialFilters = useMemo(function () {
            var pref = (runtime.prefs && Array.isArray(runtime.prefs.filters) && runtime.prefs.filters.length)
                ? runtime.prefs.filters.filter(function (l) { return configuredLayers.indexOf(l) !== -1; })
                : configuredLayers.slice();
            return pref.length ? pref : configuredLayers.slice();
        }, []);

        var stateRef = useRef({ range: computeRange(config), calendar: null });
        var hostRef = useRef(null);

        var st = useState([]);
        var events = st[0], setEvents = st[1];
        var ls = useState(true);
        var loading = ls[0], setLoading = ls[1];
        var es = useState('');
        var error = es[0], setError = es[1];
        var fs = useState(initialFilters);
        var filters = fs[0], setFilters = fs[1];
        var ps = useState(null);
        var preview = ps[0], setPreview = ps[1];
        var vs = useState(config.defaultView);
        var view = vs[0], setView = vs[1];
        var rs = useState(computeRange(config));
        var rangeState = rs[0], setRangeState = rs[1];
        var fos = useState(false);
        var filtersOpen = fos[0], setFiltersOpen = fos[1];
        var ns = useState('');
        var notice = ns[0], setNotice = ns[1];

        var anchorRef = useRef(new Date());
        var api = props.api;

        var counts = useMemo(function () {
            var c = {};
            (events || []).forEach(function (ev) {
                c[ev.layer] = (c[ev.layer] || 0) + 1;
            });
            return c;
        }, [events]);

        var periodLabel = useMemo(function () {
            return formatPeriod(rangeState, view, config.locale);
        }, [rangeState, view]);

        var fetchRange = useCallback(function (range) {
            setLoading(true);
            setError('');
            api.fetchRange({
                start: range.start,
                end: range.end,
                layers: configuredLayers,
                eventStatuses: config.eventStatuses || [],
                eventTypes: config.eventTypes || []
            }).then(function (data) {
                setEvents(data.events || []);
                var layerErrors = data.meta && data.meta.errors;
                if (layerErrors && Object.keys(layerErrors).length) {
                    var names = Object.keys(layerErrors).map(function (k) {
                        return (LAYER_LABELS[k] || k);
                    });
                    setError('Certaines couches n\'ont pas pu être chargées : ' + names.join(', '));
                    if (global.console) {
                        console.warn('[MjAgenda] layer errors', layerErrors);
                    }
                } else {
                    setError('');
                }
                setLoading(false);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') { return; }
                setError((err && err.message) || i18n('error', 'Erreur de chargement.'));
                setLoading(false);
            });
        }, []);

        // Mount Schedule-X once.
        useEffect(function () {
            var wrapper;
            try {
                wrapper = global.MjAgendaCalendar.create(config, {
                    onRangeChange: function (range) {
                        // range: { start, end } as 'YYYY-MM-DD ...' — normalise to dates
                        var s = String(range.start || '').slice(0, 10);
                        var e = String(range.end || '').slice(0, 10);
                        if (s && e) {
                            stateRef.current.range = { start: s, end: e };
                            var mid = parseIso(s);
                            var midE = parseIso(e);
                            if (mid && midE) { anchorRef.current = new Date((mid.getTime() + midE.getTime()) / 2); }
                            setRangeState({ start: s, end: e });
                            fetchRange(stateRef.current.range);
                        }
                    },
                    onEventClick: function (mjEntry) {
                        if (mjEntry) { setPreview(mjEntry); }
                    },
                    onEventUpdate: function (mjEntry) {
                        // Phase 1: no persistence yet — reload to snap back.
                        fetchRange(stateRef.current.range);
                    },
                    onClickDate: function () {},
                    onClickDateTime: function () {}
                });
                stateRef.current.calendar = wrapper;
                if (hostRef.current) {
                    wrapper.render(hostRef.current);
                }
                fetchRange(stateRef.current.range);
            } catch (err) {
                setError((err && err.message) || 'Schedule-X indisponible.');
                setLoading(false);
            }

            return function () {
                if (wrapper) { wrapper.destroy(); }
            };
        }, []);

        // Push filtered events into the calendar.
        useEffect(function () {
            var wrapper = stateRef.current.calendar;
            if (!wrapper) { return; }
            var filtered = events.filter(function (ev) {
                return filters.indexOf(ev.layer) !== -1;
            });
            wrapper.setEvents(filtered);
        }, [events, filters]);

        var persistPrefs = useCallback(function (nextView, nextFilters) {
            api.savePrefs({
                view: nextView,
                filters: JSON.stringify(nextFilters)
            }).catch(function () {});
        }, []);

        var setFiltersAnd = useCallback(function (next) {
            setFilters(next);
            persistPrefs(view, next);
        }, [view]);

        var toggleFilter = useCallback(function (layer) {
            setFilters(function (prev) {
                var next = prev.indexOf(layer) === -1
                    ? prev.concat([layer])
                    : prev.filter(function (l) { return l !== layer; });
                persistPrefs(view, next);
                return next;
            });
        }, [view]);

        var changeView = useCallback(function (name) {
            setView(name);
            var wrapper = stateRef.current.calendar;
            if (wrapper) { wrapper.setView(name); }
            persistPrefs(name, filters);
        }, [filters]);

        var goToday = useCallback(function () {
            anchorRef.current = new Date();
            var wrapper = stateRef.current.calendar;
            if (wrapper) { wrapper.setDate(isoDate(new Date())); }
        }, []);

        var navigate = useCallback(function (dir) {
            var d = new Date(anchorRef.current.getTime());
            if (view === 'day') {
                d.setDate(d.getDate() + dir);
            } else if (view === 'week') {
                d.setDate(d.getDate() + dir * 7);
            } else {
                // month-grid / month-agenda / list
                d.setDate(1);
                d.setMonth(d.getMonth() + dir);
            }
            anchorRef.current = d;
            var wrapper = stateRef.current.calendar;
            if (wrapper) { wrapper.setDate(isoDate(d)); }
        }, [view]);

        var creatableLayers = useMemo(function () {
            return acl.creatableLayers(config.layers || []);
        }, []);

        var tb = config.toolbar || {};
        var activeCount = filters.length;
        var totalLayers = configuredLayers.length;

        // -- Toolbar --------------------------------------------------------
        var navGroup = h('div', { class: 'mj-agenda__tb-group mj-agenda__nav' }, [
            h('button', {
                type: 'button', class: 'mj-agenda__icon-btn',
                'aria-label': 'Période précédente', onClick: function () { navigate(-1); }
            }, chevron('left')),
            tb.todayButton !== false ? h('button', {
                type: 'button', class: 'mj-agenda__today-btn', onClick: goToday
            }, i18n('today', 'Aujourd\'hui')) : null,
            h('button', {
                type: 'button', class: 'mj-agenda__icon-btn',
                'aria-label': 'Période suivante', onClick: function () { navigate(1); }
            }, chevron('right'))
        ]);

        var periodEl = h('div', { class: 'mj-agenda__period', 'aria-live': 'polite' }, [
            h('span', { class: 'mj-agenda__period-label' }, periodLabel || ' '),
            loading ? h('span', { class: 'mj-agenda__spinner mj-agenda__spinner--sm', 'aria-hidden': 'true' }) : null
        ]);

        var viewSwitcher = (tb.viewSwitcher !== false && (config.views || []).length > 1)
            ? h('div', { class: 'mj-agenda__views', role: 'tablist' }, (config.views || []).map(function (name) {
                return h('button', {
                    type: 'button',
                    role: 'tab',
                    'aria-selected': view === name ? 'true' : 'false',
                    class: 'mj-agenda__view-btn' + (view === name ? ' is-active' : ''),
                    onClick: function () { changeView(name); }
                }, VIEW_LABELS[name] || name);
            }))
            : null;

        var actions = [];
        if (tb.addButton !== false && creatableLayers.length) {
            actions.push(h('button', {
                type: 'button',
                class: 'mj-agenda__add-btn',
                onClick: function () { setNotice('La création d\'éléments arrive prochainement.'); }
            }, [icon('plus'), h('span', null, i18n('add', 'Ajouter'))]));
        }
        if (tb.filters !== false && totalLayers) {
            actions.push(h('button', {
                type: 'button',
                class: 'mj-agenda__filter-toggle' + (filtersOpen ? ' is-open' : ''),
                'aria-expanded': filtersOpen ? 'true' : 'false',
                onClick: function () { setFiltersOpen(function (v) { return !v; }); }
            }, [
                icon('filter'),
                h('span', null, 'Filtres'),
                h('span', { class: 'mj-agenda__badge' }, activeCount + '/' + totalLayers)
            ]));
        }

        var body = [];
        body.push(h('div', {
            class: 'mj-agenda__toolbar' + (tb.sticky ? ' is-sticky' : ''),
            hidden: tb.show === false
        }, [
            navGroup,
            periodEl,
            h('div', { class: 'mj-agenda__tb-spacer' }),
            viewSwitcher,
            actions.length ? h('div', { class: 'mj-agenda__tb-group mj-agenda__tb-actions' }, actions) : null
        ]));

        if (notice) {
            body.push(h('div', { class: 'mj-agenda__notice' }, [
                h('span', null, notice),
                h('button', {
                    type: 'button', class: 'mj-agenda__notice-close',
                    'aria-label': 'Fermer', onClick: function () { setNotice(''); }
                }, '×')
            ]));
        }

        if (error) {
            body.push(h('div', { class: 'mj-agenda__error' }, error));
        }

        // -- Filters / legend --------------------------------------------------
        if (tb.filters !== false && totalLayers) {
            var chips = configuredLayers.map(function (layer) {
                var on = filters.indexOf(layer) !== -1;
                var count = counts[layer] || 0;
                return h('button', {
                    type: 'button',
                    class: 'mj-agenda__chip' + (on ? ' is-on' : ''),
                    'aria-pressed': on ? 'true' : 'false',
                    'data-layer': layer,
                    style: '--mj-chip-color:' + ((config.colors && config.colors[layer]) || '#6366f1'),
                    onClick: function () { toggleFilter(layer); }
                }, [
                    h('span', { class: 'mj-agenda__chip-dot', 'aria-hidden': 'true' }),
                    h('span', { class: 'mj-agenda__chip-label' }, LAYER_LABELS[layer] || layer),
                    count ? h('span', { class: 'mj-agenda__chip-count' }, count) : null
                ]);
            });

            body.push(h('div', {
                class: 'mj-agenda__filters' + (filtersOpen ? ' is-open' : '')
            }, [
                h('div', { class: 'mj-agenda__filters-head' }, [
                    h('span', { class: 'mj-agenda__filters-title' }, 'Couches'),
                    h('div', { class: 'mj-agenda__filters-bulk' }, [
                        h('button', {
                            type: 'button',
                            class: 'mj-agenda__link-btn',
                            disabled: activeCount === totalLayers,
                            onClick: function () { setFiltersAnd(configuredLayers.slice()); }
                        }, 'Tout'),
                        h('button', {
                            type: 'button',
                            class: 'mj-agenda__link-btn',
                            disabled: activeCount === 0,
                            onClick: function () { setFiltersAnd([]); }
                        }, 'Aucun')
                    ])
                ]),
                h('div', { class: 'mj-agenda__chips' }, chips)
            ]));
        } else if (tb.legend) {
            body.push(h('div', { class: 'mj-agenda__legend' }, configuredLayers.map(function (layer) {
                return h('span', {
                    class: 'mj-agenda__legend-item',
                    style: '--mj-chip-color:' + ((config.colors && config.colors[layer]) || '#6366f1')
                }, [
                    h('span', { class: 'mj-agenda__chip-dot', 'aria-hidden': 'true' }),
                    LAYER_LABELS[layer] || layer
                ]);
            })));
        }

        body.push(h('div', {
            class: 'mj-agenda__calendar sx-react-calendar-wrapper',
            ref: hostRef
        }));

        if (preview) {
            body.push(h('div', { class: 'mj-agenda__overlay', onClick: function () { setPreview(null); } }));
            body.push(h(EntryPreview, { entry: preview, onClose: function () { setPreview(null); } }));
        }

        return h(Fragment, null, body);
    }

    function boot(rootEl) {
        var raw = rootEl.getAttribute('data-config');
        var config;
        try {
            config = JSON.parse(raw || '{}');
        } catch (e) {
            if (global.console) { console.error('[MjAgenda] bad data-config', e); }
            return;
        }

        // merge runtime session data (nonce/ajaxUrl authoritative from localize)
        config.ajaxUrl = runtime.ajaxUrl || config.ajaxUrl;
        config.nonce = runtime.nonce || config.nonce;

        if (config.isPreview) {
            return; // static markup from the PHP template is enough in the editor
        }

        var acl = global.MjAgendaAcl.createAcl(config.acl || {});
        var api = global.MjAgendaServices.createApiService(config);

        var bootEl = rootEl.querySelector('[data-boot]');
        var mountEl = rootEl.querySelector('[data-agenda-root]');
        if (!mountEl) { return; }
        if (bootEl) { bootEl.hidden = true; }
        mountEl.hidden = false;

        rootEl.classList.remove('mj-agenda--booting');
        rootEl.classList.add('mj-agenda--ready');

        if (config.height && config.height.mode === 'fixed' && config.height.fixed) {
            rootEl.classList.add('mj-agenda--height-fixed');
            rootEl.style.setProperty('--mj-agenda-fixed-height', config.height.fixed + 'px');
        }

        render(h(AgendaApp, { config: config, acl: acl, api: api }), mountEl);
    }

    function initAll() {
        var nodes = document.querySelectorAll('[data-mj-agenda]');
        for (var i = 0; i < nodes.length; i++) {
            if (!nodes[i].__mjAgendaBooted) {
                nodes[i].__mjAgendaBooted = true;
                boot(nodes[i]);
            }
        }
    }

    var domReady = Utils.domReady || function (cb) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', cb);
        } else {
            cb();
        }
    };
    domReady(initAll);

    if (typeof jQuery !== 'undefined') {
        jQuery(global).on('elementor/frontend/init', function () {
            if (global.elementorFrontend && global.elementorFrontend.hooks) {
                global.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-agenda.default', initAll);
            }
        });
    }
})(window);
