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

const COLOR_PALETTE = [
    '#6366f1',
    '#22c55e',
    '#f97316',
    '#a855f7',
    '#ef4444',
    '#14b8a6',
    '#f59e0b',
    '#3b82f6',
];

const WEEKLY_REQUIRED_COLOR = '#3b82f6';
const WEEKLY_EXTRA_COLOR = '#f97316';

function loadScriptOnce(id, url) {
    if (mjHoursScriptPromises[id]) {
        return mjHoursScriptPromises[id];
    }

    var existing = document.getElementById(id);
    if (existing) {
        mjHoursScriptPromises[id] = Promise.resolve();
        return mjHoursScriptPromises[id];
    }

    mjHoursScriptPromises[id] = new Promise(function (resolve, reject) {
        var script = document.createElement('script');
        script.id = id;
        script.src = url;
        script.async = true;
        script.onload = function () {
            resolve();
        };
        script.onerror = function () {
            reject(new Error('Unable to load script ' + url));
        };
        document.head.appendChild(script);
    });

    return mjHoursScriptPromises[id];
}

function ensurePreact() {
    if (h && render && useMemo && useState) {
        return Promise.resolve();
    }

    if (!mjHoursPreactReadyPromise) {
        mjHoursPreactReadyPromise = Promise.resolve()
            .then(function () {
                if (typeof window !== 'undefined' && window.preact) {
                    return;
                }
                return loadScriptOnce(PREACT_SCRIPT_ID, PREACT_SCRIPT_URL);
            })
            .then(function () {
                if (typeof window !== 'undefined' && window.preactHooks) {
                    return;
                }
                return loadScriptOnce(PREACT_HOOKS_SCRIPT_ID, PREACT_HOOKS_SCRIPT_URL);
            })
            .then(function () {
                var globalObject = typeof window !== 'undefined' ? window : {};
                var preact = globalObject.preact;
                var hooks = globalObject.preactHooks || (preact && preactHooksFallback(preact));

                if (!preact || !hooks) {
                    throw new Error('Preact global introuvable');
                }

                h = preact.h;
                render = preact.render;
                useMemo = hooks.useMemo;
                useState = hooks.useState;
                useEffect = hooks.useEffect;
                useRef = hooks.useRef;

                if (typeof h !== 'function' || typeof render !== 'function' || typeof useMemo !== 'function' || typeof useState !== 'function' || typeof useEffect !== 'function' || typeof useRef !== 'function') {
                    throw new Error('Exports Preact incomplets');
                }
            })
            .catch(function (error) {
                console.error('MJ Member Hours dashboard failed to load Preact', error);
                throw error;
            });
    }

    return mjHoursPreactReadyPromise;
}

function preactHooksFallback(preact) {
    if (!preact || !preact.options) {
        return null;
    }
    // Preact UMD n’expose pas toujours preactHooks. Cherche les hooks via options.
    if (typeof preact.useState === 'function' && typeof preact.useMemo === 'function' && typeof preact.useEffect === 'function' && typeof preact.useRef === 'function') {
        return {
            useState: preact.useState,
            useMemo: preact.useMemo,
            useEffect: preact.useEffect,
            useRef: preact.useRef,
        };
    }
    return null;
}

function ensureChartJs() {
    if (typeof window !== 'undefined' && typeof window.Chart !== 'undefined') {
        return Promise.resolve(window.Chart);
    }

    if (!chartJsReadyPromise) {
        chartJsReadyPromise = loadScriptOnce(CHART_JS_SCRIPT_ID, CHART_JS_SCRIPT_URL)
            .then(function () {
                if (typeof window === 'undefined' || typeof window.Chart === 'undefined') {
                    throw new Error('Chart.js introuvable');
                }
                return window.Chart;
            })
            .catch(function (error) {
                console.error('MJ Member Hours dashboard failed to load Chart.js', error);
                throw error;
            });
    }

    return chartJsReadyPromise;
}

function useChartJs(canvasRef, chartType, chartData, chartOptions) {
    var chartStateRef = useRef({ chart: null });

    useEffect(function () {
        var isActive = true;
        var state = chartStateRef.current;

        function destroyChart() {
            if (state.chart) {
                try {
                    state.chart.destroy();
                } catch (_) {
                    // ignore destruction errors
                }
                state.chart = null;
            }
        }

        if (!canvasRef || !canvasRef.current || !chartType || !chartData) {
            destroyChart();
            return function () {
                isActive = false;
                destroyChart();
            };
        }

        ensureChartJs().then(function (Chart) {
            if (!isActive || !canvasRef.current) {
                return;
            }

            destroyChart();

            try {
                state.chart = new Chart(canvasRef.current, {
                    type: chartType,
                    data: chartData,
                    options: chartOptions || {},
                });
            } catch (error) {
                console.error('MJ Member Hours dashboard failed to render chart', error);
            }
        }).catch(function (error) {
            if (isActive) {
                console.error('MJ Member Hours dashboard failed to initialize Chart.js', error);
            }
        });

        return function () {
            isActive = false;
            destroyChart();
        };
    }, [canvasRef, chartType, chartData, chartOptions]);
}

