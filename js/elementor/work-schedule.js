/**
 * Work Schedule Widget (Preact + Hooks)
 *
 * Displays a weekly grid showing every staff member's work schedule.
 *
 * @package MJ_Member
 */
(function () {
    'use strict';

    const { h, render, Fragment } = window.preact;
    const { useState, useEffect, useMemo, useCallback } = window.preactHooks;

    /* ------------------------------------------------------------------ */
    /*  Constants                                                         */
    /* ------------------------------------------------------------------ */

    const DAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    const ROLE_COLORS = {
        coordinateur: '#8b5cf6',
        animateur: '#3b82f6',
    };

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    const minutesToLabel = (minutes, i18n) => {
        if (minutes <= 0) return i18n.off;
        const hrs = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (mins === 0) return `${hrs}${i18n.hoursShort}`;
        return `${hrs}${i18n.hoursShort}${String(mins).padStart(2, '0')}`;
    };

    const slotNetMinutes = (slot) => {
        if (!slot || !slot.start || !slot.end) return 0;
        const [sh, sm] = slot.start.split(':').map(Number);
        const [eh, em] = slot.end.split(':').map(Number);
        const gross = (eh * 60 + em) - (sh * 60 + sm);
        const breakMin = parseInt(slot.break_minutes, 10) || 0;
        return Math.max(0, gross - breakMin);
    };

    const weekTotalMinutes = (schedule) => {
        return (schedule || []).reduce((sum, slot) => sum + slotNetMinutes(slot), 0);
    };

    const roleLabel = (role, i18n) => {
        if (role === 'coordinateur') return i18n.coordinator;
        return i18n.animator;
    };

    /* ------------------------------------------------------------------ */
    /*  Components                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Renders one employee's row across the week.
     */
    function EmployeeRow({ member, showBreaks, showTotals, i18n }) {
        const slotsByDay = useMemo(() => {
            const map = {};
            DAY_KEYS.forEach((day) => { map[day] = null; });
            (member.schedule || []).forEach((slot) => {
                if (slot.day && DAY_KEYS.includes(slot.day)) {
                    map[slot.day] = slot;
                }
            });
            return map;
        }, [member.schedule]);

        const total = useMemo(() => weekTotalMinutes(member.schedule), [member.schedule]);

        const roleColor = ROLE_COLORS[member.role] || '#64748b';

        return h('tr', { class: 'mj-ws__row' },
            h('td', { class: 'mj-ws__cell mj-ws__cell--name' },
                h('div', { class: 'mj-ws__member' },
                    h('span', { class: 'mj-ws__member-name' }, member.name),
                    h('span', {
                        class: 'mj-ws__member-role',
                        style: { color: roleColor },
                    }, roleLabel(member.role, i18n)),
                ),
            ),
            DAY_KEYS.map((day) => {
                const slot = slotsByDay[day];
                if (!slot) {
                    return h('td', { class: 'mj-ws__cell mj-ws__cell--off', key: day },
                        h('span', { class: 'mj-ws__off' }, i18n.off),
                    );
                }

                const net = slotNetMinutes(slot);
                const breakMin = parseInt(slot.break_minutes, 10) || 0;

                return h('td', { class: 'mj-ws__cell mj-ws__cell--slot', key: day },
                    h('div', { class: 'mj-ws__slot' },
                        h('span', { class: 'mj-ws__slot-time' }, `${slot.start} – ${slot.end}`),
                        showBreaks && breakMin > 0
                            ? h('span', { class: 'mj-ws__slot-break' },
                                `${i18n.break} ${breakMin}${i18n.minutesShort}`)
                            : null,
                        h('span', { class: 'mj-ws__slot-net' }, minutesToLabel(net, i18n)),
                    ),
                );
            }),
            showTotals
                ? h('td', { class: 'mj-ws__cell mj-ws__cell--total' },
                    h('strong', null, minutesToLabel(total, i18n)),
                )
                : null,
        );
    }

    /**
     * Summary row showing totals per day.
     */
    function TotalsRow({ schedules, showTotals, i18n }) {
        if (!showTotals) return null;

        const dayTotals = useMemo(() => {
            const totals = {};
            DAY_KEYS.forEach((day) => { totals[day] = 0; });
            schedules.forEach((member) => {
                (member.schedule || []).forEach((slot) => {
                    if (slot.day && DAY_KEYS.includes(slot.day)) {
                        totals[slot.day] += slotNetMinutes(slot);
                    }
                });
            });
            return totals;
        }, [schedules]);

        const grandTotal = useMemo(() => {
            return Object.values(dayTotals).reduce((a, b) => a + b, 0);
        }, [dayTotals]);

        return h('tr', { class: 'mj-ws__row mj-ws__row--totals' },
            h('td', { class: 'mj-ws__cell mj-ws__cell--name mj-ws__cell--totals-label' },
                h('strong', null, i18n.total),
            ),
            DAY_KEYS.map((day) =>
                h('td', { class: 'mj-ws__cell mj-ws__cell--total', key: day },
                    h('strong', null, minutesToLabel(dayTotals[day], i18n)),
                )
            ),
            h('td', { class: 'mj-ws__cell mj-ws__cell--total mj-ws__cell--grand-total' },
                h('strong', null, minutesToLabel(grandTotal, i18n)),
            ),
        );
    }

    /**
     * Mobile card view for a single employee.
     */
    function EmployeeCard({ member, showBreaks, showTotals, i18n }) {
        const total = useMemo(() => weekTotalMinutes(member.schedule), [member.schedule]);
        const roleColor = ROLE_COLORS[member.role] || '#64748b';
        const slots = (member.schedule || []).filter((s) => s.day && DAY_KEYS.includes(s.day));
        const dayIndex = (d) => DAY_KEYS.indexOf(d);
        const sorted = [...slots].sort((a, b) => dayIndex(a.day) - dayIndex(b.day));

        return h('div', { class: 'mj-ws__card' },
            h('div', { class: 'mj-ws__card-header' },
                h('span', { class: 'mj-ws__card-name' }, member.name),
                h('span', { class: 'mj-ws__card-role', style: { color: roleColor } }, roleLabel(member.role, i18n)),
                showTotals
                    ? h('span', { class: 'mj-ws__card-total' }, minutesToLabel(total, i18n))
                    : null,
            ),
            sorted.length === 0
                ? h('p', { class: 'mj-ws__card-empty' }, i18n.noSchedule)
                : h('div', { class: 'mj-ws__card-slots' },
                    sorted.map((slot) => {
                        const idx = DAY_KEYS.indexOf(slot.day);
                        const net = slotNetMinutes(slot);
                        const breakMin = parseInt(slot.break_minutes, 10) || 0;
                        return h('div', { class: 'mj-ws__card-slot', key: slot.day },
                            h('span', { class: 'mj-ws__card-day' }, i18n.daysShort[idx]),
                            h('span', { class: 'mj-ws__card-time' }, `${slot.start} – ${slot.end}`),
                            showBreaks && breakMin > 0
                                ? h('span', { class: 'mj-ws__card-break' },
                                    `${i18n.break} ${breakMin}${i18n.minutesShort}`)
                                : null,
                            h('span', { class: 'mj-ws__card-net' }, minutesToLabel(net, i18n)),
                        );
                    }),
                ),
        );
    }

    /**
     * Main WorkScheduleApp component.
     */
    function WorkScheduleApp({ config }) {
        const { i18n, preview } = config;
        const [schedules, setSchedules] = useState(config.schedules || []);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState(null);

        const hasData = schedules.length > 0;
        const hasAnySlot = schedules.some((m) => (m.schedule || []).length > 0);

        const refresh = useCallback(() => {
            if (preview) return;
            setLoading(true);
            setError(null);

            const fd = new FormData();
            fd.append('action', 'mj_work_schedules_get_all');
            fd.append('nonce', config.nonce);

            fetch(config.ajaxUrl, { method: 'POST', body: fd })
                .then((res) => res.json())
                .then((json) => {
                    if (json.success && json.data && json.data.schedules) {
                        setSchedules(json.data.schedules);
                    } else {
                        setError(json.data && json.data.message ? json.data.message : i18n.error);
                    }
                })
                .catch(() => setError(i18n.error))
                .finally(() => setLoading(false));
        }, [config, preview, i18n]);

        if (!config.hasAccess) {
            return null; // Fallback handled in PHP
        }

        return h(Fragment, null,
            // Header
            config.title
                ? h('h3', { class: 'mj-ws__title' }, config.title)
                : null,
            config.intro
                ? h('div', {
                    class: 'mj-ws__intro',
                    dangerouslySetInnerHTML: { __html: config.intro },
                })
                : null,

            // Loading / Error
            loading
                ? h('div', { class: 'mj-ws__loading' }, i18n.loading)
                : null,
            error
                ? h('div', { class: 'mj-ws__error' },
                    h('span', null, error),
                    h('button', {
                        class: 'mj-ws__btn mj-ws__btn--retry',
                        type: 'button',
                        onClick: refresh,
                    }, i18n.refresh),
                )
                : null,

            // Empty state
            !loading && !error && !hasData
                ? h('p', { class: 'mj-ws__empty' }, i18n.noEmployees)
                : null,
            !loading && !error && hasData && !hasAnySlot
                ? h('p', { class: 'mj-ws__empty' }, i18n.noSchedule)
                : null,

            // Desktop table
            !loading && !error && hasAnySlot
                ? h('div', { class: 'mj-ws__table-wrap' },
                    h('table', { class: 'mj-ws__table' },
                        h('thead', null,
                            h('tr', null,
                                h('th', { class: 'mj-ws__th mj-ws__th--name' }, ''),
                                i18n.days.map((day, idx) =>
                                    h('th', { class: 'mj-ws__th', key: idx }, day),
                                ),
                                config.showTotals
                                    ? h('th', { class: 'mj-ws__th mj-ws__th--total' }, i18n.total)
                                    : null,
                            ),
                        ),
                        h('tbody', null,
                            schedules.map((member) =>
                                h(EmployeeRow, {
                                    member,
                                    showBreaks: config.showBreaks,
                                    showTotals: config.showTotals,
                                    i18n,
                                    key: member.memberId,
                                }),
                            ),
                            h(TotalsRow, {
                                schedules,
                                showTotals: config.showTotals,
                                i18n,
                            }),
                        ),
                    ),
                )
                : null,

            // Mobile cards
            !loading && !error && hasAnySlot
                ? h('div', { class: 'mj-ws__cards' },
                    schedules.map((member) =>
                        h(EmployeeCard, {
                            member,
                            showBreaks: config.showBreaks,
                            showTotals: config.showTotals,
                            i18n,
                            key: member.memberId,
                        }),
                    ),
                )
                : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Init                                                              */
    /* ------------------------------------------------------------------ */

    function initWorkScheduleWidgets() {
        document.querySelectorAll('[data-mj-work-schedule-widget]').forEach((el) => {
            if (el.dataset.mjWsInitialized) return;
            el.dataset.mjWsInitialized = '1';

            let config;
            try {
                config = JSON.parse(el.dataset.config || '{}');
            } catch (e) {
                console.error('[mj-work-schedule] Invalid config JSON', e);
                return;
            }

            render(h(WorkScheduleApp, { config }), el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWorkScheduleWidgets);
    } else {
        initWorkScheduleWidgets();
    }

    // Re-init on Elementor frontend:init
    if (window.elementorFrontend) {
        jQuery(window).on('elementor/frontend/init', function () {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-work-schedule.default', function ($scope) {
                initWorkScheduleWidgets();
            });
        });
    }
})();
