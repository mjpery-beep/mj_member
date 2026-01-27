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
                rangeNotice: form.querySelector('[data-schedule-range-note]'),
            };
            this.datetimeCompositeMap = new Map();

            this.setupDatetimeComposites();
            
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

        setupDatetimeComposites() {
            this.datetimeCompositeMap = new Map();
            const containers = this.form.querySelectorAll('[data-datetime-composite]');
            containers.forEach(container => {
                const field = container.getAttribute('data-datetime-target');
                if (!field) {
                    return;
                }

                const hidden = this.form.querySelector(`[data-datetime-hidden="${field}"]`);
                if (!hidden) {
                    return;
                }

                const dateInput = container.querySelector('[data-datetime-date]');
                const timeInput = container.querySelector('[data-datetime-time]');
                if (!dateInput || !timeInput) {
                    return;
                }

                const requireTime = container.getAttribute('data-datetime-require-time') === '1';

                const composite = {
                    field,
                    hidden,
                    dateInput,
                    timeInput,
                    requireTime,
                };

                const sync = () => {
                    const dateValue = dateInput.value ? dateInput.value.trim() : '';
                    const timeValue = timeInput.value ? timeInput.value.trim() : '';

                    if (!dateValue) {
                        hidden.value = '';
                        return;
                    }

                    if (requireTime && !timeValue) {
                        hidden.value = '';
                        return;
                    }

                    hidden.value = timeValue ? `${dateValue}T${timeValue}` : dateValue;
                };

                composite.sync = sync;

                dateInput.addEventListener('input', sync);
                dateInput.addEventListener('change', sync);
                timeInput.addEventListener('input', sync);
                timeInput.addEventListener('change', sync);

                sync();

                this.datetimeCompositeMap.set(field, composite);
            });
        }

        updateDatetimeComposite(field, rawValue) {
            if (!this.datetimeCompositeMap.has(field)) {
                return;
            }

            const composite = this.datetimeCompositeMap.get(field);
            const normalized = typeof rawValue === 'string' ? rawValue.trim() : '';

            if (normalized === '') {
                composite.hidden.value = '';
                composite.dateInput.value = '';
                composite.timeInput.value = '';
                if (typeof composite.sync === 'function') {
                    composite.sync();
                }
                return;
            }

            const parts = this.extractDateParts(normalized);
            composite.hidden.value = parts.iso || '';
            composite.dateInput.value = parts.date || '';
            composite.timeInput.value = parts.hasTime ? parts.time : '';

            if (!parts.hasTime && composite.requireTime) {
                composite.timeInput.value = '';
                composite.hidden.value = '';
            }

            if (typeof composite.sync === 'function') {
                composite.sync();
            }
        }

        syncDatetimeHidden() {
            if (!this.datetimeCompositeMap) {
                return;
            }

            this.datetimeCompositeMap.forEach(composite => {
                if (typeof composite.sync === 'function') {
                    composite.sync();
                }
            });
        }

        handleModeChange() {
            const selectedMode = this.form.querySelector('[data-schedule-mode]:checked');
            const mode = selectedMode ? selectedMode.value : 'fixed';
            
            if (this.elements.recurringSection) {
                this.elements.recurringSection.hidden = mode !== 'recurring';
            }

            if (this.elements.rangeNotice) {
                this.elements.rangeNotice.hidden = mode !== 'range';
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

    class RichTextFieldController {
        constructor(form, options = {}) {
            this.form = form;
            this.wrapperSelector = (options && options.wrapperSelector) || '[data-richtext-field]';
            this.sourceSelector = (options && options.sourceSelector) || 'textarea[data-richtext-source]';
            this.wrapper = null;
            this.source = null;
            this.editor = null;
            this.toolbar = null;
            this.editable = null;
            this.placeholder = '';
            this.isEnhanced = false;
            this.isDirty = false;
            this.lastSyncedRaw = '';
            this.lastSanitizedValue = '';
            this.lastSelectionRange = null;
            this.focusRestoreHandle = null;
            this.focusRestoreHandleType = null;
            this.preventFocusRestore = false;
            this.didPointerDownOutside = false;
            this.hasPointerListener = false;
            this.mediaFrame = null;
            this.wpMediaAvailable = !!(globalObject.wp && globalObject.wp.media && typeof globalObject.wp.media === 'function');
            this.boundHandleDocumentPointerDown = this.handleDocumentPointerDown.bind(this);

            this.init();
        }

        init() {
            if (!this.form || typeof document === 'undefined') {
                return;
            }

            this.wrapper = this.form.querySelector(this.wrapperSelector);
            if (!this.wrapper) {
                return;
            }

            this.source = this.wrapper.querySelector(this.sourceSelector);
            if (!this.source) {
                return;
            }

            this.placeholder = this.source.getAttribute('placeholder') || '';

            if (!this.canEnhance()) {
                return;
            }

            this.createEditor();
            this.isEnhanced = true;
            this.syncToSource();
            this.refreshToolbarState();
        }

        canEnhance() {
            return typeof document.execCommand === 'function';
        }

        createEditor() {
            this.editor = document.createElement('div');
            this.editor.className = 'mj-richtext';

            this.toolbar = this.buildToolbar();
            this.editable = document.createElement('div');
            this.editable.className = 'mj-richtext__editable';
            this.editable.contentEditable = 'true';
            this.editable.setAttribute('role', 'textbox');
            this.editable.setAttribute('aria-multiline', 'true');

            const labelText = this.findLabelText();
            if (labelText) {
                this.editable.setAttribute('aria-label', labelText);
            }

            if (this.placeholder) {
                this.editable.dataset.placeholder = this.placeholder;
            }

            const initialValue = this.normalizeContent(this.source.value || '');
            if (initialValue) {
                this.editable.innerHTML = initialValue;
            }

            this.editor.appendChild(this.toolbar);
            this.editor.appendChild(this.editable);

            this.wrapper.insertBefore(this.editor, this.source);
            this.source.dataset.richtextHidden = 'true';
            this.source.hidden = true;
            this.source.setAttribute('aria-hidden', 'true');

            this.editable.addEventListener('input', (event) => this.handleInput(event));
            this.editable.addEventListener('blur', () => this.handleBlur());
            this.editable.addEventListener('keyup', (event) => this.handleKeyup(event));
            this.editable.addEventListener('keydown', (event) => this.handleKeydown(event));
            this.editable.addEventListener('mouseup', () => this.handleMouseup());
            this.editable.addEventListener('focus', () => this.handleFocus());
            this.editable.addEventListener('pointerdown', () => {
                this.didPointerDownOutside = false;
            });
            this.editable.addEventListener('paste', (event) => this.handlePaste(event));

            this.syncToSource(true);
            this.refreshToolbarState();

            if (!this.hasPointerListener && typeof document !== 'undefined') {
                document.addEventListener('pointerdown', this.boundHandleDocumentPointerDown, true);
                this.hasPointerListener = true;
            }
        }

        buildToolbar() {
            const toolbar = document.createElement('div');
            toolbar.className = 'mj-richtext__toolbar';

            const buttons = [
                { command: 'bold', label: 'Gras', text: 'B' },
                { command: 'italic', label: 'Italique', text: 'I' },
                { command: 'underline', label: 'Souligner', text: 'U' },
                { command: 'insertUnorderedList', label: 'Liste à puces', text: 'Bul' },
                { command: 'insertOrderedList', label: 'Liste numérotée', text: 'Num' },
                { command: 'createLink', label: 'Insérer un lien', text: 'Link' },
                { command: 'insertMedia', label: 'Insérer un média', text: 'Med' },
                { command: 'removeFormat', label: 'Nettoyer le formatage', text: 'Clr' },
            ];

            buttons.forEach(config => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mj-richtext__toolbar-btn';
                button.dataset.command = config.command;
                button.setAttribute('aria-label', config.label);
                button.textContent = config.text;
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => this.handleCommand(config));
                toolbar.appendChild(button);
            });

            return toolbar;
        }

        handleInput(event) {
            if (event) {
                if (typeof event.stopPropagation === 'function') {
                    event.stopPropagation();
                }
            }
            this.isDirty = true;
            this.didPointerDownOutside = false;
            this.captureSelection();
            this.scheduleFocusRestore();
        }

        handleKeydown(event) {
            if (!event) {
                return;
            }
            event.stopPropagation();
        }

        handleKeyup(event) {
            if (event) {
                event.stopPropagation();
            }
            this.captureSelection();
            this.refreshToolbarState();
        }

        handleFocus() {
            this.preventFocusRestore = false;
            this.didPointerDownOutside = false;
            this.captureSelection();
            this.refreshToolbarState();
        }

        handleBlur() {
            this.syncToSource(true);
            const shouldPrevent = this.didPointerDownOutside;
            this.preventFocusRestore = shouldPrevent;
            this.didPointerDownOutside = false;

            if (shouldPrevent) {
                this.cancelFocusRestore();
                return;
            }

            this.scheduleFocusRestore();
        }

        handleMouseup() {
            this.captureSelection();
            this.refreshToolbarState();
        }

        captureSelection() {
            if (!this.editable || typeof globalObject.getSelection !== 'function') {
                return;
            }
            const selection = globalObject.getSelection();
            if (!selection || selection.rangeCount === 0) {
                return;
            }
            const range = selection.getRangeAt(0);
            if (!this.editable.contains(range.startContainer) || !this.editable.contains(range.endContainer)) {
                return;
            }
            this.lastSelectionRange = range.cloneRange();
        }

        restoreSelection() {
            if (!this.editable || typeof globalObject.getSelection !== 'function') {
                return;
            }
            const selection = globalObject.getSelection();
            if (!selection) {
                return;
            }

            selection.removeAllRanges();

            if (this.lastSelectionRange && this.editable.contains(this.lastSelectionRange.startContainer) && this.editable.contains(this.lastSelectionRange.endContainer)) {
                const range = this.lastSelectionRange.cloneRange();
                selection.addRange(range);
                return;
            }

            this.placeCaretAtEnd();
        }

        placeCaretAtEnd() {
            if (!this.editable || typeof document.createRange !== 'function' || typeof globalObject.getSelection !== 'function') {
                return;
            }
            const selection = globalObject.getSelection();
            if (!selection) {
                return;
            }
            const range = document.createRange();
            range.selectNodeContents(this.editable);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
            this.lastSelectionRange = range.cloneRange();
        }

        cancelFocusRestore() {
            if (this.focusRestoreHandle === null) {
                return;
            }
            if (this.focusRestoreHandleType === 'raf' && typeof globalObject.cancelAnimationFrame === 'function') {
                globalObject.cancelAnimationFrame(this.focusRestoreHandle);
            } else if (this.focusRestoreHandleType === 'timeout' && typeof globalObject.clearTimeout === 'function') {
                globalObject.clearTimeout(this.focusRestoreHandle);
            }
            this.focusRestoreHandle = null;
            this.focusRestoreHandleType = null;
        }

        scheduleFocusRestore() {
            if (!this.editable || this.preventFocusRestore) {
                return;
            }

            this.cancelFocusRestore();

            const scheduler = typeof globalObject.requestAnimationFrame === 'function'
                ? (callback) => {
                    return { type: 'raf', id: globalObject.requestAnimationFrame(callback) };
                }
                : (callback) => {
                    return { type: 'timeout', id: (typeof globalObject.setTimeout === 'function' ? globalObject.setTimeout(callback, 16) : setTimeout(callback, 16)) };
                };

            const handle = scheduler(() => {
                this.focusRestoreHandle = null;
                this.focusRestoreHandleType = null;

                if (!this.editable || this.preventFocusRestore) {
                    return;
                }
                if (document.activeElement === this.editable) {
                    return;
                }

                try {
                    if (typeof this.editable.focus === 'function') {
                        this.editable.focus({ preventScroll: true });
                    } else {
                        this.editable.focus();
                    }
                } catch (focusError) {
                    this.editable.focus();
                }

                this.restoreSelection();
            });

            this.focusRestoreHandle = handle && handle.id !== undefined ? handle.id : null;
            this.focusRestoreHandleType = handle && handle.type ? handle.type : null;
        }

        handleDocumentPointerDown(event) {
            if (!this.editor) {
                this.didPointerDownOutside = true;
                return;
            }
            this.didPointerDownOutside = !this.editor.contains(event.target);
        }

        handleCommand(config) {
            if (!this.editable) {
                return;
            }

            try {
                this.editable.focus({ preventScroll: true });
            } catch (focusError) {
                this.editable.focus();
            }

            this.restoreSelection();

            if (config.command === 'insertMedia') {
                this.handleInsertMedia();
                return;
            }

            if (config.command === 'createLink') {
                const selection = globalObject.getSelection ? globalObject.getSelection() : null;
                if (!selection || selection.isCollapsed) {
                    return;
                }
                const rawUrl = window.prompt('Adresse du lien (https://...)', '');
                const sanitized = this.sanitizeUrl(rawUrl || '');
                if (!sanitized) {
                    return;
                }
                document.execCommand('createLink', false, sanitized);
                this.isDirty = true;
                this.syncToSource(true);
                this.refreshToolbarState();
                this.captureSelection();
                return;
            }

            if (config.command === 'removeFormat') {
                document.execCommand('removeFormat');
                document.execCommand('unlink');
                this.isDirty = true;
                this.syncToSource(true);
                this.refreshToolbarState();
                this.captureSelection();
                return;
            }

            document.execCommand(config.command, false, config.value || null);
            this.isDirty = true;
            this.syncToSource(true);
            this.refreshToolbarState();
            this.captureSelection();
        }

        handleInsertMedia() {
            if (!this.editable) {
                return;
            }

            this.wpMediaAvailable = !!(globalObject.wp && globalObject.wp.media && typeof globalObject.wp.media === 'function');
            this.captureSelection();

            if (this.wpMediaAvailable) {
                this.openMediaFrame();
                return;
            }

            this.promptForMediaUrl();
        }

        openMediaFrame() {
            if (!this.wpMediaAvailable) {
                this.promptForMediaUrl();
                return;
            }

            if (!this.mediaFrame && globalObject.wp && typeof globalObject.wp.media === 'function') {
                const frame = globalObject.wp.media({
                    title: 'Sélectionner un média',
                    button: { text: 'Insérer' },
                    library: { type: ['image', 'video'] },
                    multiple: false,
                });

                frame.on('select', () => {
                    const state = typeof frame.state === 'function' ? frame.state() : frame.state;
                    const selection = state && typeof state.get === 'function' ? state.get('selection') : null;
                    const attachment = selection && typeof selection.first === 'function' ? selection.first() : null;
                    const data = attachment && typeof attachment.toJSON === 'function' ? attachment.toJSON() : attachment;
                    if (data) {
                        this.handleMediaSelection(data);
                    }
                    this.preventFocusRestore = false;
                    try {
                        if (this.editable) {
                            this.editable.focus({ preventScroll: true });
                        }
                    } catch (focusError) {
                        if (this.editable) {
                            this.editable.focus();
                        }
                    }
                    this.restoreSelection();
                });

                frame.on('close', () => {
                    this.preventFocusRestore = false;
                    try {
                        if (this.editable) {
                            this.editable.focus({ preventScroll: true });
                        }
                    } catch (focusError) {
                        if (this.editable) {
                            this.editable.focus();
                        }
                    }
                    this.restoreSelection();
                });

                this.mediaFrame = frame;
            }

            this.preventFocusRestore = true;

            if (this.mediaFrame && typeof this.mediaFrame.open === 'function') {
                this.mediaFrame.open();
            } else {
                this.promptForMediaUrl();
            }
        }

        promptForMediaUrl() {
            const promptFn = typeof globalObject.prompt === 'function' ? globalObject.prompt : null;
            if (!promptFn) {
                this.preventFocusRestore = false;
                return;
            }

            const rawUrl = promptFn('Adresse du média (https://...)', '');
            const sanitizedUrl = this.sanitizeMediaUrl(rawUrl || '');
            if (!sanitizedUrl) {
                this.preventFocusRestore = false;
                return;
            }

            let altText = '';
            if (typeof globalObject.prompt === 'function') {
                const rawAlt = globalObject.prompt('Texte alternatif (optionnel)', '');
                if (typeof rawAlt === 'string') {
                    altText = rawAlt;
                }
            }

            this.insertImageFromUrl(sanitizedUrl, altText);
            this.preventFocusRestore = false;
        }

        handleMediaSelection(data) {
            if (!data) {
                this.preventFocusRestore = false;
                return;
            }

            const candidateUrl = data.url
                || (data.sizes && data.sizes.large && data.sizes.large.url)
                || (data.sizes && data.sizes.full && data.sizes.full.url)
                || '';
            const mime = data.mime || data.mime_type || '';
            const type = data.type || '';
            const isImage = (typeof mime === 'string' && mime.indexOf('image/') === 0) || type === 'image';

            if (isImage) {
                const altText = data.alt || data.alt_text || data.caption || data.title || data.filename || '';
                this.insertImageFromUrl(candidateUrl, altText);
                this.preventFocusRestore = false;
                return;
            }

            const sanitizedUrl = this.sanitizeUrl(candidateUrl);
            if (!sanitizedUrl) {
                this.preventFocusRestore = false;
                return;
            }

            const labelSource = data.title || data.filename || candidateUrl;
            const label = this.sanitizeAttributeText(labelSource, 160) || 'Média';
            const html = `<a href="${escapeHtml(sanitizedUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`;
            this.insertHtml(html);
            this.preventFocusRestore = false;
        }

        insertImageFromUrl(url, altText) {
            const sanitizedUrl = this.sanitizeMediaUrl(url);
            if (!sanitizedUrl) {
                return;
            }

            const safeAlt = this.sanitizeAttributeText(altText, 160);
            const html = `<img src="${escapeHtml(sanitizedUrl)}" alt="${escapeHtml(safeAlt)}">`;
            this.insertHtml(html);
        }

        insertHtml(html) {
            if (!this.editable) {
                return;
            }

            try {
                this.editable.focus({ preventScroll: true });
            } catch (focusError) {
                this.editable.focus();
            }

            this.restoreSelection();

            if (typeof document.execCommand === 'function') {
                try {
                    document.execCommand('insertHTML', false, html);
                } catch (error) {
                    return;
                }
            } else {
                this.editable.insertAdjacentHTML('beforeend', html);
            }

            this.isDirty = true;
            this.syncToSource(true);
            this.refreshToolbarState();
            this.captureSelection();
        }

        syncToSource(force = false) {
            if (!this.source) {
                return;
            }

            const raw = this.isEnhanced && this.editable ? this.editable.innerHTML : this.source.value;

            if (!force && !this.isDirty && raw === this.lastSyncedRaw) {
                return;
            }

            const normalized = this.normalizeContent(raw);

            this.source.value = normalized;
            this.lastSyncedRaw = raw;
            this.lastSanitizedValue = normalized;

            if (this.isEnhanced && this.editable && document.activeElement !== this.editable) {
                if (this.editable.innerHTML !== normalized) {
                    this.editable.innerHTML = normalized || '';
                }
            }

            this.isDirty = false;
        }

        syncSource(force = false) {
            this.syncToSource(force);
        }

        setValue(value) {
            const normalized = this.normalizeContent(value);
            if (this.source) {
                this.source.value = normalized;
            }
            if (this.isEnhanced && this.editable) {
                this.editable.innerHTML = normalized || '';
            }
            this.isDirty = true;
            this.syncToSource(true);
            this.refreshToolbarState();
            this.lastSelectionRange = null;
        }

        reset() {
            this.setValue('');
            this.cancelFocusRestore();
            this.lastSelectionRange = null;
        }

        normalizeContent(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }
            const raw = String(value);
            const trimmed = raw.trim();

            if (trimmed === '') {
                return '';
            }

            const looksLikeHtml = /<[a-z][\s\S]*>/i.test(trimmed);
            if (!looksLikeHtml) {
                return this.sanitizeHtml(this.convertTextToHtml(trimmed));
            }

            return this.sanitizeHtml(trimmed);
        }

        convertTextToHtml(text) {
            const lines = text.split(/\r?\n/);
            const htmlLines = lines.map(line => {
                const cleaned = line.trim();
                if (cleaned === '') {
                    return '<p><br></p>';
                }
                return `<p>${escapeHtml(cleaned)}</p>`;
            });
            return htmlLines.join('');
        }

        sanitizeHtml(html) {
            if (!html) {
                return '';
            }

            const template = document.createElement('template');
            template.innerHTML = html;

            const allowedTags = new Set(['P', 'BR', 'UL', 'OL', 'LI', 'STRONG', 'B', 'EM', 'I', 'U', 'A', 'SPAN', 'DIV', 'IMG']);
            const allowedAttributes = {
                A: ['href', 'target', 'rel'],
                SPAN: [],
                IMG: ['src', 'alt'],
            };

            template.content.querySelectorAll('*').forEach(node => {
                const tagName = node.tagName;
                if (!allowedTags.has(tagName)) {
                    this.unwrapNode(node);
                    return;
                }

                Array.from(node.attributes).forEach(attr => {
                    const attrName = attr.name.toLowerCase();
                    const allowed = allowedAttributes[tagName] || [];
                    if (!allowed.includes(attrName)) {
                        node.removeAttribute(attr.name);
                    }
                });

                if (tagName === 'A') {
                    const href = this.sanitizeUrl(node.getAttribute('href') || '');
                    if (!href) {
                        this.unwrapNode(node);
                        return;
                    }
                    node.setAttribute('href', href);
                    const target = node.getAttribute('target');
                    if (target && target.toLowerCase() === '_blank') {
                        node.setAttribute('rel', 'noopener noreferrer');
                    } else {
                        node.removeAttribute('target');
                        node.removeAttribute('rel');
                    }
                } else if (tagName === 'IMG') {
                    const src = this.sanitizeMediaUrl(node.getAttribute('src') || '');
                    if (!src) {
                        if (node.parentNode) {
                            node.parentNode.removeChild(node);
                        }
                        return;
                    }
                    node.setAttribute('src', src);
                    const altRaw = node.getAttribute('alt') || '';
                    const altText = this.sanitizeAttributeText(altRaw, 160);
                    node.setAttribute('alt', altText);
                }
            });

            template.content.querySelectorAll('div').forEach(div => {
                const paragraph = document.createElement('p');
                while (div.firstChild) {
                    paragraph.appendChild(div.firstChild);
                }
                if (div.parentNode) {
                    div.parentNode.replaceChild(paragraph, div);
                }
            });

            const container = document.createElement('div');
            container.appendChild(template.content.cloneNode(true));

            const sanitized = container.innerHTML
                .replace(/\s+<\/p>/g, '</p>')
                .replace(/<p>\s*<\/p>/g, '');

            return sanitized.trim();
        }

        sanitizeUrl(url) {
            if (!url) {
                return '';
            }

            const trimmed = String(url).trim();
            if (trimmed === '') {
                return '';
            }

            const lower = trimmed.toLowerCase();
            if (/^(https?:\/\/|mailto:)/.test(lower)) {
                return trimmed;
            }

            if (/^www\./.test(lower)) {
                return `https://${trimmed}`;
            }

            return '';
        }

        sanitizeMediaUrl(url) {
            if (!url) {
                return '';
            }

            const trimmed = String(url).trim();
            if (trimmed === '') {
                return '';
            }

            const lower = trimmed.toLowerCase();
            if (/^https?:\/\//.test(lower)) {
                return trimmed;
            }

            if (/^www\./.test(lower)) {
                return `https://${trimmed}`;
            }

            return '';
        }

        sanitizeAttributeText(value, maxLength = 255) {
            if (typeof value !== 'string') {
                return '';
            }

            let normalized = value.replace(/\s+/g, ' ').trim();
            if (normalized === '') {
                return '';
            }

            if (typeof maxLength === 'number' && maxLength > 0 && normalized.length > maxLength) {
                normalized = normalized.slice(0, maxLength);
            }

            return normalized;
        }

        unwrapNode(node) {
            if (!node || !node.parentNode) {
                return;
            }
            while (node.firstChild) {
                node.parentNode.insertBefore(node.firstChild, node);
            }
            node.parentNode.removeChild(node);
        }

        handlePaste(event) {
            if (!event.clipboardData) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            const html = event.clipboardData.getData('text/html');
            const text = event.clipboardData.getData('text/plain');

            if (html) {
                const sanitized = this.sanitizeHtml(html);
                document.execCommand('insertHTML', false, sanitized);
            } else if (text) {
                const sanitizedText = escapeHtml(text).replace(/\r?\n/g, '<br>');
                document.execCommand('insertHTML', false, sanitizedText);
            }

            this.isDirty = true;
            this.syncToSource(true);
            this.refreshToolbarState();
        }

        refreshToolbarState() {
            if (!this.toolbar) {
                return;
            }

            this.toolbar.querySelectorAll('[data-command]').forEach(button => {
                const command = button.getAttribute('data-command');
                if (!command || command === 'createLink' || command === 'removeFormat' || command === 'insertMedia') {
                    button.setAttribute('aria-pressed', 'false');
                    button.classList.remove('is-active');
                    return;
                }

                let active = false;
                try {
                    active = document.queryCommandState(command);
                } catch (error) {
                    active = false;
                }

                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                if (active) {
                    button.classList.add('is-active');
                } else {
                    button.classList.remove('is-active');
                }
            });
        }

        findLabelText() {
            if (!this.wrapper) {
                return '';
            }
            const label = this.wrapper.querySelector('label');
            if (label && label.textContent) {
                return label.textContent.trim();
            }
            return '';
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
            this.tabButtons = [];
            this.tabPanels = [];
            this.activeTab = null;

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

            this.descriptionField = this.elements.form
                ? new RichTextFieldController(this.elements.form, {
                    wrapperSelector: '[data-richtext-field="description"]',
                    sourceSelector: 'textarea[data-richtext-source="description"]',
                })
                : null;

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
            this.setupTabs();
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

        setupTabs() {
            if (!this.elements.form) {
                return;
            }

            const tabsContainer = this.elements.form.querySelector('[data-tabs]');
            const panels = this.elements.form.querySelectorAll('[data-tab-panel]');

            if (!tabsContainer || panels.length === 0) {
                return;
            }

            const buttons = Array.from(tabsContainer.querySelectorAll('[data-tab-target]'));
            if (buttons.length === 0) {
                return;
            }

            this.tabButtons = buttons;
            this.tabPanels = Array.from(panels);

            buttons.forEach(button => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const target = button.getAttribute('data-tab-target');
                    this.activateTab(target);
                });
            });

            const initialButton = buttons.find(button => button.classList.contains('is-active')) || buttons[0];
            if (initialButton) {
                const target = initialButton.getAttribute('data-tab-target');
                this.activateTab(target, { skipFocus: true });
            }
        }

        activateTab(tabId, options = {}) {
            if (!tabId || !Array.isArray(this.tabButtons) || this.tabButtons.length === 0) {
                return;
            }

            const { skipFocus = false } = options;

            this.tabButtons.forEach(button => {
                const target = button.getAttribute('data-tab-target');
                const isActive = target === tabId;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                button.setAttribute('tabindex', isActive ? '0' : '-1');
                if (isActive && !skipFocus) {
                    button.focus();
                }
            });

            if (Array.isArray(this.tabPanels)) {
                this.tabPanels.forEach(panel => {
                    const panelTarget = panel.getAttribute('data-tab-panel');
                    const isActive = panelTarget === tabId;
                    panel.classList.toggle('is-active', isActive);
                    if (isActive) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', 'hidden');
                    }
                });
            }

            this.activeTab = tabId;
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
                this.activateTab('general', { skipFocus: true });
            } else if (mode === 'edit' && eventId) {
                this.elements.modalTitle.textContent = this.getString('edit');
                this.loadEventToForm(eventId);
                this.activateTab('description', { skipFocus: true });
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
            if (Array.isArray(this.tabButtons) && this.tabButtons.length > 0) {
                this.activateTab('general', { skipFocus: true });
            }
            // Reset schedule fields
            if (this.scheduleController) {
                this.scheduleController.reset();
            }

            this.updateDatetimeComposite('start_date', '');
            this.updateDatetimeComposite('end_date', '');
            this.syncDatetimeHidden();

            const attendanceCheckbox = this.elements.form ? this.elements.form.querySelector('[name="attendance_show_all_members"]') : null;
            if (attendanceCheckbox) {
                attendanceCheckbox.checked = false;
            }

            this.setEmojiValue('');

            if (this.descriptionField) {
                this.descriptionField.reset();
            }
        }

        loadEventToForm(eventId) {
            const event = this.events.find(e => e.id === eventId);
            if (!event || !this.elements.form) return;

            const form = this.elements.form;
            form.querySelector('[name="title"]').value = event.title || '';
            form.querySelector('[name="type"]').value = event.type || '';
            form.querySelector('[name="status"]').value = event.status || '';
            if (this.descriptionField) {
                this.descriptionField.setValue(event.description || '');
            } else {
                const descriptionInput = form.querySelector('[name="description"]');
                if (descriptionInput) {
                    descriptionInput.value = event.description || '';
                }
            }
            form.querySelector('[name="price"]').value = event.price || '';
            form.querySelector('[name="capacity_total"]').value = event.capacity_total || '';

            const attendanceCheckbox = form.querySelector('[name="attendance_show_all_members"]');
            if (attendanceCheckbox) {
                attendanceCheckbox.checked = !!event.attendance_show_all_members;
            }

            this.updateDatetimeComposite('start_date', event.start_date || '');
            this.updateDatetimeComposite('end_date', event.end_date || '');

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

        extractDateParts(value) {
            const result = {
                iso: '',
                date: '',
                time: '',
                hasTime: false,
            };

            if (!value) {
                return result;
            }

            const raw = String(value).trim();
            if (raw === '') {
                return result;
            }

            const date = this.parseDate(raw);
            if (!date) {
                return result;
            }

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            const datePart = `${year}-${month}-${day}`;
            const timePart = `${hours}:${minutes}`;
            const hasTime = /[T ]\d{2}:\d{2}/.test(raw);

            return {
                iso: hasTime ? `${datePart}T${timePart}` : datePart,
                date: datePart,
                time: timePart,
                hasTime: hasTime,
            };
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

            if (this.descriptionField) {
                this.descriptionField.syncSource(true);
            }

            this.syncDatetimeHidden();

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
