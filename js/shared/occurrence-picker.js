/**
 * Occurrence Picker - Composant partagé de sélection de dates/récurrence.
 *
 * Modes : single (date unique), range (plage de dates), weekly (jours de semaine
 * récurrents sur une plage), monthly (Nième jour de semaine du mois), multiple
 * (liste de dates ajoutées une à une).
 *
 * Aligné sur le vocabulaire et la règle mensuelle du gestionnaire d'occurrences
 * des événements (js/registration-manager/occurrence-editor.js : modes
 * range/weekly/monthly, clés de jour mon..sun, "Nième <jour> du mois") pour rester
 * cohérent avec l'expérience déjà connue des utilisateurs, tout en restant un
 * composant léger et autonome utilisable par les widgets Notes et Tâches.
 */
(function (global) {
    'use strict';

    var preact = global.preact;
    if (!preact) {
        if (typeof console !== 'undefined') {
            console.warn('[MjOccurrencePicker] Preact doit être chargé avant occurrence-picker.js.');
        }
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;

    var WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    var WEEKDAY_LABELS = { mon: 'Lun', tue: 'Mar', wed: 'Mer', thu: 'Jeu', fri: 'Ven', sat: 'Sam', sun: 'Dim' };
    var WEEKDAY_TO_JS_INDEX = { mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6, sun: 0 };
    var MONTHLY_ORDINALS = [
        { value: 'first', label: '1er' },
        { value: 'second', label: '2e' },
        { value: 'third', label: '3e' },
        { value: 'fourth', label: '4e' },
        { value: 'last', label: 'Dernier' },
    ];

    var WEEKLY_HARD_CAP = 366;
    var RANGE_HARD_CAP = 366;
    var MONTHLY_HARD_CAP = 60;
    var MONTHLY_OPEN_ENDED_LIMIT = 12;
    var RANGE_RECURRENCE_HARD_CAP = 60;
    var RANGE_RECURRENCE_OPEN_ENDED_LIMIT = 12;

    var RANGE_RECURRENCE_OPTIONS = [
        { value: 'every_two_weeks', label: 'Une semaine sur deux' },
        { value: 'monthly', label: 'Une fois par mois' },
        { value: 'yearly', label: 'Une fois par an' },
    ];

    function shiftDateByRecurrence(date, freq, step) {
        if (freq === 'monthly') {
            return new Date(date.getFullYear(), date.getMonth() + step, date.getDate());
        }
        if (freq === 'yearly') {
            return new Date(date.getFullYear() + step, date.getMonth(), date.getDate());
        }
        // every_two_weeks
        return addDays(date, step * 14);
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function toIsoDate(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function parseIsoDate(value) {
        var parts = String(value || '').split('-');
        if (parts.length !== 3) return null;
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return isNaN(d.getTime()) ? null : d;
    }

    function addDays(date, count) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate() + count);
    }

    function resolveMonthlyOrdinalValue(key) {
        switch (key) {
            case 'second': return 2;
            case 'third': return 3;
            case 'fourth': return 4;
            case 'last': return 'last';
            case 'first':
            default: return 1;
        }
    }

    function findNthWeekdayOfMonth(baseMonthDate, weekdayIndex, ordinalKey) {
        var year = baseMonthDate.getFullYear();
        var month = baseMonthDate.getMonth();
        var ordinalValue = resolveMonthlyOrdinalValue(ordinalKey);

        if (ordinalValue === 'last') {
            var lastDay = new Date(year, month + 1, 0);
            var adjustment = (lastDay.getDay() - weekdayIndex + 7) % 7;
            return new Date(year, month + 1, 0 - adjustment);
        }

        var firstOfMonth = new Date(year, month, 1);
        var offset = (weekdayIndex - firstOfMonth.getDay() + 7) % 7;
        var day = 1 + offset + 7 * (ordinalValue - 1);
        var candidate = new Date(year, month, day);
        if (candidate.getMonth() !== month) return null;
        return candidate;
    }

    /**
     * Calcule la liste des dates (ISO, triées, dédupliquées) résultant d'un mode
     * et de ses paramètres.
     * @param {string} mode
     * @param {object} params
     * @return {string[]}
     */
    function resolveOccurrenceDates(mode, params) {
        params = params || {};
        var dates = [];

        if (mode === 'single') {
            if (params.singleDate) dates.push(params.singleDate);
        } else if (mode === 'multiple') {
            dates = (params.multipleDates || []).slice();
        } else if (mode === 'range') {
            var rStart = parseIsoDate(params.rangeStart);
            var rEnd = parseIsoDate(params.rangeEnd) || rStart;
            if (rStart) {
                if (rEnd < rStart) { var tmp = rStart; rStart = rEnd; rEnd = tmp; }
                var spanDays = Math.round((rEnd - rStart) / 86400000);
                var recurrenceUntil = params.recurrenceEnabled ? parseIsoDate(params.recurrenceEnd) : null;
                var recurrenceFreq = params.recurrenceFreq || 'every_two_weeks';
                var recurrenceCap = recurrenceUntil ? RANGE_RECURRENCE_HARD_CAP : RANGE_RECURRENCE_OPEN_ENDED_LIMIT;
                var step = 0;

                while (step < (params.recurrenceEnabled ? recurrenceCap : 1)) {
                    var blockStart = step === 0 ? rStart : shiftDateByRecurrence(rStart, recurrenceFreq, step);
                    if (recurrenceUntil && blockStart > recurrenceUntil) break;

                    var rCursor = blockStart;
                    var blockEnd = addDays(blockStart, spanDays);
                    var rGuard = 0;
                    while (rCursor <= blockEnd && rGuard < RANGE_HARD_CAP) {
                        dates.push(toIsoDate(rCursor));
                        rCursor = addDays(rCursor, 1);
                        rGuard += 1;
                    }

                    step += 1;
                }
            }
        } else if (mode === 'weekly') {
            var wStart = parseIsoDate(params.weeklyStart);
            var wEnd = parseIsoDate(params.weeklyEnd);
            var weekdays = params.weeklyDays || [];
            if (wStart && wEnd && weekdays.length) {
                if (wEnd < wStart) { var tmp2 = wStart; wStart = wEnd; wEnd = tmp2; }
                var set = {};
                weekdays.forEach(function (k) { set[k] = true; });
                var wCursor = wStart;
                var wGuard = 0;
                while (wCursor <= wEnd && wGuard < WEEKLY_HARD_CAP) {
                    var key = WEEKDAY_KEYS[(wCursor.getDay() + 6) % 7];
                    if (set[key]) dates.push(toIsoDate(wCursor));
                    wCursor = addDays(wCursor, 1);
                    wGuard += 1;
                }
            }
        } else if (mode === 'monthly') {
            var mStart = parseIsoDate(params.monthlyStart);
            var mEnd = parseIsoDate(params.monthlyEnd);
            var weekdayIndex = WEEKDAY_TO_JS_INDEX[params.monthlyWeekday] !== undefined
                ? WEEKDAY_TO_JS_INDEX[params.monthlyWeekday]
                : 1;
            var ordinal = params.monthlyOrdinal || 'first';
            if (mStart) {
                var monthCursor = new Date(mStart.getFullYear(), mStart.getMonth(), 1);
                var mGuard = 0;
                while (mGuard < MONTHLY_HARD_CAP) {
                    var candidate = findNthWeekdayOfMonth(monthCursor, weekdayIndex, ordinal);
                    if (candidate && candidate >= mStart && (!mEnd || candidate <= mEnd)) {
                        dates.push(toIsoDate(candidate));
                    }
                    if (mEnd && monthCursor > mEnd) break;
                    if (!mEnd && dates.length >= MONTHLY_OPEN_ENDED_LIMIT) break;
                    monthCursor = new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 1);
                    mGuard += 1;
                }
            }
        }

        var unique = {};
        dates.forEach(function (d) { unique[d] = true; });
        return Object.keys(unique).sort();
    }

    function defaultOccurrenceValue(overrides) {
        return Object.assign({
            mode: 'single',
            singleDate: '',
            rangeStart: '',
            rangeEnd: '',
            recurrenceEnabled: false,
            recurrenceFreq: 'every_two_weeks',
            recurrenceEnd: '',
            weeklyStart: '',
            weeklyEnd: '',
            weeklyDays: [],
            monthlyStart: '',
            monthlyEnd: '',
            monthlyOrdinal: 'first',
            monthlyWeekday: 'mon',
            multipleDates: [],
            pendingDate: '',
        }, overrides || {});
    }

    var MODE_LABELS = {
        single: 'Date unique',
        range: 'Plage de dates',
        weekly: 'Hebdomadaire',
        monthly: 'Mensuel',
        multiple: 'Dates multiples',
    };

    /**
     * @param {object} props
     * @param {object} props.value - voir defaultOccurrenceValue()
     * @param {function} props.onChange - (nextValue) => void
     * @param {boolean} [props.disableModeChange]
     * @param {string[]} [props.modes] - sous-ensemble des modes à proposer
     * @param {string} [props.classPrefix]
     * @param {object} [props.strings] - overrides de libellés { modeSingle, modeRange, modeWeekly, modeMonthly, modeMultiple, addDate }
     */
    function OccurrencePicker(props) {
        var value = defaultOccurrenceValue(props.value);
        var onChange = props.onChange;
        var disableModeChange = !!props.disableModeChange;
        var modes = props.modes || ['single', 'range', 'weekly', 'monthly', 'multiple'];
        var cls = props.classPrefix || 'mj-occurrence-picker';
        var strings = props.strings || {};
        var mode = value.mode;

        function patch(partial) {
            if (typeof onChange === 'function') {
                onChange(Object.assign({}, value, partial));
            }
        }

        function label(key) {
            var stringKey = 'mode' + key.charAt(0).toUpperCase() + key.slice(1);
            return strings[stringKey] || MODE_LABELS[key] || key;
        }

        var modeSelector = !disableModeChange && h('div', { class: cls + '__mode' }, modes.map(function (m) {
            return h('label', { key: m, class: cls + '__mode-option' }, [
                h('input', {
                    type: 'radio',
                    checked: mode === m,
                    onChange: function () { patch({ mode: m }); },
                }),
                ' ' + label(m),
            ]);
        }));

        var fields = null;

        if (mode === 'single') {
            fields = h('input', {
                type: 'date',
                class: 'mj-regmgr-form__input',
                value: value.singleDate || '',
                onChange: function (e) { patch({ singleDate: e.target.value }); },
            });
        } else if (mode === 'range') {
            fields = h(Fragment, null, [
                h('div', { class: cls + '__range' }, [
                    h('label', null, [
                        strings.rangeFrom || 'Du ',
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-form__input',
                            value: value.rangeStart || '',
                            onChange: function (e) { patch({ rangeStart: e.target.value }); },
                        }),
                    ]),
                    h('label', null, [
                        strings.rangeTo || 'au ',
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-form__input',
                            value: value.rangeEnd || '',
                            onChange: function (e) { patch({ rangeEnd: e.target.value }); },
                        }),
                    ]),
                ]),
                h('div', { class: cls + '__recurrence' }, [
                    h('label', { class: cls + '__recurrence-toggle' }, [
                        h('input', {
                            type: 'checkbox',
                            checked: value.recurrenceEnabled,
                            onChange: function (e) { patch({ recurrenceEnabled: e.target.checked }); },
                        }),
                        ' ' + (strings.recurrence || 'Récurrence'),
                    ]),
                    value.recurrenceEnabled && h('div', { class: cls + '__recurrence-options' }, [
                        h('select', {
                            class: 'mj-regmgr-form__input',
                            value: value.recurrenceFreq,
                            onChange: function (e) { patch({ recurrenceFreq: e.target.value }); },
                        }, RANGE_RECURRENCE_OPTIONS.map(function (opt) {
                            return h('option', { value: opt.value }, strings['recurrence_' + opt.value] || opt.label);
                        })),
                        h('label', null, [
                            strings.recurrenceUntil || 'Jusqu\'au (optionnel) ',
                            h('input', {
                                type: 'date',
                                class: 'mj-regmgr-form__input',
                                value: value.recurrenceEnd || '',
                                onChange: function (e) { patch({ recurrenceEnd: e.target.value }); },
                            }),
                        ]),
                    ]),
                ]),
            ]);
        } else if (mode === 'weekly') {
            fields = h(Fragment, null, [
                h('div', { class: cls + '__weekdays' }, WEEKDAY_KEYS.map(function (key) {
                    var active = value.weeklyDays.indexOf(key) >= 0;
                    return h('button', {
                        key: key,
                        type: 'button',
                        class: cls + '__weekday' + (active ? ' ' + cls + '__weekday--active' : ''),
                        onClick: function () {
                            var next = active
                                ? value.weeklyDays.filter(function (d) { return d !== key; })
                                : value.weeklyDays.concat([key]);
                            patch({ weeklyDays: next });
                        },
                    }, WEEKDAY_LABELS[key]);
                })),
                h('div', { class: cls + '__range' }, [
                    h('label', null, [
                        strings.rangeFrom || 'Du ',
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-form__input',
                            value: value.weeklyStart || '',
                            onChange: function (e) { patch({ weeklyStart: e.target.value }); },
                        }),
                    ]),
                    h('label', null, [
                        strings.rangeTo || 'au ',
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-form__input',
                            value: value.weeklyEnd || '',
                            onChange: function (e) { patch({ weeklyEnd: e.target.value }); },
                        }),
                    ]),
                ]),
            ]);
        } else if (mode === 'monthly') {
            fields = h(Fragment, null, [
                h('div', { class: cls + '__monthly-rule' }, [
                    h('select', {
                        class: 'mj-regmgr-form__input',
                        value: value.monthlyOrdinal,
                        onChange: function (e) { patch({ monthlyOrdinal: e.target.value }); },
                    }, MONTHLY_ORDINALS.map(function (opt) {
                        return h('option', { value: opt.value }, opt.label);
                    })),
                    h('select', {
                        class: 'mj-regmgr-form__input',
                        value: value.monthlyWeekday,
                        onChange: function (e) { patch({ monthlyWeekday: e.target.value }); },
                    }, WEEKDAY_KEYS.map(function (key) {
                        return h('option', { value: key }, WEEKDAY_LABELS[key]);
                    })),
                    h('span', null, strings.monthlyOfEachMonth || 'du mois'),
                ]),
                h('div', { class: cls + '__range' }, [
                    h('label', null, [
                        strings.rangeFrom || 'Du ',
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-form__input',
                            value: value.monthlyStart || '',
                            onChange: function (e) { patch({ monthlyStart: e.target.value }); },
                        }),
                    ]),
                    h('label', null, [
                        strings.rangeToOptional || 'au (optionnel) ',
                        h('input', {
                            type: 'date',
                            class: 'mj-regmgr-form__input',
                            value: value.monthlyEnd || '',
                            onChange: function (e) { patch({ monthlyEnd: e.target.value }); },
                        }),
                    ]),
                ]),
            ]);
        } else if (mode === 'multiple') {
            fields = h(Fragment, null, [
                h('div', { class: cls + '__multi-add' }, [
                    h('input', {
                        type: 'date',
                        class: 'mj-regmgr-form__input',
                        value: value.pendingDate || '',
                        onChange: function (e) { patch({ pendingDate: e.target.value }); },
                    }),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-btn mj-regmgr-btn--secondary',
                        onClick: function () {
                            if (!value.pendingDate) return;
                            if (value.multipleDates.indexOf(value.pendingDate) >= 0) {
                                patch({ pendingDate: '' });
                                return;
                            }
                            patch({
                                multipleDates: value.multipleDates.concat([value.pendingDate]).sort(),
                                pendingDate: '',
                            });
                        },
                    }, strings.addDate || 'Ajouter'),
                ]),
                h('div', { class: cls + '__chips' }, value.multipleDates.map(function (d) {
                    return h('span', { key: d, class: cls + '__chip' }, [
                        d,
                        h('button', {
                            type: 'button',
                            onClick: function () {
                                patch({ multipleDates: value.multipleDates.filter(function (x) { return x !== d; }) });
                            },
                        }, '×'),
                    ]);
                })),
            ]);
        }

        return h('div', { class: cls }, [modeSelector, fields]);
    }

    global.MjOccurrencePicker = {
        OccurrencePicker: OccurrencePicker,
        resolveOccurrenceDates: resolveOccurrenceDates,
        defaultOccurrenceValue: defaultOccurrenceValue,
        WEEKDAY_KEYS: WEEKDAY_KEYS,
        WEEKDAY_LABELS: WEEKDAY_LABELS,
        MONTHLY_ORDINALS: MONTHLY_ORDINALS,
        RANGE_RECURRENCE_OPTIONS: RANGE_RECURRENCE_OPTIONS,
    };

})(window);
