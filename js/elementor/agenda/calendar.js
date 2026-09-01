/**
 * Agenda widget — Schedule-X wrapper.
 *
 * Wraps window.MjScheduleX (vendored bundle) behind a small stable API used by
 * the Preact shell. Also owns the data adapters (backend layer entries <-> the
 * Schedule-X event shape).
 *
 * Exposed as window.MjAgendaCalendar.
 */
(function (global) {
    'use strict';

    var LAYER_KEYS = [
        'event_occurrences', 'todos', 'leave_requests', 'work_schedules',
        'worked_hours', 'requests', 'internal_notes'
    ];

    function hexToRgba(hex, alpha) {
        var h = (hex || '').replace('#', '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length !== 6) {
            return 'rgba(99,102,241,' + alpha + ')';
        }
        var r = parseInt(h.slice(0, 2), 16);
        var g = parseInt(h.slice(2, 4), 16);
        var b = parseInt(h.slice(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function buildCalendars(colors) {
        var out = {};
        LAYER_KEYS.forEach(function (layer) {
            var main = (colors && colors[layer]) || '#6366f1';
            out[layer] = {
                colorName: layer,
                lightColors: { main: main, container: hexToRgba(main, 0.16), onContainer: '#1e293b' },
                darkColors: { main: main, container: hexToRgba(main, 0.32), onContainer: '#e2e8f0' }
            };
        });
        return out;
    }

    // Schedule-X requires event ids usable in document.querySelector (CSS idents).
    // Our backend ids look like "occurrence:146:1788188400" — swap the invalid
    // chars; the authoritative identity is kept on evt._mj anyway.
    function safeId(raw) {
        return String(raw == null ? '' : raw).replace(/[^A-Za-z0-9_-]/g, '_');
    }

    /**
     * Backend entry -> Schedule-X event.
     */
    function toScheduleXEvent(raw) {
        var evt = {
            id: safeId(raw.id),
            title: raw.title || '',
            start: raw.start,
            end: raw.end || raw.start,
            calendarId: raw.layer
        };
        if (raw.color) {
            evt._customColor = raw.color;
        }
        var opts = {};
        if (!raw.movable) { opts.disableDND = true; }
        if (!raw.editable || raw.allDay) { opts.disableResize = true; }
        if (Object.keys(opts).length) {
            evt._options = opts;
        }
        // keep the full payload around for previews / write dispatch
        evt._mj = raw;
        return evt;
    }

    // Schedule-X only accepts locales matching /^[a-z]{2}-[A-Z]{2}$/ and ships a
    // fixed set of translation bundles. WordPress hands us "fr_BE" style strings,
    // so map the language onto the closest bundled locale (fr_BE -> fr-FR).
    var BUNDLED_LOCALES = {
        fr: 'fr-FR', nl: 'nl-NL', en: 'en-GB', de: 'de-DE', es: 'es-ES',
        it: 'it-IT', pt: 'pt-BR', ru: 'ru-RU', pl: 'pl-PL'
    };

    function normalizeLocale(raw) {
        var loc = String(raw || '').replace('_', '-');
        var lang = loc.toLowerCase().split('-')[0];
        if (BUNDLED_LOCALES[lang]) {
            return BUNDLED_LOCALES[lang];
        }
        if (/^[a-z]{2}-[A-Z]{2}$/.test(loc)) {
            return loc;
        }
        var parts = loc.toLowerCase().split('-');
        if (parts[0] && parts[0].length === 2) {
            var region = (parts[1] && parts[1].length === 2) ? parts[1].toUpperCase() : parts[0].toUpperCase();
            return parts[0] + '-' + region;
        }
        return 'fr-FR';
    }

    function pickViews(SX, requested) {
        var views = [];
        (requested || ['month-grid', 'week', 'day', 'list']).forEach(function (key) {
            var factory = SX.views[key];
            if (typeof factory === 'function') {
                views.push(factory());
            }
        });
        if (!views.length) {
            views.push(SX.views['month-grid']());
        }
        return views;
    }

    function create(config, hooks) {
        var SX = global.MjScheduleX;
        if (!SX || typeof SX.createCalendar !== 'function') {
            throw new Error('[MjAgenda] Schedule-X bundle not loaded.');
        }
        hooks = hooks || {};

        var eventsService = SX.plugins.eventsService();
        var controls = SX.plugins.calendarControls();
        var plugins = [eventsService, controls];

        if (config.interactions && config.interactions.dnd) {
            plugins.push(SX.plugins.dragAndDrop());
        }
        if (config.interactions && config.interactions.resize) {
            plugins.push(SX.plugins.resize());
        }
        if (config.grid && config.grid.nowIndicator) {
            plugins.push(SX.plugins.currentTime({ fullWeekWidth: true }));
        }

        var views = pickViews(SX, config.views);
        var defaultViewName = config.defaultView;
        var isMobile = global.matchMedia && global.matchMedia('(max-width: 768px)').matches;
        if (isMobile && config.defaultViewMobile) {
            defaultViewName = config.defaultViewMobile;
        }
        if (!views.some(function (v) { return v.name === defaultViewName; })) {
            defaultViewName = views[0].name;
        }

        var calendarConfig = {
            views: views,
            defaultView: defaultViewName,
            events: [],
            calendars: buildCalendars(config.colors),
            locale: normalizeLocale(config.locale),
            firstDayOfWeek: (config.grid && config.grid.firstDay === 0) ? 0 : 1,
            plugins: plugins,
            callbacks: {
                // Schedule-X's built-in rule collapses month-grid into a vertical
                // agenda when the wrapper is < ~700px — and it also fires when the
                // wrapper is measured at 0px before layout settles, which is why
                // the grid was rendering "as a list". Only treat genuinely narrow
                // (phone-ish) containers as small; ignore the not-yet-laid-out 0.
                isCalendarSmall: function ($app) {
                    var el = $app && $app.elements && $app.elements.calendarWrapper;
                    var w = el ? el.clientWidth : 0;
                    return w > 0 && w < 560;
                },
                onRangeUpdate: function (range) {
                    if (hooks.onRangeChange) { hooks.onRangeChange(range); }
                },
                onEventClick: function (calendarEvent, e) {
                    if (hooks.onEventClick) { hooks.onEventClick(calendarEvent && calendarEvent._mj, calendarEvent, e); }
                },
                onEventUpdate: function (updatedEvent) {
                    if (hooks.onEventUpdate) { hooks.onEventUpdate(updatedEvent && updatedEvent._mj, updatedEvent); }
                },
                onClickDate: function (date) {
                    if (hooks.onClickDate) { hooks.onClickDate(date); }
                },
                onClickDateTime: function (dateTime) {
                    if (hooks.onClickDateTime) { hooks.onClickDateTime(dateTime); }
                }
            }
        };

        if (config.grid && config.grid.dayStart && config.grid.dayEnd) {
            calendarConfig.dayBoundaries = { start: config.grid.dayStart, end: config.grid.dayEnd };
        }
        if (config.grid && config.grid.weekends === false) {
            calendarConfig.weekOptions = calendarConfig.weekOptions || {};
            calendarConfig.weekOptions.nDays = 5;
        }

        var calendar = SX.createCalendar(calendarConfig);

        return {
            calendar: calendar,
            render: function (el) {
                // Render after layout has settled so Schedule-X's initial size
                // measurement sees a real width (see isCalendarSmall above).
                var raf = global.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); };
                raf(function () {
                    raf(function () {
                        calendar.render(el);
                        // Nudge Schedule-X to (re)evaluate its size once mounted.
                        if (global.dispatchEvent) {
                            try { global.dispatchEvent(new Event('resize')); } catch (e) {}
                        }
                    });
                });
            },
            destroy: function () {
                if (typeof calendar.destroy === 'function') {
                    calendar.destroy();
                }
            },
            setEvents: function (rawEvents) {
                var mapped = (rawEvents || []).map(toScheduleXEvent);
                eventsService.set(mapped);
            },
            getRange: function () {
                try { return controls.getRange(); } catch (e) { return null; }
            },
            setView: function (name) {
                try { controls.setView(name); } catch (e) {}
            },
            setDate: function (isoDate) {
                try { controls.setDate(isoDate); } catch (e) {}
            }
        };
    }

    global.MjAgendaCalendar = {
        create: create,
        toScheduleXEvent: toScheduleXEvent,
        LAYER_KEYS: LAYER_KEYS
    };
})(window);
