/**
 * MJ Member – Hours Dashboard front-end Elementor widget.
 *
 * Preact-based UI for coordinators to review hour statistics.
 * Completely self-contained IIFE — loads Preact + Chart.js from CDN if needed.
 */

/* global preact, preactHooks */

(function () {
    'use strict';

    /* ====================================================================
     *  Preact & Chart.js bootstrap
     * ==================================================================== */

    var _p = {};          // { h, render, useState, useEffect, useMemo, useRef, useCallback }
    var _preactReady = null;
    var _chartReady = null;
    var _scriptCache = {};

    var PREACT_ID   = 'mj-member-preact-lib';
    var HOOKS_ID    = 'mj-member-preact-hooks';
    var CHART_ID    = 'mj-member-chartjs';
    var PREACT_URL  = 'https://unpkg.com/preact@10.19.3/dist/preact.min.js';
    var HOOKS_URL   = 'https://unpkg.com/preact@10.19.3/hooks/dist/hooks.umd.js';
    var CHART_URL   = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js';

    function loadScript(id, url) {
        if (_scriptCache[id]) return _scriptCache[id];
        if (document.getElementById(id)) { _scriptCache[id] = Promise.resolve(); return _scriptCache[id]; }
        _scriptCache[id] = new Promise(function (ok, fail) {
            var s = document.createElement('script');
            s.id = id; s.src = url; s.async = true;
            s.onload = ok; s.onerror = function () { fail(new Error('load ' + url)); };
            document.head.appendChild(s);
        });
        return _scriptCache[id];
    }

    function ensurePreact() {
        if (_p.h) return Promise.resolve();
        if (!_preactReady) {
            _preactReady = Promise.resolve()
                .then(function () { if (window.preact) return; return loadScript(PREACT_ID, PREACT_URL); })
                .then(function () { if (window.preactHooks) return; return loadScript(HOOKS_ID, HOOKS_URL); })
                .then(function () {
                    var pr = window.preact; var hk = window.preactHooks;
                    if (!pr || !hk) throw new Error('Preact introuvable');
                    _p.h = pr.h; _p.render = pr.render;
                    _p.useState = hk.useState; _p.useEffect = hk.useEffect;
                    _p.useMemo = hk.useMemo; _p.useRef = hk.useRef; _p.useCallback = hk.useCallback;
                });
        }
        return _preactReady;
    }

    function ensureChart() {
        if (window.Chart) return Promise.resolve(window.Chart);
        if (!_chartReady) {
            _chartReady = loadScript(CHART_ID, CHART_URL)
                .then(function () { if (!window.Chart) throw new Error('Chart.js introuvable'); return window.Chart; });
        }
        return _chartReady;
    }

    /* ====================================================================
     *  Helpers
     * ==================================================================== */

    var PALETTE = ['#6366f1','#22c55e','#f97316','#a855f7','#ef4444','#14b8a6','#f59e0b','#3b82f6','#ec4899','#06b6d4'];
    var REQUIRED_COLOR = '#3b82f6';
    var EXTRA_COLOR    = '#f97316';

    function clr(i) { return PALETTE[i % PALETTE.length]; }

    function fmtMin(m) {
        m = Math.max(Math.round(m || 0), 0);
        if (m === 0) return '0 min';
        var h = Math.floor(m / 60); var r = m % 60;
        if (h === 0) return r + ' min';
        if (r === 0) return h + ' h';
        return h + ' h ' + r + ' min';
    }

    function fmtSigned(m) {
        if (!Number.isFinite(m) || m === 0) return '0 min';
        return (m > 0 ? '+' : '-') + fmtMin(Math.abs(m));
    }

    function fmtPct(v) { return Number.isFinite(v) ? v.toFixed(1) + ' %' : '0 %'; }

    function parseJSON(s) { try { return JSON.parse(s); } catch (_) { return {}; } }

    /* ====================================================================
     *  Chart.js hook
     * ==================================================================== */

    function useChart(canvasRef, type, data, options) {
        var stateRef = _p.useRef({ chart: null });

        _p.useEffect(function () {
            var alive = true;
            var st = stateRef.current;
            function kill() { if (st.chart) { try { st.chart.destroy(); } catch (_) {} st.chart = null; } }

            if (!canvasRef || !canvasRef.current || !type || !data) { kill(); return function () { alive = false; kill(); }; }

            ensureChart().then(function (Chart) {
                if (!alive || !canvasRef.current) return;
                kill();
                try { st.chart = new Chart(canvasRef.current, { type: type, data: data, options: options || {} }); } catch (e) { console.error('[MJ-HD] chart', e); }
            });

            return function () { alive = false; kill(); };
        }, [canvasRef, type, data, options]);
    }

    /* ====================================================================
     *  KPI strip
     * ==================================================================== */

    function KpiStrip(props) {
        var h = _p.h;
        var t = props.totals || {};
        var i = props.i18n || {};
        var entries = t.entries || 0;

        var cards = [
            { accent: 'blue',   label: i.totalHours || 'Heures totales',        value: t.human || '0', meta: entries > 0 ? entries + ' ' + (i.entriesLabel || 'encodages') : '' },
            { accent: 'green',  label: i.membersCount || 'Membres',              value: String(t.member_count || 0), meta: (t.project_count || 0) + ' ' + (i.projectsCount || 'projets') },
            { accent: 'amber',  label: i.averageWeeklyHours || 'Moy. hebdo.',    value: t.weekly_average_human || '0', meta: t.weekly_average_meta || '' },
            { accent: 'violet', label: i.weeklyExpectedLabel || 'Heures attendues', value: t.weekly_contract_human || '0', meta: '' },
            { accent: 'rose',   label: i.weeklyBalanceNetLabel || 'Solde cumulé', value: t.weekly_balance_human || '0', meta: '' },
        ];

        return h('div', { className: 'mj-hd__kpi-strip' },
            cards.map(function (c) {
                return h('div', { key: c.label, className: 'mj-hd-kpi mj-hd-kpi--accent-' + c.accent },
                    h('p', { className: 'mj-hd-kpi__label' }, c.label),
                    h('p', { className: 'mj-hd-kpi__value' }, c.value),
                    c.meta ? h('p', { className: 'mj-hd-kpi__meta' }, c.meta) : null
                );
            })
        );
    }

    /* ====================================================================
     *  Member selector
     * ==================================================================== */

    function MemberSelector(props) {
        var h = _p.h;
        var members = Array.isArray(props.members) ? props.members : [];
        if (members.length === 0) return null;

        return h('div', { className: 'mj-hd__member-tabs' },
            h('div', { className: 'mj-hd__member-tabs-scroll', role: 'tablist' },
                members.map(function (m) {
                    var active = m.id === props.selectedId;
                    return h('button', {
                        key: m.id,
                        type: 'button',
                        role: 'tab',
                        'aria-selected': String(active),
                        className: 'mj-hd__member-tab' + (active ? ' mj-hd__member-tab--active' : ''),
                        onClick: function () { props.onChange(m.id); },
                    }, m.label || '');
                })
            )
        );
    }

    /* ====================================================================
     *  Donut chart
     * ==================================================================== */

    function DonutChart(props) {
        var h = _p.h;
        var items = Array.isArray(props.items) ? props.items : [];
        var total = Math.max(props.totalMinutes || 0, 0);
        var ok = items.length > 0 && total > 0;
        var canvasRef = _p.useRef(null);

        var chartData = _p.useMemo(function () {
            if (!ok) return null;
            return {
                labels: items.map(function (x) { return x.label || ''; }),
                datasets: [{ data: items.map(function (x) { return Math.max(x.minutes || 0, 0); }),
                    backgroundColor: items.map(function (x, i) { return x.color || clr(i); }),
                    borderWidth: 0, hoverOffset: 4 }],
            };
        }, [ok, items]);

        var chartOpts = _p.useMemo(function () {
            if (!ok) return null;
            return {
                responsive: true, maintainAspectRatio: true, cutout: '68%',
                plugins: { legend: { display: false },
                    tooltip: { callbacks: { label: function (ctx) { return (ctx.label || '') + ': ' + fmtMin(ctx.parsed); } } } },
            };
        }, [ok]);

        useChart(canvasRef, ok ? 'doughnut' : null, chartData, chartOpts);

        return h('div', { className: 'mj-hd-card mj-hd-donut' },
            props.title ? h('h2', { className: 'mj-hd-donut__title' }, props.title) : null,
            ok ? h('div', { className: 'mj-hd-donut__canvas-wrap' },
                h('canvas', { ref: canvasRef }),
                h('div', { className: 'mj-hd-donut__center' },
                    h('p', { className: 'mj-hd-donut__center-value' }, props.centerValue || '0'),
                    props.centerLabel ? h('p', { className: 'mj-hd-donut__center-label' }, props.centerLabel) : null
                )
            ) : h('p', { className: 'mj-hd__empty' }, props.emptyLabel || ''),
            ok ? h('ul', { className: 'mj-hd-donut__legend' },
                items.map(function (item, idx) {
                    var c = item.color || clr(idx);
                    return h('li', { key: item.key || idx, className: 'mj-hd-donut__legend-item' },
                        h('span', { className: 'mj-hd-donut__legend-left' },
                            h('span', { className: 'mj-hd-donut__legend-swatch', style: { background: c } }),
                            h('span', { className: 'mj-hd-donut__legend-name' }, item.label || '')
                        ),
                        h('span', { className: 'mj-hd-donut__legend-value' }, item.human || '0')
                    );
                })
            ) : null
        );
    }

    /* ====================================================================
     *  Bar chart
     * ==================================================================== */

    function BarChart(props) {
        var h = _p.h;
        var items = Array.isArray(props.items) ? props.items : [];
        var ok = items.length > 0;
        var i18n = props.i18n || {};
        var canvasRef = _p.useRef(null);

        var hasSegments = _p.useMemo(function () {
            return ok && items.some(function (x) { return typeof x.required_minutes === 'number' && typeof x.extra_minutes === 'number'; });
        }, [ok, items]);

        var chartData = _p.useMemo(function () {
            if (!ok) return null;
            var labels = items.map(function (x) { return x.short_label || x.label || ''; });
            if (hasSegments) {
                return { labels: labels, datasets: [
                    { label: i18n.weeklyRequiredLabel || 'Heures dues', data: items.map(function (x) { return Math.max(x.required_minutes || 0, 0); }),
                      backgroundColor: REQUIRED_COLOR, stack: 'h', borderRadius: 6, maxBarThickness: 40 },
                    { label: i18n.weeklyExtraLabel || 'Supplémentaires', data: items.map(function (x) { return Math.max(x.extra_minutes || 0, 0); }),
                      backgroundColor: EXTRA_COLOR, stack: 'h', borderRadius: 6, maxBarThickness: 40 },
                ] };
            }
            return { labels: labels, datasets: [{ label: props.datasetLabel || props.title || '',
                data: items.map(function (x) { return Math.max(x.minutes || 0, 0); }),
                backgroundColor: items.map(function (_, i) { return clr(i); }),
                borderRadius: 6, maxBarThickness: 40 }] };
        }, [ok, hasSegments, items, i18n]);

        var chartOpts = _p.useMemo(function () {
            if (!ok) return null;
            return {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { stacked: hasSegments, grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, font: { size: 11 } } },
                    y: { beginAtZero: true, stacked: hasSegments,
                        grid: { color: 'rgba(148,163,184,0.18)', drawBorder: false },
                        ticks: { callback: function (v) { return fmtMin(v); }, font: { size: 11 } } },
                },
                plugins: {
                    legend: { display: hasSegments, position: 'bottom', labels: { usePointStyle: true, font: { size: 12 } } },
                    tooltip: { callbacks: {
                        label: function (ctx) {
                            var l = hasSegments ? (ctx.dataset.label || '') : (ctx.label || props.title || '');
                            var v = (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y : ctx.parsed;
                            return l ? l + ': ' + fmtMin(v) : fmtMin(v);
                        },
                        afterBody: hasSegments ? function (ctxs) {
                            if (!ctxs.length) return [];
                            var item = items[ctxs[0].dataIndex];
                            if (!item) return [];
                            var lines = [];
                            var exp = Math.max(item.expected_minutes || 0, 0);
                            if (exp > 0) lines.push((i18n.weeklyExpectedLabel || 'Attendues') + ': ' + fmtMin(exp));
                            var diff = item.difference_minutes || 0;
                            if (diff !== 0) {
                                var dl = diff > 0 ? (i18n.weeklyExtraLabel || 'Supp.') : (i18n.weeklyDeficitLabel || 'Manquantes');
                                lines.push(dl + ': ' + fmtSigned(diff));
                            }
                            return lines;
                        } : undefined,
                    } },
                },
            };
        }, [ok, hasSegments, items, i18n]);

        useChart(canvasRef, ok ? 'bar' : null, chartData, chartOpts);

        return h('div', { className: 'mj-hd-card mj-hd-bar' },
            h('div', { className: 'mj-hd-bar__header' },
                props.title ? h('h2', { className: 'mj-hd-bar__title' }, props.title) : null,
                props.subtitle ? h('span', { className: 'mj-hd-bar__subtitle' }, props.subtitle) : null
            ),
            ok ? h('div', { className: 'mj-hd-bar__canvas-wrap' },
                h('canvas', { ref: canvasRef, role: 'img', 'aria-label': props.title || '' })
            ) : h('p', { className: 'mj-hd__empty' }, props.emptyLabel || i18n.barChartEmpty || '')
        );
    }

    /* ====================================================================
     *  Balance chip
     * ==================================================================== */

    function BalanceChip(props) {
        var h = _p.h;
        var minutes = props.minutes;
        var human = props.human || fmtSigned(minutes);
        var variant = minutes > 0 ? 'positive' : minutes < 0 ? 'negative' : 'neutral';
        return h('span', { className: 'mj-hd-balance mj-hd-balance--' + variant }, human);
    }

    /* ====================================================================
     *  Members table
     * ==================================================================== */

    function MembersTable(props) {
        var h = _p.h;
        var members = Array.isArray(props.members) ? props.members : [];
        var totals = props.totals || {};
        var i18n = props.i18n || {};
        if (members.length === 0) return h('p', { className: 'mj-hd__empty' }, i18n.noMemberData || '');

        var maxMinutes = Math.max.apply(null, members.map(function (m) { return m.minutes || 0; }).concat([1]));
        var totalMinutes = Math.max(totals.minutes || 0, 1);

        return h('div', { className: 'mj-hd-card mj-hd-members' },
            h('h2', { className: 'mj-hd-card__heading' }, i18n.memberTableTitle || 'Heures par membre'),
            h('div', { className: 'mj-hd-members__scroll' },
                h('table', null,
                    h('thead', null,
                        h('tr', null,
                            h('th', null, i18n.memberColumn || 'Membre'),
                            h('th', null, i18n.hoursColumn || 'Heures'),
                            h('th', null, i18n.entriesColumn || 'Encodages'),
                            h('th', null, i18n.rateColumn || 'Part'),
                            h('th', null, i18n.weeklyBalanceNetLabel || 'Solde')
                        )
                    ),
                    h('tbody', null,
                        members.map(function (m) {
                            var share = (m.minutes || 0) / totalMinutes;
                            var barW = Math.round(((m.minutes || 0) / maxMinutes) * 100);
                            var balMin = typeof m.weekly_balance_minutes === 'number' ? m.weekly_balance_minutes : null;
                            return h('tr', { key: m.id },
                                h('td', null, h('span', { className: 'mj-hd-members__name' }, m.label || '')),
                                h('td', null,
                                    h('span', { className: 'mj-hd-members__bar', style: { width: barW + '%', minWidth: '4px' } }),
                                    m.human || '0'
                                ),
                                h('td', null, String(m.entries || 0)),
                                h('td', null, fmtPct(share * 100)),
                                h('td', null,
                                    balMin !== null
                                        ? h(BalanceChip, { minutes: balMin, human: m.weekly_balance_human || '' })
                                        : '—'
                                )
                            );
                        })
                    )
                )
            )
        );
    }

    /* ====================================================================
     *  Hour-encode embed
     * ==================================================================== */

    function HourEncodeEmbed(props) {
        var h = _p.h;
        var containerRef = _p.useRef(null);

        _p.useEffect(function () {
            if (!props.config || !props.configJson) return;
            var el = containerRef.current;
            if (!el) return;

            document.dispatchEvent(new CustomEvent('mj-member-hour-encode:destroy', { detail: { context: el } }));
            el.innerHTML = '';

            var w = document.createElement('div');
            w.className = 'mj-hour-encode';
            w.setAttribute('data-config', props.configJson);
            el.appendChild(w);

            document.dispatchEvent(new CustomEvent('mj-member-hour-encode:init', { detail: { context: el } }));

            return function () {
                document.dispatchEvent(new CustomEvent('mj-member-hour-encode:destroy', { detail: { context: el } }));
                el.innerHTML = '';
            };
        }, [props.config, props.configJson]);

        var wrapProps = { className: 'mj-hd-card mj-hd__editor-card' };
        if (props.panelId) wrapProps.id = props.panelId;
        if (props.labelledBy) { wrapProps.role = 'tabpanel'; wrapProps['aria-labelledby'] = props.labelledBy; }

        if (!props.config || !props.configJson) return h('div', wrapProps, h('p', { className: 'mj-hd__empty' }, props.emptyLabel || ''));
        return h('div', wrapProps, h('div', { ref: containerRef }));
    }

    /* ====================================================================
     *  Dashboard App
     * ==================================================================== */

    function DashboardApp(props) {
        var h = _p.h;
        var cfg  = props.config || {};
        var data = cfg.data || {};
        var i18n = cfg.i18n || {};
        var totals = data.totals || {};
        var projects = Array.isArray(data.projects) ? data.projects : [];
        var members  = Array.isArray(data.members) ? data.members : [];
        var ts = data.timeseries || {};
        var monthlyAll  = Array.isArray(ts.months) ? ts.months : [];
        var weeklyAll   = Array.isArray(ts.weeks)  ? ts.weeks  : [];
        var monthlyByM  = ts.months_by_member || {};
        var weeklyByM   = ts.weeks_by_member  || {};
        var showEdit = cfg.showEditTab !== false;

        /* state */
        var selArr = _p.useState(members.length > 0 ? members[0].id : 0);
        var selId = selArr[0]; var setSelId = selArr[1];
        var tabArr = _p.useState('graphs');
        var tab = tabArr[0]; var setTab = tabArr[1];

        /* selected member */
        var selMember = _p.useMemo(function () {
            return members.find(function (m) { return m.id === selId; }) || null;
        }, [members, selId]);

        /* donut data */
        var donutItems, donutTotal, donutValue, donutLabel;
        if (selMember) {
            donutItems = selMember.projects || [];
            donutTotal = Math.max(selMember.minutes || 0, 0);
            donutValue = selMember.human || '0';
            donutLabel = selMember.label || '';
        } else {
            donutItems = projects;
            donutTotal = Math.max(totals.minutes || 0, 0);
            donutValue = totals.human || '0';
            donutLabel = i18n.totalHours || '';
        }

        /* series */
        var monthly = selMember ? (monthlyByM[selId] || []) : monthlyAll;
        var weekly  = selMember ? (weeklyByM[selId]  || []) : weeklyAll;

        /* subtitles */
        var mLabel = selMember ? (selMember.label || '') : '';
        var mHuman = selMember ? (selMember.human || '0') : (totals.human || '');
        var monthSub = selMember ? (mLabel + ' · ' + mHuman) : mHuman;

        var expectedLbl = i18n.weeklyExpectedLabel || '';
        var cMins = selMember ? Math.max(selMember.weekly_contract_minutes || 0, 0) : Math.max(totals.weekly_contract_minutes || 0, 0);
        var cHuman = selMember ? (selMember.weekly_contract_human || '') : (totals.weekly_contract_human || '');
        var weekSub = selMember ? (mLabel + ' · ' + mHuman) : mHuman;
        if (cMins > 0 && expectedLbl && cHuman) weekSub += ' · ' + expectedLbl + ' : ' + cHuman;

        var balLbl = i18n.weeklyBalanceNetLabel || '';
        var balHuman = selMember ? (selMember.weekly_balance_human || '') : (totals.weekly_balance_human || '');
        if (balLbl && balHuman) weekSub += ' · ' + balLbl + ' : ' + balHuman;

        var donutTitle = (selMember && mLabel)
            ? (i18n.memberDonutPrefix || 'Projets de ') + mLabel
            : (i18n.projectsDonutTitle || 'Répartition par projet');

        /* edit tab */
        var hourEncodeBase = cfg.hourEncode || null;
        var canEdit = showEdit && Boolean(hourEncodeBase && selMember && selId);
        _p.useEffect(function () { if (!canEdit && tab !== 'graphs') setTab('graphs'); }, [canEdit, tab]);

        var editConfig = _p.useMemo(function () {
            if (!hourEncodeBase || !selMember || !selId) return null;
            var ajx = Object.assign({}, hourEncodeBase.ajax || {});
            var sp = Object.assign({}, ajx.staticParams || {}); sp.member_id = String(selId); ajx.staticParams = sp;
            var lbl = Object.assign({}, hourEncodeBase.labels || {});
            if (selMember.label) { var bt = (lbl.title || ''); lbl.title = bt ? bt + ' · ' + selMember.label : selMember.label; }
            var projects2 = (selMember.projects || []).map(function (p) { return (p.raw_label || (!p.is_unassigned && p.label) || '').trim(); }).filter(Boolean);
            if (!projects2.length && Array.isArray(hourEncodeBase.projects)) projects2 = hourEncodeBase.projects;
            projects2 = Array.from(new Set(projects2));
            var ws = Array.isArray(selMember.work_schedule) ? selMember.work_schedule : (hourEncodeBase.workSchedule || []);
            var cb = selMember.cumulative_balance || null;
            return Object.assign({}, hourEncodeBase, { ajax: ajx, labels: lbl, projects: projects2, entries: [], events: [], workSchedule: ws, cumulativeBalance: cb, capabilities: Object.assign({}, hourEncodeBase.capabilities || {}, { canManage: true }) });
        }, [hourEncodeBase, selMember, selId]);

        var editJSON = _p.useMemo(function () {
            if (!editConfig) return null;
            try { return JSON.stringify(editConfig); } catch (_) { return null; }
        }, [editConfig]);

        /* render */
        return h('div', { className: 'mj-hd__content' },

            /* KPI strip */
            h(KpiStrip, { totals: totals, i18n: i18n }),

            /* Member selector */
            h(MemberSelector, {
                members: members, selectedId: selId, onChange: setSelId,
                label: i18n.memberSelectLabel, helper: i18n.memberSelectHelper,
            }),

            /* Tabs */
            canEdit ? h('div', { className: 'mj-hd__tabs', role: 'tablist' },
                h('button', { type: 'button', role: 'tab', id: 'mj-hd-tg', 'aria-selected': String(tab === 'graphs'), 'aria-controls': 'mj-hd-pg',
                    className: 'mj-hd__tab' + (tab === 'graphs' ? ' mj-hd__tab--active' : ''), onClick: function () { setTab('graphs'); } },
                    i18n.graphsTabLabel || 'Graphiques'),
                h('button', { type: 'button', role: 'tab', id: 'mj-hd-te', 'aria-selected': String(tab === 'edit'), 'aria-controls': 'mj-hd-pe',
                    className: 'mj-hd__tab' + (tab === 'edit' ? ' mj-hd__tab--active' : ''), onClick: function () { setTab('edit'); } },
                    i18n.editTabLabel || 'Éditer les heures')
            ) : null,

            /* Graphs panel */
            (!canEdit || tab === 'graphs') ? h('div', {
                className: 'mj-hd__graphs',
                id: canEdit ? 'mj-hd-pg' : undefined,
                role: canEdit ? 'tabpanel' : undefined,
                'aria-labelledby': canEdit ? 'mj-hd-tg' : undefined,
            },
                /* Donut + Weekly bar side by side */
                h('div', { className: 'mj-hd__charts-row' },
                    h(DonutChart, {
                        title: donutTitle, items: donutItems, totalMinutes: donutTotal,
                        centerValue: donutValue, centerLabel: donutLabel, i18n: i18n,
                        emptyLabel: i18n.noProjectsForMember,
                    }),
                    h(BarChart, {
                        title: i18n.weeklyHoursTitle || 'Heures par semaine',
                        subtitle: weekSub, items: weekly, i18n: i18n,
                        emptyLabel: i18n.barChartEmpty,
                    })
                ),

                /* Monthly bar full width */
                h(BarChart, {
                    title: i18n.monthlyHoursTitle || 'Heures par mois',
                    subtitle: monthSub, items: monthly, i18n: i18n,
                    emptyLabel: i18n.barChartEmpty,
                }),

                /* Members table */
                h(MembersTable, { members: members, totals: totals, i18n: i18n })
            ) : null,

            /* Edit panel */
            (canEdit && tab === 'edit') ? h(HourEncodeEmbed, {
                config: editConfig, configJson: editJSON,
                panelId: 'mj-hd-pe', labelledBy: 'mj-hd-te',
                emptyLabel: i18n.editTabError || 'Impossible de charger l\'éditeur.',
            }) : null
        );
    }

    /* ====================================================================
     *  Bootstrap
     * ==================================================================== */

    function boot(root) {
        var raw = root.getAttribute('data-config') || '{}';
        var cfg = parseJSON(raw);

        ensurePreact().then(function () {
            try {
                _p.render(_p.h(DashboardApp, { config: cfg }), root);
                root.setAttribute('data-mj-hours-dashboard-front-ready', '1');
            } catch (e) {
                console.error('[MJ-HD] render', e);
                root.innerHTML = '<p class="mj-hd__empty">' + ((cfg.i18n || {}).renderError || "Impossible d'afficher le tableau de bord.") + '</p>';
            }
        }).catch(function () {
            root.innerHTML = '<p class="mj-hd__empty">' + ((cfg.i18n || {}).renderError || "Impossible d'afficher le tableau de bord.") + '</p>';
        });
    }

    function init() {
        document.querySelectorAll('[data-mj-hours-dashboard-front]').forEach(boot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
