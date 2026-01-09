(function () {
    'use strict';

    const globalObject = typeof window !== 'undefined' ? window : globalThis;
    const Utils = globalObject.MjMemberUtils || {};

    const domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
            if (typeof document === 'undefined') return;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
            } else {
                callback();
            }
        };

    const escapeHtml = typeof Utils.escapeHtml === 'function'
        ? Utils.escapeHtml
        : function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };

    let emojiModuleCache = null;

    function getEmojiModule() {
        if (emojiModuleCache) {
            return emojiModuleCache;
        }
        const candidate = globalObject.MjRegMgrEmojiPicker || globalObject.MjRegMgrEmojiHelper || null;
        if (candidate && typeof candidate === 'object') {
            emojiModuleCache = candidate;
        }
        return emojiModuleCache;
    }

    function sliceEmojiGraphemes(text, max) {
        if (typeof text !== 'string' || !max || max <= 0) {
            return '';
        }
        if (typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
            try {
                const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
                const iterator = segmenter.segment(text);
                if (iterator && typeof Symbol === 'function' && typeof iterator[Symbol.iterator] === 'function') {
                    const iter = iterator[Symbol.iterator]();
                    let collected = '';
                    let count = 0;
                    let step = iter.next();
                    while (!step.done && count < max) {
                        collected += step.value.segment;
                        count += 1;
                        step = iter.next();
                    }
                    if (collected !== '') {
                        return collected;
                    }
                }
            } catch (segmenterError) {
                // Ignore segmenter errors and fall back to array slicing
            }
        }
        let units;
        try {
            units = Array.from(text);
        } catch (arrayError) {
            units = String(text).split('');
        }
        return units.slice(0, max).join('');
    }

    function sanitizeEmojiInput(value) {
        const module = getEmojiModule();
        if (module && typeof module.sanitizeValue === 'function') {
            return module.sanitizeValue(value);
        }
        if (typeof value !== 'string') {
            return '';
        }
        let normalized = value.replace(/\s+/g, ' ').trim();
        if (normalized === '') {
            return '';
        }
        let limited = sliceEmojiGraphemes(normalized, 8);
        if (limited.length > 16) {
            limited = limited.slice(0, 16);
        }
        return limited;
    }

    function resolveEmojiCandidate(input) {
        if (typeof input === 'string') {
            return input;
        }
        if (input && typeof input === 'object') {
            if (typeof input.value === 'string') {
                return input.value;
            }
            if (input.target && typeof input.target.value === 'string') {
                return input.target.value;
            }
        }
        return '';
    }

    /**
     * Controller pour les champs de planification/récurrence
     */
    class ScheduleFieldsController {
        constructor(form) {
            this.form = form;
            this.elements = {
                modeRadios: form.querySelectorAll('[data-schedule-mode]'),
                recurringSection: form.querySelector('[data-schedule-recurring-section]'),
                frequencySelect: form.querySelector('[data-recurring-frequency]'),
                weeklySection: form.querySelector('[data-schedule-weekly-section]'),
                monthlySection: form.querySelector('[data-schedule-monthly-section]'),
                weekdaySelector: form.querySelector('[data-weekday-selector]'),
                intervalLabel: form.querySelector('[data-interval-label]'),
                payloadInput: form.querySelector('[data-schedule-payload]'),
            };
            
            this.init();
        }

        init() {
            // Mode radios
            this.elements.modeRadios.forEach(radio => {
                radio.addEventListener('change', () => this.handleModeChange());
            });

            // Frequency change
            if (this.elements.frequencySelect) {
                this.elements.frequencySelect.addEventListener('change', () => this.handleFrequencyChange());
            }

            // Weekday checkboxes
            if (this.elements.weekdaySelector) {
                this.elements.weekdaySelector.querySelectorAll('[data-weekday-checkbox]').forEach(checkbox => {
                    checkbox.addEventListener('change', (e) => this.handleWeekdayToggle(e.target));
                });
            }

            // Initial state
            this.handleModeChange();
            this.handleFrequencyChange();
        }

        handleModeChange() {
            const selectedMode = this.form.querySelector('[data-schedule-mode]:checked');
            const mode = selectedMode ? selectedMode.value : 'fixed';
            
            if (this.elements.recurringSection) {
                this.elements.recurringSection.hidden = mode !== 'recurring';
            }
        }

        handleFrequencyChange() {
            const frequency = this.elements.frequencySelect ? this.elements.frequencySelect.value : 'weekly';
            
            if (this.elements.weeklySection) {
                this.elements.weeklySection.hidden = frequency !== 'weekly';
            }
            if (this.elements.monthlySection) {
                this.elements.monthlySection.hidden = frequency !== 'monthly';
            }
            if (this.elements.intervalLabel) {
                this.elements.intervalLabel.textContent = frequency === 'weekly' ? 'semaine(s)' : 'mois';
            }
        }

        handleWeekdayToggle(checkbox) {
            const item = checkbox.closest('[data-weekday]');
            if (!item) return;
            
            const timesContainer = item.querySelector('[data-weekday-times]');
            if (timesContainer) {
                timesContainer.hidden = !checkbox.checked;
            }
        }

        /**
         * Collecte les données de planification depuis le formulaire
         */
        collectScheduleData() {
            const selectedMode = this.form.querySelector('[data-schedule-mode]:checked');
            const mode = selectedMode ? selectedMode.value : 'fixed';

            if (mode !== 'recurring') {
                return { mode: mode, payload: {} };
            }

            const frequency = this.elements.frequencySelect ? this.elements.frequencySelect.value : 'weekly';
            const intervalInput = this.form.querySelector('[name="recurring_interval"]');
            const interval = intervalInput ? parseInt(intervalInput.value, 10) || 1 : 1;

            const payload = {
                frequency: frequency,
                interval: interval,
            };

            if (frequency === 'weekly') {
                // Collecter les jours cochés et leurs horaires
                const weekdays = [];
                const weekday_times = {};

                this.form.querySelectorAll('[name="recurring_weekdays[]"]:checked').forEach(checkbox => {
                    const day = checkbox.value;
                    weekdays.push(day);

                    const item = checkbox.closest('[data-weekday]');
                    if (item) {
                        const startInput = item.querySelector('[name^="weekday_times"][name$="[start]"]');
                        const endInput = item.querySelector('[name^="weekday_times"][name$="[end]"]');
                        if (startInput || endInput) {
                            weekday_times[day] = {
                                start: startInput ? startInput.value : '',
                                end: endInput ? endInput.value : '',
                            };
                        }
                    }
                });

                payload.weekdays = weekdays;
                payload.weekday_times = weekday_times;
            } else {
                // Mensuel
                const ordinalSelect = this.form.querySelector('[name="recurring_ordinal"]');
                const weekdaySelect = this.form.querySelector('[name="recurring_monthly_weekday"]');
                const startTimeInput = this.form.querySelector('[name="recurring_monthly_start_time"]');
                const endTimeInput = this.form.querySelector('[name="recurring_monthly_end_time"]');

                payload.ordinal = ordinalSelect ? ordinalSelect.value : 'first';
                payload.weekday = weekdaySelect ? weekdaySelect.value : 'saturday';
                payload.start_time = startTimeInput ? startTimeInput.value : '';
                payload.end_time = endTimeInput ? endTimeInput.value : '';
            }

            // Until date
            const untilInput = this.form.querySelector('[name="recurring_until"]');
            if (untilInput && untilInput.value) {
                payload.until = untilInput.value;
            }

            return { mode: mode, payload: payload };
        }

        /**
         * Hydrate le formulaire avec des données existantes
         */
        hydrateFromEvent(event) {
            const scheduleMode = event.schedule_mode || 'fixed';
            
            // Set mode radio
            const modeRadio = this.form.querySelector(`[data-schedule-mode][value="${scheduleMode}"]`);
            if (modeRadio) {
                modeRadio.checked = true;
            }

            // Parse payload
            let payload = {};
            if (event.schedule_payload) {
                if (typeof event.schedule_payload === 'string') {
                    try {
                        payload = JSON.parse(event.schedule_payload);
                    } catch (e) {
                        console.warn('Invalid schedule_payload JSON', e);
                    }
                } else if (typeof event.schedule_payload === 'object') {
                    payload = event.schedule_payload;
                }
            }

            // Frequency
            if (this.elements.frequencySelect && payload.frequency) {
                this.elements.frequencySelect.value = payload.frequency;
            }

            // Interval
            const intervalInput = this.form.querySelector('[name="recurring_interval"]');
            if (intervalInput && payload.interval) {
                intervalInput.value = payload.interval;
            }

            // Weekdays and times
            if (payload.weekdays && Array.isArray(payload.weekdays)) {
                // Reset all checkboxes first
                this.form.querySelectorAll('[name="recurring_weekdays[]"]').forEach(cb => {
                    cb.checked = false;
                    const item = cb.closest('[data-weekday]');
                    if (item) {
                        const timesContainer = item.querySelector('[data-weekday-times]');
                        if (timesContainer) timesContainer.hidden = true;
                    }
                });

                // Check selected weekdays
                payload.weekdays.forEach(day => {
                    const checkbox = this.form.querySelector(`[name="recurring_weekdays[]"][value="${day}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        const item = checkbox.closest('[data-weekday]');
                        if (item) {
                            const timesContainer = item.querySelector('[data-weekday-times]');
                            if (timesContainer) timesContainer.hidden = false;

                            // Set times if available
                            const dayTimes = payload.weekday_times && payload.weekday_times[day];
                            if (dayTimes) {
                                const startInput = item.querySelector('[name^="weekday_times"][name$="[start]"]');
                                const endInput = item.querySelector('[name^="weekday_times"][name$="[end]"]');
                                if (startInput && dayTimes.start) startInput.value = dayTimes.start;
                                if (endInput && dayTimes.end) endInput.value = dayTimes.end;
                            }
                        }
                    }
                });
            }

            // Monthly fields
            const ordinalSelect = this.form.querySelector('[name="recurring_ordinal"]');
            if (ordinalSelect && payload.ordinal) {
                ordinalSelect.value = payload.ordinal;
            }

            const monthlyWeekdaySelect = this.form.querySelector('[name="recurring_monthly_weekday"]');
            if (monthlyWeekdaySelect && payload.weekday) {
                monthlyWeekdaySelect.value = payload.weekday;
            }

            const monthlyStartTime = this.form.querySelector('[name="recurring_monthly_start_time"]');
            if (monthlyStartTime && payload.start_time) {
                monthlyStartTime.value = payload.start_time;
            }

            const monthlyEndTime = this.form.querySelector('[name="recurring_monthly_end_time"]');
            if (monthlyEndTime && payload.end_time) {
                monthlyEndTime.value = payload.end_time;
            }

            // Until date
            const untilInput = this.form.querySelector('[name="recurring_until"]');
            if (untilInput && payload.until) {
                untilInput.value = payload.until;
            }

            // Trigger UI updates
            this.handleModeChange();
            this.handleFrequencyChange();
        }

        /**
         * Reset les champs de récurrence
         */
        reset() {
            // Reset mode to fixed
            const fixedRadio = this.form.querySelector('[data-schedule-mode][value="fixed"]');
            if (fixedRadio) {
                fixedRadio.checked = true;
            }

            // Reset frequency
            if (this.elements.frequencySelect) {
                this.elements.frequencySelect.value = 'weekly';
            }

            // Reset interval
            const intervalInput = this.form.querySelector('[name="recurring_interval"]');
            if (intervalInput) {
                intervalInput.value = '1';
            }

            // Uncheck all weekdays and reset times
            this.form.querySelectorAll('[name="recurring_weekdays[]"]').forEach(cb => {
                cb.checked = false;
                const item = cb.closest('[data-weekday]');
                if (item) {
                    const timesContainer = item.querySelector('[data-weekday-times]');
                    if (timesContainer) {
                        timesContainer.hidden = true;
                        timesContainer.querySelectorAll('input[type="time"]').forEach(input => {
                            input.value = '';
                        });
                    }
                }
            });

            // Reset monthly fields
            const ordinalSelect = this.form.querySelector('[name="recurring_ordinal"]');
            if (ordinalSelect) ordinalSelect.value = 'first';

            const monthlyWeekdaySelect = this.form.querySelector('[name="recurring_monthly_weekday"]');
            if (monthlyWeekdaySelect) monthlyWeekdaySelect.value = 'saturday';

            const monthlyStartTime = this.form.querySelector('[name="recurring_monthly_start_time"]');
            if (monthlyStartTime) monthlyStartTime.value = '';

            const monthlyEndTime = this.form.querySelector('[name="recurring_monthly_end_time"]');
            if (monthlyEndTime) monthlyEndTime.value = '';

            // Reset until
            const untilInput = this.form.querySelector('[name="recurring_until"]');
            if (untilInput) untilInput.value = '';

            // Trigger UI updates
            this.handleModeChange();
            this.handleFrequencyChange();
        }
    }

    class EventsManagerController {
        constructor(container) {
            this.container = container;
            this.config = this.parseConfig();
            this.events = [];
            this.filteredEvents = [];
            this.currentPage = 1;
            this.totalPages = 1;
            this.isLoading = false;

            this.locale = (typeof document !== 'undefined' && document.documentElement && document.documentElement.lang)
                ? document.documentElement.lang
                : 'fr-FR';
            this.weekdayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            this.weekdayLabels = Object.assign({
                monday: 'Lundi',
                tuesday: 'Mardi',
                wednesday: 'Mercredi',
                thursday: 'Jeudi',
                friday: 'Vendredi',
                saturday: 'Samedi',
                sunday: 'Dimanche',
            }, this.config.weekdayLabels || {});
            this.ordinalLabels = Object.assign({
                first: '1er',
                second: '2e',
                third: '3e',
                fourth: '4e',
                last: 'Dernier',
            }, this.config.ordinals || {});

            this.initFormatters();
            
            this.elements = {
                list: container.querySelector('[data-events-list]'),
                loading: container.querySelector('[data-loading]'),
                emptyState: container.querySelector('[data-empty-state]'),
                pagination: container.querySelector('[data-pagination]'),
                feedback: container.querySelector('[data-feedback]'),
                searchInput: container.querySelector('[data-search-input]'),
                filterStatus: container.querySelector('[data-filter-status]'),
                filterType: container.querySelector('[data-filter-type]'),
                modal: document.querySelector('[data-modal]'),
                modalTitle: document.querySelector('[data-modal-title]'),
                modalBody: document.querySelector('[data-modal-body]'),
                modalClose: document.querySelectorAll('[data-modal-close]'),
                modalOverlay: document.querySelector('[data-modal-overlay]'),
                form: document.querySelector('[data-event-form]'),
                formFeedback: document.querySelector('[data-form-feedback]'),
            };

            this.currentEventId = null;
            this.searchTerm = '';
            this.filterStatus = '';
            this.filterType = '';

            // Schedule fields controller
            this.scheduleController = this.elements.form ? new ScheduleFieldsController(this.elements.form) : null;

            this.emojiController = null;
            this.setupEmojiField();

            this.init();
        }

        initFormatters() {
            const locale = this.locale || 'fr-FR';
            try {
                this.dateFormatter = new Intl.DateTimeFormat(locale, {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                });
            } catch (error) {
                this.dateFormatter = null;
            }

            try {
                this.timeFormatter = new Intl.DateTimeFormat(locale, {
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch (error) {
                this.timeFormatter = null;
            }
        }

        parseRegistrationPayload(rawPayload) {
            if (!rawPayload) {
                return {};
            }

            if (typeof rawPayload === 'object') {
                return rawPayload;
            }

            if (typeof rawPayload === 'string') {
                try {
                    const parsed = JSON.parse(rawPayload);
                    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                        return parsed;
                    }
                } catch (error) {
                    // Silent parse failure; return empty object for resilience.
                }
            }

            return {};
        }

        normalizeEvent(rawEvent) {
            if (!rawEvent || typeof rawEvent !== 'object') {
                return {
                    attendance_show_all_members: false,
                };
            }

            const normalized = Object.assign({}, rawEvent);
            const payload = this.parseRegistrationPayload(rawEvent.registration_payload);
            normalized.registration_payload_data = payload;
            const attendanceRaw = payload ? payload.attendance_show_all_members : false;
            normalized.attendance_show_all_members = attendanceRaw === true
                || attendanceRaw === 1
                || attendanceRaw === '1'
                || attendanceRaw === 'true';
            normalized.emoji = sanitizeEmojiInput(rawEvent.emoji);

            return normalized;
        }

        buildEmojiStrings() {
            const strings = (this.config && this.config.strings) || {};
            return {
                eventEmoji: strings.emojiLabel || 'Emoji',
                eventEmojiHint: strings.emojiHint || '',
                eventEmojiPlaceholder: strings.emojiPlaceholder || 'Ex : 🎉',
                eventEmojiPicker: strings.emojiPicker || 'Choisir',
                eventEmojiPickerClose: strings.emojiPickerClose || 'Fermer',
                eventEmojiClear: strings.emojiClear || 'Effacer',
                eventEmojiSuggestions: strings.emojiSuggestions || 'Suggestions',
                eventEmojiSearchPlaceholder: strings.emojiSearchPlaceholder || 'Rechercher un emoji',
                eventEmojiSearchNoResult: strings.emojiSearchNoResult || 'Aucun emoji ne correspond à votre recherche.',
                eventEmojiAllCategory: strings.emojiAllCategory || 'Tout',
            };
        }

        setupEmojiField() {
            if (!this.elements.form) {
                return;
            }

            const field = this.elements.form.querySelector('[data-emoji-field]');
            if (!field) {
                return;
            }

            const fallbackInput = field.querySelector('[data-emoji-input]');
            if (!fallbackInput) {
                return;
            }

            const container = field.querySelector('[data-emoji-container]') || field;
            let mount = field.querySelector('[data-emoji-picker-root]');
            if (!mount) {
                mount = document.createElement('div');
                mount.setAttribute('data-emoji-picker-root', '');
                if (container && container.contains(fallbackInput)) {
                    container.insertBefore(mount, fallbackInput);
                } else {
                    field.insertBefore(mount, fallbackInput);
                }
            }

            const module = getEmojiModule();
            const preactLib = globalObject.preact || null;
            const EmojiPickerField = module && module.EmojiPickerField ? module.EmojiPickerField : null;
            const emojiStrings = this.buildEmojiStrings();

            const controller = {
                field,
                input: fallbackInput,
                mount,
                strings: emojiStrings,
                currentValue: sanitizeEmojiInput(fallbackInput.value || ''),
                enabled: !!(preactLib && EmojiPickerField),
                render: null,
                applyValue: null,
            };

            controller.applyValue = () => {
                controller.currentValue = sanitizeEmojiInput(controller.currentValue);
                controller.input.value = controller.currentValue;
            };

            if (controller.enabled) {
                const { h, render } = preactLib;

                const handleChange = (nextValue) => {
                    controller.currentValue = sanitizeEmojiInput(resolveEmojiCandidate(nextValue));
                    controller.applyValue();
                    if (controller.render) {
                        controller.render();
                    }
                };

                controller.render = () => {
                    controller.applyValue();
                    render(h(EmojiPickerField, {
                        value: controller.currentValue,
                        onChange: handleChange,
                        strings: controller.strings,
                        labels: controller.strings,
                        disabled: false,
                        fallbackPlaceholder: controller.strings.eventEmojiPlaceholder || 'Ex : 🎉',
                        'aria-describedby': controller.input.getAttribute('aria-describedby') || undefined,
                    }), controller.mount);
                };

                field.classList.add('mj-form-field--emoji-enhanced');
                controller.render();
            } else {
                controller.applyValue();
            }

            this.emojiController = controller;
        }

        setEmojiValue(value) {
            if (!this.emojiController) {
                if (this.elements && this.elements.form) {
                    const fallback = this.elements.form.querySelector('[data-emoji-input]');
                    if (fallback) {
                        fallback.value = sanitizeEmojiInput(value);
                    }
                }
                return;
            }

            this.emojiController.currentValue = sanitizeEmojiInput(value);
            this.emojiController.input.value = this.emojiController.currentValue;
            if (this.emojiController.enabled && typeof this.emojiController.render === 'function') {
                this.emojiController.render();
            } else if (typeof this.emojiController.applyValue === 'function') {
                this.emojiController.applyValue();
            }
        }

        parseConfig() {
            const configAttr = this.container.getAttribute('data-config');
            if (!configAttr) return {};
            try {
                return JSON.parse(configAttr);
            } catch (error) {
                console.error('EventsManager: Invalid config JSON', error);
                return {};
            }
        }

        getString(key, fallback = '') {
            return (this.config.strings && this.config.strings[key]) || fallback;
        }

        init() {
            this.attachEventListeners();
            this.loadEvents();
        }

        attachEventListeners() {
            // Add event button
            this.container.querySelectorAll('[data-action="add-event"]').forEach(btn => {
                btn.addEventListener('click', () => this.openModal('add'));
            });

            // Search
            if (this.elements.searchInput) {
                this.elements.searchInput.addEventListener('input', (e) => {
                    this.searchTerm = e.target.value.toLowerCase();
                    this.applyFilters();
                });
            }

            // Filters
            if (this.elements.filterStatus) {
                this.elements.filterStatus.addEventListener('change', (e) => {
                    this.filterStatus = e.target.value;
                    this.applyFilters();
                });
            }

            if (this.elements.filterType) {
                this.elements.filterType.addEventListener('change', (e) => {
                    this.filterType = e.target.value;
                    this.applyFilters();
                });
            }

            // Modal close
            this.elements.modalClose.forEach(btn => {
                btn.addEventListener('click', () => this.closeModal());
            });

            if (this.elements.modalOverlay) {
                this.elements.modalOverlay.addEventListener('click', () => this.closeModal());
            }

            // Form submit
            if (this.elements.form) {
                this.elements.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleFormSubmit();
                });
            }

            // Event delegation for list actions
            if (this.elements.list) {
                this.elements.list.addEventListener('click', (e) => {
                    const editBtn = e.target.closest('[data-action="edit"]');
                    const deleteBtn = e.target.closest('[data-action="delete"]');

                    if (editBtn) {
                        const eventId = parseInt(editBtn.dataset.eventId, 10);
                        this.openModal('edit', eventId);
                    } else if (deleteBtn) {
                        const eventId = parseInt(deleteBtn.dataset.eventId, 10);
                        this.handleDelete(eventId);
                    }
                });
            }
        }

        async loadEvents() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showLoading(true);
            this.clearFeedback();

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mj_events_manager_list',
                        nonce: this.config.nonce,
                        show_past: this.config.showPast ? '1' : '0',
                    }),
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data?.message || this.getString('error'));
                }

                const incomingEvents = Array.isArray(data.data?.events) ? data.data.events : [];
                this.events = incomingEvents.map(event => this.normalizeEvent(event));
                this.applyFilters();
            } catch (error) {
                console.error('EventsManager: Load error', error);
                this.showFeedback(error.message, 'error');
                this.events = [];
                this.filteredEvents = [];
                this.renderList();
            } finally {
                this.isLoading = false;
                this.showLoading(false);
            }
        }

        applyFilters() {
            this.filteredEvents = this.events.filter(event => {
                // Search filter
                if (this.searchTerm) {
                    const title = (event.title || '').toLowerCase();
                    const description = (event.description || '').toLowerCase();
                    const emoji = (event.emoji || '').toLowerCase();
                    if (!title.includes(this.searchTerm) && !description.includes(this.searchTerm) && !emoji.includes(this.searchTerm)) {
                        return false;
                    }
                }

                // Status filter
                if (this.filterStatus && event.status !== this.filterStatus) {
                    return false;
                }

                // Type filter
                if (this.filterType && event.type !== this.filterType) {
                    return false;
                }

                return true;
            });

            this.currentPage = 1;
            this.calculatePagination();
            this.renderList();
        }

        calculatePagination() {
            const perPage = this.config.perPage || 20;
            this.totalPages = Math.ceil(this.filteredEvents.length / perPage);
        }

        renderList() {
            if (!this.elements.list) return;

            if (this.filteredEvents.length === 0) {
                this.elements.list.innerHTML = '';
                this.elements.emptyState.hidden = false;
                this.elements.pagination.hidden = true;
                return;
            }

            this.elements.emptyState.hidden = true;

            const perPage = this.config.perPage || 20;
            const start = (this.currentPage - 1) * perPage;
            const end = start + perPage;
            const pageEvents = this.filteredEvents.slice(start, end);

            const html = pageEvents.map(event => this.renderEventCard(event)).join('');
            this.elements.list.innerHTML = html;

            this.renderPagination();
        }

        renderEventCard(event) {
            const statusLabel = this.config.eventStatuses[event.status] || event.status;
            const typeLabel = this.config.eventTypes[event.type] || event.type;
            const startDate = event.start_date ? this.formatDate(event.start_date) : '—';
            const price = event.price > 0 ? `${parseFloat(event.price).toFixed(2)} €` : this.getString('free', 'Gratuit');
            const scheduleMarkup = this.renderSchedule(event);
            const attendanceLabelKey = event.attendance_show_all_members ? 'attendanceAllMembers' : 'attendanceRegisteredOnly';
            const attendanceLabelFallback = event.attendance_show_all_members
                ? 'Liste de présence : tous les membres'
                : 'Liste de présence : inscrits uniquement';
            const attendanceLabel = this.getString(attendanceLabelKey, attendanceLabelFallback);
            const emojiValue = sanitizeEmojiInput(event.emoji || '');
            const emojiLabel = this.getString('emojiLabel', 'Emoji');
            const emojiAccessible = `${emojiLabel}: ${emojiValue}`;
            const titleContent = [
                emojiValue ? `<span class="mj-events-manager-card__emoji" aria-hidden="true">${escapeHtml(emojiValue)}</span>` : '',
                `<span class="mj-events-manager-card__title-text">${escapeHtml(event.title)}</span>`,
                emojiValue ? `<span class="screen-reader-text">${escapeHtml(emojiAccessible)}</span>` : '',
            ].filter(Boolean).join('');

            return `
                <div class="mj-events-manager-card" data-event-id="${escapeHtml(event.id)}">
                    <div class="mj-events-manager-card__header">
                        <h3 class="mj-events-manager-card__title">${titleContent}</h3>
                        <div class="mj-events-manager-card__badges">
                            <span class="mj-events-manager-card__badge mj-events-manager-card__badge--status">${escapeHtml(statusLabel)}</span>
                            <span class="mj-events-manager-card__badge mj-events-manager-card__badge--type">${escapeHtml(typeLabel)}</span>
                        </div>
                    </div>
                    <div class="mj-events-manager-card__body">
                        <div class="mj-events-manager-card__meta">
                            <div class="mj-events-manager-card__meta-item">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <span>${escapeHtml(startDate)}</span>
                            </div>
                            <div class="mj-events-manager-card__meta-item">
                                <span class="dashicons dashicons-tickets-alt"></span>
                                <span>${escapeHtml(price)}</span>
                            </div>
                            ${event.capacity_total > 0 ? `
                                <div class="mj-events-manager-card__meta-item">
                                    <span class="dashicons dashicons-groups"></span>
                                    <span>${escapeHtml(event.capacity_total)} places</span>
                                </div>
                            ` : ''}
                            <div class="mj-events-manager-card__meta-item">
                                <span class="dashicons dashicons-admin-users"></span>
                                <span>${escapeHtml(attendanceLabel)}</span>
                            </div>
                        </div>
                            ${scheduleMarkup}
                        ${event.description ? `
                            <p class="mj-events-manager-card__description">${escapeHtml(event.description.substring(0, 150))}${event.description.length > 150 ? '...' : ''}</p>
                        ` : ''}
                    </div>
                    <div class="mj-events-manager-card__actions">
                        <button type="button" class="mj-events-manager-card__action" data-action="edit" data-event-id="${escapeHtml(event.id)}">
                            <span class="dashicons dashicons-edit"></span>
                            <span>${this.getString('edit')}</span>
                        </button>
                        <button type="button" class="mj-events-manager-card__action mj-events-manager-card__action--danger" data-action="delete" data-event-id="${escapeHtml(event.id)}">
                            <span class="dashicons dashicons-trash"></span>
                            <span>${this.getString('delete')}</span>
                        </button>
                    </div>
                </div>
            `;
        }

        renderPagination() {
            if (!this.elements.pagination || this.totalPages <= 1) {
                this.elements.pagination.hidden = true;
                return;
            }

            this.elements.pagination.hidden = false;

            const buttons = [];
            for (let i = 1; i <= this.totalPages; i++) {
                const active = i === this.currentPage ? ' mj-events-manager-pagination__btn--active' : '';
                buttons.push(`
                    <button type="button" class="mj-events-manager-pagination__btn${active}" data-page="${i}">
                        ${i}
                    </button>
                `);
            }

            this.elements.pagination.innerHTML = `
                <div class="mj-events-manager-pagination">
                    ${buttons.join('')}
                </div>
            `;

            this.elements.pagination.querySelectorAll('[data-page]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.currentPage = parseInt(e.target.dataset.page, 10);
                    this.renderList();
                });
            });
        }

        renderSchedule(event) {
            if (!event) {
                return '';
            }

            const mode = (event.schedule_mode || '').toLowerCase();
            if (mode === 'recurring') {
                const schedule = this.buildRecurringSchedule(event);
                if (!schedule || (!schedule.items || schedule.items.length === 0) && !schedule.until) {
                    return '';
                }

                if (!schedule.items || schedule.items.length === 0) {
                    const fallback = this.getString('scheduleFallback', 'Planification non renseignée.');
                    return `
                        <div class="mj-events-manager-card__schedule">
                            <div class="mj-events-manager-card__schedule-title">${escapeHtml(this.getString('scheduleTitle', 'Planification'))}</div>
                            <p class="mj-events-manager-card__schedule-empty">${escapeHtml(fallback)}</p>
                        </div>
                    `;
                }

                const itemsHtml = schedule.items.map(item => `
                    <li class="mj-events-manager-card__schedule-item">
                        ${item.day ? `<span class="mj-events-manager-card__schedule-day">${escapeHtml(item.day)}</span>` : ''}
                        ${item.time ? `<span class="mj-events-manager-card__schedule-time">${escapeHtml(item.time)}</span>` : ''}
                    </li>
                `).join('');

                const footer = schedule.until
                    ? `<div class="mj-events-manager-card__schedule-footer">${escapeHtml(schedule.until)}</div>`
                    : '';

                return `
                    <div class="mj-events-manager-card__schedule">
                        <div class="mj-events-manager-card__schedule-title">${escapeHtml(this.getString('scheduleTitle', 'Planification'))}</div>
                        <ul class="mj-events-manager-card__schedule-list">
                            ${itemsHtml}
                        </ul>
                        ${footer}
                    </div>
                `;
            }

            const rangeLabel = this.formatScheduleRange(event.start_date, event.end_date);
            if (rangeLabel) {
                return `
                    <div class="mj-events-manager-card__schedule">
                        <div class="mj-events-manager-card__schedule-title">${escapeHtml(this.getString('scheduleTitle', 'Planification'))}</div>
                        <p class="mj-events-manager-card__schedule-range">${escapeHtml(rangeLabel)}</p>
                    </div>
                `;
            }

            return '';
        }

        formatScheduleRange(startValue, endValue) {
            const startDate = this.parseDate(startValue);
            if (!startDate) {
                return '';
            }

            const startDateLabel = this.formatDateForDisplay(startDate);
            const startTimeLabel = this.formatTimeForDisplay(startDate);

            const endDate = this.parseDate(endValue);
            if (!endDate) {
                return startTimeLabel
                    ? `${startDateLabel} • ${startTimeLabel}`
                    : startDateLabel;
            }

            const endDateLabel = this.formatDateForDisplay(endDate);
            const endTimeLabel = this.formatTimeForDisplay(endDate);
            const sameDay = startDate.toDateString() === endDate.toDateString();

            if (sameDay) {
                if (startTimeLabel && endTimeLabel) {
                    return `${startDateLabel} • ${startTimeLabel} - ${endTimeLabel}`;
                }
                if (startTimeLabel) {
                    return `${startDateLabel} • ${startTimeLabel}`;
                }
                return `${startDateLabel}`;
            }

            const firstPart = startTimeLabel ? `${startDateLabel} • ${startTimeLabel}` : startDateLabel;
            const secondPart = endTimeLabel ? `${endDateLabel} • ${endTimeLabel}` : endDateLabel;
            return `${firstPart} → ${secondPart}`;
        }

        buildRecurringSchedule(event) {
            const payload = this.parseSchedulePayload(event && event.schedule_payload);
            if (!payload || typeof payload !== 'object') {
                return { items: [], until: '' };
            }

            const frequency = (payload.frequency || 'weekly').toLowerCase();
            const items = [];

            if (frequency === 'monthly') {
                const ordinalKey = (payload.ordinal || 'first').toLowerCase();
                const weekdayKey = (payload.weekday || 'monday').toLowerCase();
                const ordinalLabel = this.ordinalLabels[ordinalKey] || payload.ordinal || ordinalKey;
                const weekdayLabel = this.weekdayLabels[weekdayKey] || payload.weekday || weekdayKey;
                const pattern = this.getString('scheduleMonthlyPattern', 'Chaque %1$s %2$s')
                    .replace('%1$s', ordinalLabel)
                    .replace('%2$s', weekdayLabel);

                const startLabel = this.formatPlainTime(payload.start_time);
                const endLabel = this.formatPlainTime(payload.end_time);
                let timeLabel = '';
                if (startLabel && endLabel) {
                    timeLabel = `${startLabel} - ${endLabel}`;
                } else if (startLabel || endLabel) {
                    timeLabel = startLabel || endLabel;
                } else {
                    timeLabel = this.getString('scheduleAllDay', 'Toute la journée');
                }

                items.push({
                    day: pattern,
                    time: timeLabel,
                });
            } else {
                const weekdays = Array.isArray(payload.weekdays)
                    ? payload.weekdays.map(value => String(value).toLowerCase())
                    : [];
                const rawWeekdayTimes = (payload.weekday_times && typeof payload.weekday_times === 'object') ? payload.weekday_times : {};
                const weekdayTimes = {};
                Object.keys(rawWeekdayTimes).forEach(key => {
                    const normalizedKey = String(key).toLowerCase();
                    weekdayTimes[normalizedKey] = rawWeekdayTimes[key];
                });

                this.weekdayOrder.forEach(weekdayKey => {
                    const isSelected = weekdays.includes(weekdayKey) || Object.prototype.hasOwnProperty.call(weekdayTimes, weekdayKey);
                    if (!isSelected) {
                        return;
                    }

                    const times = weekdayTimes[weekdayKey] || {};
                    const startLabel = this.formatPlainTime(times.start || payload.start_time || '');
                    const endLabel = this.formatPlainTime(times.end || payload.end_time || '');
                    let timeLabel = '';

                    if (startLabel && endLabel) {
                        timeLabel = `${startLabel} - ${endLabel}`;
                    } else if (startLabel || endLabel) {
                        timeLabel = startLabel || endLabel;
                    } else {
                        timeLabel = this.getString('scheduleAllDay', 'Toute la journée');
                    }

                    items.push({
                        day: this.weekdayLabels[weekdayKey] || weekdayKey,
                        time: timeLabel,
                    });
                });

                if (items.length === 0) {
                    const startLabel = this.formatPlainTime(payload.start_time);
                    const endLabel = this.formatPlainTime(payload.end_time);
                    if (startLabel || endLabel) {
                        items.push({
                            day: '',
                            time: startLabel && endLabel ? `${startLabel} - ${endLabel}` : (startLabel || endLabel),
                        });
                    }
                }
            }

            return {
                items,
                until: typeof payload.until === 'string' ? this.buildUntilLabel(payload.until) : '',
            };
        }

        parseSchedulePayload(payload) {
            if (!payload) {
                return {};
            }

            if (typeof payload === 'string') {
                const trimmed = payload.trim();
                if (trimmed === '') {
                    return {};
                }
                try {
                    const parsed = JSON.parse(trimmed);
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (error) {
                    return {};
                }
            }

            if (typeof payload === 'object') {
                return payload;
            }

            return {};
        }

        parseDate(value) {
            if (!value || typeof value !== 'string') {
                return null;
            }

            let normalized = value.trim();
            if (normalized === '' || normalized === '0000-00-00 00:00:00' || normalized === '0000-00-00') {
                return null;
            }

            if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
                normalized = `${normalized}T00:00:00`;
            } else if (normalized.indexOf(' ') !== -1 && normalized.indexOf('T') === -1) {
                normalized = normalized.replace(' ', 'T');
            }

            const date = new Date(normalized);
            if (Number.isNaN(date.getTime())) {
                return null;
            }

            return date;
        }

        formatDateForDisplay(date) {
            if (!(date instanceof Date)) {
                return '';
            }

            if (this.dateFormatter) {
                return this.dateFormatter.format(date);
            }

            return date.toLocaleDateString(this.locale || undefined, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        }

        formatTimeForDisplay(date) {
            if (!(date instanceof Date)) {
                return '';
            }

            if (this.timeFormatter) {
                return this.timeFormatter.format(date);
            }

            return date.toLocaleTimeString(this.locale || undefined, {
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        formatPlainTime(value) {
            if (!value || typeof value !== 'string') {
                return '';
            }

            const trimmed = value.trim();
            if (trimmed === '') {
                return '';
            }

            const segments = trimmed.split(':');
            const hours = parseInt(segments[0], 10);
            if (Number.isNaN(hours)) {
                return trimmed;
            }
            const minutes = segments.length > 1 ? parseInt(segments[1], 10) : 0;
            const seconds = segments.length > 2 ? parseInt(segments[2], 10) : 0;

            const baseDate = new Date();
            baseDate.setHours(hours, minutes, seconds, 0);
            return this.formatTimeForDisplay(baseDate);
        }

        buildUntilLabel(value) {
            const date = this.parseDate(value);
            if (!date) {
                return '';
            }

            const prefix = this.getString('scheduleUntilPrefix', "Jusqu'au");
            return `${prefix} ${this.formatDateForDisplay(date)}`;
        }

        formatDate(dateString) {
            try {
                const date = new Date(dateString.replace(' ', 'T'));
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch (error) {
                return dateString;
            }
        }

        openModal(mode, eventId = null) {
            this.currentEventId = eventId;
            this.clearFormFeedback();

            if (mode === 'add') {
                this.elements.modalTitle.textContent = this.getString('addNew');
                this.resetForm();
            } else if (mode === 'edit' && eventId) {
                this.elements.modalTitle.textContent = this.getString('edit');
                this.loadEventToForm(eventId);
            }

            this.elements.modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        closeModal() {
            this.elements.modal.hidden = true;
            document.body.style.overflow = '';
            this.resetForm();
            this.currentEventId = null;
        }

        resetForm() {
            if (this.elements.form) {
                this.elements.form.reset();
            }
            // Reset schedule fields
            if (this.scheduleController) {
                this.scheduleController.reset();
            }

            const attendanceCheckbox = this.elements.form ? this.elements.form.querySelector('[name="attendance_show_all_members"]') : null;
            if (attendanceCheckbox) {
                attendanceCheckbox.checked = false;
            }

            this.setEmojiValue('');
        }

        loadEventToForm(eventId) {
            const event = this.events.find(e => e.id === eventId);
            if (!event || !this.elements.form) return;

            const form = this.elements.form;
            form.querySelector('[name="title"]').value = event.title || '';
            form.querySelector('[name="type"]').value = event.type || '';
            form.querySelector('[name="status"]').value = event.status || '';
            form.querySelector('[name="description"]').value = event.description || '';
            form.querySelector('[name="price"]').value = event.price || '';
            form.querySelector('[name="capacity_total"]').value = event.capacity_total || '';

            const attendanceCheckbox = form.querySelector('[name="attendance_show_all_members"]');
            if (attendanceCheckbox) {
                attendanceCheckbox.checked = !!event.attendance_show_all_members;
            }

            if (event.start_date) {
                const startFormatted = this.formatDateForInput(event.start_date);
                form.querySelector('[name="start_date"]').value = startFormatted;
            }

            if (event.end_date) {
                const endFormatted = this.formatDateForInput(event.end_date);
                form.querySelector('[name="end_date"]').value = endFormatted;
            }

            this.setEmojiValue(event.emoji || '');

            // Hydrate schedule fields
            if (this.scheduleController) {
                this.scheduleController.hydrateFromEvent(event);
            }
        }

        formatDateForInput(dateString) {
            try {
                const date = new Date(dateString.replace(' ', 'T'));
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            } catch (error) {
                return '';
            }
        }

        async handleFormSubmit() {
            if (!this.elements.form) return;

            if (this.emojiController && this.emojiController.input) {
                this.emojiController.input.value = sanitizeEmojiInput(this.emojiController.input.value || '');
            } else {
                const emojiInput = this.elements.form.querySelector('[name="emoji"]');
                if (emojiInput) {
                    emojiInput.value = sanitizeEmojiInput(emojiInput.value || '');
                }
            }

            const formData = new FormData(this.elements.form);
            const action = this.currentEventId ? 'mj_events_manager_update' : 'mj_events_manager_create';

            formData.append('action', action);
            formData.append('nonce', this.config.nonce);

            if (this.currentEventId) {
                formData.append('event_id', this.currentEventId);
            }

            // Add schedule data
            if (this.scheduleController) {
                const scheduleData = this.scheduleController.collectScheduleData();
                formData.set('schedule_mode', scheduleData.mode);
                formData.set('schedule_payload', JSON.stringify(scheduleData.payload));
            }

            const attendanceCheckbox = this.elements.form.querySelector('[name="attendance_show_all_members"]');
            formData.set('attendance_show_all_members', attendanceCheckbox && attendanceCheckbox.checked ? '1' : '0');

            this.showFormFeedback(this.getString('loading'), 'loading');

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data?.message || this.getString('error'));
                }

                this.showFeedback(this.getString('success'), 'success');
                this.closeModal();
                await this.loadEvents();
            } catch (error) {
                console.error('EventsManager: Save error', error);
                this.showFormFeedback(error.message, 'error');
            }
        }

        async handleDelete(eventId) {
            if (!confirm(this.getString('confirmDelete'))) {
                return;
            }

            this.clearFeedback();

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mj_events_manager_delete',
                        nonce: this.config.nonce,
                        event_id: eventId,
                    }),
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data?.message || this.getString('error'));
                }

                this.showFeedback(this.getString('success'), 'success');
                await this.loadEvents();
            } catch (error) {
                console.error('EventsManager: Delete error', error);
                this.showFeedback(error.message, 'error');
            }
        }

        showLoading(show) {
            if (this.elements.loading) {
                this.elements.loading.hidden = !show;
            }
        }

        showFeedback(message, type = 'info') {
            if (!this.elements.feedback) return;

            this.elements.feedback.textContent = message;
            this.elements.feedback.className = `mj-events-manager__feedback mj-events-manager__feedback--${type}`;
            
            setTimeout(() => {
                this.clearFeedback();
            }, 5000);
        }

        clearFeedback() {
            if (this.elements.feedback) {
                this.elements.feedback.textContent = '';
                this.elements.feedback.className = 'mj-events-manager__feedback';
            }
        }

        showFormFeedback(message, type = 'info') {
            if (!this.elements.formFeedback) return;

            this.elements.formFeedback.textContent = message;
            this.elements.formFeedback.className = `mj-events-manager-form__feedback mj-events-manager-form__feedback--${type}`;
        }

        clearFormFeedback() {
            if (this.elements.formFeedback) {
                this.elements.formFeedback.textContent = '';
                this.elements.formFeedback.className = 'mj-events-manager-form__feedback';
            }
        }
    }

    function bootstrap() {
        const containers = document.querySelectorAll('[data-mj-events-manager]');
        containers.forEach(container => {
            if (!container.dataset.mjEventsManagerInit) {
                container.dataset.mjEventsManagerInit = '1';
                new EventsManagerController(container);
            }
        });
    }

    domReady(bootstrap);

    if (typeof globalObject.MutationObserver !== 'undefined') {
        const observer = new MutationObserver(() => {
            bootstrap();
        });
        domReady(() => {
            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        });
    }
})();
