(function () {
    'use strict';

    const SELECTOR = '[data-mj-hourencore]';

    const parseConfig = (root) => {
        try {
            const raw = root.getAttribute('data-config');
            return raw ? JSON.parse(raw) : {};
        } catch (error) {
            console.error('[HourEncore] Configuration invalide', error);
            return {};
        }
    };

    const formatTotal = (minutes, i18n) => {
        const hours = Math.floor(minutes / 60);
        const remaining = minutes % 60;
        const labelHour = i18n?.weekTotal ?? 'Total semaine';
        if (remaining <= 0) {
            return `${hours}h`;
        }
        return `${hours}h${String(remaining).padStart(2, '0')}`;
    };

    const computeMinutes = (entries) => {
        return entries.reduce((total, entry) => {
            if (!entry.start || !entry.end) {
                return total;
            }
            const [startHour, startMinute] = entry.start.split(':').map((value) => parseInt(value, 10));
            const [endHour, endMinute] = entry.end.split(':').map((value) => parseInt(value, 10));
            if (Number.isNaN(startHour) || Number.isNaN(endHour)) {
                return total;
            }
            const startMinutes = startHour * 60 + (Number.isNaN(startMinute) ? 0 : startMinute);
            const endMinutes = endHour * 60 + (Number.isNaN(endMinute) ? 0 : endMinute);
            const diff = Math.max(0, endMinutes - startMinutes);
            return total + diff;
        }, 0);
    };

    const attachNavigation = (root, state) => {
        const buttons = root.querySelectorAll('.mj-hourencore__nav');
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const direction = button.getAttribute('data-direction') === 'prev' ? -1 : 1;
                state.onNavigate?.(direction, state, root);
            });
        });
    };

    const attachSlots = (root, state) => {
        root.querySelectorAll('[data-action="add-slot"]').forEach((button) => {
            button.addEventListener('click', () => {
                const dayElement = button.closest('[data-date]');
                const date = dayElement?.getAttribute('data-date');
                state.onAddSlot?.(date, state, root);
            });
        });

        root.querySelectorAll('[data-action="edit-slot"]').forEach((button) => {
            button.addEventListener('click', () => {
                const slotElement = button.closest('[data-slot-id]');
                if (!slotElement) {
                    return;
                }
                const slotId = slotElement.getAttribute('data-slot-id');
                state.onEditSlot?.(slotId, state, root);
            });
        });
    };

    const attachChips = (root, state) => {
        root.querySelectorAll('[data-task-label]').forEach((chip) => {
            chip.addEventListener('click', () => {
                const label = chip.getAttribute('data-task-label');
                state.onTaskSuggestion?.(label, state, root);
            });
        });

        root.querySelectorAll('[data-project-id]').forEach((chip) => {
            chip.addEventListener('click', () => {
                const project = {
                    id: chip.getAttribute('data-project-id'),
                    label: chip.getAttribute('data-project-label') || chip.textContent,
                };
                state.onProjectSuggestion?.(project, state, root);
            });
        });
    };

    const attachProjectForm = (root, state) => {
        const form = root.querySelector('[data-project-form]');
        if (!form) {
            return;
        }
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = form.querySelector('input');
            const value = input?.value?.trim();
            if (!value) {
                return;
            }
            input.disabled = true;
            const handler = state.onProjectCreate || (() => Promise.resolve());
            Promise.resolve(handler(value, state, root)).finally(() => {
                input.disabled = false;
                input.value = '';
                input.focus();
            });
        });
    };

    const updateTotal = (root, state) => {
        const display = root.querySelector('[data-total-display]');
        if (!display) {
            return;
        }
        const minutes = computeMinutes(state.entries);
        state.totalMinutes = minutes;
        display.textContent = formatTotal(minutes, state.i18n);
    };

    const initWidget = (root) => {
        const config = parseConfig(root);
        const state = {
            element: root,
            config,
            week: config.week || {},
            days: Array.isArray(config.days) ? config.days : [],
            entries: Array.isArray(config.entries) ? config.entries : [],
            tasks: Array.isArray(config.tasks) ? config.tasks : [],
            projects: Array.isArray(config.projects) ? config.projects : [],
            ajax: config.ajax || {},
            i18n: config.i18n || {},
            totalMinutes: 0,
            onNavigate: config.callbacks?.navigate || null,
            onAddSlot: config.callbacks?.addSlot || null,
            onEditSlot: config.callbacks?.editSlot || null,
            onTaskSuggestion: config.callbacks?.task || null,
            onProjectSuggestion: config.callbacks?.project || null,
            onProjectCreate: config.callbacks?.projectCreate || null,
        };

        root.__hourencoreState = state;

        attachNavigation(root, state);
        attachSlots(root, state);
        attachChips(root, state);
        attachProjectForm(root, state);
        updateTotal(root, state);
    };

    const initAll = () => {
        document.querySelectorAll(SELECTOR).forEach((root) => {
            if (root.__hourencoreState) {
                return;
            }
            initWidget(root);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', initAll);
    }

    window.MjMemberHourencore = {
        refresh: initAll,
        getInstance(id) {
            if (!id) {
                return null;
            }
            return document.querySelector(`[data-mj-hourencore="${id}"]`)?.__hourencoreState || null;
        },
    };
})();