function parseConfig(raw) {
    if (typeof raw !== 'string' || raw === '') {
        return {};
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        try {
            var textarea = document.createElement('textarea');
            textarea.innerHTML = raw;
            return JSON.parse(textarea.value);
        } catch (secondError) {
            return {};
        }
    }
}

function formatPercentage(value) {
    if (!Number.isFinite(value) || value <= 0) {
        return '0%';
    }
    if (value >= 99.5) {
        return '100%';
    }
    return `${Math.round(value * 10) / 10}%`;
}

function formatMinutesToHoursLabel(minutes) {
    var value = Number(minutes);
    if (!Number.isFinite(value) || value <= 0) {
        return '0 min';
    }

    var totalMinutes = Math.round(value);
    var hours = Math.floor(totalMinutes / 60);
    var rest = totalMinutes % 60;
    var parts = [];

    if (hours > 0) {
        parts.push(hours + ' h');
    }

    if (rest > 0) {
        parts.push(rest + ' min');
    }

    if (parts.length === 0) {
        return '0 min';
    }

    return parts.join(' ');
}

function formatSignedMinutesToHoursLabel(minutes) {
    var value = Number(minutes);
    if (!Number.isFinite(value) || value === 0) {
        return '0 min';
    }

    var sign = value > 0 ? '+' : '-';
    return sign + formatMinutesToHoursLabel(Math.abs(value));
}

function DonutChart({ title, items, totalMinutes, centerLabel, centerValue, i18n, emptyLabel, wrap = true, headingLevel = 'h2' }) {
    var hasData = Array.isArray(items) && items.length > 0 && totalMinutes > 0;
    var projectWithoutLabel = i18n && i18n.projectWithoutLabel ? i18n.projectWithoutLabel : '';
    var headingTag = typeof headingLevel === 'string' && headingLevel !== '' ? headingLevel : 'h2';
    var containerClass = wrap ? 'mj-hours-dashboard-donut mj-hours-dashboard-card' : 'mj-hours-dashboard-donut';

    var canvasRef = useRef(null);

    var chartData = useMemo(function () {
        if (!hasData) {
            return null;
        }

        var labels = items.map(function (item) {
            return item.label || projectWithoutLabel;
        });

        return {
            labels: labels,
            datasets: [
                {
                    data: items.map(function (item) {
                        return Math.max(item.minutes || 0, 0);
                    }),
                    backgroundColor: items.map(function (_item, index) {
                        return COLOR_PALETTE[index % COLOR_PALETTE.length];
                    }),
                    borderWidth: 0,
                    hoverOffset: 6,
                },
            ],
        };
    }, [hasData, items, projectWithoutLabel]);

    var chartOptions = useMemo(function () {
        if (!hasData) {
            return null;
        }

        return {
            responsive: true,
            maintainAspectRatio: false,
            rotation: -90,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            var label = context.label || '';
                            var value = typeof context.parsed === 'number' ? context.parsed : 0;
                            var human = formatMinutesToHoursLabel(value);
                            return label ? label + ': ' + human : human;
                        },
                    },
                },
            },
        };
    }, [hasData]);

    useChartJs(canvasRef, hasData ? 'doughnut' : null, chartData, chartOptions);

    return h('div', { className: containerClass },
        title ? h(headingTag, { className: 'mj-hours-dashboard-card__heading mj-hours-dashboard-donut__title' }, title || '') : null,
        hasData ? h('div', { className: 'mj-hours-dashboard-donut__canvas' },
            h('canvas', { ref: canvasRef, role: 'img', 'aria-label': title || '' }),
            h('div', { className: 'mj-hours-dashboard-donut__center' },
                h('p', { className: 'mj-hours-dashboard-donut__center-value' }, centerValue || '0'),
                centerLabel ? h('p', { className: 'mj-hours-dashboard-donut__center-label' }, centerLabel) : null,
            ),
        ) : h('p', { className: 'mj-hours-dashboard__empty' }, emptyLabel || (i18n && i18n.noProjectsForMember) || ''),
        hasData ? h('ul', { className: 'mj-hours-dashboard-donut__legend' },
            items.map(function (item, index) {
                var minutes = Math.max(item.minutes || 0, 0);
                var percentage = totalMinutes > 0 ? (minutes / totalMinutes) * 100 : 0;
                return h('li', { key: `${item.key || index}-legend`, className: 'mj-hours-dashboard-donut__legend-item' },
                    h('span', { className: 'mj-hours-dashboard-donut__legend-label' },
                        h('span', {
                            className: 'mj-hours-dashboard-donut__legend-swatch',
                            style: { background: COLOR_PALETTE[index % COLOR_PALETTE.length] },
                        }),
                        h('span', null, item.label || projectWithoutLabel),
                    ),
                    h('span', null, `${item.human || '0'} · ${formatPercentage(percentage)}`),
                );
            })
        ) : null,
    );
}

