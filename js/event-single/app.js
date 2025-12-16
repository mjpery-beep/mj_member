(function () {
    'use strict';

    var globalObject = typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : null);
    if (!globalObject) {
        return;
    }

    var Utils = globalObject.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
            if (typeof callback !== 'function') {
                return;
            }
            if (typeof document === 'undefined') {
                callback();
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
            } else {
                callback();
            }
        };
    var toInt = typeof Utils.toInt === 'function'
        ? function (value, fallback) {
            var parsed = Utils.toInt(value, fallback);
            return parsed === undefined ? fallback : parsed;
        }
        : function (value, fallback) {
            var parsed = parseInt(value, 10);
            return isNaN(parsed) ? fallback : parsed;
        };

    var registrationForm = null;
    var hiddenParticipantField = null;
    var noteField = null;
    var occurrencesField = null;
    var widgetSettings = globalObject.mjMemberEventsWidget || {};
    var ajaxUrl = typeof widgetSettings.ajaxUrl === 'string' ? widgetSettings.ajaxUrl : '';
    var ajaxNonce = typeof widgetSettings.nonce === 'string' ? widgetSettings.nonce : '';
    var ajaxStrings = widgetSettings.strings && typeof widgetSettings.strings === 'object' ? widgetSettings.strings : {};

    function getString(key, fallback) {
        if (!ajaxStrings || typeof ajaxStrings !== 'object') {
            return fallback;
        }
        var value = ajaxStrings[key];
        if (typeof value === 'string' && value !== '') {
            return value;
        }
        return fallback;
    }

    function setFeedbackMessage(element, message, isError) {
        if (!element) {
            return;
        }
        element.textContent = message || '';
        if (isError) {
            element.classList.add('is-error');
        } else {
            element.classList.remove('is-error');
        }
    }

    function parseJsonSafely(text) {
        if (!text || typeof text !== 'string') {
            return null;
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            return null;
        }
    }

    function readJsonResponse(response) {
        if (!response) {
            return Promise.resolve({ ok: false, status: 0, json: null, raw: null });
        }
        var contentType = response.headers && typeof response.headers.get === 'function'
            ? response.headers.get('content-type')
            : '';
        var looksJson = contentType && contentType.indexOf('json') !== -1;
        if (looksJson && typeof response.json === 'function') {
            return response.json().then(function (json) {
                return { ok: response.ok, status: response.status, json: json, raw: null };
            }).catch(function () {
                return { ok: response.ok, status: response.status, json: null, raw: null };
            });
        }
        if (typeof response.text === 'function') {
            return response.text().then(function (text) {
                return { ok: response.ok, status: response.status, json: parseJsonSafely(text), raw: text };
            }).catch(function () {
                return { ok: response.ok, status: response.status, json: null, raw: null };
            });
        }
        return Promise.resolve({ ok: response.ok, status: response.status, json: null, raw: null });
    }

    function ensureAjaxSettings() {
        if ((!ajaxUrl || !ajaxNonce) && (!registrationForm || typeof registrationForm.getAttribute !== 'function')) {
            if (namespace.form && typeof namespace.form.getAttribute === 'function') {
                registrationForm = namespace.form;
            } else if (typeof document !== 'undefined') {
                var located = document.querySelector('[data-mj-event-registration]');
                if (located) {
                    registrationForm = located;
                }
            }
        }

        var form = registrationForm && typeof registrationForm.getAttribute === 'function' ? registrationForm : null;
        if (form) {
            if (!ajaxUrl) {
                var attrUrl = form.getAttribute('data-ajax-url');
                if (attrUrl) {
                    ajaxUrl = attrUrl;
                }
            }
            if (!ajaxNonce) {
                var attrNonce = form.getAttribute('data-ajax-nonce');
                if (attrNonce) {
                    ajaxNonce = attrNonce;
                }
            }
        }

        if (!ajaxUrl && widgetSettings && typeof widgetSettings.ajaxUrl === 'string') {
            ajaxUrl = widgetSettings.ajaxUrl;
        }
        if (!ajaxNonce && widgetSettings && typeof widgetSettings.nonce === 'string') {
            ajaxNonce = widgetSettings.nonce;
        }
        if (!ajaxUrl && globalObject && typeof globalObject.ajaxurl === 'string') {
            ajaxUrl = globalObject.ajaxurl;
        }

        if (!ajaxStrings || typeof ajaxStrings !== 'object') {
            ajaxStrings = {};
        }

        return {
            url: ajaxUrl,
            nonce: ajaxNonce,
        };
    }

    function normalizeCapacity(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }

        var source = raw;
        var capacityTotal = toInt(source.capacity_total !== undefined ? source.capacity_total : source.total, null);
        var waitlistTotal = toInt(source.waitlist_total !== undefined ? source.waitlist_total : source.waitlist, null);
        var activeCount = toInt(source.active_count !== undefined ? source.active_count : source.registered, 0);
        var waitlistCount = toInt(source.waitlist_count !== undefined ? source.waitlist_count : source.waiting, 0);
        var remainingCandidate = source.remaining !== undefined ? source.remaining : null;
        var waitlistRemainingCandidate = source.waitlist_remaining !== undefined ? source.waitlist_remaining : null;
        var waitlistEnabledCandidate = source.waitlist_enabled !== undefined ? source.waitlist_enabled : null;

        var remaining = remainingCandidate !== null && remainingCandidate !== undefined
            ? toInt(remainingCandidate, null)
            : (capacityTotal !== null && activeCount !== null ? Math.max(capacityTotal - activeCount, 0) : null);
        var waitlistRemaining = waitlistRemainingCandidate !== null && waitlistRemainingCandidate !== undefined
            ? toInt(waitlistRemainingCandidate, null)
            : (waitlistTotal !== null ? Math.max(waitlistTotal - waitlistCount, 0) : null);
        var waitlistEnabled = waitlistEnabledCandidate !== null && waitlistEnabledCandidate !== undefined
            ? !!waitlistEnabledCandidate
            : waitlistTotal !== null;

        return {
            capacityTotal: capacityTotal,
            waitlistTotal: waitlistTotal,
            activeCount: activeCount,
            waitlistCount: waitlistCount,
            remaining: remaining,
            waitlistRemaining: waitlistRemaining,
            waitlistEnabled: waitlistEnabled,
            isFull: remaining !== null && remaining <= 0,
            hasWaitlist: waitlistEnabled,
            waitlistIsFull: waitlistEnabled && waitlistRemaining !== null && waitlistRemaining <= 0,
            raw: source,
        };
    }

    function parseDataAttribute(element, attribute) {
        if (!element || typeof element.getAttribute !== 'function') {
            return null;
        }
        var raw = element.getAttribute(attribute);
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function toBool(value) {
        if (value === true || value === false) {
            return value;
        }
        if (typeof value === 'number') {
            return value !== 0;
        }
        if (typeof value === 'string') {
            var lowered = value.toLowerCase();
            return lowered === '1' || lowered === 'true' || lowered === 'yes';
        }
        return false;
    }

    function sanitizeString(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value);
    }

    function arraysEqual(left, right) {
        if (left === right) {
            return true;
        }
        if (!Array.isArray(left) || !Array.isArray(right)) {
            return false;
        }
        if (left.length !== right.length) {
            return false;
        }
        for (var idx = 0; idx < left.length; idx += 1) {
            if (left[idx] !== right[idx]) {
                return false;
            }
        }
        return true;
    }

    function normalizeParticipants(rawList) {
        if (!Array.isArray(rawList) || rawList.length === 0) {
            return [];
        }

        var normalized = [];
        for (var index = 0; index < rawList.length; index += 1) {
            var raw = rawList[index];
            if (!raw || typeof raw !== 'object') {
                continue;
            }

            var id = toInt(raw.id !== undefined ? raw.id : raw.member_id, null);
            if (id === null) {
                continue;
            }

            var name = sanitizeString(raw.name || raw.fullName || raw.full_name || raw.label || ('#' + id));
            var statusLabel = sanitizeString(raw.status_label || raw.statusLabel || raw.registrationStatusLabel || '');
            var statusClass = sanitizeString(raw.status_class || raw.statusClass || '');
            var eligibilityLabel = sanitizeString(raw.eligibility_label || raw.eligibilityLabel || '');
            var isRegistered = toBool(raw.isRegistered || raw.registered || (raw.registrationStatus && raw.registrationStatus !== ''));
            var eligibleFlag = raw.isEligible !== undefined ? toBool(raw.isEligible) : (raw.eligible !== undefined ? toBool(raw.eligible) : true);
            var ineligibleReasons = [];
            if (Array.isArray(raw.ineligible_reasons)) {
                for (var reasonIndex = 0; reasonIndex < raw.ineligible_reasons.length; reasonIndex += 1) {
                    ineligibleReasons.push(sanitizeString(raw.ineligible_reasons[reasonIndex]));
                }
            } else if (Array.isArray(raw.ineligibleReasons)) {
                for (var reasonIndexAlt = 0; reasonIndexAlt < raw.ineligibleReasons.length; reasonIndexAlt += 1) {
                    ineligibleReasons.push(sanitizeString(raw.ineligibleReasons[reasonIndexAlt]));
                }
            }

            var selectable = eligibleFlag && !isRegistered;

            normalized.push({
                id: id,
                name: name,
                statusLabel: statusLabel,
                statusClass: statusClass,
                eligibilityLabel: eligibilityLabel,
                isRegistered: isRegistered,
                eligible: eligibleFlag,
                selectable: selectable,
                ineligibleReasons: ineligibleReasons,
            });
        }

        return normalized;
    }

    function normalizeOccurrenceValue(value) {
        if (typeof value === 'string') {
            return value.trim();
        }
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).trim();
    }

    function sanitizeOccurrenceValues(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        var sanitized = [];
        var registry = Object.create(null);
        for (var idx = 0; idx < list.length; idx += 1) {
            var candidate = normalizeOccurrenceValue(list[idx]);
            if (!candidate || registry[candidate]) {
                continue;
            }
            registry[candidate] = true;
            sanitized.push(candidate);
        }
        return sanitized;
    }

    function parseOccurrenceTimestamp(value) {
        if (value === null || value === undefined) {
            return null;
        }
        if (typeof value === 'number' && !isNaN(value) && value > 0) {
            return Math.floor(value);
        }
        if (typeof value === 'string') {
            var numeric = parseInt(value, 10);
            if (!isNaN(numeric) && numeric > 0) {
                return Math.floor(numeric);
            }
            var parsed = Date.parse(value);
            if (!isNaN(parsed)) {
                return Math.floor(parsed / 1000);
            }
        }
        return null;
    }

    var cachedLocale = null;

    function getPreferredLocale() {
        if (cachedLocale) {
            return cachedLocale;
        }
        var locale = 'fr-FR';
        if (globalObject && globalObject.mjMemberLocale) {
            locale = String(globalObject.mjMemberLocale);
        } else if (typeof document !== 'undefined' && document.documentElement && document.documentElement.lang) {
            locale = document.documentElement.lang;
        }
        if (typeof locale === 'string') {
            locale = locale.replace(/_/g, '-');
        } else {
            locale = 'fr-FR';
        }
        cachedLocale = locale;
        return cachedLocale;
    }

    function formatOccurrenceDate(dateObject) {
        if (!dateObject) {
            return '';
        }
        try {
            var formatter = new Intl.DateTimeFormat(getPreferredLocale(), {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            });
            var formatted = formatter.format(dateObject);
            if (formatted && formatted.charAt) {
                return formatted.charAt(0).toUpperCase() + formatted.slice(1);
            }
            return formatted;
        } catch (error) {
            return '';
        }
    }

    function formatOccurrenceTime(dateObject) {
        if (!dateObject) {
            return '';
        }
        try {
            var formatter = new Intl.DateTimeFormat(getPreferredLocale(), {
                hour: '2-digit',
                minute: '2-digit',
            });
            return formatter.format(dateObject);
        } catch (error) {
            var hours = String(dateObject.getHours()).padStart(2, '0');
            var minutes = String(dateObject.getMinutes()).padStart(2, '0');
            return hours + ':' + minutes;
        }
    }

    function parseDateKey(value) {
        if (typeof value !== 'string' || !value) {
            return null;
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return null;
        }
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var day = parseInt(parts[2], 10);
        if (isNaN(year) || isNaN(month) || isNaN(day)) {
            return null;
        }
        return new Date(year, Math.max(0, month - 1), day, 12, 0, 0, 0);
    }

    function dateKeyFromDate(date) {
        if (!date) {
            return '';
        }
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function startOfMonthDate(date) {
        var base = date ? new Date(date.getTime()) : new Date();
        return new Date(base.getFullYear(), base.getMonth(), 1, 12, 0, 0, 0);
    }

    function addMonths(baseDate, offset) {
        var base = baseDate ? new Date(baseDate.getTime()) : new Date();
        return new Date(base.getFullYear(), base.getMonth() + offset, 1, 12, 0, 0, 0);
    }

    function addDays(baseDate, offset) {
        var base = baseDate ? new Date(baseDate.getTime()) : new Date();
        base.setDate(base.getDate() + offset);
        base.setHours(12, 0, 0, 0);
        return base;
    }

    function monthKey(date) {
        if (!date) {
            return '';
        }
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    }

    function compareMonth(left, right) {
        if (!left && !right) {
            return 0;
        }
        if (!left) {
            return -1;
        }
        if (!right) {
            return 1;
        }
        var diffYear = left.getFullYear() - right.getFullYear();
        if (diffYear !== 0) {
            return diffYear;
        }
        return left.getMonth() - right.getMonth();
    }

    function startOfWeek(date) {
        var base = date ? new Date(date.getTime()) : new Date();
        base.setHours(12, 0, 0, 0);
        var weekday = base.getDay();
        var offset = weekday === 0 ? -6 : 1 - weekday;
        return addDays(base, offset);
    }

    function isSameDay(left, right) {
        if (!left || !right) {
            return false;
        }
        return left.getFullYear() === right.getFullYear()
            && left.getMonth() === right.getMonth()
            && left.getDate() === right.getDate();
    }

    function formatMonthLabel(dateObject) {
        if (!dateObject) {
            return '';
        }
        try {
            var formatter = new Intl.DateTimeFormat(getPreferredLocale(), {
                month: 'long',
                year: 'numeric',
            });
            var formatted = formatter.format(dateObject);
            if (formatted && formatted.charAt) {
                return formatted.charAt(0).toUpperCase() + formatted.slice(1);
            }
            return formatted;
        } catch (error) {
            return dateObject.getFullYear() + '-' + String(dateObject.getMonth() + 1).padStart(2, '0');
        }
    }

    function normalizeAssignments(raw) {
        if (!raw || typeof raw !== 'object') {
            return {
                mode: 'all',
                occurrences: [],
            };
        }

        var modeCandidate = '';
        if (typeof raw.mode === 'string' && raw.mode) {
            modeCandidate = raw.mode;
        } else if (typeof raw.selectionMode === 'string' && raw.selectionMode) {
            modeCandidate = raw.selectionMode;
        } else if (typeof raw.selection_mode === 'string' && raw.selection_mode) {
            modeCandidate = raw.selection_mode;
        }

        var normalizedMode = modeCandidate ? modeCandidate.toLowerCase() : 'all';
        if (normalizedMode !== 'custom' && normalizedMode !== 'all') {
            normalizedMode = 'all';
        }

        var occurrences = [];
        if (Array.isArray(raw.occurrences)) {
            occurrences = sanitizeOccurrenceValues(raw.occurrences);
        } else if (Array.isArray(raw.values)) {
            occurrences = sanitizeOccurrenceValues(raw.values);
        }

        return {
            mode: normalizedMode,
            occurrences: occurrences,
        };
    }

    function normalizeOccurrences(rawList) {
        if (!Array.isArray(rawList) || rawList.length === 0) {
            return [];
        }

        var normalized = [];
        var registry = Object.create(null);
        var now = Date.now();

        for (var idx = 0; idx < rawList.length; idx += 1) {
            var raw = rawList[idx];
            if (!raw || typeof raw !== 'object') {
                continue;
            }

            var valueCandidate = normalizeOccurrenceValue(raw.slug !== undefined ? raw.slug : (raw.value !== undefined ? raw.value : raw.start));
            if (!valueCandidate || registry[valueCandidate]) {
                continue;
            }
            registry[valueCandidate] = true;

            var timestampCandidate = null;
            if (raw.timestamp !== undefined) {
                timestampCandidate = parseOccurrenceTimestamp(raw.timestamp);
            } else if (raw.time !== undefined) {
                timestampCandidate = parseOccurrenceTimestamp(raw.time);
            }

            var startString = raw.start ? String(raw.start) : '';
            var fallbackTimestamp = null;
            if (timestampCandidate === null && startString) {
                var parsed = Date.parse(startString.replace(' ', 'T'));
                if (!isNaN(parsed)) {
                    fallbackTimestamp = Math.floor(parsed / 1000);
                }
            }

            var timestamp = timestampCandidate !== null ? timestampCandidate : fallbackTimestamp;
            var dateObject = null;
            if (timestamp !== null) {
                dateObject = new Date(timestamp * 1000);
            } else if (startString) {
                var parsedFallback = Date.parse(startString);
                if (!isNaN(parsedFallback)) {
                    dateObject = new Date(parsedFallback);
                    timestamp = Math.floor(parsedFallback / 1000);
                }
            }

            var dateKey = dateObject ? (dateObject.getFullYear() + '-' + String(dateObject.getMonth() + 1).padStart(2, '0') + '-' + String(dateObject.getDate()).padStart(2, '0')) : valueCandidate;
            var dateLabel = dateObject ? formatOccurrenceDate(dateObject) : '';
            var timeLabel = dateObject ? formatOccurrenceTime(dateObject) : '';

            var labelCandidate = '';
            if (typeof raw.label === 'string' && raw.label) {
                labelCandidate = raw.label;
            } else if (typeof raw.display === 'string' && raw.display) {
                labelCandidate = raw.display;
            }

            var fullLabel = labelCandidate;
            if (!fullLabel) {
                if (dateLabel) {
                    fullLabel = dateLabel + (timeLabel ? ' · ' + timeLabel : '');
                } else if (startString) {
                    fullLabel = startString;
                } else {
                    fullLabel = valueCandidate;
                }
            }

            var isPast = false;
            if (raw.isPast !== undefined) {
                isPast = !!raw.isPast;
            } else if (timestamp !== null) {
                isPast = (timestamp * 1000) < now;
            }

            var isToday = false;
            if (raw.isToday !== undefined) {
                isToday = !!raw.isToday;
            } else if (dateObject) {
                var today = new Date();
                isToday = today.getFullYear() === dateObject.getFullYear()
                    && today.getMonth() === dateObject.getMonth()
                    && today.getDate() === dateObject.getDate();
            }

            var groupLabel = dateLabel || fullLabel;
            var displayLabel = timeLabel || fullLabel;

            normalized.push({
                value: valueCandidate,
                start: startString,
                timestamp: timestamp,
                dateKey: dateKey,
                dateLabel: dateLabel,
                timeLabel: timeLabel,
                label: fullLabel,
                groupLabel: groupLabel,
                displayLabel: displayLabel,
                isPast: isPast,
                isToday: isToday,
                selectable: !isPast,
            });
        }

        normalized.sort(function (a, b) {
            var aTime = a.timestamp !== null && a.timestamp !== undefined ? a.timestamp : 0;
            var bTime = b.timestamp !== null && b.timestamp !== undefined ? b.timestamp : 0;
            if (aTime && bTime && aTime !== bTime) {
                return aTime - bTime;
            }
            return a.value.localeCompare(b.value);
        });

        return normalized;
    }

    function findParticipantById(participants, id) {
        if (!Array.isArray(participants)) {
            return null;
        }
        for (var index = 0; index < participants.length; index += 1) {
            var candidate = participants[index];
            if (candidate && candidate.id === id) {
                return candidate;
            }
        }
        return null;
    }

    function findDefaultParticipant(participants) {
        if (!Array.isArray(participants) || participants.length === 0) {
            return null;
        }
        for (var idx = 0; idx < participants.length; idx += 1) {
            if (participants[idx].selectable) {
                return participants[idx];
            }
        }
        for (var idxEligible = 0; idxEligible < participants.length; idxEligible += 1) {
            if (participants[idxEligible].eligible) {
                return participants[idxEligible];
            }
        }
        return participants[0];
    }

    function applyDataset(element, config) {
        if (!element || !config || typeof element.setAttribute !== 'function') {
            return;
        }
        element.setAttribute('data-allow-guardian-registration', config.allowGuardianRegistration ? '1' : '0');
        var capacity = config.capacity;
        if (!capacity) {
            element.removeAttribute('data-capacity-total');
            element.removeAttribute('data-capacity-remaining');
            element.removeAttribute('data-capacity-waitlist');
            element.removeAttribute('data-capacity-waitlist-remaining');
            return;
        }
        if (capacity.capacityTotal !== null && capacity.capacityTotal !== undefined) {
            element.setAttribute('data-capacity-total', String(capacity.capacityTotal));
        } else {
            element.removeAttribute('data-capacity-total');
        }
        if (capacity.remaining !== null && capacity.remaining !== undefined) {
            element.setAttribute('data-capacity-remaining', String(capacity.remaining));
        } else {
            element.removeAttribute('data-capacity-remaining');
        }
        if (capacity.waitlistTotal !== null && capacity.waitlistTotal !== undefined) {
            element.setAttribute('data-capacity-waitlist', String(capacity.waitlistTotal));
        } else {
            element.removeAttribute('data-capacity-waitlist');
        }
        if (capacity.waitlistRemaining !== null && capacity.waitlistRemaining !== undefined) {
            element.setAttribute('data-capacity-waitlist-remaining', String(capacity.waitlistRemaining));
        } else {
            element.removeAttribute('data-capacity-waitlist-remaining');
        }
        var paymentFlag = config.paymentRequired !== undefined ? config.paymentRequired : (config.config && config.config.paymentRequired);
        element.setAttribute('data-payment-required', paymentFlag ? '1' : '0');
        var priceLabelSource = config.priceLabel !== undefined ? config.priceLabel : (config.config && config.config.priceLabel);
        if (priceLabelSource) {
            element.setAttribute('data-price-label', sanitizeString(priceLabelSource));
        } else {
            element.removeAttribute('data-price-label');
        }
        var priceAmountSource = config.priceAmount !== undefined ? config.priceAmount : (config.config && config.config.priceAmount);
        if (priceAmountSource !== undefined && priceAmountSource !== null && priceAmountSource !== '') {
            element.setAttribute('data-price-amount', String(priceAmountSource));
        } else {
            element.removeAttribute('data-price-amount');
        }
        var priceCurrencySource = config.priceCurrency !== undefined ? config.priceCurrency : (config.config && config.config.priceCurrency);
        if (priceCurrencySource) {
            element.setAttribute('data-price-currency', sanitizeString(priceCurrencySource));
        } else {
            element.removeAttribute('data-price-currency');
        }
        var validationFlag = config.requiresValidation !== undefined ? config.requiresValidation : (config.config && config.config.requiresValidation);
        element.setAttribute('data-requires-validation', validationFlag ? '1' : '0');
    }

    function buildConfig(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }
        var clone = {};
        var keys = Object.keys(raw);
        for (var index = 0; index < keys.length; index += 1) {
            var key = keys[index];
            clone[key] = raw[key];
        }
        clone.allowGuardianRegistration = !!raw.allowGuardianRegistration;
        var capacitySource = raw.capacity !== undefined && raw.capacity !== null ? raw.capacity : raw.capacity_state;
        clone.capacity = capacitySource ? normalizeCapacity(capacitySource) : null;
        clone.participants = normalizeParticipants(raw.participants || raw.participantsList || raw.participants_source || []);
        var priceAmount = null;
        if (raw.priceAmount !== undefined && raw.priceAmount !== null) {
            var priceCandidate = typeof raw.priceAmount === 'number' ? raw.priceAmount : parseFloat(raw.priceAmount);
            if (!isNaN(priceCandidate)) {
                priceAmount = priceCandidate;
            }
        } else if (raw.price !== undefined && raw.price !== null) {
            var fallbackPriceCandidate = typeof raw.price === 'number' ? raw.price : parseFloat(raw.price);
            if (!isNaN(fallbackPriceCandidate)) {
                priceAmount = fallbackPriceCandidate;
            }
        }
        clone.priceAmount = priceAmount;
        clone.priceLabel = sanitizeString(raw.priceLabel || raw.price_label || '');
        clone.priceCurrency = sanitizeString(raw.priceCurrency || raw.price_currency || '');
        clone.eventTitle = sanitizeString(raw.eventTitle || raw.event_title || '');
        clone.paymentRequired = raw.paymentRequired !== undefined
            ? !!raw.paymentRequired
            : (priceAmount !== null ? priceAmount > 0 : false);
        clone.requiresValidation = raw.requiresValidation !== undefined ? !!raw.requiresValidation : false;
        clone.scheduleMode = sanitizeString(raw.scheduleMode || raw.schedule_mode || '');
        clone.occurrenceSummary = sanitizeString(raw.occurrenceSummary || raw.occurrence_summary || raw.occurrenceSummaryText || '');
        var occurrenceSelectionMode = sanitizeString(raw.occurrenceSelectionMode || raw.occurrence_selection_mode || '');
        clone.occurrenceSelectionMode = occurrenceSelectionMode;
        var allowOccurrenceSelection = raw.allowOccurrenceSelection !== undefined
            ? !!raw.allowOccurrenceSelection
            : (occurrenceSelectionMode !== '' ? occurrenceSelectionMode !== 'all_occurrences' : false);
        clone.allowOccurrenceSelection = allowOccurrenceSelection;
        clone.occurrences = normalizeOccurrences(raw.occurrences || raw.occurrenceList || raw.occurrence_options || []);
        clone.assignments = normalizeAssignments(raw.assignments || raw.occurrenceAssignments || raw.occurrence_assignments || null);
        return clone;
    }

    function readConfig() {
        if (typeof document === 'undefined') {
            return null;
        }
        var form = document.querySelector('[data-mj-event-registration]');
        var parsed = null;
        if (form) {
            parsed = parseDataAttribute(form, 'data-registration-config');
        }
        if (!parsed) {
            var trigger = document.querySelector('[data-registration]');
            if (trigger) {
                parsed = parseDataAttribute(trigger, 'data-registration');
            }
        }
        if (!parsed) {
            return null;
        }
        return buildConfig(parsed);
    }

    var namespace = globalObject.MjMemberEventSingleApp || (globalObject.MjMemberEventSingleApp = {});
    if (namespace.bootstrapped) {
        return;
    }

    function dispatchConfigEvent(detail) {
        if (typeof document === 'undefined') {
            return;
        }
        var eventName = 'mj-member:event-single-config';
        if (typeof globalObject.CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent(eventName, { detail: detail }));
            return;
        }
        if (typeof document.createEvent === 'function') {
            var legacyEvent = document.createEvent('CustomEvent');
            legacyEvent.initCustomEvent(eventName, false, false, detail);
            document.dispatchEvent(legacyEvent);
        }
    }

    var preact = globalObject.preact;
    var hooks = globalObject.preactHooks;

    if (!preact || !hooks) {
        domReady(function () {
            var fallbackConfig = readConfig();
            if (fallbackConfig) {
                var fallbackForm = document.querySelector('[data-mj-event-registration]');
                namespace.form = fallbackForm;
                applyDataset(fallbackForm, fallbackConfig);
                namespace.config = fallbackConfig;
                namespace.allowGuardianRegistration = fallbackConfig.allowGuardianRegistration;
                namespace.capacity = fallbackConfig.capacity;
                namespace.occurrences = fallbackConfig.occurrences || [];
                namespace.allowOccurrenceSelection = !!fallbackConfig.allowOccurrenceSelection;
                namespace.participants = fallbackConfig.participants;
                dispatchConfigEvent({
                    config: fallbackConfig,
                    allowGuardianRegistration: fallbackConfig.allowGuardianRegistration,
                    capacity: fallbackConfig.capacity,
                });
            }
        });
        return;
    }

    var h = preact.h;
    var render = preact.render;
    var createContext = preact.createContext;
    var useCallback = hooks.useCallback;
    var useContext = hooks.useContext;
    var useEffect = hooks.useEffect;
    var useMemo = hooks.useMemo;
    var useReducer = hooks.useReducer;
    var useRef = hooks.useRef;
    var useState = hooks.useState;

    var ConfigContext = createContext({
        config: null,
        allowGuardianRegistration: false,
        capacity: null,
    });

    namespace.ConfigContext = ConfigContext;
    namespace.useEventConfig = function () {
        return useContext(ConfigContext);
    };

    var lastSnapshot = null;

    namespace.getConfigSnapshot = function () {
        return lastSnapshot;
    };

    function StateBridge(props) {
        var value = props && props.value ? props.value : null;
        useEffect(function () {
            if (!value) {
                return;
            }
            lastSnapshot = value;
            namespace.config = value.config;
            namespace.allowGuardianRegistration = value.allowGuardianRegistration;
            namespace.capacity = value.capacity;
            namespace.occurrences = value.config ? value.config.occurrences : [];
            namespace.allowOccurrenceSelection = value.config ? !!value.config.allowOccurrenceSelection : false;
            namespace.participants = value.config ? value.config.participants : [];
            namespace.priceAmount = value.config ? value.config.priceAmount : null;
            namespace.priceLabel = value.config ? value.config.priceLabel : '';
            namespace.priceCurrency = value.config ? value.config.priceCurrency : '';
            namespace.paymentRequired = value.config ? !!value.config.paymentRequired : false;
            namespace.requiresValidation = value.config ? !!value.config.requiresValidation : false;
            namespace.lastUpdate = Date.now();
            if (registrationForm) {
                applyDataset(registrationForm, value);
            }
            if (!registrationForm && namespace.form) {
                registrationForm = namespace.form;
                if (registrationForm) {
                    applyDataset(registrationForm, value);
                }
            }
            dispatchConfigEvent(value);
        }, [value]);
        return null;
    }

    function formatPlaces(count) {
        if (count === null || count === undefined) {
            return '';
        }
        var absolute = Math.max(0, parseInt(count, 10) || 0);
        return absolute === 1 ? '1 place' : absolute + ' places';
    }

    function formatPriceAmount(amount, currency) {
        if (amount === null || amount === undefined) {
            return '';
        }
        var numeric = Number(amount);
        if (!isFinite(numeric)) {
            return '';
        }
        try {
            var formatter = new Intl.NumberFormat(getPreferredLocale(), {
                style: 'currency',
                currency: currency && currency.length ? currency.toUpperCase() : 'EUR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            return formatter.format(numeric);
        } catch (error) {
            var fixed = numeric.toFixed(2);
            return fixed.replace('.', ',') + ' ' + (currency || 'EUR');
        }
    }

    function CapacityOverview(props) {
        var capacity = props && props.capacity ? props.capacity : null;
        if (!capacity) {
            return null;
        }

        var highlight = '';
        if (capacity.capacityTotal !== null && capacity.capacityTotal !== undefined) {
            if (capacity.isFull) {
                if (capacity.hasWaitlist && !capacity.waitlistIsFull) {
                    highlight = 'Complet : inscris-toi sur la liste d attente, des places peuvent se liberer.';
                } else if (capacity.hasWaitlist && capacity.waitlistIsFull) {
                    highlight = 'Complet : liste d attente fermee.';
                } else {
                    highlight = 'Complet : plus aucune place disponible.';
                }
            } else if (capacity.remaining !== null && capacity.remaining !== undefined) {
                highlight = 'Encore ' + formatPlaces(capacity.remaining) + ' disponibles.';
            } else {
                highlight = 'Capacite limitee pour cet evenement.';
            }
        } else if (capacity.isFull) {
            highlight = 'Complet : toutes les reservations ont ete attribuees.';
        } else if (capacity.activeCount > 0) {
            highlight = formatPlaces(capacity.activeCount) + ' deja confirmees.';
        }
        if (highlight === '') {
            highlight = 'Gestion des capacites en cours.';
        }

        var details = [];
        if (capacity.capacityTotal !== null && capacity.capacityTotal !== undefined) {
            details.push('Capacite totale : ' + formatPlaces(capacity.capacityTotal) + '.');
        }
        if (capacity.activeCount !== null && capacity.activeCount !== undefined) {
            details.push('Inscriptions confirmees : ' + formatPlaces(capacity.activeCount) + '.');
        }
        if (capacity.remaining !== null && capacity.remaining !== undefined && !capacity.isFull) {
            details.push('Places restantes : ' + formatPlaces(capacity.remaining) + '.');
        }
        if (capacity.hasWaitlist) {
            if (capacity.waitlistRemaining !== null && capacity.waitlistRemaining !== undefined && !capacity.waitlistIsFull) {
                details.push('Liste d\'attente : ' + formatPlaces(capacity.waitlistRemaining) + ' disponibles.');
            } else if (capacity.waitlistIsFull) {
                details.push('Liste d\'attente complete.');
            } else {
                details.push('Liste d\'attente ouverte.');
            }
        }

        return h('div', {
            className: 'mj-member-event-single__registration-enhancement',
            'data-role': 'capacity',
            role: 'status',
            'aria-live': 'polite'
        },
            h('p', { className: 'mj-member-event-single__registration-note mj-member-event-single__registration-note--highlight' }, highlight),
            details.length ? h('ul', { className: 'mj-member-event-single__registration-meta' }, details.map(function (entry, index) {
                return h('li', { key: 'capacity-' + index }, entry);
            })) : null
        );
    }

    function GuardianNotice(props) {
        var allow = !!(props && props.allowGuardianRegistration);
        var message = allow
            ? 'Tu peux inscrire un jeune dont tu es responsable dans la liste ci-dessous.'
            : 'Seul le participant peut confirmer sa participation pour cet evenement.';
        return h('p', {
            className: 'mj-member-event-single__registration-note',
            'data-role': 'guardian'
        }, message);
    }

    function PaymentNotice(props) {
        if (!props) {
            return null;
        }
        var paymentRequired = !!props.paymentRequired;
        var priceLabel = sanitizeString(props.priceLabel || '');
        var priceAmount = props.priceAmount !== undefined ? props.priceAmount : null;
        var priceCurrency = sanitizeString(props.priceCurrency || '');
        if (!priceLabel && priceAmount !== null) {
            priceLabel = 'Participation : ' + formatPriceAmount(priceAmount, priceCurrency);
        }
        if (!paymentRequired && priceLabel === '') {
            return null;
        }
        var highlight = paymentRequired
            ? 'Paiement requis apres la confirmation de ton inscription.'
            : 'Cet evenement est gratuit pour les participants.';
        return h('div', {
            className: 'mj-member-event-single__registration-enhancement',
            'data-role': 'payment',
            role: 'status',
            'aria-live': 'polite'
        },
            h('p', { className: 'mj-member-event-single__registration-note mj-member-event-single__registration-note--highlight' }, highlight),
            priceLabel ? h('p', { className: 'mj-member-event-single__registration-note' }, priceLabel) : null
        );
    }

    function ValidationNotice(props) {
        if (!props || !props.requiresValidation) {
            return null;
        }
        var detail = sanitizeString(props.detail || 'Tu recevras une confirmation des que l equipe aura valide ta demande.');
        return h('div', {
            className: 'mj-member-event-single__registration-enhancement',
            'data-role': 'validation',
            role: 'status',
            'aria-live': 'polite'
        },
            h('p', { className: 'mj-member-event-single__registration-note mj-member-event-single__registration-note--highlight' }, 'Validation necessaire par l equipe.'),
            detail ? h('p', { className: 'mj-member-event-single__registration-note' }, detail) : null
        );
    }

    function PaymentSummaryModal() {
        var registration = useContext(RegistrationContext);
        var snapshot = useContext(ConfigContext);
        var closeButtonRef = useRef(null);
        var identifiers = useMemo(function () {
            var base = Math.random().toString(36).slice(2);
            return {
                title: 'mj-member-event-payment-title-' + base,
                description: 'mj-member-event-payment-description-' + base,
            };
        }, []);

        var hasSummary = registration && registration.paymentSummaryVisible && registration.paymentSummary;
        if (!hasSummary) {
            return null;
        }

        var summary = registration.paymentSummary;
        var eventTitleFallback = snapshot && snapshot.config && snapshot.config.eventTitle ? sanitizeString(snapshot.config.eventTitle) : '';
        var eventTitle = sanitizeString(summary.eventTitle || eventTitleFallback || '');
        var message = summary.message && summary.message !== ''
            ? sanitizeString(summary.message)
            : getString('paymentSummaryMessage', 'Tu peux effectuer le paiement maintenant ou plus tard depuis ton espace membre ou en main propre aupres d\'un animateur.');
        var amountHeading = getString('paymentSummaryAmountLabel', 'Montant a regler');
        var payNowLabel = getString('paymentSummaryPayNow', 'Payer en ligne');
        var closeLabel = getString('paymentSummaryClose', 'Fermer');
        var fallbackLabel = getString('paymentFallback', "Si la page de paiement ne s'ouvre pas, copie ce lien :");
        var checkoutUrl = sanitizeString(summary.checkoutUrl || '');
        var amountLabel = summary.amountLabel && summary.amountLabel !== ''
            ? sanitizeString(summary.amountLabel)
            : '';
        if (!amountLabel && summary.priceAmount !== null && summary.priceAmount !== undefined) {
            amountLabel = formatPriceAmount(summary.priceAmount, summary.priceCurrency || '');
        }

        var handleClose = useCallback(function () {
            if (registration && typeof registration.hidePaymentSummary === 'function') {
                registration.hidePaymentSummary();
            }
        }, [registration]);

        var handleOverlayClick = useCallback(function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            handleClose();
        }, [handleClose]);

        var handlePanelClick = useCallback(function (event) {
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
        }, []);

        useEffect(function () {
            if (typeof document === 'undefined') {
                return;
            }
            var previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            return function () {
                document.body.style.overflow = previousOverflow;
            };
        }, []);

        useEffect(function () {
            if (closeButtonRef.current && typeof closeButtonRef.current.focus === 'function') {
                closeButtonRef.current.focus();
            }
        }, []);

        useEffect(function () {
            if (typeof document === 'undefined') {
                return;
            }
            var handleKey = function (event) {
                if (event && (event.key === 'Escape' || event.key === 'Esc')) {
                    event.preventDefault();
                    handleClose();
                }
            };
            document.addEventListener('keydown', handleKey, true);
            return function () {
                document.removeEventListener('keydown', handleKey, true);
            };
        }, [handleClose]);

        return h('div', {
            className: 'mj-member-event-single__payment-modal',
            role: 'dialog',
            'aria-modal': 'true',
            'aria-labelledby': identifiers.title,
            'aria-describedby': identifiers.description,
            onClick: handleOverlayClick
        },
            h('div', { className: 'mj-member-event-single__payment-modal-overlay' }),
            h('div', {
                className: 'mj-member-event-single__payment-modal-panel',
                role: 'document',
                onClick: handlePanelClick
            },
                h('header', { className: 'mj-member-event-single__payment-modal-header' }, [
                    h('h3', { className: 'mj-member-event-single__payment-modal-title', id: identifiers.title }, getString('paymentSummaryTitle', 'Recapitulatif du paiement')),
                    eventTitle ? h('p', { className: 'mj-member-event-single__payment-modal-event' }, eventTitle) : null
                ]),
                h('div', { className: 'mj-member-event-single__payment-modal-body' }, [
                    h('p', { className: 'mj-member-event-single__payment-modal-message', id: identifiers.description }, message),
                    amountLabel ? h('div', { className: 'mj-member-event-single__payment-modal-amount' }, [
                        h('span', { className: 'mj-member-event-single__payment-modal-amount-label' }, amountHeading),
                        h('span', { className: 'mj-member-event-single__payment-modal-amount-value' }, amountLabel)
                    ]) : null,
                    h('div', { className: 'mj-member-event-single__payment-modal-actions' }, [
                        checkoutUrl ? h('a', {
                            key: 'pay-now',
                            className: 'mj-member-event-single__payment-modal-link',
                            href: checkoutUrl,
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }, payNowLabel) : null,
                        h('button', {
                            key: 'close-modal',
                            type: 'button',
                            className: 'mj-member-event-single__payment-modal-close',
                            onClick: handleClose,
                            ref: closeButtonRef
                        }, closeLabel)
                    ]),
                    checkoutUrl ? h('p', { className: 'mj-member-event-single__payment-modal-fallback' }, [
                        fallbackLabel,
                        ' ',
                        h('a', {
                            href: checkoutUrl,
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }, checkoutUrl)
                    ]) : null
                ])
            )
        );
    }

    function Enhancements(props) {
        var contextSnapshot = useContext(ConfigContext);
        var snapshot = props && props.snapshot ? props.snapshot : contextSnapshot;
        if (!snapshot) {
            return null;
        }

        var scope = props && props.scope ? String(props.scope) : 'registration';
        var config = snapshot.config || null;
        var priceLabel = config ? sanitizeString(config.priceLabel || '') : '';
        var paymentRequired = config && config.paymentRequired !== undefined
            ? !!config.paymentRequired
            : (config && config.priceAmount !== undefined && config.priceAmount !== null ? Number(config.priceAmount) > 0 : false);
        var requiresValidation = config ? !!config.requiresValidation : false;
        var validationDetail = config ? sanitizeString(config.validationMessage || config.validationNotice || '') : '';
        var nodes = [];

        if (scope === 'sidebar') {
            if (paymentRequired || priceLabel) {
                nodes.push(h(PaymentNotice, {
                    priceLabel: priceLabel,
                    paymentRequired: paymentRequired,
                    priceAmount: config ? config.priceAmount : null,
                    priceCurrency: config ? config.priceCurrency : '',
                    key: 'payment'
                }));
            }
            if (snapshot.capacity) {
                nodes.push(h(CapacityOverview, { capacity: snapshot.capacity, key: 'capacity' }));
            }
            if (requiresValidation) {
                nodes.push(h(ValidationNotice, {
                    requiresValidation: requiresValidation,
                    detail: validationDetail,
                    key: 'validation'
                }));
            }
        } else {
            nodes.push(h(GuardianNotice, {
                allowGuardianRegistration: snapshot.allowGuardianRegistration,
                key: 'guardian'
            }));
        }

        if (!nodes.length) {
            return null;
        }

        var className = 'mj-member-event-single__registration-enhancements';
        if (scope === 'sidebar') {
            className += ' mj-member-event-single__registration-enhancements--sidebar';
        }

        return h('div', { className: className },
            h('div', { className: 'mj-member-event-single__registration-enhancements-body' }, nodes)
        );
    }

    function SidebarEnhancementsRenderer() {
        var snapshot = useContext(ConfigContext);

        useEffect(function () {
            if (typeof document === 'undefined') {
                return;
            }
            var container = document.querySelector('[data-mj-event-sidebar-enhancements]');
            if (!container) {
                return;
            }
            if (!snapshot) {
                render(null, container);
                return function () {
                    render(null, container);
                };
            }
            render(h(Enhancements, { scope: 'sidebar', snapshot: snapshot }), container);
            return function () {
                render(null, container);
            };
        }, [snapshot]);

        return null;
    }

    var RegistrationContext = createContext(null);
    namespace.RegistrationContext = RegistrationContext;

    function registrationReducer(state, action) {
        switch (action.type) {
            case 'set':
                if (state.selectedId === action.payload) {
                    return state;
                }
                return { selectedId: action.payload };
            default:
                return state;
        }
    }

    function occurrenceReducer(state, action) {
        var current = state && Array.isArray(state.selected) ? state.selected.slice() : [];
        switch (action.type) {
            case 'toggle': {
                var value = normalizeOccurrenceValue(action.payload);
                if (!value) {
                    return state;
                }
                var index = current.indexOf(value);
                if (index >= 0) {
                    current.splice(index, 1);
                } else {
                    current.push(value);
                }
                return { selected: current };
            }
            case 'set':
            case 'reset':
            case 'external': {
                var sanitized = sanitizeOccurrenceValues(action.payload);
                if (arraysEqual(sanitized, current)) {
                    return state;
                }
                return { selected: sanitized };
            }
            default:
                return state;
        }
    }

    function ensureHiddenParticipant(form) {
        if (!form) {
            return null;
        }
        if (hiddenParticipantField && form.contains(hiddenParticipantField)) {
            return hiddenParticipantField;
        }
        var existing = form.querySelector('input[type="hidden"][data-event-participant-selected]');
        if (existing) {
            hiddenParticipantField = existing;
            return hiddenParticipantField;
        }
        var field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'participant_selected';
        field.setAttribute('data-event-participant-selected', '1');
        form.appendChild(field);
        hiddenParticipantField = field;
        return hiddenParticipantField;
    }
    

    function ensureNoteField(form) {
        if (!form) {
            return null;
        }
        if (noteField && form.contains(noteField)) {
            return noteField;
        }
        var existing = form.querySelector('textarea[name="note"]');
        if (existing) {
            noteField = existing;
            return noteField;
        }
        var field = document.createElement('textarea');
        field.name = 'note';
        field.hidden = true;
        form.appendChild(field);
        noteField = field;
        return noteField;
    }

    function ensureOccurrencesField(form) {
        if (!form) {
            return null;
        }
        if (occurrencesField && form.contains(occurrencesField)) {
            return occurrencesField;
        }
        var existing = form.querySelector('input[type="hidden"][data-event-occurrences-selected]');
        if (existing) {
            occurrencesField = existing;
            return occurrencesField;
        }
        var field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'occurrences_json';
        field.value = '[]';
        field.setAttribute('data-event-occurrences-selected', '1');
        form.appendChild(field);
        occurrencesField = field;
        return occurrencesField;
    }

    function syncNativeParticipant(form, participantId) {
        if (!form) {
            return;
        }
        var inputs = form.querySelectorAll('input[name="participant"]');
        for (var idx = 0; idx < inputs.length; idx += 1) {
            var input = inputs[idx];
            if (!input) {
                continue;
            }
            var matches = participantId !== null && participantId !== undefined && String(participantId) === input.value;
            input.checked = matches;
        }
        if (participantId !== null && participantId !== undefined) {
            form.setAttribute('data-selected-participant', String(participantId));
        } else {
            form.removeAttribute('data-selected-participant');
        }
    }

    function useRegistrationController(snapshot) {
        var config = snapshot && snapshot.config ? snapshot.config : null;
        var participants = config && Array.isArray(config.participants) ? config.participants : [];
        var occurrences = config && Array.isArray(config.occurrences) ? config.occurrences : [];
        var assignments = config && config.assignments ? config.assignments : null;
        var allowOccurrenceSelection = config ? !!config.allowOccurrenceSelection : false;
        var scheduleMode = config && config.scheduleMode ? String(config.scheduleMode) : '';
        var occurrenceSummary = config && config.occurrenceSummary ? String(config.occurrenceSummary) : '';
        var priceAmount = null;
        if (config && config.priceAmount !== undefined && config.priceAmount !== null) {
            var amountCandidate = Number(config.priceAmount);
            if (!isNaN(amountCandidate)) {
                priceAmount = amountCandidate;
            }
        }
        var priceLabel = config ? sanitizeString(config.priceLabel || '') : '';
        var priceCurrency = config ? sanitizeString(config.priceCurrency || '') : '';
        var eventTitle = config ? sanitizeString(config.eventTitle || '') : '';
        var paymentRequired = config && config.paymentRequired !== undefined
            ? !!config.paymentRequired
            : (priceAmount !== null ? priceAmount > 0 : false);
        var requiresValidation = config ? !!config.requiresValidation : false;
        var noteMaxLength = config && config.noteMaxLength !== undefined ? toInt(config.noteMaxLength, 400) : 400;
        if (noteMaxLength === null) {
            noteMaxLength = 0;
        }
        var hasOccurrences = occurrences && occurrences.length > 0;
        var requiresOccurrenceSelection = allowOccurrenceSelection && hasOccurrences;
        var refreshState = useState(0);
        var refreshCounter = refreshState[0];
        var setRefreshCounter = refreshState[1];
        var refresh = useCallback(function () {
            setRefreshCounter(function (value) {
                return value + 1;
            });
        }, []);

        var participantsSignature = useMemo(function () {
            if (!participants || !participants.length) {
                return 'empty';
            }
            var parts = [];
            for (var idx = 0; idx < participants.length; idx += 1) {
                var participant = participants[idx];
                parts.push(participant.id + ':' + (participant.selectable ? '1' : '0') + ':' + (participant.eligible ? '1' : '0'));
            }
            return parts.join('|');
        }, [participants, refreshCounter]);

        var defaultParticipant = useMemo(function () {
            return findDefaultParticipant(participants);
        }, [participantsSignature, refreshCounter]);

        var initialState = useMemo(function () {
            return {
                selectedId: defaultParticipant ? defaultParticipant.id : null,
            };
        }, [defaultParticipant && defaultParticipant.id]);

        var _a = useReducer(registrationReducer, initialState), state = _a[0], dispatch = _a[1];
        var noteInitializedRef = useRef(false);
        var noteState = useState('');
        var note = noteState[0];
        var setNote = noteState[1];
        var paymentSummaryState = useState(null);
        var paymentSummary = paymentSummaryState[0];
        var setPaymentSummary = paymentSummaryState[1];
        var paymentModalState = useState(false);
        var paymentSummaryVisible = paymentModalState[0];
        var setPaymentSummaryVisible = paymentModalState[1];

        var occurrencesSignature = useMemo(function () {
            if (!occurrences || !occurrences.length) {
                return 'empty';
            }
            var footprint = [];
            for (var idx = 0; idx < occurrences.length; idx += 1) {
                var occurrence = occurrences[idx];
                if (!occurrence) {
                    continue;
                }
                var stamp = (occurrence.value || '') + ':' + (occurrence.isPast ? 'past' : 'future');
                footprint.push(stamp);
            }
            return footprint.join('|');
        }, [occurrences]);

        var assignmentsSignature = useMemo(function () {
            if (!assignments) {
                return 'none';
            }
            var mode = assignments.mode || assignments.selectionMode || assignments.selection_mode || '';
            var values = Array.isArray(assignments.occurrences) ? assignments.occurrences : (Array.isArray(assignments.values) ? assignments.values : []);
            return String(mode) + ':' + sanitizeOccurrenceValues(values).join('|');
        }, [assignments]);

        var defaultOccurrences = useMemo(function () {
            if (!assignments) {
                return [];
            }
            if ((assignments.mode || assignments.selectionMode || assignments.selection_mode) === 'custom') {
                var values = Array.isArray(assignments.occurrences) ? assignments.occurrences : (Array.isArray(assignments.values) ? assignments.values : []);
                return sanitizeOccurrenceValues(values);
            }
            return [];
        }, [assignmentsSignature]);

        var initialOccurrenceState = useMemo(function () {
            return {
                selected: sanitizeOccurrenceValues(defaultOccurrences),
            };
        }, [assignmentsSignature, occurrencesSignature]);

        var _b = useReducer(occurrenceReducer, initialOccurrenceState), occurrenceState = _b[0], occurrenceDispatch = _b[1];
        var selectedOccurrences = occurrenceState && Array.isArray(occurrenceState.selected) ? occurrenceState.selected : [];
        var selectedOccurrencesSignature = useMemo(function () {
            if (!selectedOccurrences.length) {
                return 'none';
            }
            return selectedOccurrences.join('|');
        }, [selectedOccurrences]);

        useEffect(function () {
            if (!noteInitializedRef.current) {
                if (!registrationForm && namespace.form) {
                    registrationForm = namespace.form;
                }
                var form = registrationForm;
                if (!form) {
                    return;
                }
                var field = ensureNoteField(form);
                if (!field) {
                    return;
                }
                noteInitializedRef.current = true;
                var initialValue = field.value || '';
                setNote(initialValue);
            }
        }, [config]);

        useEffect(function () {
            if (!participants.length) {
                if (state.selectedId !== null) {
                    dispatch({ type: 'set', payload: null });
                }
                return;
            }
            var current = findParticipantById(participants, state.selectedId);
            if (current && current.selectable) {
                return;
            }
            var fallback = defaultParticipant;
            var fallbackId = fallback ? fallback.id : null;
            if (fallbackId !== state.selectedId) {
                dispatch({ type: 'set', payload: fallbackId });
            }
        }, [participantsSignature, defaultParticipant && defaultParticipant.id, state.selectedId]);

        useEffect(function () {
            if (!registrationForm && namespace.form) {
                registrationForm = namespace.form;
            }
            var form = registrationForm;
            if (!form) {
                return;
            }
            var hiddenField = ensureHiddenParticipant(form);
            if (hiddenField) {
                hiddenField.value = state.selectedId !== null && state.selectedId !== undefined
                    ? String(state.selectedId)
                    : '';
            }
            syncNativeParticipant(form, state.selectedId);
            if (typeof form.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
                try {
                    form.dispatchEvent(new CustomEvent('mj-member:event-single-participant', {
                        detail: {
                            participantId: state.selectedId,
                        },
                    }));
                } catch (error) {
                    // No-op fallback.
                }
            }
        }, [state.selectedId, participantsSignature]);

        useEffect(function () {
            if (!registrationForm && namespace.form) {
                registrationForm = namespace.form;
            }
            var form = registrationForm;
            if (!form) {
                return;
            }
            var field = ensureNoteField(form);
            if (!field) {
                return;
            }
            var nextValue = note || '';
            if (noteMaxLength > 0 && nextValue.length > noteMaxLength) {
                nextValue = nextValue.slice(0, noteMaxLength);
            }
            if (field.value !== nextValue) {
                field.value = nextValue;
            }
        }, [note, noteMaxLength]);

            useEffect(function () {
                if (!registrationForm && namespace.form) {
                    registrationForm = namespace.form;
                }
                var form = registrationForm;
                if (!form) {
                    return;
                }
                ensureOccurrencesField(form);
                var handler = function (event) {
                    if (!event || !event.detail) {
                        return;
                    }
                    var source = event.detail.source || '';
                    if (source === 'preact') {
                        return;
                    }
                    var values = sanitizeOccurrenceValues(event.detail.occurrences);
                    occurrenceDispatch({ type: 'external', payload: values });
                };
                form.addEventListener('mj-member:event-single-occurrences-sync', handler);
                return function () {
                    form.removeEventListener('mj-member:event-single-occurrences-sync', handler);
                };
            }, [occurrenceDispatch]);

            useEffect(function () {
                var normalizedDefaults = sanitizeOccurrenceValues(defaultOccurrences);
                if (!arraysEqual(normalizedDefaults, selectedOccurrences)) {
                    occurrenceDispatch({ type: 'reset', payload: normalizedDefaults });
                }
            }, [assignmentsSignature, occurrencesSignature]);

            useEffect(function () {
                if (!registrationForm && namespace.form) {
                    registrationForm = namespace.form;
                }
                var form = registrationForm;
                if (!form) {
                    return;
                }
                var field = ensureOccurrencesField(form);
                if (!field) {
                    return;
                }
                var payload = sanitizeOccurrenceValues(selectedOccurrences);
                try {
                    field.value = JSON.stringify(payload);
                } catch (error) {
                    field.value = '[]';
                }
                if (typeof form.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
                    try {
                        form.dispatchEvent(new CustomEvent('mj-member:event-single-occurrences', {
                            detail: {
                                origin: 'preact',
                                occurrences: payload,
                            },
                        }));
                    } catch (error) {
                        // ignore
                    }
                }
            }, [selectedOccurrencesSignature, requiresOccurrenceSelection]);

        var toggleOccurrence = useCallback(function (value) {
            if (!allowOccurrenceSelection || !occurrences || !occurrences.length) {
                return;
            }
            var normalized = normalizeOccurrenceValue(value);
            if (!normalized) {
                return;
            }
            var candidate = null;
            for (var idx = 0; idx < occurrences.length; idx += 1) {
                var occurrence = occurrences[idx];
                if (occurrence && occurrence.value === normalized) {
                    candidate = occurrence;
                    break;
                }
            }
            if (!candidate || !candidate.selectable) {
                return;
            }
            occurrenceDispatch({ type: 'toggle', payload: normalized });
        }, [occurrencesSignature, allowOccurrenceSelection]);

        var selectParticipant = useCallback(function (id) {
            if (!participants || !participants.length) {
                return;
            }
            var candidate = findParticipantById(participants, id);
            if (!candidate || !candidate.selectable) {
                return;
            }
            dispatch({ type: 'set', payload: candidate.id });
        }, [participantsSignature]);

        var selectedParticipant = useMemo(function () {
            return findParticipantById(participants, state.selectedId);
        }, [participantsSignature, state.selectedId]);

        var showPaymentSummary = useCallback(function (payload) {
            if (!payload) {
                setPaymentSummary(null);
                setPaymentSummaryVisible(false);
                return false;
            }
            var summaryEventTitle = sanitizeString(payload.eventTitle || eventTitle || '');
            var summaryCurrency = sanitizeString(payload.priceCurrency || priceCurrency || '');
            var summaryPriceLabel = sanitizeString(payload.priceLabel || '');
            var summaryPriceAmount = payload.priceAmount !== undefined && payload.priceAmount !== null
                ? Number(payload.priceAmount)
                : (priceAmount !== null ? priceAmount : null);
            var summaryAmount = sanitizeString(payload.amountLabel || payload.amount || '');
            if (!summaryAmount) {
                if (summaryPriceLabel) {
                    summaryAmount = summaryPriceLabel;
                } else if (summaryPriceAmount !== null && !isNaN(summaryPriceAmount)) {
                    summaryAmount = formatPriceAmount(summaryPriceAmount, summaryCurrency);
                }
            }
            var summaryMessage = sanitizeString(payload.message || '');
            var checkoutUrl = sanitizeString(payload.checkoutUrl || (payload.payment && payload.payment.checkout_url) || '');
            var summary = {
                eventTitle: summaryEventTitle,
                amountLabel: summaryAmount,
                message: summaryMessage,
                checkoutUrl: checkoutUrl,
                priceCurrency: summaryCurrency,
                priceAmount: summaryPriceAmount,
                priceLabel: summaryPriceLabel,
                payment: payload.payment || null,
            };
            setPaymentSummary(summary);
            setPaymentSummaryVisible(true);
            return true;
        }, [eventTitle, priceAmount, priceCurrency]);

        var hidePaymentSummary = useCallback(function () {
            setPaymentSummaryVisible(false);
            setPaymentSummary(null);
        }, []);

        useEffect(function () {
            namespace.showPaymentSummary = showPaymentSummary;
            namespace.hidePaymentSummary = hidePaymentSummary;
            namespace.paymentSummary = paymentSummary;
            namespace.paymentSummaryVisible = paymentSummaryVisible;
            return function () {
                if (namespace.showPaymentSummary === showPaymentSummary) {
                    delete namespace.showPaymentSummary;
                }
                if (namespace.hidePaymentSummary === hidePaymentSummary) {
                    delete namespace.hidePaymentSummary;
                }
                if (namespace.paymentSummary === paymentSummary) {
                    delete namespace.paymentSummary;
                }
                if (namespace.paymentSummaryVisible === paymentSummaryVisible) {
                    delete namespace.paymentSummaryVisible;
                }
            };
        }, [showPaymentSummary, hidePaymentSummary, paymentSummary, paymentSummaryVisible]);

        return {
            participants: participants,
            selectedId: state.selectedId,
            selectedParticipant: selectedParticipant,
            selectParticipant: selectParticipant,
            occurrences: occurrences,
            occurrenceSignature: occurrencesSignature,
            allowOccurrenceSelection: allowOccurrenceSelection,
            requiresOccurrenceSelection: requiresOccurrenceSelection,
            hasOccurrences: hasOccurrences,
            occurrenceSummary: occurrenceSummary,
            scheduleMode: scheduleMode,
            selectedOccurrences: selectedOccurrences,
            selectedOccurrencesSignature: selectedOccurrencesSignature,
            toggleOccurrence: toggleOccurrence,
            allRegistered: config ? !!config.allRegistered : false,
            hasParticipants: config ? !!config.hasParticipants : false,
            hasAvailableParticipants: config ? !!config.hasAvailableParticipants : false,
            note: note,
            setNote: setNote,
            noteMaxLength: noteMaxLength,
            noteRemaining: noteMaxLength > 0 ? Math.max(noteMaxLength - (note ? note.length : 0), 0) : null,
            priceAmount: priceAmount,
            priceLabel: priceLabel,
            priceCurrency: priceCurrency,
            eventTitle: eventTitle,
            paymentRequired: paymentRequired,
            requiresValidation: requiresValidation,
            refresh: refresh,
            paymentSummary: paymentSummary,
            paymentSummaryVisible: paymentSummaryVisible,
            showPaymentSummary: showPaymentSummary,
            hidePaymentSummary: hidePaymentSummary,
        };
    }

    function ParticipantSelector() {
        var registration = useContext(RegistrationContext);
        var participants = registration ? registration.participants : [];
        var hasParticipants = registration ? registration.hasParticipants : false;
        var hasAvailableParticipants = registration ? registration.hasAvailableParticipants : false;
        var allRegistered = registration ? registration.allRegistered : false;
        var selectedParticipant = registration ? registration.selectedParticipant : null;

        if (!participants || participants.length === 0) {
            if (hasParticipants) {
                return h('p', { className: 'mj-member-event-single__registration-note' }, allRegistered
                    ? 'Tous les profils sont deja inscrits pour cet evenement.'
                    : 'Aucun profil eligible n\'est disponible pour cette inscription.');
            }
            return null;
        }

        if (participants.length === 1) {
            var participant = selectedParticipant || participants[0];
            var participantName = participant ? participant.name : '';
            var summaryLabel = participantName
                ? getString('registrationSummaryLabel', 'Inscription pour ') + participantName
                : getString('registrationSummaryFallback', 'Inscription en cours');
            var statusTextSingle = '';
            var statusClassSingle = '';
            if (participant) {
                statusTextSingle = participant.statusLabel || '';
                if (!participant.eligible && participant.eligibilityLabel) {
                    statusTextSingle = participant.eligibilityLabel;
                }
                statusClassSingle = participant.statusClass ? ' ' + participant.statusClass : '';
            }

            return h('div', { className: 'mj-member-event-single__registration-participants is-single' }, [
                h('p', { className: 'mj-member-event-single__registration-member-summary' }, summaryLabel),
                statusTextSingle ? h('span', {
                    className: 'mj-member-event-single__registration-member-status' + statusClassSingle,
                }, statusTextSingle) : null,
                participant && !participant.eligible && participant.ineligibleReasons && participant.ineligibleReasons.length
                    ? h('ul', { className: 'mj-member-event-single__registration-member-reasons' }, participant.ineligibleReasons.map(function (reason, reasonIndex) {
                        return h('li', { key: 'single-participant-reason-' + reasonIndex }, reason);
                    }))
                    : null,
                !hasAvailableParticipants
                    ? h('p', { className: 'mj-member-event-single__registration-note' }, allRegistered
                        ? 'Tous tes profils disponibles sont deja inscrits.'
                        : 'Aucune place n\'est disponible pour ces profils pour le moment.')
                    : null,
            ]);
        }

        return h('div', { className: 'mj-member-event-single__registration-participants' },
            h('h4', { className: 'mj-member-event-single__registration-heading' }, 'Choisis la personne a inscrire'),
            h('ul', { className: 'mj-member-event-single__registration-members' }, participants.map(function (participant) {
                var className = 'mj-member-event-single__registration-member';
                if (participant.isRegistered) {
                    className += ' is-registered';
                }
                if (!participant.eligible) {
                    className += ' is-ineligible';
                }
                if (registration.selectedId === participant.id) {
                    className += ' is-selected';
                }

                var statusText = participant.statusLabel;
                if (!participant.eligible && participant.eligibilityLabel) {
                    statusText = participant.eligibilityLabel;
                }

                return h('li', { key: 'participant-' + participant.id, className: className },
                    h('button', {
                        type: 'button',
                        className: 'mj-member-event-single__registration-member-button',
                        onClick: function () { return registration.selectParticipant(participant.id); },
                        disabled: !participant.selectable,
                        'aria-pressed': registration.selectedId === participant.id ? 'true' : 'false',
                    }, participant.name),
                    statusText ? h('span', {
                        className: 'mj-member-event-single__registration-member-status' + (participant.statusClass ? ' ' + participant.statusClass : ''),
                    }, statusText) : null,
                    !participant.eligible && participant.ineligibleReasons && participant.ineligibleReasons.length
                        ? h('ul', { className: 'mj-member-event-single__registration-member-reasons' }, participant.ineligibleReasons.map(function (reason, reasonIndex) {
                            return h('li', { key: participant.id + '-reason-' + reasonIndex }, reason);
                        }))
                        : null
                );
            })),
            !hasAvailableParticipants && participants.length > 0
                ? h('p', { className: 'mj-member-event-single__registration-note' }, allRegistered
                    ? 'Tous tes profils disponibles sont deja inscrits.'
                    : 'Aucune place n\'est disponible pour ces profils pour le moment.')
                : null
        );
    }

    function buildOccurrenceHint(scheduleMode, allowSelection) {
        var mode = (scheduleMode || '').toLowerCase();
        if (!allowSelection) {
            return 'Toutes les occurrences seront appliquees automatiquement.';
        }
        if (mode === 'series' || mode === 'recurring') {
            return 'Selectionne les seances qui t interessent.';
        }
        return 'Choisis les occurrences auxquelles tu participeras.';
    }

    var MOBILE_CALENDAR_MEDIA_QUERY = '(max-width: 720px)';

    function createCalendarMediaQueryList() {
        if (!globalObject || typeof globalObject.matchMedia !== 'function') {
            return null;
        }
        try {
            return globalObject.matchMedia(MOBILE_CALENDAR_MEDIA_QUERY);
        } catch (error) {
            return null;
        }
    }

    function evaluateCalendarMobileLayout() {
        var mediaList = createCalendarMediaQueryList();
        return mediaList ? !!mediaList.matches : false;
    }

    function useMobileCalendarLayout() {
        var _a = useState(function () {
            return evaluateCalendarMobileLayout();
        }), isMobile = _a[0], setIsMobile = _a[1];

        useEffect(function () {
            var mediaList = createCalendarMediaQueryList();
            if (!mediaList) {
                return;
            }

            var handleChange = function (event) {
                if (event && typeof event.matches === 'boolean') {
                    setIsMobile(event.matches);
                } else {
                    setIsMobile(mediaList.matches);
                }
            };

            handleChange(mediaList);

            if (typeof mediaList.addEventListener === 'function') {
                mediaList.addEventListener('change', handleChange);
                return function () {
                    mediaList.removeEventListener('change', handleChange);
                };
            }
            if (typeof mediaList.addListener === 'function') {
                mediaList.addListener(handleChange);
                return function () {
                    mediaList.removeListener(handleChange);
                };
            }
        }, []);

        return isMobile;
    }

    function OccurrenceCalendar(props) {
        var monthDate = props && props.monthDate ? props.monthDate : null;
        if (!monthDate) {
            return null;
        }

        var occurrenceSignature = props && props.occurrenceSignature ? props.occurrenceSignature : '';
        var selectedSignature = props && props.selectedSignature ? props.selectedSignature : '';
        var occurrencesByDate = props && props.occurrencesByDate ? props.occurrencesByDate : {};
        var selectedSet = props && props.selectedSet ? props.selectedSet : Object.create(null);
        var canGoPrev = !!(props && props.canGoPrev);
        var canGoNext = !!(props && props.canGoNext);
        var onPrev = props && typeof props.onPrev === 'function' ? props.onPrev : null;
        var onNext = props && typeof props.onNext === 'function' ? props.onNext : null;
        var onToggle = props && typeof props.onToggle === 'function' ? props.onToggle : null;

        var weekdayLabels = useMemo(function () {
            var base = startOfWeek(new Date(2020, 5, 1, 12));
            var labels = [];
            for (var idx = 0; idx < 7; idx += 1) {
                var current = addDays(base, idx);
                try {
                    var formatter = new Intl.DateTimeFormat(getPreferredLocale(), { weekday: 'short' });
                    labels.push(formatter.format(current));
                } catch (error) {
                    labels.push(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'][idx] || '');
                }
            }
            return labels;
        }, []);

        var matrix = useMemo(function () {
            var base = startOfMonthDate(monthDate);
            var start = startOfWeek(base);
            var weeks = [];
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var cursor = new Date(start.getTime());
            for (var weekIndex = 0; weekIndex < 6; weekIndex += 1) {
                var days = [];
                for (var dayIndex = 0; dayIndex < 7; dayIndex += 1) {
                    var current = new Date(cursor.getTime());
                    var key = dateKeyFromDate(current);
                    var dayOccurrences = occurrencesByDate[key] || [];
                    var transformed = [];
                    var hasSelectable = false;
                    var hasSelected = false;
                    for (var occurrenceIndex = 0; occurrenceIndex < dayOccurrences.length; occurrenceIndex += 1) {
                        var occurrence = dayOccurrences[occurrenceIndex];
                        if (!occurrence) {
                            continue;
                        }
                        var selectable = !!occurrence.selectable && !occurrence.isPast;
                        if (selectable) {
                            hasSelectable = true;
                        }
                        var selected = !!selectedSet[occurrence.value];
                        if (selected) {
                            hasSelected = true;
                        }
                        transformed.push({
                            value: occurrence.value,
                            label: occurrence.timeLabel || occurrence.displayLabel || occurrence.label || occurrence.value,
                            selectable: selectable,
                            selected: selected,
                            isPast: !!occurrence.isPast,
                            occurrence: occurrence,
                        });
                    }
                    var dayInfo = {
                        key: key,
                        label: current.getDate(),
                        date: current,
                        isCurrentMonth: current.getFullYear() === base.getFullYear() && current.getMonth() === base.getMonth(),
                        isToday: isSameDay(current, today),
                        isPastDay: current < today && !isSameDay(current, today),
                        hasSelectable: hasSelectable,
                        hasSelected: hasSelected,
                        occurrences: transformed,
                        weekdayLabel: weekdayLabels[dayIndex] || '',
                    };
                    days.push(dayInfo);
                    cursor = addDays(cursor, 1);
                }
                weeks.push(days);
            }
            return weeks;
        }, [occurrenceSignature, selectedSignature, monthKey(monthDate), occurrencesByDate, selectedSet, weekdayLabels]);

        var isMobileLayout = useMobileCalendarLayout();
        var calendarClassName = 'mj-member-event-single__occurrence-calendar';
        if (isMobileLayout) {
            calendarClassName += ' is-mobile';
        }

        return h('div', { className: calendarClassName },
            h('header', { className: 'mj-member-event-single__occurrence-calendar-header' },
                h('button', {
                    type: 'button',
                    className: 'mj-member-event-single__occurrence-calendar-nav',
                    onClick: canGoPrev && onPrev ? onPrev : undefined,
                    disabled: !canGoPrev,
                    'aria-label': 'Mois precedent',
                }, '<'),
                h('span', { className: 'mj-member-event-single__occurrence-calendar-month' }, formatMonthLabel(monthDate)),
                h('button', {
                    type: 'button',
                    className: 'mj-member-event-single__occurrence-calendar-nav',
                    onClick: canGoNext && onNext ? onNext : undefined,
                    disabled: !canGoNext,
                    'aria-label': 'Mois suivant',
                }, '>')
            ),
            h('div', { className: 'mj-member-event-single__occurrence-calendar-weekdays' }, weekdayLabels.map(function (label, index) {
                return h('span', { key: 'weekday-' + index, className: 'mj-member-event-single__occurrence-calendar-weekday' }, label);
            })),
            h('div', { className: 'mj-member-event-single__occurrence-calendar-grid' }, matrix.map(function (week, weekIndex) {
                return h('div', { key: 'week-' + weekIndex, className: 'mj-member-event-single__occurrence-calendar-week' }, week.map(function (day) {
                    var dayClass = 'mj-member-event-single__occurrence-calendar-day';
                    if (!day.isCurrentMonth) {
                        dayClass += ' is-outside';
                    }
                    if (day.isPastDay) {
                        dayClass += ' is-past';
                    }
                    if (day.isToday) {
                        dayClass += ' is-today';
                    }
                    if (day.hasSelected) {
                        dayClass += ' has-selected';
                    }
                    if (!day.hasSelectable) {
                        dayClass += ' is-disabled';
                    }
                    return h('div', { key: day.key || (weekIndex + '-' + day.label), className: dayClass },
                        day.weekdayLabel ? h('div', { className: 'mj-member-event-single__occurrence-calendar-day-weekday' }, day.weekdayLabel) : null,
                        h('div', { className: 'mj-member-event-single__occurrence-calendar-day-number' }, day.label),
                        day.occurrences.length ? h('div', { className: 'mj-member-event-single__occurrence-calendar-day-times' }, day.occurrences.map(function (entry, occurrenceIndex) {
                            var isMobile = isMobileLayout;
                            var baseClass = 'mj-member-event-single__occurrence-calendar-time';
                            if (entry.selected) {
                                baseClass += ' is-active';
                            }
                            if (!entry.selectable) {
                                baseClass += ' is-disabled';
                            }

                            if (isMobile) {
                                var checkboxId = day.key + '-occurrence-' + occurrenceIndex;
                                var wrapperClass = 'mj-member-event-single__occurrence-calendar-checkbox-wrapper';
                                if (entry.selected) {
                                    wrapperClass += ' is-active';
                                }
                                if (!entry.selectable) {
                                    wrapperClass += ' is-disabled';
                                }
                                return h('label', {
                                    key: day.key + '-time-' + occurrenceIndex,
                                    className: wrapperClass,
                                    htmlFor: checkboxId,
                                },
                                    h('input', {
                                        id: checkboxId,
                                        type: 'checkbox',
                                        className: 'mj-member-event-single__occurrence-calendar-checkbox',
                                        checked: entry.selected,
                                        onChange: function () {
                                            if (!entry.selectable || !onToggle) {
                                                return;
                                            }
                                            onToggle(entry.value);
                                        },
                                        disabled: !entry.selectable,
                                        'aria-label': formatOccurrenceDate(day.date) + ' - ' + entry.label,
                                    }),
                                    h('span', { className: 'mj-member-event-single__occurrence-calendar-sr-only' }, entry.label)
                                );
                            }

                            return h('button', {
                                type: 'button',
                                key: day.key + '-time-' + occurrenceIndex,
                                className: baseClass,
                                onClick: function () {
                                    if (!entry.selectable || !onToggle) {
                                        return;
                                    }
                                    onToggle(entry.value);
                                },
                                disabled: !entry.selectable,
                                'aria-pressed': entry.selected ? 'true' : 'false',
                                'aria-label': formatOccurrenceDate(day.date) + ' - ' + entry.label,
                            }, entry.label);
                        })) : null
                    );
                }));
            }))
        );
    }

    function OccurrenceSelector() {
        var registration = useContext(RegistrationContext);
        if (!registration) {
            return null;
        }

        var occurrences = registration.occurrences || [];
        var hasOccurrences = registration.hasOccurrences;
        var allowSelection = registration.allowOccurrenceSelection;
        var requiresSelection = registration.requiresOccurrenceSelection;
        var occurrenceSummary = registration.occurrenceSummary || '';
        var scheduleMode = registration.scheduleMode || '';
        var toggleOccurrence = registration.toggleOccurrence;
        var selectedOccurrences = registration.selectedOccurrences || [];
        var selectedSignature = registration.selectedOccurrencesSignature || '';
        var occurrenceSignature = registration.occurrenceSignature || '';

        if (!hasOccurrences) {
            if (occurrenceSummary) {
                return h('div', { className: 'mj-member-event-single__registration-occurrences' },
                    h('h4', { className: 'mj-member-event-single__occurrence-heading' }, 'Planification'),
                    h('p', { className: 'mj-member-event-single__occurrence-hint' }, occurrenceSummary)
                );
            }
            return null;
        }

        var hint = occurrenceSummary || buildOccurrenceHint(scheduleMode, allowSelection);
        var selectedSet = useMemo(function () {
            var map = Object.create(null);
            for (var idx = 0; idx < selectedOccurrences.length; idx += 1) {
                map[selectedOccurrences[idx]] = true;
            }
            return map;
        }, [selectedSignature]);

        var occurrencesByDate = useMemo(function () {
            if (!occurrences.length) {
                return Object.create(null);
            }
            var map = Object.create(null);
            for (var idx = 0; idx < occurrences.length; idx += 1) {
                var occurrence = occurrences[idx];
                if (!occurrence) {
                    continue;
                }
                var key = occurrence.dateKey || occurrence.value;
                if (!key) {
                    continue;
                }
                if (!map[key]) {
                    map[key] = [];
                }
                map[key].push(occurrence);
            }
            var keys = Object.keys(map);
            for (var index = 0; index < keys.length; index += 1) {
                var list = map[keys[index]];
                list.sort(function (a, b) {
                    var aTime = a.timestamp !== null && a.timestamp !== undefined ? a.timestamp : 0;
                    var bTime = b.timestamp !== null && b.timestamp !== undefined ? b.timestamp : 0;
                    if (aTime && bTime && aTime !== bTime) {
                        return aTime - bTime;
                    }
                    return a.value.localeCompare(b.value);
                });
            }
            return map;
        }, [occurrenceSignature]);

        var selectionCount = selectedOccurrences.length;
        var selectionSummary = allowSelection
            ? (selectionCount > 0
                ? (selectionCount === 1 ? '1 occurrence selectionnee.' : selectionCount + ' occurrences selectionnees.')
                : 'Aucune date selectionnee pour le moment.')
            : '';
        var warning = requiresSelection && selectionCount === 0;

        var initialMonth = useMemo(function () {
            if (!occurrences.length) {
                return startOfMonthDate(new Date());
            }
            var nowMs = Date.now();
            var future = null;
            var earliest = null;
            for (var idx = 0; idx < occurrences.length; idx += 1) {
                var occurrence = occurrences[idx];
                if (!occurrence) {
                    continue;
                }
                var dateCandidate = null;
                if (occurrence.timestamp) {
                    dateCandidate = new Date(occurrence.timestamp * 1000);
                } else if (occurrence.dateKey) {
                    dateCandidate = parseDateKey(occurrence.dateKey);
                }
                if (!dateCandidate) {
                    continue;
                }
                var monthStart = startOfMonthDate(dateCandidate);
                if (!earliest || compareMonth(monthStart, earliest) < 0) {
                    earliest = monthStart;
                }
                if (dateCandidate.getTime() >= nowMs) {
                    if (!future || dateCandidate.getTime() < future.getTime()) {
                        future = monthStart;
                    }
                }
            }
            return future || earliest || startOfMonthDate(new Date());
        }, [occurrenceSignature]);

        var _c = useState(function () {
            return initialMonth;
        }), visibleMonth = _c[0], setVisibleMonth = _c[1];

        var initialMonthKey = monthKey(initialMonth);
        useEffect(function () {
            setVisibleMonth(function (current) {
                if (!current) {
                    return initialMonth;
                }
                if (monthKey(current) !== initialMonthKey) {
                    return initialMonth;
                }
                return current;
            });
        }, [initialMonthKey, initialMonth]);

        var monthBounds = useMemo(function () {
            if (!occurrences.length) {
                var fallback = startOfMonthDate(new Date());
                return { min: fallback, max: fallback };
            }
            var minMonth = null;
            var maxMonth = null;
            for (var idx = 0; idx < occurrences.length; idx += 1) {
                var occurrence = occurrences[idx];
                if (!occurrence) {
                    continue;
                }
                var dateCandidate = null;
                if (occurrence.timestamp) {
                    dateCandidate = new Date(occurrence.timestamp * 1000);
                } else if (occurrence.dateKey) {
                    dateCandidate = parseDateKey(occurrence.dateKey);
                }
                if (!dateCandidate) {
                    continue;
                }
                var monthDate = startOfMonthDate(dateCandidate);
                if (!minMonth || compareMonth(monthDate, minMonth) < 0) {
                    minMonth = monthDate;
                }
                if (!maxMonth || compareMonth(monthDate, maxMonth) > 0) {
                    maxMonth = monthDate;
                }
            }
            if (!minMonth || !maxMonth) {
                var today = startOfMonthDate(new Date());
                return { min: today, max: today };
            }
            return { min: minMonth, max: maxMonth };
        }, [occurrenceSignature]);

        var canGoPrev = visibleMonth && monthBounds.min ? compareMonth(visibleMonth, monthBounds.min) > 0 : false;
        var canGoNext = visibleMonth && monthBounds.max ? compareMonth(visibleMonth, monthBounds.max) < 0 : false;

        var goPrevMonth = useCallback(function () {
            setVisibleMonth(function (current) {
                if (!current) {
                    return monthBounds.min || startOfMonthDate(new Date());
                }
                var candidate = addMonths(current, -1);
                if (monthBounds.min && compareMonth(candidate, monthBounds.min) < 0) {
                    return monthBounds.min;
                }
                return candidate;
            });
        }, [monthBounds.min]);

        var goNextMonth = useCallback(function () {
            setVisibleMonth(function (current) {
                if (!current) {
                    return monthBounds.max || startOfMonthDate(new Date());
                }
                var candidate = addMonths(current, 1);
                if (monthBounds.max && compareMonth(candidate, monthBounds.max) > 0) {
                    return monthBounds.max;
                }
                return candidate;
            });
        }, [monthBounds.max]);

        if (!allowSelection) {
            return h('div', { className: 'mj-member-event-single__registration-occurrences' },
                h('h4', { className: 'mj-member-event-single__occurrence-heading' }, 'Occurrences'),
                hint ? h('p', { className: 'mj-member-event-single__occurrence-hint' }, hint) : null
            );
        }

        if (!occurrences.length) {
            return h('div', { className: 'mj-member-event-single__registration-occurrences' },
                h('h4', { className: 'mj-member-event-single__occurrence-heading' }, 'Occurrences disponibles'),
                hint ? h('p', { className: 'mj-member-event-single__occurrence-hint' }, hint) : null,
                h('p', { className: 'mj-member-event-single__occurrence-empty' }, 'Aucune date disponible pour le moment.')
            );
        }

        return h('div', { className: 'mj-member-event-single__registration-occurrences' },
            h('h4', { className: 'mj-member-event-single__occurrence-heading' }, 'Choisis les occurrences'),
            hint ? h('p', { className: 'mj-member-event-single__occurrence-hint' }, hint) : null,
            visibleMonth ? h(OccurrenceCalendar, {
                key: 'calendar',
                monthDate: visibleMonth,
                occurrenceSignature: occurrenceSignature,
                selectedSignature: selectedSignature,
                occurrencesByDate: occurrencesByDate,
                selectedSet: selectedSet,
                canGoPrev: canGoPrev,
                canGoNext: canGoNext,
                onPrev: canGoPrev ? goPrevMonth : null,
                onNext: canGoNext ? goNextMonth : null,
                onToggle: toggleOccurrence,
            }) : null,
            h('p', { className: 'mj-member-event-single__occurrence-summary' }, selectionSummary),
            warning ? h('p', { className: 'mj-member-event-single__occurrence-warning', role: 'alert' }, 'Selectionne au moins une occurrence.') : null
        );
    }

    function NoteEditor() {
        var registration = useContext(RegistrationContext);
        if (!registration) {
            return null;
        }

        var note = registration.note || '';
        var maxLength = registration.noteMaxLength || 0;
        var remaining = registration.noteRemaining;

        var handleInput = useCallback(function (event) {
            if (!registration.setNote) {
                return;
            }
            var value = event && event.target ? String(event.target.value || '') : '';
            if (maxLength > 0 && value.length > maxLength) {
                value = value.slice(0, maxLength);
            }
            registration.setNote(value);
        }, [registration, maxLength]);

        return h('div', { className: 'mj-member-event-single__registration-note-editor' },
            h('label', {
                htmlFor: 'mj-member-event-single-note-editor',
                className: 'mj-member-event-single__registration-note-label',
            }, 'Message pour l\'equipe (optionnel)'),
            h('textarea', {
                id: 'mj-member-event-single-note-editor',
                className: 'mj-member-event-single__registration-note-input',
                value: note,
                maxLength: maxLength > 0 ? maxLength : undefined,
                rows: 3,
                onInput: handleInput,
                placeholder: 'Precise une allergie, une contrainte horaire, ...',
            }),
            maxLength > 0 && remaining !== null ? h('p', {
                className: 'mj-member-event-single__registration-note-hint',
                role: 'status',
                'aria-live': 'polite',
            }, remaining + ' caractere' + (remaining === 1 ? ' restant' : 's restants')) : null
        );
    }

    function FormSubmissionBridge() {
        var registration = useContext(RegistrationContext);
        var snapshot = useContext(ConfigContext);
        var stateRef = useRef({ registration: registration, snapshot: snapshot });
        var feedbackRef = useRef(null);
        var submitButtonRef = useRef(null);
        var originalLabelRef = useRef('');
        var submittingRef = useRef(false);
        var _d = useState(false), submitting = _d[0], setSubmitting = _d[1];

        useEffect(function () {
            stateRef.current = {
                registration: registration,
                snapshot: snapshot,
            };
        }, [registration, snapshot]);

        useEffect(function () {
            if (!registrationForm && namespace.form) {
                registrationForm = namespace.form;
            }
            var formNode = registrationForm;
            if (!formNode) {
                return;
            }
            formNode.dataset.mjEventRegistrationInit = '1';

            var feedbackElement = formNode.querySelector('[data-mj-event-registration-feedback]');
            feedbackRef.current = feedbackElement;

            var submitButton = formNode.querySelector('[data-mj-event-registration-submit]');
            submitButtonRef.current = submitButton;
            if (submitButton && !submitButton.dataset.originalLabel) {
                submitButton.dataset.originalLabel = submitButton.textContent || '';
            }

            var confirmationField = formNode.querySelector('[data-mj-event-confirmation-checkbox]');
            var handleConfirmationChange = function () {
                var container = formNode.querySelector('[data-mj-event-confirmation]');
                if (container) {
                    container.classList.remove('is-invalid');
                }
                if (confirmationField && confirmationField.checked && feedbackRef.current && feedbackRef.current.dataset && feedbackRef.current.dataset.mjEventFeedbackSource === 'confirmation') {
                    setFeedbackMessage(feedbackRef.current, '', false);
                    delete feedbackRef.current.dataset.mjEventFeedbackSource;
                }
            };
            if (confirmationField) {
                confirmationField.addEventListener('change', handleConfirmationChange, false);
            }

            var handleSubmit = function (event) {
                if (!event) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();

                if (submittingRef.current) {
                    return;
                }

                if (!registrationForm && namespace.form) {
                    registrationForm = namespace.form;
                }
                var formReference = registrationForm;
                if (!formReference) {
                    return;
                }

                var currentState = stateRef.current || {};
                var registrationState = currentState.registration || null;
                var configSnapshot = currentState.snapshot && currentState.snapshot.config ? currentState.snapshot.config : null;

                if (feedbackRef.current && feedbackRef.current.dataset) {
                    delete feedbackRef.current.dataset.mjEventFeedbackSource;
                }

                ensureAjaxSettings();

                if (!ajaxUrl || !ajaxNonce || typeof globalObject.fetch !== 'function' || typeof globalObject.FormData === 'undefined') {
                    setFeedbackMessage(feedbackRef.current, getString('genericError', 'Une erreur est survenue. Merci de reessayer.'), true);
                    return;
                }

                if (!registrationState) {
                    setFeedbackMessage(feedbackRef.current, getString('genericError', 'Une erreur est survenue. Merci de reessayer.'), true);
                    return;
                }

                var selectedParticipant = registrationState.selectedParticipant || null;
                if (!selectedParticipant || !selectedParticipant.id || !selectedParticipant.selectable) {
                    setFeedbackMessage(feedbackRef.current, getString('selectParticipant', 'Merci de selectionner un participant.'), true);
                    return;
                }

                var eventAttr = formReference.getAttribute('data-event-id') || '0';
                var eventId = parseInt(eventAttr, 10);
                if (!eventId || isNaN(eventId) || eventId <= 0) {
                    setFeedbackMessage(feedbackRef.current, getString('genericError', 'Une erreur est survenue. Merci de reessayer.'), true);
                    return;
                }

                var confirmationContainer = formReference.querySelector('[data-mj-event-confirmation]');
                if (confirmationContainer) {
                    confirmationContainer.classList.remove('is-invalid');
                }
                var confirmationNode = formReference.querySelector('[data-mj-event-confirmation-checkbox]');
                if (confirmationNode && !confirmationNode.checked) {
                    if (confirmationContainer) {
                        confirmationContainer.classList.add('is-invalid');
                    }
                    var confirmationKey = registrationState && registrationState.paymentRequired ? 'confirmationRequiredPayment' : 'confirmationRequired';
                    var confirmationMessage = getString(
                        confirmationKey,
                        registrationState && registrationState.paymentRequired
                            ? 'Merci de confirmer que tu finaliseras ton inscription et le paiement.'
                            : 'Merci de confirmer que ta reservation est correcte.'
                    );
                    setFeedbackMessage(feedbackRef.current, confirmationMessage, true);
                    if (feedbackRef.current && feedbackRef.current.dataset) {
                        feedbackRef.current.dataset.mjEventFeedbackSource = 'confirmation';
                    }
                    if (typeof confirmationNode.focus === 'function') {
                        confirmationNode.focus();
                    }
                    return;
                }

                var occurrences = registrationState.selectedOccurrences || [];
                if (registrationState.requiresOccurrenceSelection && (!occurrences || occurrences.length === 0)) {
                    setFeedbackMessage(feedbackRef.current, getString('occurrenceMissing', 'Merci de selectionner au moins une occurrence.'), true);
                    return;
                }

                submittingRef.current = true;
                setSubmitting(true);
                formReference.dataset.submitting = '1';
                setFeedbackMessage(feedbackRef.current, '', false);

                var payload = new globalObject.FormData();
                payload.append('action', 'mj_member_register_event');
                payload.append('nonce', ajaxNonce);
                payload.append('event_id', String(eventId));
                payload.append('member_id', String(selectedParticipant.id));

                var noteValue = registrationState.note || '';
                var noteLimit = registrationState.noteMaxLength || (configSnapshot && configSnapshot.noteMaxLength ? configSnapshot.noteMaxLength : 0);
                if (noteLimit > 0 && noteValue.length > noteLimit) {
                    noteValue = noteValue.slice(0, noteLimit);
                }
                payload.append('note', noteValue);

                if (occurrences && occurrences.length) {
                    try {
                        payload.append('occurrences', JSON.stringify(occurrences));
                    } catch (serializationError) {
                        // ignore serialization errors
                    }
                }

                globalObject.fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: payload,
                }).then(function (response) {
                    return readJsonResponse(response);
                }).then(function (result) {
                    var payload = result && typeof result.json === 'object' && result.json !== null ? result.json : null;
                    if (!result.ok || !payload) {
                        var networkMessage = getString('genericError', 'Une erreur est survenue. Merci de reessayer.');
                        if (payload && payload.data && payload.data.message) {
                            networkMessage = payload.data.message;
                        }
                        setFeedbackMessage(feedbackRef.current, networkMessage, true);
                        var networkError = new Error(networkMessage);
                        networkError._handled = true;
                        throw networkError;
                    }

                    if (!payload.success) {
                        var failureMessage = getString('genericError', 'Une erreur est survenue. Merci de reessayer.');
                        if (payload.data && payload.data.message) {
                            failureMessage = payload.data.message;
                        }
                        setFeedbackMessage(feedbackRef.current, failureMessage, true);
                        var failureError = new Error(failureMessage);
                        failureError._handled = true;
                        throw failureError;
                    }

                    return payload.data || {};
                }).then(function (data) {
                    var successMessage = data.message || getString('success', 'Inscription enregistree !');
                    var paymentInfo = data.payment || null;
                    var showPaymentModal = false;
                    if (paymentInfo && paymentInfo.checkout_url && registrationState && typeof registrationState.showPaymentSummary === 'function') {
                        var summaryMessage = getString('paymentSummaryMessage', 'Tu peux effectuer le paiement maintenant ou plus tard depuis ton espace membre ou en main propre aupres d\'un animateur.');
                        var priceLabelSnapshot = registrationState.priceLabel || (configSnapshot && configSnapshot.priceLabel ? sanitizeString(configSnapshot.priceLabel) : '');
                        var priceAmountSnapshot = registrationState.priceAmount !== undefined && registrationState.priceAmount !== null
                            ? registrationState.priceAmount
                            : (configSnapshot && configSnapshot.priceAmount !== undefined ? configSnapshot.priceAmount : null);
                        var priceCurrencySnapshot = registrationState.priceCurrency || (configSnapshot && configSnapshot.priceCurrency ? sanitizeString(configSnapshot.priceCurrency) : '');
                        var eventTitleSnapshot = registrationState && registrationState.eventTitle ? registrationState.eventTitle : (configSnapshot && configSnapshot.eventTitle ? sanitizeString(configSnapshot.eventTitle) : '');
                        showPaymentModal = registrationState.showPaymentSummary({
                            payment: paymentInfo,
                            checkoutUrl: paymentInfo.checkout_url,
                            amountLabel: paymentInfo.amount || '',
                            priceLabel: priceLabelSnapshot,
                            priceAmount: priceAmountSnapshot,
                            priceCurrency: priceCurrencySnapshot,
                            eventTitle: eventTitleSnapshot,
                            message: summaryMessage,
                        }) === true;
                    }

                    var finalMessage = successMessage;
                    if (!showPaymentModal && paymentInfo && paymentInfo.checkout_url) {
                        finalMessage = successMessage + ' ' + getString('paymentFallback', "Si la page de paiement ne s'ouvre pas, copie ce lien :") + ' ' + paymentInfo.checkout_url;
                    }
                    setFeedbackMessage(feedbackRef.current, finalMessage, !!data.payment_error);

                    if (registrationState && typeof registrationState.setNote === 'function') {
                        registrationState.setNote('');
                    }

                    var confirmationNodeSuccess = formReference.querySelector('[data-mj-event-confirmation-checkbox]');
                    if (confirmationNodeSuccess) {
                        confirmationNodeSuccess.checked = false;
                    }
                    var confirmationContainerSuccess = formReference.querySelector('[data-mj-event-confirmation]');
                    if (confirmationContainerSuccess) {
                        confirmationContainerSuccess.classList.remove('is-invalid');
                    }
                    if (feedbackRef.current && feedbackRef.current.dataset) {
                        delete feedbackRef.current.dataset.mjEventFeedbackSource;
                    }

                    if (configSnapshot && Array.isArray(configSnapshot.participants)) {
                        for (var index = 0; index < configSnapshot.participants.length; index += 1) {
                            var candidate = configSnapshot.participants[index];
                            if (!candidate || candidate.id !== selectedParticipant.id) {
                                continue;
                            }
                            candidate.isRegistered = true;
                            candidate.selectable = false;
                            candidate.statusLabel = data.is_waitlist
                                ? getString('waitlistStatus', "En liste d'attente")
                                : getString('pendingStatus', getString('registered', 'Inscription envoyee'));
                            candidate.statusClass = data.is_waitlist ? 'is-waitlist' : 'is-pending';
                            candidate.ineligibleReasons = [];
                            break;
                        }

                        var availableCount = 0;
                        for (var scanIndex = 0; scanIndex < configSnapshot.participants.length; scanIndex += 1) {
                            var entry = configSnapshot.participants[scanIndex];
                            if (entry && entry.selectable) {
                                availableCount += 1;
                            }
                        }
                        configSnapshot.hasAvailableParticipants = availableCount > 0;
                        configSnapshot.allRegistered = availableCount === 0 && configSnapshot.participants.length > 0;
                        namespace.participants = configSnapshot.participants;
                        namespace.config = configSnapshot;
                        namespace.lastUpdate = Date.now();
                    }

                    if (registrationState && typeof registrationState.refresh === 'function') {
                        registrationState.refresh();
                    }

                    var occurrencePayload = {
                        source: 'legacy',
                        occurrences: [],
                    };
                    if (formReference && typeof formReference.dispatchEvent === 'function') {
                        try {
                            if (typeof globalObject.CustomEvent === 'function') {
                                formReference.dispatchEvent(new CustomEvent('mj-member:event-single-occurrences-sync', { detail: occurrencePayload }));
                            } else if (globalObject.document && typeof globalObject.document.createEvent === 'function') {
                                var legacyEvent = globalObject.document.createEvent('CustomEvent');
                                legacyEvent.initCustomEvent('mj-member:event-single-occurrences-sync', false, false, occurrencePayload);
                                formReference.dispatchEvent(legacyEvent);
                            }
                        } catch (syncError) {
                            // ignore
                        }
                    }

                    var nextParticipant = null;
                    if (configSnapshot && Array.isArray(configSnapshot.participants)) {
                        for (var nextIndex = 0; nextIndex < configSnapshot.participants.length; nextIndex += 1) {
                            var possible = configSnapshot.participants[nextIndex];
                            if (possible && possible.selectable) {
                                nextParticipant = possible;
                                break;
                            }
                        }
                    }
                    if (nextParticipant && typeof registrationState.selectParticipant === 'function') {
                        registrationState.selectParticipant(nextParticipant.id);
                    }

                    if (globalObject.document && typeof globalObject.document.dispatchEvent === 'function') {
                        try {
                            var refreshDetail = { eventId: eventId };
                            if (typeof globalObject.CustomEvent === 'function') {
                                globalObject.document.dispatchEvent(new CustomEvent('mj-member:refresh-reservations', { detail: refreshDetail }));
                            } else if (typeof globalObject.document.createEvent === 'function') {
                                var refreshEvent = globalObject.document.createEvent('CustomEvent');
                                refreshEvent.initCustomEvent('mj-member:refresh-reservations', false, false, refreshDetail);
                                globalObject.document.dispatchEvent(refreshEvent);
                            }
                        } catch (refreshError) {
                            // ignore
                        }
                    }

                    if (!showPaymentModal && paymentInfo && paymentInfo.checkout_url) {
                        var opened = globalObject.open ? globalObject.open(paymentInfo.checkout_url, '_blank', 'noopener,noreferrer') : null;
                        if (!opened && globalObject.location) {
                            globalObject.location.href = paymentInfo.checkout_url;
                        }
                    }
                }).catch(function (error) {
                    if (error && error._handled) {
                        return;
                    }
                    var fallbackMessage = getString('genericError', 'Une erreur est survenue. Merci de reessayer.');
                    if (error && error.message) {
                        fallbackMessage = error.message;
                    }
                    setFeedbackMessage(feedbackRef.current, fallbackMessage, true);
                }).finally(function () {
                    submittingRef.current = false;
                    setSubmitting(false);
                    formReference.dataset.submitting = '0';
                });
            };

            formNode.addEventListener('submit', handleSubmit, true);

            return function () {
                formNode.removeEventListener('submit', handleSubmit, true);
                if (confirmationField) {
                    confirmationField.removeEventListener('change', handleConfirmationChange, false);
                }
            };
        }, []);

        useEffect(function () {
            var button = submitButtonRef.current;
            if (!button) {
                return;
            }
            if (!originalLabelRef.current) {
                originalLabelRef.current = button.dataset.originalLabel || button.textContent || '';
            }
            if (submitting) {
                button.disabled = true;
                if (ajaxStrings && ajaxStrings.loading) {
                    button.textContent = ajaxStrings.loading;
                }
            } else {
                button.disabled = false;
                var restoredLabel = button.dataset.originalLabel || originalLabelRef.current;
                if (restoredLabel) {
                    button.textContent = restoredLabel;
                }
            }
        }, [submitting]);

        return null;
    }

    function ReservationBridge() {
        var registration = useContext(RegistrationContext);
        var snapshot = useContext(ConfigContext);
        var stateRef = useRef({ registration: registration, snapshot: snapshot });

        useEffect(function () {
            stateRef.current = {
                registration: registration,
                snapshot: snapshot,
            };
        }, [registration, snapshot]);

        useEffect(function () {
            if (typeof document === 'undefined') {
                return;
            }

            var root = document.querySelector('[data-mj-event-reservations]');
            if (!root) {
                return;
            }

            if (root.dataset.mjEventReservationBridge === '1') {
                return;
            }
            root.dataset.mjEventReservationBridge = '1';

            var feedbackNode = root.querySelector('[data-mj-event-reservations-feedback]');
            var emptyNode = root.querySelector('[data-mj-event-reservations-empty]');
            var listNode = root.querySelector('[data-mj-event-reservations-list]');
            var eventAttr = root.getAttribute('data-event-id') || '0';

            var handleClick = function (event) {
                if (!event) {
                    return;
                }

                var button = event.target && typeof event.target.closest === 'function'
                    ? event.target.closest('[data-mj-event-cancel]')
                    : null;

                if (!button || !root.contains(button)) {
                    return;
                }

                event.preventDefault();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                } else if (typeof event.stopPropagation === 'function') {
                    event.stopPropagation();
                }

                if (button.disabled || button.dataset.mjReservationCancelling === '1') {
                    return;
                }

                ensureAjaxSettings();

                if (!ajaxUrl || !ajaxNonce || typeof globalObject.fetch !== 'function' || typeof globalObject.FormData === 'undefined') {
                    setFeedbackMessage(feedbackNode, getString('genericError', 'Une erreur est survenue. Merci de reessayer.'), true);
                    return;
                }

                var eventId = parseInt(eventAttr, 10);
                if (!eventId || isNaN(eventId) || eventId <= 0) {
                    setFeedbackMessage(feedbackNode, getString('genericError', 'Une erreur est survenue. Merci de reessayer.'), true);
                    return;
                }

                var memberId = parseInt(button.getAttribute('data-member-id') || '0', 10);
                if (!memberId || isNaN(memberId) || memberId <= 0) {
                    setFeedbackMessage(feedbackNode, getString('genericError', 'Une erreur est survenue. Merci de reessayer.'), true);
                    return;
                }

                var registrationIdRaw = button.getAttribute('data-registration-id') || '0';
                var registrationId = parseInt(registrationIdRaw, 10);
                if (isNaN(registrationId) || registrationId < 0) {
                    registrationId = 0;
                }

                var confirmMessage = getString('unregisterConfirm', 'Annuler cette inscription ?');
                if (confirmMessage && typeof globalObject.confirm === 'function' && !globalObject.confirm(confirmMessage)) {
                    return;
                }

                button.disabled = true;
                button.dataset.mjReservationCancelling = '1';
                root.classList.add('is-loading');
                setFeedbackMessage(feedbackNode, getString('reservationsLoading', getString('loading', 'En cours...')), false);

                var payload = new globalObject.FormData();
                payload.append('action', 'mj_member_unregister_event');
                payload.append('nonce', ajaxNonce);
                payload.append('event_id', String(eventId));
                payload.append('member_id', String(memberId));
                if (registrationId > 0) {
                    payload.append('registration_id', String(registrationId));
                }

                globalObject.fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: payload,
                }).then(function (response) {
                    return readJsonResponse(response);
                }).then(function (result) {
                    if (!result.ok || !result.json) {
                        var messageNetwork = getString('unregisterError', getString('genericError', 'Une erreur est survenue. Merci de reessayer.'));
                        if (result.json && result.json.data && result.json.data.message) {
                            messageNetwork = result.json.data.message;
                        }
                        setFeedbackMessage(feedbackNode, messageNetwork, true);
                        var networkError = new Error(messageNetwork);
                        networkError._handled = true;
                        throw networkError;
                    }

                    if (!result.json.success) {
                        var messageFail = getString('unregisterError', getString('genericError', 'Une erreur est survenue. Merci de reessayer.'));
                        if (result.json.data && result.json.data.message) {
                            messageFail = result.json.data.message;
                        }
                        setFeedbackMessage(feedbackNode, messageFail, true);
                        var handledError = new Error(messageFail);
                        handledError._handled = true;
                        throw handledError;
                    }

                    var data = result.json.data || {};
                    var successMessage = data.message || getString('unregisterSuccess', 'Inscription annulee.');
                    setFeedbackMessage(feedbackNode, successMessage, false);

                    var listItem = button.closest('[data-mj-event-reservation]');
                    if (listItem && listItem.parentNode) {
                        listItem.parentNode.removeChild(listItem);
                    }

                    if (listNode) {
                        var remaining = listNode.querySelectorAll('[data-mj-event-reservation]').length;
                        root.setAttribute('data-has-reservations', remaining > 0 ? '1' : '0');
                        root.classList.toggle('is-empty', remaining === 0);
                        if (emptyNode) {
                            emptyNode.hidden = remaining > 0;
                        }
                    }

                    var currentState = stateRef.current;
                    var configSnapshot = currentState.snapshot ? currentState.snapshot.config : null;
                    if (configSnapshot && Array.isArray(configSnapshot.participants)) {
                        var participants = configSnapshot.participants;
                        for (var index = 0; index < participants.length; index += 1) {
                            var participant = participants[index];
                            if (!participant) {
                                continue;
                            }
                            if (String(participant.id) !== String(memberId)) {
                                continue;
                            }
                            participant.isRegistered = false;
                            participant.selectable = true;
                            participant.statusLabel = '';
                            participant.statusClass = '';
                            if (!Array.isArray(participant.ineligibleReasons)) {
                                participant.ineligibleReasons = [];
                            }
                        }

                        var availableCount = 0;
                        for (var scan = 0; scan < participants.length; scan += 1) {
                            if (participants[scan] && participants[scan].selectable) {
                                availableCount += 1;
                            }
                        }

                        configSnapshot.hasAvailableParticipants = availableCount > 0;
                        configSnapshot.allRegistered = availableCount === 0 && participants.length > 0;
                        namespace.participants = participants;
                        namespace.config = configSnapshot;
                        namespace.lastUpdate = Date.now();
                    }

                    if (currentState.registration) {
                        if (typeof currentState.registration.refresh === 'function') {
                            currentState.registration.refresh();
                        }
                        if (typeof currentState.registration.selectParticipant === 'function') {
                            currentState.registration.selectParticipant(memberId);
                        }
                    }

                    if (globalObject.document && typeof globalObject.document.dispatchEvent === 'function') {
                        try {
                            var refreshDetail = { eventId: eventId };
                            if (typeof globalObject.CustomEvent === 'function') {
                                globalObject.document.dispatchEvent(new CustomEvent('mj-member:refresh-reservations', { detail: refreshDetail }));
                            } else if (typeof globalObject.document.createEvent === 'function') {
                                var refreshEvent = globalObject.document.createEvent('CustomEvent');
                                refreshEvent.initCustomEvent('mj-member:refresh-reservations', false, false, refreshDetail);
                                globalObject.document.dispatchEvent(refreshEvent);
                            }
                        } catch (dispatchError) {
                            // ignore dispatch failures
                        }
                    }
                }).catch(function (error) {
                    if (error && error._handled) {
                        return;
                    }
                    var fallback = getString('unregisterError', getString('genericError', 'Une erreur est survenue. Merci de reessayer.'));
                    if (error && error.message) {
                        fallback = error.message;
                    }
                    setFeedbackMessage(feedbackNode, fallback, true);
                }).finally(function () {
                    button.disabled = false;
                    delete button.dataset.mjReservationCancelling;
                    root.classList.remove('is-loading');
                });
            };

            root.addEventListener('click', handleClick, true);

            return function () {
                root.removeEventListener('click', handleClick, true);
                delete root.dataset.mjEventReservationBridge;
            };
        }, []);

        return null;
    }

    function App(props) {
        var memoValue = useMemo(function () {
            var cfg = props && props.config ? props.config : null;
            return {
                config: cfg,
                allowGuardianRegistration: !!(cfg && cfg.allowGuardianRegistration),
                capacity: cfg ? cfg.capacity : null,
            };
        }, [props && props.config ? props.config : null]);

        var registration = useRegistrationController(memoValue);

        var children = [
            h(StateBridge, { key: 'bridge', value: memoValue }),
            h(SidebarEnhancementsRenderer, { key: 'sidebar-enhancements' })
        ];
        if (registration) {
            children.push(h(RegistrationContext.Provider, { key: 'registration', value: registration },
                h(FormSubmissionBridge, { key: 'form-bridge' }),
                h(ReservationBridge, { key: 'reservation-bridge' }),
                h(Enhancements, { key: 'enhancements', scope: 'registration' }),
                h(ParticipantSelector, { key: 'participants' }),
                h(OccurrenceSelector, { key: 'occurrences' }),
                h(NoteEditor, { key: 'note' }),
                h(PaymentSummaryModal, { key: 'payment-modal' })
            ));
        } else {
            children.push(
                h(ReservationBridge, { key: 'reservation-bridge' }),
                h(Enhancements, { key: 'enhancements-only', scope: 'registration' })
            );
        }

        return h(ConfigContext.Provider, { value: memoValue }, children);
    }

    function ensureMount(form) {
        if (typeof document === 'undefined') {
            return null;
        }
        if (form && typeof form.querySelector === 'function') {
            var existing = form.querySelector('[data-mj-event-single-app]');
            if (existing) {
                return existing;
            }
            var mount = document.createElement('div');
            mount.setAttribute('data-mj-event-single-app', 'root');
            mount.className = 'mj-member-event-single__registration-enhancements-mount';
            form.appendChild(mount);
            return mount;
        }
        var globalMount = document.querySelector('[data-mj-event-single-app]');
        if (globalMount) {
            return globalMount;
        }
        var fallback = document.createElement('div');
        fallback.setAttribute('data-mj-event-single-app', 'root');
        fallback.className = 'mj-member-event-single__registration-enhancements-mount';
        document.body.appendChild(fallback);
        return fallback;
    }

    function bootstrap() {
        var config = readConfig();
        if (!config) {
            return;
        }
        var form = typeof document !== 'undefined' ? document.querySelector('[data-mj-event-registration]') : null;
        var mount = ensureMount(form);
        if (!mount) {
            return;
        }
        registrationForm = form;
        namespace.form = form;
        if (registrationForm && registrationForm.classList) {
            registrationForm.classList.add('mj-member-event-single__registration-form--enhanced');
        }
        namespace.config = config;
        namespace.allowGuardianRegistration = config.allowGuardianRegistration;
        namespace.capacity = config.capacity;
        namespace.occurrences = config.occurrences || [];
        namespace.allowOccurrenceSelection = !!config.allowOccurrenceSelection;
        namespace.participants = config.participants || [];
        if (registrationForm) {
            applyDataset(registrationForm, config);
        }
        render(h(App, { config: config }), mount);
        namespace.bootstrapped = true;
    }

    domReady(bootstrap);
})();