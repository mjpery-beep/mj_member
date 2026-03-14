/**
 * MJ Member – Hours Dashboard front-end Elementor widget.
 *
 * Preact-based widget that renders hour statistics for coordinators.
 * Re-uses the same visual components as the admin hours dashboard.
 */

/* global preact, preactHooks */

(function () {
    'use strict';

    /* ---------- Preact bootstrap helpers ---------- */

    var mjHoursPreactReadyPromise = null;
    var mjHoursScriptPromises = {};
    var h = null;
    var render = null;
    var useMemo = null;
    var useState = null;
    var useEffect = null;
    var useRef = null;

    var PREACT_SCRIPT_ID = 'mj-member-preact-lib';
    var PREACT_HOOKS_SCRIPT_ID = 'mj-member-preact-hooks';
    var PREACT_SCRIPT_URL = 'https://unpkg.com/preact@10.19.3/dist/preact.min.js';
    var PREACT_HOOKS_SCRIPT_URL = 'https://unpkg.com/preact@10.19.3/hooks/dist/hooks.umd.js';

    var CHART_JS_SCRIPT_ID = 'mj-member-chartjs';
    var CHART_JS_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js';
    var chartJsReadyPromise = null;

    var COLOR_PALETTE = [
        '#6366f1', '#22c55e', '#f97316', '#a855f7',
        '#ef4444', '#14b8a6', '#f59e0b', '#3b82f6',
    ];

    var WEEKLY_REQUIRED_COLOR = '#3b82f6';
    var WEEKLY_EXTRA_COLOR = '#f97316';

    function loadScriptOnce(id, url) {
        if (mjHoursScriptPromises[id]) return mjHoursScriptPromises[id];
        var existing = document.getElementById(id);
        if (existing) { mjHoursScriptPromises[id] = Promise.resolve(); return mjHoursScriptPromises[id]; }
        mjHoursScriptPromises[id] = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.id = id; s.src = url; s.async = true;
            s.onload = resolve; s.onerror = function () { reject(new Error('Failed to load ' + url)); };
            document.head.appendChild(s);
        });
        return mjHoursScriptPromises[id];
    }

    function ensurePreact() {
        if (h && render && useMemo && useState) return Promise.resolve();
        if (!mjHoursPreactReadyPromise) {
            mjHoursPreactReadyPromise = Promise.resolve()
                .then(function () { if (typeof window !== 'undefined' && window.preact) return; return loadScriptOnce(PREACT_SCRIPT_ID, PREACT_SCRIPT_URL); })
                .then(function () { if (typeof window !== 'undefined' && window.preactHooks) return; return loadScriptOnce(PREACT_HOOKS_SCRIPT_ID, PREACT_HOOKS_SCRIPT_URL); })
                .then(function () {
                    var g = typeof window !== 'undefined' ? window : {};
                    var p = g.preact; var hooks = g.preactHooks;
                    if (!p || !hooks) throw new Error('Preact global introuvable');
                    h = p.h; render = p.render; useMemo = hooks.useMemo; useState = hooks.useState; useEffect = hooks.useEffect; useRef = hooks.useRef;
                    if (typeof h !== 'function' || typeof render !== 'function') throw new Error('Exports Preact incomplets');
                })
                .catch(function (e) { console.error('[MJ Hours Dashboard] Preact load error', e); throw e; });
        }
        return mjHoursPreactReadyPromise;
    }

    function ensureChartJs() {
        if (typeof window !== 'undefined' && typeof window.Chart !== 'undefined') return Promise.resolve(window.Chart);
        if (!chartJsReadyPromise) {
            chartJsReadyPromise = loadScriptOnce(CHART_JS_SCRIPT_ID, CHART_JS_SCRIPT_URL)
                .then(function () { if (!window.Chart) throw new Error('Chart.js introuvable'); return window.Chart; })
                .catch(function (e) { console.error('[MJ Hours Dashboard] Chart.js load error', e); throw e; });
        }
        return chartJsReadyPromise;
    }

    /* ---------- Utility functions ---------- */

    function parseConfig(raw) {
        if (!raw || typeof raw !== 'string') return {};
        try { return JSON.parse(raw); } catch (_) { return {}; }
    }

    function formatMinutesToHoursLabel(m) {
        m = Math.max(Math.round(m || 0), 0);
        if (m === 0) return '0 min';
        var hrs = Math.floor(m / 60); var rest = m % 60;
        if (hrs === 0) return rest + ' min';
        if (rest === 0) return hrs + ' h';
        return hrs + ' h ' + rest + ' min';
    }

    function formatSignedMinutesToHoursLabel(m) {
        if (!Number.isFinite(m) || m === 0) return '0 min';
        var sign = m > 0 ? '+' : '-';
        return sign + formatMinutesToHoursLabel(Math.abs(m));
    }

    function formatPercentage(value) {
        if (!Number.isFinite(value)) return '0 %';
        return value.toFixed(1) + ' %';
    }

    /* ---------- Chart hook ---------- */

    function useChartJs(canvasRef, chartType, chartData, chartOptions) {
        var chartStateRef = useRef({ chart: null });
        useEffect(function () {
            var isActive = true;
            var state = chartStateRef.current;

            function destroyChart() {
                if (state.chart) { try { state.chart.destroy(); } catch (_) {} state.chart = null; }
            }

            if (!canvasRef || !canvasRef.current || !chartType || !chartData) {
                destroyChart();
                return function () { isActive = false; destroyChart(); };
            }

            ensureChartJs().then(function (Chart) {
                if (!isActive || !canvasRef.current) return;
                destroyChart();
                try {
                    state.chart = new Chart(canvasRef.current, { type: chartType, data: chartData, options: chartOptions || {} });
                } catch (e) { console.error('[MJ Hours Dashboard] Chart render error', e); }
            }).catch(function () {});

            return function () { isActive = false; destroyChart(); };
        }, [canvasRef, chartType, chartData, chartOptions]);
    }

    /* ---------- Preact Components ---------- */

    function DonutChart(props) {
        var title = props.title || '';
        var items = Array.isArray(props.items) ? props.items : [];
        var totalMinutes = Math.max(props.totalMinutes || 0, 0);
        var centerValue = props.centerValue || '0';
        var centerLabel = props.centerLabel || '';
        var emptyLabel = props.emptyLabel || '';
        var i18n = props.i18n || {};

        var hasData = items.length > 0 && totalMinutes > 0;
        var canvasRef = useRef(null);

        var chartData = useMemo(function () {
            if (!hasData) return null;
            return {
                labels: items.map(function (item) { return item.label || ''; }),
                datasets: [{
                    data: items.map(function (item) { return Math.max(item.minutes || 0, 0); }),
                    backgroundColor: items.map(function (item, index) { return item.color || COLOR_PALETTE[index % COLOR_PALETTE.length]; }),
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            };
        }, [hasData, items]);

        var chartOptions = useMemo(function () {
            if (!hasData) return null;
            return {
                responsive: true, maintainAspectRatio: true, cutout: '65%',
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) {
                    var label = ctx.label || ''; var min = ctx.parsed || 0; return label + ': ' + formatMinutesToHoursLabel(min);
                } } } },
            };
        }, [hasData]);

        useChartJs(canvasRef, hasData ? 'doughnut' : null, chartData, chartOptions);

        return h('div', { className: 'mj-hd-card mj-hd-donut' },
            title ? h('h2', { className: 'mj-hd-donut__title' }, title) : null,
            hasData ? h('div', { className: 'mj-hd-donut__canvas' },
                h('canvas', { ref: canvasRef }),
                h('div', { className: 'mj-hd-donut__center' },
                    h('p', { className: 'mj-hd-donut__center-value' }, centerValue),
                    centerLabel ? h('p', { className: 'mj-hd-donut__center-label' }, centerLabel) : null
                )
            ) : h('p', { className: 'mj-hd__empty' }, emptyLabel || i18n.noProjectsForMember || ''),
            hasData ? h('ul', { className: 'mj-hd-donut__legend' },
                items.map(function (item, index) {
                    var color = item.color || COLOR_PALETTE[index % COLOR_PALETTE.length];
                    return h('li', { key: item.key || index, className: 'mj-hd-donut__legend-item' },
                        h('span', { className: 'mj-hd-donut__legend-label' },
                            h('span', { className: 'mj-hd-donut__legend-swatch', style: { background: color } }),
                            item.label || ''
                        ),
                        h('span', null, item.human || '0')
                    );
                })
            ) : null
        );
    }

    function BarChart(props) {
        var title = props.title || '';
        var subtitle = props.subtitle || '';
        var items = Array.isArray(props.items) ? props.items : [];
        var datasetLabel = props.datasetLabel || title;
        var emptyLabel = props.emptyLabel || '';
        var i18n = props.i18n || {};

        var hasData = items.length > 0;
        var canvasRef = useRef(null);

        var hasSegments = useMemo(function () {
            return hasData && items.some(function (item) {
                return typeof item.required_minutes === 'number' && typeof item.extra_minutes === 'number';
            });
        }, [hasData, items]);

        var chartData = useMemo(function () {
            if (!hasData) return null;
            var labels = items.map(function (item) { return item.short_label || item.label || ''; });

            if (hasSegments) {
                var requiredLabel = (i18n && i18n.weeklyRequiredLabel) || 'Heures dues';
                var extraLabel = (i18n && i18n.weeklyExtraLabel) || 'Heures supplémentaires';
                return {
                    labels: labels,
                    datasets: [
                        { label: requiredLabel, data: items.map(function (item) { return Math.max(item.required_minutes || 0, 0); }), backgroundColor: WEEKLY_REQUIRED_COLOR, stack: 'hours', borderRadius: 10, maxBarThickness: 48 },
                        { label: extraLabel, data: items.map(function (item) { return Math.max(item.extra_minutes || 0, 0); }), backgroundColor: WEEKLY_EXTRA_COLOR, stack: 'hours', borderRadius: 10, maxBarThickness: 48 },
                    ],
                };
            }

            return {
                labels: labels,
                datasets: [{
                    label: datasetLabel,
                    data: items.map(function (item) { return Math.max(item.minutes || 0, 0); }),
                    backgroundColor: items.map(function (_, index) { return COLOR_PALETTE[index % COLOR_PALETTE.length]; }),
                    borderRadius: 10, maxBarThickness: 48,
                }],
            };
        }, [hasData, hasSegments, items, datasetLabel, i18n]);

        var chartOptions = useMemo(function () {
            if (!hasData) return null;
            return {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { stacked: hasSegments, grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, minRotation: 0 } },
                    y: {
                        beginAtZero: true, stacked: hasSegments,
                        grid: { color: 'rgba(148,163,184,0.25)', drawBorder: false },
                        ticks: { callback: function (v) { return formatMinutesToHoursLabel(v); } },
                    },
                },
                plugins: {
                    legend: { display: hasSegments, position: 'bottom', labels: { usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var dsLabel = ctx.dataset && ctx.dataset.label ? ctx.dataset.label : '';
                                var fallback = !hasSegments && dsLabel === '' ? datasetLabel : dsLabel;
                                var label = hasSegments ? dsLabel : (ctx.label || fallback || '');
                                var val = (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y : ctx.parsed;
                                return label ? label + ': ' + formatMinutesToHoursLabel(val) : formatMinutesToHoursLabel(val);
                            },
                            afterBody: hasSegments ? function (contexts) {
                                if (!Array.isArray(contexts) || contexts.length === 0) return [];
                                var idx = contexts[0].dataIndex;
                                var item = items && items[idx] ? items[idx] : null;
                                if (!item) return [];
                                var lines = [];
                                var exp = typeof item.expected_minutes === 'number' ? Math.max(item.expected_minutes, 0) : 0;
                                if (exp > 0) {
                                    lines.push(((i18n && i18n.weeklyExpectedLabel) || 'Heures attendues') + ': ' + formatMinutesToHoursLabel(exp));
                                }
                                var diff = typeof item.difference_minutes === 'number' ? item.difference_minutes : 0;
                                if (diff !== 0) {
                                    var dl = diff > 0 ? ((i18n && i18n.weeklyExtraLabel) || 'Heures supplémentaires') : ((i18n && i18n.weeklyDeficitLabel) || 'Heures manquantes');
                                    lines.push(dl + ': ' + (diff > 0 ? '+' : '-') + formatMinutesToHoursLabel(Math.abs(diff)));
                                }
                                return lines;
                            } : undefined,
                        },
                    },
                },
            };
        }, [hasData, hasSegments, items, i18n]);

        useChartJs(canvasRef, hasData ? 'bar' : null, chartData, chartOptions);

        return h('div', { className: 'mj-hd-card mj-hd-bar-chart' },
            title ? h('h2', { className: 'mj-hd-card__heading' }, title) : null,
            subtitle ? h('p', { className: 'mj-hd-card__subtitle' }, subtitle) : null,
            hasData ? h('div', { className: 'mj-hd-bar-chart__canvas' },
                h('canvas', { ref: canvasRef, role: 'img', 'aria-label': title || '' })
            ) : h('p', { className: 'mj-hd__empty' }, emptyLabel || (i18n && i18n.barChartEmpty) || '')
        );
    }

    function SummaryCards(props) {
        var totals = props.totals || {};
        var i18n = props.i18n || {};
        var totalEntries = Number.isFinite(totals.entries) ? totals.entries : 0;
        var entriesLabel = i18n.entriesLabel || '';
        var entriesMeta = totalEntries > 0 && entriesLabel ? totalEntries + ' ' + entriesLabel : '';
        var membersCount = Number.isFinite(totals.member_count) ? totals.member_count : 0;
        var projectsCount = Number.isFinite(totals.project_count) ? totals.project_count : 0;
        var membersMeta = projectsCount > 0 && (i18n.projectsCount || '') ? projectsCount + ' ' + i18n.projectsCount : '';
        var weeklyAverageMeta = (totals.weekly_average_meta && totals.weekly_average_meta !== '') ? totals.weekly_average_meta : (i18n.weeklyAverageMetaFallback || '');
        var balanceExtraHuman = typeof totals.weekly_extra_recent_human === 'string' ? totals.weekly_extra_recent_human : '';
        var balanceMeta = balanceExtraHuman && (i18n.weeklyExtraLabel || '') ? i18n.weeklyExtraLabel + ' : ' + balanceExtraHuman : '';
        var expectedLabel = i18n.weeklyExpectedLabel || i18n.weeklyRequiredLabel || '';

        var cards = [
            { key: 'total-hours', title: i18n.totalHours || 'Heures totales encodées', value: totals.human || '0 min', meta: entriesMeta },
            { key: 'members-count', title: i18n.membersCount || 'Membres', value: membersCount > 0 ? String(membersCount) : '0', meta: membersMeta },
            { key: 'weekly-average', title: i18n.averageWeeklyHours || 'Moyenne hebdomadaire encodée', value: totals.weekly_average_human || '0 min', meta: weeklyAverageMeta },
            { key: 'weekly-expected', title: expectedLabel || 'Heures attendues', value: totals.weekly_contract_human || '0 min', meta: balanceMeta },
            { key: 'weekly-balance', title: i18n.weeklyBalanceNetLabel || 'Solde cumulé', value: totals.weekly_balance_human || '0 min', meta: balanceMeta },
        ];

        var filtered = cards.filter(function (c) { return c && c.title; });
        if (filtered.length === 0) return null;

        return h('div', { className: 'mj-hd__summary' },
            filtered.map(function (card) {
                return h('article', { key: card.key, className: 'mj-hd-card' },
                    h('p', { className: 'mj-hd-card__title' }, card.title),
                    h('p', { className: 'mj-hd-card__value' }, card.value || '0'),
                    card.meta ? h('p', { className: 'mj-hd-card__meta' }, card.meta) : null
                );
            })
        );
    }

    function MemberSelector(props) {
        var members = Array.isArray(props.members) ? props.members : [];
        if (members.length === 0) return null;

        return h('div', { className: 'mj-hd__select' },
            props.label ? h('label', { htmlFor: 'mj-hd-member-select' }, props.label) : null,
            h('select', {
                id: 'mj-hd-member-select',
                value: props.selectedId || '',
                onChange: function (e) {
                    var val = parseInt(e.target.value, 10);
                    if (Number.isNaN(val)) val = 0;
                    props.onChange(val);
                },
            },
                members.map(function (m) { return h('option', { key: m.id, value: m.id }, m.label || ''); })
            ),
            props.helper ? h('p', { className: 'mj-hd__select-helper' }, props.helper) : null
        );
    }

    function MembersTable(props) {
        var members = Array.isArray(props.members) ? props.members : [];
        var totals = props.totals || {};
        var i18n = props.i18n || {};

        if (members.length === 0) {
            return h('p', { className: 'mj-hd__empty' }, i18n.noMemberData || '');
        }

        var totalMinutes = Math.max(totals.minutes || 0, 0);

        return h('div', { className: 'mj-hd-card mj-hd__table-card' },
            h('h2', { className: 'mj-hd-card__heading' }, i18n.memberTableTitle || ''),
            h('table', null,
                h('thead', null,
                    h('tr', null,
                        h('th', null, i18n.memberColumn || ''),
                        h('th', null, i18n.hoursColumn || ''),
                        h('th', null, i18n.entriesColumn || ''),
                        h('th', null, i18n.rateColumn || '')
                    )
                ),
                h('tbody', null,
                    members.map(function (m) {
                        var share = totalMinutes > 0 ? (m.minutes || 0) / totalMinutes : 0;
                        return h('tr', { key: m.id },
                            h('td', null, m.label || ''),
                            h('td', null, m.human || '0'),
                            h('td', null, String(m.entries || 0)),
                            h('td', null, formatPercentage(share * 100))
                        );
                    })
                )
            )
        );
    }

    function HourEncodeEmbed(props) {
        var config = props.config;
        var configJson = props.configJson;
        var panelId = props.panelId;
        var labelledBy = props.labelledBy;
        var emptyLabel = props.emptyLabel;
        var containerRef = useRef(null);

        useEffect(function () {
            if (!config || !configJson) return undefined;
            var container = containerRef.current;
            if (!container) return undefined;

            var destroyEvent = new CustomEvent('mj-member-hour-encode:destroy', { detail: { context: container } });
            document.dispatchEvent(destroyEvent);
            container.innerHTML = '';

            var widgetElement = document.createElement('div');
            widgetElement.className = 'mj-hour-encode';
            widgetElement.setAttribute('data-config', configJson);
            container.appendChild(widgetElement);

            var initEvent = new CustomEvent('mj-member-hour-encode:init', { detail: { context: container } });
            document.dispatchEvent(initEvent);

            return function () {
                var cleanup = new CustomEvent('mj-member-hour-encode:destroy', { detail: { context: container } });
                document.dispatchEvent(cleanup);
                container.innerHTML = '';
            };
        }, [config, configJson]);

        var panelProps = { className: 'mj-hd-card mj-hd__editor-card' };
        if (panelId) panelProps.id = panelId;
        if (labelledBy) { panelProps.role = 'tabpanel'; panelProps['aria-labelledby'] = labelledBy; }

        if (!config || !configJson) {
            return h('div', panelProps, h('p', { className: 'mj-hd__empty' }, emptyLabel || ''));
        }

        return h('div', panelProps, h('div', { ref: containerRef, className: 'mj-hd__editor-widget' }));
    }

    /* ---------- Main App Component ---------- */

    function DashboardApp(props) {
        var config = props.config || {};
        var data = config.data || {};
        var i18n = config.i18n || {};
        var totals = data.totals || {};
        var projects = Array.isArray(data.projects) ? data.projects : [];
        var members = Array.isArray(data.members) ? data.members : [];
        var timeseries = data.timeseries || {};
        var monthlyAll = Array.isArray(timeseries.months) ? timeseries.months : [];
        var weeklyAll = Array.isArray(timeseries.weeks) ? timeseries.weeks : [];
        var monthlyByMember = timeseries.months_by_member || {};
        var weeklyByMember = timeseries.weeks_by_member || {};
        var showEditTab = config.showEditTab !== false;

        var defaultMemberId = members.length > 0 ? members[0].id : 0;
        var stateArr = useState(defaultMemberId);
        var selectedMemberId = stateArr[0];
        var setSelectedMemberId = stateArr[1];
        var tabArr = useState('graphs');
        var activeTab = tabArr[0];
        var setActiveTab = tabArr[1];

        var selectedMember = useMemo(function () {
            if (!Array.isArray(members)) return null;
            return members.find(function (m) { return m.id === selectedMemberId; }) || null;
        }, [members, selectedMemberId]);

        var hourEncodeBase = config.hourEncode || null;
        var memberProjects = selectedMember && Array.isArray(selectedMember.projects) ? selectedMember.projects : [];
        var memberTotalMinutes = selectedMember ? Math.max(selectedMember.minutes || 0, 0) : 0;
        var memberContractHuman = selectedMember ? (selectedMember.weekly_contract_human || '') : '';
        var memberContractMinutes = selectedMember ? Math.max(selectedMember.weekly_contract_minutes || 0, 0) : 0;
        var totalContractMinutes = Math.max(totals.weekly_contract_minutes || 0, 0);
        var totalContractHuman = totals.weekly_contract_human || '';
        var memberBalanceHuman = selectedMember ? (selectedMember.weekly_balance_human || '') : '';
        var totalBalanceHuman = totals.weekly_balance_human || '';

        var donutItems, donutTotalMinutes, donutCenterValue, donutCenterLabel;
        if (selectedMember) {
            donutItems = memberProjects; donutTotalMinutes = memberTotalMinutes;
            donutCenterValue = selectedMember.human || '0'; donutCenterLabel = selectedMember.label || '';
        } else {
            donutItems = projects; donutTotalMinutes = Math.max(totals.minutes || 0, 0);
            donutCenterValue = totals.human || '0'; donutCenterLabel = i18n.totalHours || '';
        }

        var monthlySeries = selectedMember
            ? (Array.isArray(monthlyByMember[selectedMemberId]) ? monthlyByMember[selectedMemberId] : [])
            : monthlyAll;
        var weeklySeries = selectedMember
            ? (Array.isArray(weeklyByMember[selectedMemberId]) ? weeklyByMember[selectedMemberId] : [])
            : weeklyAll;

        var memberLabel = selectedMember ? (selectedMember.label || '') : '';
        var memberHuman = selectedMember ? (selectedMember.human || '0') : '';
        var totalHuman = totals.human || '';
        var monthlySubtitle = selectedMember ? (memberLabel ? memberLabel + ' · ' + memberHuman : memberHuman) : totalHuman;
        var expectedLabelShort = i18n.weeklyExpectedLabel || i18n.weeklyRequiredLabel || '';
        var weeklySubtitle;
        if (selectedMember) {
            weeklySubtitle = memberLabel ? memberLabel + ' · ' + memberHuman : memberHuman;
            if (memberContractMinutes > 0 && expectedLabelShort && memberContractHuman) weeklySubtitle += ' · ' + expectedLabelShort + ' : ' + memberContractHuman;
        } else {
            weeklySubtitle = totalHuman;
            if (totalContractMinutes > 0 && expectedLabelShort && totalContractHuman) weeklySubtitle += ' · ' + expectedLabelShort + ' : ' + totalContractHuman;
        }
        var balanceLabel = i18n.weeklyBalanceNetLabel || '';
        var balanceHuman = selectedMember ? memberBalanceHuman : totalBalanceHuman;
        if (balanceLabel && balanceHuman) weeklySubtitle += ' · ' + balanceLabel + ' : ' + balanceHuman;

        var projectsDonutTitle = i18n.projectsDonutTitle || '';
        if (selectedMember && memberLabel) {
            projectsDonutTitle = projectsDonutTitle ? projectsDonutTitle + ' · ' + memberLabel : memberLabel;
        }

        var canShowEditTab = showEditTab && Boolean(hourEncodeBase && selectedMember && selectedMemberId);
        useEffect(function () {
            if (!canShowEditTab && activeTab !== 'graphs') setActiveTab('graphs');
        }, [canShowEditTab, activeTab]);

        var editConfig = useMemo(function () {
            if (!hourEncodeBase || !selectedMember || !selectedMemberId) return null;
            var ajaxConfig = Object.assign({}, hourEncodeBase.ajax || {});
            var staticParams = Object.assign({}, ajaxConfig.staticParams || {});
            staticParams.member_id = String(selectedMemberId);
            ajaxConfig.staticParams = staticParams;

            var labelsConfig = Object.assign({}, hourEncodeBase.labels || {});
            if (selectedMember.label) {
                var baseTitle = (hourEncodeBase.labels && hourEncodeBase.labels.title) || '';
                labelsConfig.title = baseTitle !== '' ? baseTitle + ' · ' + selectedMember.label : selectedMember.label;
            }

            var projectSuggestions = Array.isArray(selectedMember.projects)
                ? selectedMember.projects.map(function (p) { return p && p.raw_label ? String(p.raw_label) : (p && !p.is_unassigned && p.label ? String(p.label) : ''); })
                : [];
            if (projectSuggestions.length === 0 && Array.isArray(hourEncodeBase.projects)) projectSuggestions = hourEncodeBase.projects;
            var uniqueProjects = Array.from(new Set(projectSuggestions.map(function (n) { return typeof n === 'string' ? n.trim() : ''; }).filter(function (n) { return n !== ''; })));

            var ws = Array.isArray(selectedMember.work_schedule) ? selectedMember.work_schedule : (hourEncodeBase.workSchedule || []);
            var cb = selectedMember.cumulative_balance || null;
            var caps = Object.assign({}, hourEncodeBase.capabilities || {}, { canManage: true });

            return Object.assign({}, hourEncodeBase, {
                ajax: ajaxConfig, labels: labelsConfig, projects: uniqueProjects,
                entries: [], events: [], projectTotals: hourEncodeBase.projectTotals || [],
                workSchedule: ws, cumulativeBalance: cb, capabilities: caps,
            });
        }, [hourEncodeBase, selectedMember, selectedMemberId]);

        var editConfigJson = useMemo(function () {
            if (!editConfig) return null;
            try { return JSON.stringify(editConfig); } catch (_) { return null; }
        }, [editConfig]);

        return h('div', { className: 'mj-hd__content' },
            h(SummaryCards, { totals: totals, i18n: i18n }),
            h(MemberSelector, {
                members: members, selectedId: selectedMemberId,
                onChange: setSelectedMemberId, label: i18n.memberSelectLabel, helper: i18n.memberSelectHelper,
            }),
            canShowEditTab ? h('div', { className: 'mj-hd__tabs', role: 'tablist' },
                h('button', {
                    type: 'button', id: 'mj-hd-tab-graphs', role: 'tab',
                    'aria-selected': activeTab === 'graphs' ? 'true' : 'false', 'aria-controls': 'mj-hd-panel-graphs',
                    tabIndex: activeTab === 'graphs' ? 0 : -1,
                    className: 'mj-hd__tab' + (activeTab === 'graphs' ? ' mj-hd__tab--active' : ''),
                    onClick: function () { setActiveTab('graphs'); },
                }, i18n.graphsTabLabel || 'Graphiques'),
                h('button', {
                    type: 'button', id: 'mj-hd-tab-edit', role: 'tab',
                    'aria-selected': activeTab === 'edit' ? 'true' : 'false', 'aria-controls': 'mj-hd-panel-edit',
                    tabIndex: activeTab === 'edit' ? 0 : -1,
                    className: 'mj-hd__tab' + (activeTab === 'edit' ? ' mj-hd__tab--active' : ''),
                    onClick: function () { setActiveTab('edit'); },
                }, i18n.editTabLabel || 'Éditer les heures')
            ) : null,
            (!canShowEditTab || activeTab === 'graphs') ? h('div', {
                className: 'mj-hd__graphs',
                id: canShowEditTab ? 'mj-hd-panel-graphs' : undefined,
                role: canShowEditTab ? 'tabpanel' : undefined,
                'aria-labelledby': canShowEditTab ? 'mj-hd-tab-graphs' : undefined,
            },
                h(DonutChart, {
                    title: projectsDonutTitle, items: donutItems, totalMinutes: donutTotalMinutes,
                    centerValue: donutCenterValue, centerLabel: donutCenterLabel, i18n: i18n,
                    emptyLabel: i18n.noProjectsForMember,
                }),
                h('div', { className: 'mj-hd__grid mj-hd__grid--timeseries' },
                    h(BarChart, { title: i18n.monthlyHoursTitle || '', subtitle: monthlySubtitle, items: monthlySeries, i18n: i18n, emptyLabel: i18n.barChartEmpty }),
                    h(BarChart, { title: i18n.weeklyHoursTitle || '', subtitle: weeklySubtitle, items: weeklySeries, i18n: i18n, emptyLabel: i18n.barChartEmpty })
                ),
                h(MembersTable, { members: members, totals: totals, i18n: i18n })
            ) : null,
            (canShowEditTab && activeTab === 'edit') ? h(HourEncodeEmbed, {
                config: editConfig, configJson: editConfigJson,
                panelId: 'mj-hd-panel-edit', labelledBy: 'mj-hd-tab-edit',
                emptyLabel: i18n.editTabError || 'Impossible de charger l\'éditeur pour ce membre.',
            }) : null
        );
    }

    /* ---------- Bootstrap ---------- */

    function bootstrap(root) {
        var rawConfig = root.getAttribute('data-config') || root.dataset.config || '{}';
        var config = parseConfig(rawConfig);
        if (!config || typeof config !== 'object') config = {};

        ensurePreact().then(function () {
            try {
                render(h(DashboardApp, { config: config }), root);
                root.setAttribute('data-mj-hours-dashboard-front-ready', '1');
            } catch (e) {
                console.error('[MJ Hours Dashboard] Render error', e);
                var msg = (config && config.i18n && config.i18n.renderError) || "Impossible d'afficher le tableau de bord.";
                root.innerHTML = '<p class="mj-hd__empty">' + msg + '</p>';
            }
        }).catch(function () {
            var msg = (config && config.i18n && config.i18n.renderError) || "Impossible d'afficher le tableau de bord.";
            root.innerHTML = '<p class="mj-hd__empty">' + msg + '</p>';
        });
    }

    function init() {
        var roots = document.querySelectorAll('[data-mj-hours-dashboard-front]');
        roots.forEach(function (root) { bootstrap(root); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