function BarChart({ title, subtitle, items, i18n, emptyLabel }) {
    var hasData = Array.isArray(items) && items.length > 0;
    var datasetLabel = subtitle || title || (i18n && i18n.totalHours ? i18n.totalHours : '');
    var canvasRef = useRef(null);
    var hasSegments = hasData && items.some(function (item) {
        if (!item || typeof item !== 'object') {
            return false;
        }
        return typeof item.required_minutes === 'number' || typeof item.extra_minutes === 'number';
    });

    var chartData = useMemo(function () {
        if (!hasData) {
            return null;
        }

        var labels = items.map(function (item) {
            return item.short_label || item.label || '';
        });

        if (hasSegments) {
            var requiredLabel = (i18n && i18n.weeklyRequiredLabel) || datasetLabel || 'Heures dues';
            var extraLabel = (i18n && i18n.weeklyExtraLabel) || 'Heures supplémentaires';

            var requiredData = items.map(function (item) {
                if (!item || typeof item !== 'object') {
                    return 0;
                }
                if (typeof item.required_minutes === 'number') {
                    return Math.max(item.required_minutes, 0);
                }
                return Math.max(item.minutes || 0, 0);
            });

            var extraData = items.map(function (item) {
                if (!item || typeof item !== 'object') {
                    return 0;
                }
                if (typeof item.extra_minutes === 'number') {
                    return Math.max(item.extra_minutes, 0);
                }
                return 0;
            });

            return {
                labels: labels,
                datasets: [
                    {
                        label: requiredLabel,
                        data: requiredData,
                        backgroundColor: WEEKLY_REQUIRED_COLOR,
                        stack: 'hours',
                        borderRadius: 10,
                        maxBarThickness: 48,
                    },
                    {
                        label: extraLabel,
                        data: extraData,
                        backgroundColor: WEEKLY_EXTRA_COLOR,
                        stack: 'hours',
                        borderRadius: 10,
                        maxBarThickness: 48,
                    },
                ],
            };
        }

        return {
            labels: labels,
            datasets: [
                {
                    label: datasetLabel,
                    data: items.map(function (item) {
                        return Math.max(item.minutes || 0, 0);
                    }),
                    backgroundColor: items.map(function (_item, index) {
                        return COLOR_PALETTE[index % COLOR_PALETTE.length];
                    }),
                    borderRadius: 10,
                    maxBarThickness: 48,
                },
            ],
        };
    }, [hasData, hasSegments, items, datasetLabel, i18n]);

    var chartOptions = useMemo(function () {
        if (!hasData) {
            return null;
        }

        return {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: hasSegments,
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        maxRotation: 0,
                        minRotation: 0,
                    },
                },
                y: {
                    beginAtZero: true,
                    stacked: hasSegments,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.25)',
                        drawBorder: false,
                    },
                    ticks: {
                        callback: function (value) {
                            return formatMinutesToHoursLabel(value);
                        },
                    },
                },
            },
            plugins: {
                legend: {
                    display: hasSegments,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            var datasetLabelForPoint = context.dataset && context.dataset.label ? context.dataset.label : '';
                            var fallbackLabel = !hasSegments && datasetLabelForPoint === '' ? datasetLabel : datasetLabelForPoint;
                            var label = hasSegments ? datasetLabelForPoint : (context.label || fallbackLabel || '');
                            var value = (context.parsed && typeof context.parsed.y === 'number') ? context.parsed.y : context.parsed;
                            var minutes = typeof value === 'number' ? value : 0;
                            var human = formatMinutesToHoursLabel(minutes);
                            return label ? label + ': ' + human : human;
                        },
                        afterBody: hasSegments ? function (contexts) {
                            if (!Array.isArray(contexts) || contexts.length === 0) {
                                return [];
                            }

                            var index = contexts[0].dataIndex;
                            var item = items && items[index] ? items[index] : null;
                            if (!item) {
                                return [];
                            }

                            var lines = [];
                            var expectedMinutes = item && typeof item.expected_minutes === 'number' ? Math.max(item.expected_minutes, 0) : 0;
                            if (expectedMinutes > 0) {
                                var expectedLabel = (i18n && i18n.weeklyExpectedLabel) || (i18n && i18n.weeklyRequiredLabel) || 'Heures attendues';
                                lines.push(expectedLabel + ': ' + formatMinutesToHoursLabel(expectedMinutes));
                            }

                            var difference = item && typeof item.difference_minutes === 'number' ? item.difference_minutes : 0;
                            if (difference !== 0) {
                                var diffLabel = difference > 0
                                    ? (i18n && i18n.weeklyExtraLabel) || 'Heures supplémentaires'
                                    : (i18n && i18n.weeklyDeficitLabel) || 'Heures manquantes';
                                var sign = difference > 0 ? '+' : '-';
                                lines.push(diffLabel + ': ' + sign + formatMinutesToHoursLabel(Math.abs(difference)));
                            }

                            return lines;
                        } : undefined,
                    },
                },
            },
        };
    }, [hasData, hasSegments, items, i18n]);

    useChartJs(canvasRef, hasData ? 'bar' : null, chartData, chartOptions);

    return h('div', { className: 'mj-hours-dashboard-card mj-hours-dashboard-bar-chart' },
        title ? h('h2', { className: 'mj-hours-dashboard-card__heading' }, title || '') : null,
        subtitle ? h('p', { className: 'mj-hours-dashboard-card__subtitle' }, subtitle) : null,
        hasData ? h('div', { className: 'mj-hours-dashboard-bar-chart__canvas' },
            h('canvas', { ref: canvasRef, role: 'img', 'aria-label': title || datasetLabel || '' })
        ) : h('p', { className: 'mj-hours-dashboard__empty' }, emptyLabel || (i18n && (i18n.barChartEmpty || i18n.noMemberData)) || '')
    );
}


