/**
 * MJ Admin Dashboard Widget – Preact front-end.
 * Complete rewrite – modern card-based UI with inline SVG icons,
 * animated KPIs, horizontal donut + legend, responsive grid.
 */
(function () {
    'use strict';

    var h = window.preact.h;
    var render = window.preact.render;
    var useState = window.preactHooks.useState;
    var useEffect = window.preactHooks.useEffect;
    var useRef = window.preactHooks.useRef;

    /* ── SVG icon helper ────────────────────────────────────────── */
    var ICONS = {
        users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        star: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
        wallet: 'M21 4H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2ZM16 12a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z',
        ticket: 'M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2Z',
        calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
        clock: 'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10ZM12 6v6l4 2',
        chat: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z',
        alert: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0ZM12 9v4M12 17h.01',
        check: 'M22 11.08V12a10 10 0 1 1-5.93-9.14M22 4 12 14.01l-3-3',
        chevDown: 'M6 9l6 6 6-6',
        chevRight: 'M9 18l6-6-6-6',
    };

    function Icon(props) {
        var d = ICONS[props.name] || '';
        return h('svg', {
            className: 'mjd-icon' + (props.className ? ' ' + props.className : ''),
            width: props.size || 20,
            height: props.size || 20,
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': props.strokeWidth || 2,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round'
        }, d.split('Z').map(function (seg, i) {
            seg = seg.trim();
            if (!seg) return null;
            var closed = i < d.split('Z').length - 1;
            return h('path', { key: i, d: seg + (closed ? 'Z' : '') });
        }));
    }

    /* ── Chart.js lazy loader ───────────────────────────────────── */
    var CHART_JS_URL = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js';
    var chartJsPromise = null;
    function ensureChartJs() {
        if (window.Chart) return Promise.resolve(window.Chart);
        if (chartJsPromise) return chartJsPromise;
        chartJsPromise = new Promise(function (ok, fail) {
            var s = document.createElement('script');
            s.src = CHART_JS_URL; s.async = true;
            s.onload = function () { ok(window.Chart); };
            s.onerror = function () { fail(new Error('Chart.js failed')); };
            document.head.appendChild(s);
        });
        return chartJsPromise;
    }

    /* ── Palette ────────────────────────────────────────────────── */
    var PAL = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#f97316','#ec4899','#84cc16','#14b8a6'];
    function col(i) { return PAL[i % PAL.length]; }

    /* ── Helpers ─────────────────────────────────────────────────── */
    function fmt(n) { return (Number(n) || 0).toLocaleString('fr-BE'); }
    function money(n) { return (Number(n) || 0).toLocaleString('fr-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; }
    function pct(v, t) { return t > 0 ? Math.round(v / t * 100) : 0; }
    function cls() { return [].slice.call(arguments).filter(Boolean).join(' '); }

    /* ── Animated number ────────────────────────────────────────── */
    function AnimNum(props) {
        var ref = useRef(null);
        var target = Number(props.value) || 0;
        useEffect(function () {
            var el = ref.current; if (!el) return;
            var start = 0; var dur = 600; var t0 = null;
            function tick(ts) {
                if (!t0) t0 = ts;
                var p = Math.min((ts - t0) / dur, 1);
                p = 1 - Math.pow(1 - p, 3); // ease-out cubic
                el.textContent = Math.round(start + (target - start) * p).toLocaleString('fr-BE');
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }, [target]);
        return h('span', { ref: ref }, '0');
    }

    /* ── KPI Card ───────────────────────────────────────────────── */
    function KpiCard(props) {
        return h('div', { className: 'mjd-kpi mjd-kpi--' + (props.accent || 'indigo') },
            h('div', { className: 'mjd-kpi__icon-ring' }, h(Icon, { name: props.icon, size: 22 })),
            h('div', { className: 'mjd-kpi__body' },
                h('span', { className: 'mjd-kpi__label' }, props.label),
                h('span', { className: 'mjd-kpi__value' }, h(AnimNum, { value: props.value })),
                props.sub ? h('span', { className: 'mjd-kpi__sub' }, props.sub) : null
            )
        );
    }

    /* ── Mini progress bar row ──────────────────────────────────── */
    function ProgressRow(props) {
        var max = props.max || 100;
        var w = Math.min(100, pct(props.value, max));
        return h('div', { className: 'mjd-prow' },
            h('div', { className: 'mjd-prow__head' },
                h('span', { className: 'mjd-prow__label' }, props.label),
                h('span', { className: 'mjd-prow__num' }, fmt(props.value))
            ),
            h('div', { className: 'mjd-prow__track' },
                h('div', { className: 'mjd-prow__fill', style: { width: w + '%', background: props.color || col(0) } })
            )
        );
    }

    /* ── Donut chart ────────────────────────────────────────────── */
    function Donut(props) {
        var canvasRef = useRef(null);
        var chartRef  = useRef(null);
        var items = props.items || [];
        var total = Number(props.total) || items.reduce(function (s, i) { return s + (Number(i.count) || 0); }, 0);

        useEffect(function () {
            if (!items.length) return;
            ensureChartJs().then(function (Chart) {
                if (!canvasRef.current) return;
                if (chartRef.current) chartRef.current.destroy();
                chartRef.current = new Chart(canvasRef.current, {
                    type: 'doughnut',
                    data: {
                        labels: items.map(function (r) { return r.label; }),
                        datasets: [{
                            data: items.map(function (r) { return r.count; }),
                            backgroundColor: items.map(function (_, i) { return col(i); }),
                            borderWidth: 0, hoverOffset: 6
                        }]
                    },
                    options: {
                        cutout: '66%', responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { bodyFont: { size: 13 } } },
                        animation: { animateRotate: true, duration: 700 }
                    }
                });
            });
            return function () { if (chartRef.current) chartRef.current.destroy(); };
        }, [items]);

        if (!items.length) return h('p', { className: 'mjd-empty' }, 'Aucune donnée.');

        return h('div', { className: 'mjd-donut' },
            h('div', { className: 'mjd-donut__chart' },
                h('canvas', { ref: canvasRef }),
                h('div', { className: 'mjd-donut__center' },
                    h('strong', null, fmt(total)),
                    props.centerLabel ? h('small', null, props.centerLabel) : null
                )
            ),
            h('ul', { className: 'mjd-donut__legend' },
                items.map(function (item, i) {
                    return h('li', { key: item.key || i },
                        h('span', { className: 'mjd-donut__swatch', style: { background: col(i) } }),
                        h('span', { className: 'mjd-donut__lbl' }, item.label),
                        h('span', { className: 'mjd-donut__cnt' }, fmt(item.count)),
                        h('span', { className: 'mjd-donut__pct' }, (item.percent || pct(item.count, total)) + '%')
                    );
                })
            )
        );
    }

    /* ── Bar chart ──────────────────────────────────────────────── */
    function BarChart(props) {
        var canvasRef = useRef(null);
        var chartRef  = useRef(null);
        var series = props.series || [];

        useEffect(function () {
            if (!series.length) return;
            ensureChartJs().then(function (Chart) {
                if (!canvasRef.current) return;
                if (chartRef.current) chartRef.current.destroy();
                chartRef.current = new Chart(canvasRef.current, {
                    type: 'bar',
                    data: {
                        labels: series.map(function (s) { return s.label; }),
                        datasets: [
                            { label: 'Inscriptions', data: series.map(function (s) { return s.registrations; }), backgroundColor: '#6366f1', borderRadius: 6, barPercentage: 0.5 },
                            { label: 'Paiements', data: series.map(function (s) { return s.payments; }), backgroundColor: '#10b981', borderRadius: 6, barPercentage: 0.5 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', padding: 20, font: { size: 12 } } },
                            tooltip: {
                                backgroundColor: '#1e293b', titleFont: { size: 13 }, bodyFont: { size: 12 }, cornerRadius: 8, padding: 12,
                                callbacks: { afterBody: function (ctx) { return 'Montant : ' + money(series[ctx[0].dataIndex].amount); } }
                            }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.15)' }, ticks: { precision: 0, font: { size: 11 }, color: '#94a3b8' } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94a3b8' } }
                        },
                        animation: { duration: 800, easing: 'easeOutQuart' }
                    }
                });
            });
            return function () { if (chartRef.current) chartRef.current.destroy(); };
        }, [series]);

        if (!series.length) return h('p', { className: 'mjd-empty' }, 'Aucune donnée disponible.');
        return h('div', { className: 'mjd-barchart' }, h('canvas', { ref: canvasRef }));
    }

    /* ── Card shell ─────────────────────────────────────────────── */
    function Card(props) {
        return h('section', { className: cls('mjd-card', props.className) },
            h('header', { className: 'mjd-card__head' },
                props.icon ? h('div', { className: 'mjd-card__head-icon' }, h(Icon, { name: props.icon, size: 18 })) : null,
                h('h2', { className: 'mjd-card__title' }, props.title),
                props.badge ? h('span', { className: 'mjd-card__badge' }, props.badge) : null
            ),
            h('div', { className: 'mjd-card__body' }, props.children)
        );
    }

    /* ── Tabs ────────────────────────────────────────────────────── */
    function Tabs(props) {
        var st = useState(0);
        var active = st[0], set = st[1];
        var tabs = props.tabs || [];
        if (!tabs.length) return null;
        return h('div', { className: 'mjd-tabs' },
            h('nav', { className: 'mjd-tabs__nav' },
                tabs.map(function (t, i) {
                    return h('button', { key: i, type: 'button', className: cls('mjd-tabs__btn', active === i && 'mjd-tabs__btn--on'), onClick: function () { set(i); } }, t.label);
                })
            ),
            h('div', { className: 'mjd-tabs__pane' }, tabs[active] ? tabs[active].content : null)
        );
    }

    /* ── Table ───────────────────────────────────────────────────── */
    function Table(props) {
        var cols = props.columns || [];
        var rows = props.rows || [];
        if (!rows.length) return h('p', { className: 'mjd-empty' }, props.empty || 'Aucune donnée.');
        return h('div', { className: 'mjd-table-wrap' },
            h('table', { className: 'mjd-table' },
                h('thead', null, h('tr', null, cols.map(function (c) { return h('th', { key: c.key }, c.label); }))),
                h('tbody', null, rows.map(function (row, ri) {
                    return h('tr', { key: ri }, cols.map(function (c) {
                        var v = row[c.key];
                        if (c.render) return h('td', { key: c.key }, c.render(v, row));
                        return h('td', { key: c.key }, v != null ? String(v) : '');
                    }));
                }))
            )
        );
    }

    /* ── Badge ───────────────────────────────────────────────────── */
    function Badge(props) {
        return h('span', { className: cls('mjd-badge', 'mjd-badge--' + (props.type || 'default')) }, props.children);
    }

    /* ── Metric pill row (membership) ───────────────────────────── */
    function MetricPills(props) {
        return h('div', { className: 'mjd-pills' },
            (props.items || []).map(function (item, i) {
                return h('div', { key: i, className: cls('mjd-pill', item.alert && 'mjd-pill--alert') },
                    h('span', { className: 'mjd-pill__val' }, fmt(item.value)),
                    h('span', { className: 'mjd-pill__lbl' }, item.label)
                );
            })
        );
    }

    /* ── Event row card ─────────────────────────────────────────── */
    function EventRow(props) {
        var ev = props.event;
        var capacity = ev.capacity_total > 0;
        var full = capacity && ev.active_count >= ev.capacity_total;
        return h('div', { className: cls('mjd-event-row', full && 'mjd-event-row--full') },
            h('div', { className: 'mjd-event-row__info' },
                h('strong', null, ev.title),
                h('span', { className: 'mjd-event-row__date' }, ev.date)
            ),
            h('div', { className: 'mjd-event-row__stats' },
                h('span', { className: 'mjd-event-row__count' },
                    capacity ? fmt(ev.active_count) + '/' + fmt(ev.capacity_total) : fmt(ev.active_count),
                    ' inscrits'
                ),
                ev.waitlist_count > 0 ? h('span', { className: 'mjd-event-row__wait' }, fmt(ev.waitlist_count) + ' en attente') : null
            ),
            capacity ? h('div', { className: 'mjd-event-row__bar' },
                h('div', { className: 'mjd-event-row__bar-fill', style: { width: Math.min(100, pct(ev.active_count, ev.capacity_total)) + '%' } })
            ) : null
        );
    }

    /* ──────────────────────────────────────────────────────────────
       MAIN DASHBOARD
       ────────────────────────────────────────────────────────────── */
    function Dashboard(props) {
        var d     = props.data || {};
        var stats = d.stats || {};
        var ms    = d.memberStats || {};
        var es    = d.eventStats || {};
        var mb    = d.membershipSummary || {};
        var recent = d.recentMembers || [];
        var ts    = d.testimonialStats || {};
        var mwl   = d.membersWithLogin || [];
        var dfs   = d.dynamicFieldStats || [];

        /* ── Row 1 : KPI strip ─────────────────────────────────── */
        var kpiRow = h('div', { className: 'mjd-kpis' },
            h(KpiCard, { icon: 'users',  accent: 'indigo', label: 'Membres total',      value: stats.total_members }),
            h(KpiCard, { icon: 'check',  accent: 'emerald', label: 'Membres actifs',    value: stats.active_members }),
            h(KpiCard, { icon: 'star',   accent: 'violet', label: 'Animateurs actifs',   value: stats.active_animateurs }),
            h(KpiCard, { icon: 'wallet', accent: 'amber',  label: 'Paiements (30 j)',   value: stats.recent_payments_count, sub: money(stats.recent_payments_total) }),
            h(KpiCard, { icon: 'ticket', accent: 'cyan',   label: 'Inscriptions (30 j)', value: stats.recent_registrations })
        );

        /* ── Row 2 : Chart + Member breakdown ──────────────────── */
        var chartCard = h(Card, { title: 'Activité mensuelle', icon: 'calendar', className: 'mjd-card--chart' },
            h(BarChart, { series: d.series })
        );

        var memberTabs = [];
        if (ms.roles && ms.roles.length)        memberTabs.push({ label: 'Rôles',       content: h(Donut, { items: ms.roles,        total: ms.total, centerLabel: 'membres' }) });
        if (ms.statuses && ms.statuses.length)   memberTabs.push({ label: 'Statut',      content: h(Donut, { items: ms.statuses,      total: ms.total, centerLabel: 'membres' }) });
        if (ms.payments && ms.payments.length)   memberTabs.push({ label: 'Cotisations', content: h(Donut, { items: ms.payments,      total: ms.total, centerLabel: 'membres' }) });
        if (ms.age_brackets && ms.age_brackets.length) memberTabs.push({ label: 'Âge',   content: h(Donut, { items: ms.age_brackets, total: ms.total, centerLabel: 'membres' }) });

        /* Append dynamic field distribution tabs */
        dfs.forEach(function (df) {
            if (df.items && df.items.length) {
                memberTabs.push({ label: df.title, content: h(Donut, { items: df.items, total: df.total, centerLabel: 'réponses' }) });
            }
        });

        var memberCard = memberTabs.length > 0
            ? h(Card, { title: 'Membres', icon: 'users', badge: fmt(ms.total), className: 'mjd-card--members' }, h(Tabs, { tabs: memberTabs }))
            : null;

        /* ── Row 3 : Membership alerts + Upcoming events ───────── */
        var membershipCard = null;
        if ((mb.requires_payment_total || 0) > 0) {
            var mbChildren = [
                h(MetricPills, { items: [
                    { label: 'Manquant',    value: mb.missing_count,  alert: (mb.missing_count || 0) > 0 },
                    { label: 'Expire bientôt', value: mb.expiring_count, alert: (mb.expiring_count || 0) > 0 },
                    { label: 'En retard',   value: mb.expired_count,  alert: (mb.expired_count || 0) > 0 },
                    { label: 'À jour',      value: mb.up_to_date_count }
                ]})
            ];
            if (mb.upcoming && mb.upcoming.length) {
                mbChildren.push(h(Table, {
                    columns: [
                        { key: 'label', label: 'Membre' },
                        { key: 'status_label', label: 'Statut', render: function (v, r) {
                            var type = (r.status_label || '').indexOf('expir') >= 0 ? 'danger' : 'warning';
                            return h(Badge, { type: type }, v);
                        }},
                        { key: 'deadline', label: 'Échéance' },
                        { key: 'delay_label', label: 'Délai' }
                    ],
                    rows: mb.upcoming
                }));
            }
            membershipCard = h(Card, { title: 'Cotisations', icon: 'alert', badge: fmt(mb.requires_payment_total) + ' à suivre', className: 'mjd-card--alert' }, mbChildren);
        }

        var eventsCard = null;
        var evSummary = (es.upcoming_events_summary || []);
        if (es.total_events > 0 || evSummary.length > 0) {
            var evChildren = [];
            if (es.total_events > 0) {
                evChildren.push(h('div', { className: 'mjd-ev-stats' },
                    h(ProgressRow, { label: 'Actifs', value: es.active_registrations, max: es.active_registrations + es.cancelled_registrations + es.waitlist_registrations, color: '#10b981' }),
                    h(ProgressRow, { label: 'Annulés', value: es.cancelled_registrations, max: es.active_registrations + es.cancelled_registrations + es.waitlist_registrations, color: '#ef4444' }),
                    h(ProgressRow, { label: 'Liste d\'attente', value: es.waitlist_registrations, max: es.active_registrations + es.cancelled_registrations + es.waitlist_registrations, color: '#f59e0b' })
                ));
            }
            if (evSummary.length) {
                evChildren.push(
                    h('h2', { className: 'mjd-section-title' }, 'Prochains événements'),
                    h('div', { className: 'mjd-event-list' },
                        evSummary.map(function (ev, i) { return h(EventRow, { key: i, event: ev }); })
                    )
                );
            }
            eventsCard = h(Card, { title: 'Événements', icon: 'calendar', badge: fmt(es.upcoming_events) + ' à venir' }, evChildren);
        }

        /* ── Row 4 : Recent members + Testimonials ─────────── */
        var recentCard = recent.length > 0
            ? h(Card, { title: 'Nouveaux membres', icon: 'users' },
                h(Table, {
                    columns: [
                        { key: 'label', label: 'Nom' },
                        { key: 'status_label', label: 'Statut', render: function (v, r) { return h(Badge, { type: r.status === 'active' ? 'success' : r.status === 'inactive' ? 'danger' : 'default' }, v); } },
                        { key: 'date_display', label: 'Inscription' }
                    ],
                    rows: recent
                })
            ) : null;

        var testimonialCard = null;
        var tsTotal = Number(ts.total) || 0;
        if (tsTotal > 0 || (ts.recent && ts.recent.length)) {
            var tsChildren = [
                h(MetricPills, { items: [
                    { label: 'Total',     value: ts.total },
                    { label: 'Validés',   value: ts.approved },
                    { label: 'En attente', value: ts.pending, alert: (Number(ts.pending) || 0) > 0 },
                    { label: 'Refusés',   value: ts.rejected }
                ]})
            ];
            if (ts.recent && ts.recent.length) {
                tsChildren.push(
                    h('h2', { className: 'mjd-section-title' }, 'Derniers témoignages'),
                    h('div', { className: 'mjd-testimonial-list' },
                        ts.recent.map(function (t, i) {
                            var statusType = t.status === 'approved' ? 'success' : t.status === 'rejected' ? 'danger' : 'warning';
                            var statusLabel = t.status === 'approved' ? 'Validé' : t.status === 'rejected' ? 'Refusé' : 'En attente';
                            return h('div', { key: i, className: 'mjd-testimonial-item' },
                                h('div', { className: 'mjd-testimonial-item__head' },
                                    h('strong', { className: 'mjd-testimonial-item__author' }, t.author),
                                    h(Badge, { type: statusType }, statusLabel)
                                ),
                                h('p', { className: 'mjd-testimonial-item__excerpt' }, t.excerpt),
                                h('span', { className: 'mjd-testimonial-item__date' }, t.date)
                            );
                        })
                    )
                );
            }
            testimonialCard = h(Card, { title: 'Témoignages', icon: 'chat', badge: (Number(ts.pending) || 0) > 0 ? fmt(ts.pending) + ' en attente' : null, className: (Number(ts.pending) || 0) > 0 ? 'mjd-card--alert' : '' }, tsChildren);
        }

        /* ── Row 5 : Members with login ────────────────────────── */
        var loginCard = null;
        var withLogin = Number(stats.members_with_login) || 0;
        var totalAll  = Number(stats.total_members) || 0;
        if (withLogin > 0 || mwl.length > 0) {
            var loginChildren = [
                h('div', { className: 'mjd-login-summary' },
                    h('div', { className: 'mjd-login-summary__nums' },
                        h('span', { className: 'mjd-login-summary__big' }, fmt(withLogin)),
                        h('span', { className: 'mjd-login-summary__sep' }, '/'),
                        h('span', { className: 'mjd-login-summary__total' }, fmt(totalAll) + ' membres')
                    ),
                    h('div', { className: 'mjd-login-summary__bar' },
                        h('div', { className: 'mjd-login-summary__fill', style: { width: pct(withLogin, totalAll) + '%' } })
                    ),
                    h('span', { className: 'mjd-login-summary__pct' }, pct(withLogin, totalAll) + '% ont un compte WordPress')
                )
            ];
            if (mwl.length > 0) {
                loginChildren.push(
                    h('h2', { className: 'mjd-section-title' }, 'Dernières connexions'),
                    h(Table, {
                        columns: [
                            { key: 'label', label: 'Nom' },
                            { key: 'role', label: 'Rôle' },
                            { key: 'status_label', label: 'Statut', render: function (v, r) {
                                return h(Badge, { type: r.status === 'active' ? 'success' : 'danger' }, v);
                            }},
                            { key: 'last_login', label: 'Dernière connexion' },
                            { key: 'last_activity', label: 'Dernière activité' }
                        ],
                        rows: mwl
                    })
                );
            }
            loginCard = h(Card, { title: 'Membres avec login', icon: 'check', badge: fmt(withLogin) + ' comptes' }, loginChildren);
        }

        /* ── Assemble ──────────────────────────────────────────── */
        return h('div', { className: 'mjd' },
            kpiRow,
            h('div', { className: 'mjd-row mjd-row--2' }, chartCard, memberCard),
            h('div', { className: 'mjd-row mjd-row--2' }, membershipCard, eventsCard),
            h('div', { className: 'mjd-row mjd-row--2' }, recentCard, testimonialCard),
            loginCard ? h('div', { className: 'mjd-row mjd-row--1' }, loginCard) : null
        );
    }

    /* ── Bootstrap ──────────────────────────────────────────────── */
    function mount(container, data) { container.innerHTML = ''; render(h(Dashboard, { data: data }), container); }

    function bootstrap(root) {
        if (root.getAttribute('data-mj-admin-dashboard-ready')) return;
        root.setAttribute('data-mj-admin-dashboard-ready', '1');
        var data = {};
        try { data = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { /* */ }
        mount(root, data);
    }

    function init() {
        document.querySelectorAll('[data-mj-admin-dashboard]').forEach(function (r) { bootstrap(r); });
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }

    window.MjAdminDashboard = { mount: mount };
})();
