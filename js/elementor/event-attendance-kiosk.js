(function () {
    'use strict';

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function postAction(config, action, payload) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', config.nonce || '');
        Object.keys(payload || {}).forEach(function (key) {
            var val = payload[key];
            if (val === null || typeof val === 'undefined') {
                return;
            }
            if (typeof val === 'object') {
                body.append(key, JSON.stringify(val));
                return;
            }
            body.append(key, String(val));
        });

        return fetch(config.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin',
        }).then(function (res) {
            return res.json();
        });
    }

    function formatDayLabel(value) {
        if (!value) {
            return '';
        }
        var d = new Date(value);
        if (isNaN(d.getTime())) {
            return value;
        }
        return d.toLocaleDateString('fr-BE', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function initials(member) {
        var first = (member && member.firstName) ? member.firstName.charAt(0) : '';
        var last = (member && member.lastName) ? member.lastName.charAt(0) : '';
        return (first + last).toUpperCase() || '?';
    }

    function normalizeMemberRows(registrations, attendanceMembers) {
        var rows = [];
        var seen = {};

        (registrations || []).forEach(function (reg) {
            if (!reg || !reg.member || !reg.member.id) {
                return;
            }
            seen[reg.member.id] = true;
            rows.push({
                memberId: reg.member.id,
                member: reg.member,
                guardian: reg.guardian || null,
                attendance: reg.attendance || {},
            });
        });

        (attendanceMembers || []).forEach(function (member) {
            if (!member || !member.id || seen[member.id]) {
                return;
            }
            rows.push({
                memberId: member.id,
                member: member,
                guardian: null,
                attendance: {},
            });
        });

        return rows;
    }

    function resolveStatus(attendanceMap, occurrenceKey) {
        if (!attendanceMap || !occurrenceKey) {
            return '';
        }
        if (attendanceMap[occurrenceKey]) {
            return attendanceMap[occurrenceKey];
        }
        var dayKey = occurrenceKey.slice(0, 10);
        if (attendanceMap[dayKey]) {
            return attendanceMap[dayKey];
        }
        return '';
    }

    function render(app) {
        var cfg = app.config;
        var s = app.state;
        var strings = cfg.strings || {};
        var previousSearch = app.root.querySelector('.mj-attkiosk__search');
        var shouldRestoreSearchFocus = false;
        var searchSelectionStart = null;
        var searchSelectionEnd = null;

        if (previousSearch && document.activeElement === previousSearch) {
            shouldRestoreSearchFocus = true;
            searchSelectionStart = previousSearch.selectionStart;
            searchSelectionEnd = previousSearch.selectionEnd;
        }

        if (!cfg.defaultEventId) {
            app.root.innerHTML = '<div class="mj-attkiosk__error">' + esc(strings.eventRequired || 'Event required') + '</div>';
            return;
        }

        if (s.loading) {
            app.root.innerHTML = '<div class="mj-attkiosk__boot">' + esc(strings.loading || 'Loading...') + '</div>';
            return;
        }

        if (s.error) {
            app.root.innerHTML = '<div class="mj-attkiosk__error">' + esc(s.error) + '</div>';
            return;
        }

        var dayButtons = s.occurrences.map(function (occ) {
            var key = occ.start || occ.date || '';
            var active = key === s.selectedOccurrence;
            return '<button class="mj-attkiosk__day-btn' + (active ? ' is-active' : '') + '" data-day="' + esc(key) + '">' + esc(formatDayLabel(key)) + '</button>';
        }).join('');

        var query = (s.search || '').trim().toLowerCase();
        var filtered = s.rows.filter(function (row) {
            if (!query) {
                return true;
            }
            var m = row.member || {};
            var g = row.guardian || {};
            var txt = [m.firstName, m.lastName, m.phone, g.firstName, g.lastName, g.phone].join(' ').toLowerCase();
            return txt.indexOf(query) !== -1;
        });

        var cards = filtered.map(function (row) {
            var m = row.member || {};
            var g = row.guardian || {};
            var occ = s.selectedOccurrence || '';
            var st = resolveStatus(row.attendance, occ);
            var nextPresent = st === 'present' ? '' : 'present';
            var guardianName = (g.firstName || g.lastName) ? ((g.firstName || '') + ' ' + (g.lastName || '')).trim() : '-';
            var age = (m.age !== null && typeof m.age !== 'undefined') ? (String(m.age) + ' ' + (strings.ageSuffix || 'ans')) : '-';
            var memberName = ((m.firstName || '') + ' ' + (m.lastName || '')).trim() || 'Membre';
            var isSaving = !!s.saving[row.memberId];
            var stateClass = st === 'present' ? 'is-present' : (st === 'absent' ? 'is-absent' : 'is-undefined');
            var stateText = st === 'present'
                ? (strings.present || 'Present')
                : (st === 'absent' ? (strings.absent || 'Absent') : (strings.statusUndefined || 'Note comme present'));

            return '' +
                '<article class="mj-attkiosk__card" data-member-id="' + esc(row.memberId) + '">' +
                    '<div class="mj-attkiosk__identity">' +
                        (m.photoUrl
                            ? '<img class="mj-attkiosk__avatar" src="' + esc(m.photoUrl) + '" alt="" />'
                            : '<div class="mj-attkiosk__avatar mj-attkiosk__avatar--fallback">' + esc(initials(m)) + '</div>') +
                        '<div class="mj-attkiosk__who">' +
                            '<h2 class="mj-attkiosk__name">' + esc(memberName) + '</h2>' +
                            '<div class="mj-attkiosk__meta-grid">' +
                                '<div><span>Age</span><strong>' + esc(age) + '</strong></div>' +
                                '<div><span>' + esc(strings.phoneMember || 'Tel membre') + '</span><strong>' + esc(m.phone || '-') + '</strong></div>' +
                                '<div><span>' + esc(strings.guardianName || 'Tuteur') + '</span><strong>' + esc(guardianName) + '</strong></div>' +
                                '<div><span>' + esc(strings.phoneGuardian || 'Tel tuteur') + '</span><strong>' + esc((g.phone || m.guardianPhone || '-')) + '</strong></div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="mj-attkiosk__actions">' +
                        '<button class="mj-attkiosk__status-btn mj-attkiosk__status-btn--present ' + stateClass + '" data-status="' + esc(nextPresent) + '" ' + (isSaving ? 'disabled' : '') + '>' + esc(stateText) + '</button>' +
                    '</div>' +
                '</article>';
        }).join('');

        app.root.innerHTML = '' +
            '<section class="mj-attkiosk__shell">' +
                '<header class="mj-attkiosk__header">' +
                    '<h2 class="mj-attkiosk__title">' + esc(cfg.title || 'Feuille de presence') + '</h2>' +
                    '<div class="mj-attkiosk__search-wrap">' +
                        '<input type="search" class="mj-attkiosk__search" placeholder="' + esc(strings.searchPlaceholder || 'Rechercher...') + '" value="' + esc(s.search || '') + '" />' +
                    '</div>' +
                    '<div class="mj-attkiosk__days">' + (dayButtons || '<div class="mj-attkiosk__empty-days">' + esc(strings.noOccurrences || 'No day') + '</div>') + '</div>' +
                '</header>' +
                '<div class="mj-attkiosk__list">' + (cards || '<div class="mj-attkiosk__empty">' + esc(strings.noResults || 'No result') + '</div>') + '</div>' +
            '</section>';

        if (shouldRestoreSearchFocus) {
            var restoredSearch = app.root.querySelector('.mj-attkiosk__search');
            if (restoredSearch) {
                restoredSearch.focus();
                if (
                    typeof searchSelectionStart === 'number'
                    && typeof searchSelectionEnd === 'number'
                    && typeof restoredSearch.setSelectionRange === 'function'
                ) {
                    restoredSearch.setSelectionRange(searchSelectionStart, searchSelectionEnd);
                }
            }
        }
    }

    function load(app) {
        var cfg = app.config;
        app.state.loading = true;
        app.state.error = '';
        render(app);

        return Promise.all([
            postAction(cfg, 'mj_regmgr_get_event_details', { eventId: cfg.defaultEventId }),
            postAction(cfg, 'mj_regmgr_get_registrations', { eventId: cfg.defaultEventId }),
        ]).then(function (res) {
            var eventRes = res[0];
            var regRes = res[1];

            if (!eventRes || !eventRes.success || !regRes || !regRes.success) {
                throw new Error((cfg.strings && cfg.strings.loadError) || 'Erreur de chargement');
            }

            var event = eventRes.data && eventRes.data.event ? eventRes.data.event : {};
            var occurrences = Array.isArray(event.occurrences) ? event.occurrences : [];
            occurrences.sort(function (a, b) {
                var sa = (a && a.start) ? a.start : '';
                var sb = (b && b.start) ? b.start : '';
                return sa < sb ? -1 : sa > sb ? 1 : 0;
            });

            app.state.occurrences = occurrences;
            app.state.rows = normalizeMemberRows(
                regRes.data && regRes.data.registrations ? regRes.data.registrations : [],
                regRes.data && regRes.data.attendanceMembers ? regRes.data.attendanceMembers : []
            );

            if (!app.state.selectedOccurrence && occurrences.length > 0) {
                app.state.selectedOccurrence = occurrences[0].start || occurrences[0].date || '';
            }
            app.state.loading = false;
            render(app);
        }).catch(function (err) {
            app.state.loading = false;
            app.state.error = (err && err.message) ? err.message : ((cfg.strings && cfg.strings.loadError) || 'Erreur');
            render(app);
        });
    }

    function bind(app) {
        app.root.addEventListener('input', function (e) {
            if (!e.target.classList.contains('mj-attkiosk__search')) {
                return;
            }
            app.state.search = e.target.value || '';
            render(app);
        });

        app.root.addEventListener('click', function (e) {
            var dayBtn = e.target.closest('.mj-attkiosk__day-btn');
            if (dayBtn) {
                app.state.selectedOccurrence = dayBtn.getAttribute('data-day') || '';
                render(app);
                return;
            }

            var statusBtn = e.target.closest('.mj-attkiosk__status-btn');
            if (!statusBtn) {
                return;
            }

            var card = e.target.closest('.mj-attkiosk__card');
            if (!card) {
                return;
            }

            var memberId = parseInt(card.getAttribute('data-member-id') || '0', 10);
            var nextStatus = statusBtn.getAttribute('data-status') || '';
            var occ = app.state.selectedOccurrence || '';
            if (!memberId || !occ) {
                return;
            }

            app.state.saving[memberId] = true;
            render(app);

            postAction(app.config, 'mj_regmgr_update_attendance', {
                eventId: app.config.defaultEventId,
                memberId: memberId,
                occurrence: occ,
                status: nextStatus,
            }).then(function (res) {
                if (!res || !res.success) {
                    throw new Error('update_failed');
                }

                app.state.rows.forEach(function (row) {
                    if (row.memberId !== memberId) {
                        return;
                    }
                    row.attendance = row.attendance || {};
                    row.attendance[occ] = nextStatus;
                });
            }).catch(function () {
                // No-op: leave previous status if update failed.
            }).finally(function () {
                delete app.state.saving[memberId];
                render(app);
            });
        });
    }

    function boot(node) {
        var raw = node.getAttribute('data-config') || '{}';
        var config = {};
        try {
            config = JSON.parse(raw);
        } catch (e) {
            config = {};
        }

        var app = {
            root: node,
            config: config,
            state: {
                loading: true,
                error: '',
                search: '',
                occurrences: [],
                selectedOccurrence: '',
                rows: [],
                saving: {},
            },
        };

        bind(app);
        load(app);
    }

    function init() {
        var nodes = document.querySelectorAll('[data-mj-attkiosk]');
        nodes.forEach(boot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