function SummaryCards({ totals, i18n }) {
    var safeTotals = totals || {};
    var safeI18n = i18n || {};

    var totalEntries = Number.isFinite(safeTotals.entries) ? safeTotals.entries : 0;
    var entriesLabel = safeI18n.entriesLabel || '';
    var entriesMeta = totalEntries > 0 && entriesLabel
        ? totalEntries + ' ' + entriesLabel
        : '';

    var membersCount = Number.isFinite(safeTotals.member_count) ? safeTotals.member_count : 0;
    var projectsCount = Number.isFinite(safeTotals.project_count) ? safeTotals.project_count : 0;
    var projectsLabel = safeI18n.projectsCount || '';
    var membersMeta = projectsCount > 0 && projectsLabel
        ? projectsCount + ' ' + projectsLabel
        : '';

    var weeklyAverageMeta = (safeTotals.weekly_average_meta && safeTotals.weekly_average_meta !== '')
        ? safeTotals.weekly_average_meta
        : (safeI18n.weeklyAverageMetaFallback || '');

    var balanceExtraHuman = (typeof safeTotals.weekly_extra_recent_human === 'string')
        ? safeTotals.weekly_extra_recent_human
        : '';
    var balanceExtraLabel = safeI18n.weeklyExtraLabel || '';
    var balanceMeta = balanceExtraHuman && balanceExtraLabel
        ? balanceExtraLabel + ' : ' + balanceExtraHuman
        : '';

    var expectedLabel = safeI18n.weeklyExpectedLabel || safeI18n.weeklyRequiredLabel || '';

    var cards = [
        {
            key: 'total-hours',
            title: safeI18n.totalHours || 'Heures totales encodées',
            value: typeof safeTotals.human === 'string' ? safeTotals.human : '0 min',
            meta: entriesMeta,
        },
        {
            key: 'members-count',
            title: safeI18n.membersCount || 'Membres',
            value: membersCount > 0 ? String(membersCount) : '0',
            meta: membersMeta,
        },
        {
            key: 'weekly-average',
            title: safeI18n.averageWeeklyHours || 'Moyenne hebdomadaire encodée',
            value: typeof safeTotals.weekly_average_human === 'string' ? safeTotals.weekly_average_human : '0 min',
            meta: weeklyAverageMeta,
        },
        {
            key: 'weekly-expected',
            title: expectedLabel || 'Heures attendues',
            value: typeof safeTotals.weekly_contract_human === 'string' ? safeTotals.weekly_contract_human : '0 min',
            meta: balanceMeta,
        },
        {
            key: 'weekly-balance',
            title: safeI18n.weeklyBalanceNetLabel || 'Solde cumulé',
            value: typeof safeTotals.weekly_balance_human === 'string' ? safeTotals.weekly_balance_human : '0 min',
            meta: balanceMeta,
        },
    ];

    var filteredCards = cards.filter(function (card) {
        return card && typeof card.title === 'string' && card.title !== '';
    });

    if (filteredCards.length === 0) {
        return null;
    }

    return h('div', { className: 'mj-hours-dashboard__summary' },
        filteredCards.map(function (card) {
            return h('article', { key: card.key, className: 'mj-hours-dashboard-card' },
                h('p', { className: 'mj-hours-dashboard-card__title' }, card.title),
                h('p', { className: 'mj-hours-dashboard-card__value' }, card.value || '0'),
                card.meta ? h('p', { className: 'mj-hours-dashboard-card__meta' }, card.meta) : null,
            );
        })
    );
}


function MemberSelector({ members, selectedId, onChange, label, helper }) {
    if (!Array.isArray(members) || members.length === 0) {
        return null;
    }

    return h('div', { className: 'mj-hours-dashboard__select' },
        label ? h('label', { htmlFor: 'mj-hours-dashboard-member-select' }, label) : null,
        h('select', {
            id: 'mj-hours-dashboard-member-select',
            value: selectedId || '',
            onChange: function (event) {
                var value = parseInt(event.target.value, 10);
                if (Number.isNaN(value)) {
                    value = 0;
                }
                onChange(value);
            },
        },
            members.map(function (member) {
                return h('option', { key: member.id, value: member.id }, member.label || '');
            })
        ),
        helper ? h('p', { className: 'mj-hours-dashboard__select-helper' }, helper) : null
    );
}

function MembersTable({ members, totals, i18n }) {
    if (!Array.isArray(members) || members.length === 0) {
        return h('p', { className: 'mj-hours-dashboard__empty' }, i18n.noMemberData || '');
    }

    var totalMinutes = Math.max(totals.minutes || 0, 0);

    return h('div', { className: 'mj-hours-dashboard-card mj-hours-dashboard__table-card' },
        h('h2', { className: 'mj-hours-dashboard-card__heading' }, i18n.memberTableTitle || ''),
        h('table', null,
            h('thead', null,
                h('tr', null,
                    h('th', null, i18n.memberColumn || ''),
                    h('th', null, i18n.hoursColumn || ''),
                    h('th', null, i18n.entriesColumn || ''),
                    h('th', null, i18n.rateColumn || ''),
                ),
            ),
            h('tbody', null,
                members.map(function (member) {
                    var share = totalMinutes > 0 ? (member.minutes || 0) / totalMinutes : 0;
                    return h('tr', { key: member.id },
                        h('td', null, member.label || ''),
                        h('td', null, member.human || '0'),
                        h('td', null, String(member.entries || 0)),
                        h('td', null, formatPercentage(share * 100)),
                    );
                })
            )
        )
    );
}

function HourEncodeEmbed({ config, configJson, panelId, labelledBy, emptyLabel }) {
    var containerRef = useRef(null);

    useEffect(function () {
        if (!config || !configJson) {
            return undefined;
        }

        if (typeof document === 'undefined') {
            return undefined;
        }

        var container = containerRef.current;
        if (!container) {
            return undefined;
        }

        var destroyEvent = new CustomEvent('mj-member-hour-encode:destroy', { detail: { context: container } });
        document.dispatchEvent(destroyEvent);
        container.innerHTML = '';

        var widgetElement = document.createElement('div');
        widgetElement.className = 'mj-hour-encode';
        widgetElement.setAttribute('data-config', configJson);
        container.appendChild(widgetElement);

        var initEvent = new CustomEvent('mj-member-hour-encode:init', { detail: { context: container } });
        document.dispatchEvent(initEvent);

        return function cleanup() {
            var cleanupEvent = new CustomEvent('mj-member-hour-encode:destroy', { detail: { context: container } });
            document.dispatchEvent(cleanupEvent);
            container.innerHTML = '';
        };
    }, [config, configJson]);

    var panelProps = {
        className: 'mj-hours-dashboard-card mj-hours-dashboard__editor-card',
    };

    if (panelId) {
        panelProps.id = panelId;
    }

    if (labelledBy) {
        panelProps.role = 'tabpanel';
        panelProps['aria-labelledby'] = labelledBy;
    }

    if (!config || !configJson) {
        return h('div', panelProps,
            h('p', { className: 'mj-hours-dashboard__empty' }, emptyLabel || 'Impossible de charger l’éditeur pour ce membre.')
        );
    }

    return h('div', panelProps,
        h('div', { ref: containerRef, className: 'mj-hours-dashboard__editor-widget' })
    );
}

function DashboardApp({ config }) {
    var data = config.data || {};
    var i18n = config.i18n || {};
    var totals = data.totals || {};
    var projects = Array.isArray(data.projects) ? data.projects : [];
    var members = Array.isArray(data.members) ? data.members : [];
    var timeseries = data.timeseries || {};
    var monthlyAll = Array.isArray(timeseries.months) ? timeseries.months : [];
    var weeklyAll = Array.isArray(timeseries.weeks) ? timeseries.weeks : [];
    var monthlyByMember = (timeseries.months_by_member && typeof timeseries.months_by_member === 'object') ? timeseries.months_by_member : {};
    var weeklyByMember = (timeseries.weeks_by_member && typeof timeseries.weeks_by_member === 'object') ? timeseries.weeks_by_member : {};

    var defaultMemberId = members.length > 0 ? members[0].id : 0;
    var [selectedMemberId, setSelectedMemberId] = useState(defaultMemberId);
    var [activeTab, setActiveTab] = useState('graphs');

    var selectedMember = useMemo(function () {
        if (!Array.isArray(members)) {
            return null;
        }
        return members.find(function (member) {
            return member.id === selectedMemberId;
        }) || null;
    }, [members, selectedMemberId]);

    var hourEncodeBase = (config && typeof config.hourEncode === 'object') ? config.hourEncode : null;

    var memberProjects = selectedMember && Array.isArray(selectedMember.projects) ? selectedMember.projects : [];
    var memberTotalMinutes = selectedMember ? Math.max(selectedMember.minutes || 0, 0) : 0;
    var memberContractMinutes = selectedMember ? Math.max(selectedMember.weekly_contract_minutes || 0, 0) : 0;
    var memberContractHuman = selectedMember ? (selectedMember.weekly_contract_human || '') : '';
    var totalContractMinutes = Math.max(totals.weekly_contract_minutes || 0, 0);
    var totalContractHuman = totals.weekly_contract_human || '';
    var memberBalanceMinutes = selectedMember ? Number(selectedMember.weekly_balance_minutes || 0) : 0;
    if (!Number.isFinite(memberBalanceMinutes)) {
        memberBalanceMinutes = 0;
    }
    var totalBalanceMinutes = Number(totals.weekly_balance_minutes || 0);
    if (!Number.isFinite(totalBalanceMinutes)) {
        totalBalanceMinutes = 0;
    }
    var memberBalanceHuman = selectedMember ? (selectedMember.weekly_balance_human || formatSignedMinutesToHoursLabel(memberBalanceMinutes)) : '';
    var totalBalanceHuman = totals.weekly_balance_human || formatSignedMinutesToHoursLabel(totalBalanceMinutes);

    var donutItems;
    var donutTotalMinutes;
    var donutCenterValue;
    var donutCenterLabel;

    if (selectedMember) {
        donutItems = memberProjects;
        donutTotalMinutes = memberTotalMinutes;
        donutCenterValue = selectedMember.human || '0';
        donutCenterLabel = selectedMember.label || '';
    } else {
        donutItems = projects;
        donutTotalMinutes = Math.max(totals.minutes || 0, 0);
        donutCenterValue = totals.human || '0';
        donutCenterLabel = i18n.totalHours || '';
    }

    var monthlySeriesForMember = (selectedMemberId && monthlyByMember && monthlyByMember[selectedMemberId]) ? monthlyByMember[selectedMemberId] : null;
    var weeklySeriesForMember = (selectedMemberId && weeklyByMember && weeklyByMember[selectedMemberId]) ? weeklyByMember[selectedMemberId] : null;

    var monthlySeries;
    if (selectedMember) {
        monthlySeries = Array.isArray(monthlySeriesForMember) ? monthlySeriesForMember : [];
    } else {
        monthlySeries = monthlyAll;
    }

    var weeklySeries;
    if (selectedMember) {
        weeklySeries = Array.isArray(weeklySeriesForMember) ? weeklySeriesForMember : [];
    } else {
        weeklySeries = weeklyAll;
    }

    var monthlyTitle = i18n.monthlyHoursTitle || '';
    var memberLabel = selectedMember ? (selectedMember.label || '') : '';
    var memberHuman = selectedMember ? (selectedMember.human || '0') : '';
    var totalHuman = totals.human || '';

    var monthlySubtitle = selectedMember ? (memberLabel ? memberLabel + ' · ' + memberHuman : memberHuman) : totalHuman;
    var weeklyTitle = i18n.weeklyHoursTitle || '';
    var expectedLabelShort = (i18n && i18n.weeklyExpectedLabel) || (i18n && i18n.weeklyRequiredLabel) || '';
    var weeklySubtitle;
    if (selectedMember) {
        weeklySubtitle = memberLabel ? memberLabel + ' · ' + memberHuman : memberHuman;
        if (memberContractMinutes > 0 && expectedLabelShort && memberContractHuman) {
            weeklySubtitle += ' · ' + expectedLabelShort + ' : ' + memberContractHuman;
        }
    } else {
        weeklySubtitle = totalHuman;
        if (totalContractMinutes > 0 && expectedLabelShort && totalContractHuman) {
            weeklySubtitle += ' · ' + expectedLabelShort + ' : ' + totalContractHuman;
        }
    }

    var balanceLabel = i18n.weeklyBalanceNetLabel || '';
    var balanceHuman = selectedMember ? memberBalanceHuman : totalBalanceHuman;
    if (balanceLabel && balanceHuman) {
        weeklySubtitle += ' · ' + balanceLabel + ' : ' + balanceHuman;
    }

    var baseProjectsTitle = i18n.projectsDonutTitle || '';
    var projectsDonutTitle;
    if (selectedMember && memberLabel) {
        projectsDonutTitle = baseProjectsTitle ? (baseProjectsTitle + ' · ' + memberLabel) : memberLabel;
    } else {
        projectsDonutTitle = baseProjectsTitle;
    }

    var canShowEditTab = Boolean(hourEncodeBase && selectedMember && selectedMemberId);
    useEffect(function () {
        if (!canShowEditTab && activeTab !== 'graphs') {
            setActiveTab('graphs');
        }
    }, [canShowEditTab, activeTab]);

    var editConfig = useMemo(function () {
        if (!hourEncodeBase || !selectedMember || !selectedMemberId) {
            return null;
        }

        var ajaxConfig = Object.assign({}, hourEncodeBase.ajax || {});
        var staticParams = Object.assign({}, ajaxConfig.staticParams || {});
        staticParams.member_id = String(selectedMemberId);
        ajaxConfig.staticParams = staticParams;

        var labelsConfig = Object.assign({}, hourEncodeBase.labels || {});
        if (selectedMember.label) {
            var baseTitle = typeof (hourEncodeBase.labels && hourEncodeBase.labels.title) === 'string'
                ? hourEncodeBase.labels.title
                : '';
            labelsConfig.title = baseTitle !== '' ? baseTitle + ' · ' + selectedMember.label : selectedMember.label;
        }

        var projectSuggestions = Array.isArray(selectedMember.projects)
            ? selectedMember.projects.map(function (project) {
                if (!project || typeof project !== 'object') {
                    return '';
                }
                if (project.raw_label) {
                    return String(project.raw_label);
                }
                if (!project.is_unassigned && project.label) {
                    return String(project.label);
                }
                return '';
            })
            : [];

        if ((!Array.isArray(projectSuggestions) || projectSuggestions.length === 0) && Array.isArray(hourEncodeBase.projects)) {
            projectSuggestions = hourEncodeBase.projects;
        }

        var uniqueProjects = Array.from(new Set(
            (Array.isArray(projectSuggestions) ? projectSuggestions : []).map(function (name) {
                return typeof name === 'string' ? name.trim() : '';
            }).filter(function (name) {
                return name !== '';
            })
        ));

        var workSchedule = Array.isArray(selectedMember.work_schedule) ? selectedMember.work_schedule : (hourEncodeBase.workSchedule || []);
        var cumulativeBalance = (selectedMember.cumulative_balance && typeof selectedMember.cumulative_balance === 'object')
            ? selectedMember.cumulative_balance
            : null;

        var capabilities = Object.assign({}, hourEncodeBase.capabilities || {}, {
            canManage: true,
        });

        return Object.assign({}, hourEncodeBase, {
            ajax: ajaxConfig,
            labels: labelsConfig,
            projects: uniqueProjects,
            entries: [],
            events: [],
            projectTotals: hourEncodeBase.projectTotals || [],
            workSchedule: workSchedule,
            cumulativeBalance: cumulativeBalance,
            capabilities: capabilities,
        });
    }, [hourEncodeBase, selectedMember, selectedMemberId]);

    var editConfigJson = useMemo(function () {
        if (!editConfig) {
            return null;
        }
        try {
            return JSON.stringify(editConfig);
        } catch (error) {
            console.error('MJ Member Hours dashboard failed to serialise edit config', error);
            return null;
        }
    }, [editConfig]);

    return h('div', { className: 'mj-hours-dashboard__content' },
        h(SummaryCards, { totals: totals, i18n: i18n }),
        h(MemberSelector, {
            members: members,
            selectedId: selectedMemberId,
            onChange: setSelectedMemberId,
            label: i18n.memberSelectLabel,
            helper: i18n.memberSelectHelper,
        }),
        canShowEditTab ? h('div', { className: 'mj-hours-dashboard__tabs', role: 'tablist' },
            h('button', {
                type: 'button',
                id: 'mj-hours-dashboard-tab-graphs',
                role: 'tab',
                'aria-selected': activeTab === 'graphs' ? 'true' : 'false',
                'aria-controls': 'mj-hours-dashboard-panel-graphs',
                tabIndex: activeTab === 'graphs' ? 0 : -1,
                className: 'mj-hours-dashboard__tab' + (activeTab === 'graphs' ? ' mj-hours-dashboard__tab--active' : ''),
                onClick: function () {
                    setActiveTab('graphs');
                },
            }, i18n.graphsTabLabel || 'Graphiques'),
            h('button', {
                type: 'button',
                id: 'mj-hours-dashboard-tab-edit',
                role: 'tab',
                'aria-selected': activeTab === 'edit' ? 'true' : 'false',
                'aria-controls': 'mj-hours-dashboard-panel-edit',
                tabIndex: activeTab === 'edit' ? 0 : -1,
                className: 'mj-hours-dashboard__tab' + (activeTab === 'edit' ? ' mj-hours-dashboard__tab--active' : ''),
                onClick: function () {
                    setActiveTab('edit');
                },
            }, i18n.editTabLabel || 'Éditer les heures')
        ) : null,
        (!canShowEditTab || activeTab === 'graphs') ? h('div', {
            className: 'mj-hours-dashboard__graphs',
            id: canShowEditTab ? 'mj-hours-dashboard-panel-graphs' : undefined,
            role: canShowEditTab ? 'tabpanel' : undefined,
            'aria-labelledby': canShowEditTab ? 'mj-hours-dashboard-tab-graphs' : undefined,
        },
            h(DonutChart, {
                title: projectsDonutTitle,
                items: donutItems,
                totalMinutes: donutTotalMinutes,
                centerValue: donutCenterValue,
                centerLabel: donutCenterLabel,
                i18n: i18n,
                emptyLabel: i18n.noProjectsForMember,
            }),
            h('div', { className: 'mj-hours-dashboard__grid mj-hours-dashboard__grid--timeseries' },
                h(BarChart, {
                    title: monthlyTitle,
                    subtitle: monthlySubtitle,
                    items: monthlySeries,
                    i18n: i18n,
                    emptyLabel: i18n.barChartEmpty,
                }),
                h(BarChart, {
                    title: weeklyTitle,
                    subtitle: weeklySubtitle,
                    items: weeklySeries,
                    i18n: i18n,
                    emptyLabel: i18n.barChartEmpty,
                })
            ),
            h(MembersTable, { members: members, totals: totals, i18n: i18n })
        ) : null,
        (canShowEditTab && activeTab === 'edit') ? h(HourEncodeEmbed, {
            config: editConfig,
            configJson: editConfigJson,
            panelId: 'mj-hours-dashboard-panel-edit',
            labelledBy: 'mj-hours-dashboard-tab-edit',
            emptyLabel: i18n.editTabError || 'Impossible de charger l’éditeur pour ce membre.',
        }) : null
    );
}

function bootstrap(root) {
    var globalConfig = typeof window !== 'undefined' ? window.mjMemberHoursDashboardConfig : null;
    var config = null;

    if (globalConfig && typeof globalConfig === 'object') {
        config = globalConfig;
    }

    if (!config) {
        var rawConfig = root.getAttribute('data-config') || root.dataset.config || '{}';
        config = parseConfig(rawConfig);
    }

    if (!config || typeof config !== 'object') {
        config = {};
    }

    ensurePreact().then(function () {
        try {
            render(h(DashboardApp, { config: config }), root);
            root.setAttribute('data-mj-hours-dashboard-ready', '1');
        } catch (error) {
            console.error('MJ Member Hours dashboard failed to render', error);
            var fallbackMessageRender = (config && config.i18n && config.i18n.renderError)
                ? config.i18n.renderError
                : "Impossible d'afficher le tableau de bord pour le moment.";
            root.innerHTML = '<p class="mj-hours-dashboard__empty">' + fallbackMessageRender + '</p>';
        }
    }).catch(function () {
        var fallbackMessageLoad = (config && config.i18n && config.i18n.renderError)
            ? config.i18n.renderError
            : "Impossible d'afficher le tableau de bord pour le moment.";
        root.innerHTML = '<p class="mj-hours-dashboard__empty">' + fallbackMessageLoad + '</p>';
    });
}

function init() {
    var roots = document.querySelectorAll('[data-mj-hours-dashboard]');
    roots.forEach(function (root) {
        bootstrap(root);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
