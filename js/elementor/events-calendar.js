(function(){
    'use strict';

    var Utils = window.MjMemberUtils || {};
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
    var toArray = typeof Utils.toArray === 'function'
        ? Utils.toArray
        : function (collection) {
            if (!collection) {
                return [];
            }

            if (Array.isArray(collection)) {
                return collection.slice();
            }

            try {
                return Array.prototype.slice.call(collection);
            } catch (error) {
                var fallback = [];
                for (var idx = 0; idx < collection.length; idx += 1) {
                    fallback.push(collection[idx]);
                }
                return fallback;
            }
        };

    function initCalendar(root, config) {
        if (!root) {
            return;
        }

        // Optional EventPage modal opened from event links.
        var eventPageModal = null;
        var eventPageModalFrame = null;
        var eventPageModalTitle = null;
        var previousBodyOverflow = '';

        function ensureEventPageModal() {
            if (eventPageModal) {
                return true;
            }

            if (!document || !document.body) {
                return false;
            }

            eventPageModal = document.createElement('div');
            eventPageModal.className = 'mj-cal-eventpage-modal';
            eventPageModal.hidden = true;

            var backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'mj-cal-eventpage-modal__backdrop';
            backdrop.setAttribute('aria-label', 'Fermer');

            var panel = document.createElement('div');
            panel.className = 'mj-cal-eventpage-modal__panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('aria-label', 'Page événement');

            var header = document.createElement('div');
            header.className = 'mj-cal-eventpage-modal__header';

            eventPageModalTitle = document.createElement('span');
            eventPageModalTitle.className = 'mj-cal-eventpage-modal__title';
            eventPageModalTitle.textContent = 'Page événement';

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'mj-cal-eventpage-modal__close';
            closeBtn.setAttribute('aria-label', 'Fermer');
            closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

            eventPageModalFrame = document.createElement('iframe');
            eventPageModalFrame.className = 'mj-cal-eventpage-modal__frame';
            eventPageModalFrame.setAttribute('title', 'EventPage');
            eventPageModalFrame.setAttribute('loading', 'eager');
            eventPageModalFrame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            eventPageModalFrame.addEventListener('load', function() {
                // Same-origin iframe: hide site chrome so only EventPage content is visible.
                try {
                    if (!eventPageModalFrame || !eventPageModalFrame.contentDocument) {
                        return;
                    }
                    var iframeDoc = eventPageModalFrame.contentDocument;
                    var head = iframeDoc.head || iframeDoc.getElementsByTagName('head')[0];
                    if (!head) {
                        return;
                    }
                    if (iframeDoc.getElementById('mj-cal-eventpage-modal-style')) {
                        return;
                    }
                    var style = iframeDoc.createElement('style');
                    style.id = 'mj-cal-eventpage-modal-style';
                    style.textContent = [
                        '#site-header,',
                        '#site-footer,',
                        '.site-header,',
                        '.site-footer,',
                        '#masthead,',
                        '#colophon,',
                        'header[role="banner"],',
                        'footer[role="contentinfo"] { display: none !important; }',
                        'html, body { margin: 0 !important; padding: 0 !important; }'
                    ].join(' ');
                    head.appendChild(style);
                } catch (error) {
                    // Ignore when iframe document cannot be accessed.
                }
            });

            header.appendChild(eventPageModalTitle);
            header.appendChild(closeBtn);
            panel.appendChild(header);
            panel.appendChild(eventPageModalFrame);
            eventPageModal.appendChild(backdrop);
            eventPageModal.appendChild(panel);
            document.body.appendChild(eventPageModal);

            function closeEventPageModal() {
                if (!eventPageModal || eventPageModal.hidden) {
                    return;
                }
                eventPageModal.hidden = true;
                if (eventPageModalFrame) {
                    eventPageModalFrame.setAttribute('src', 'about:blank');
                }
                document.body.style.overflow = previousBodyOverflow;
            }

            backdrop.addEventListener('click', closeEventPageModal);
            closeBtn.addEventListener('click', closeEventPageModal);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeEventPageModal();
                }
            });

            eventPageModal._close = closeEventPageModal;

            return true;
        }

        function openEventPageModal(href, titleText) {
            if (!href) {
                return;
            }
            if (!ensureEventPageModal()) {
                window.location.href = href;
                return;
            }

            if (eventPageModalTitle) {
                eventPageModalTitle.textContent = titleText || 'Page événement';
            }
            if (eventPageModalFrame) {
                eventPageModalFrame.setAttribute('src', href);
            }

            previousBodyOverflow = document.body.style.overflow || '';
            document.body.style.overflow = 'hidden';
            eventPageModal.hidden = false;
        }

        // ---- Create modal (delegated to shared module) ----
        var ccmInstance = null;
        if (window.MjCreateEventModal && root.querySelector('[data-ccm-modal]')) {
            ccmInstance = window.MjCreateEventModal.init(root, config);
        }
        if (!ccmInstance && root.querySelector('[data-ccm-modal]')) {
            console.warn('[Calendar] CCM modal markup found but MjCreateEventModal.init() returned null.',
                'MjCreateEventModal available:', !!window.MjCreateEventModal);
        }

        if (config && config.openEventPageModal) {
            root.addEventListener('click', function(e) {
                var eventLink = e.target.closest('a.mj-member-events-calendar__event-trigger, a.mj-member-events-calendar__mobile-link');
                if (!eventLink || !root.contains(eventLink)) {
                    return;
                }

                var href = (eventLink.getAttribute('href') || '').trim();
                if (!href || href.charAt(0) === '#') {
                    return;
                }

                if (/^(javascript:|mailto:|tel:)/i.test(href)) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                if (mobileModal && !mobileModal.hidden && eventLink.closest('[data-calendar-mobile-modal]')) {
                    closeMobileModal();
                }

                var titleNode = eventLink.querySelector('.mj-member-events-calendar__event-title-text, .mj-member-events-calendar__mobile-title-text');
                var titleText = titleNode && titleNode.textContent ? titleNode.textContent.trim() : '';
                openEventPageModal(href, titleText);
            });
        }

        // ---- Delete occurrence handler & add-event day buttons ----
        if (config && config.ajaxUrl && config.deleteNonce) {
            root.addEventListener('click', function(e) {
                var addBtn = e.target.closest('[data-calendar-create-day]');
                if (addBtn && ccmInstance) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Close mobile modal if the button is inside it
                    if (mobileModal && !mobileModal.hidden && addBtn.closest('[data-calendar-mobile-modal]')) {
                        closeMobileModal();
                    }
                    ccmInstance.open(addBtn.getAttribute('data-calendar-create-day') || '', addBtn);
                    return;
                }

                var btn = e.target.closest('.mj-member-events-calendar__event-delete');
                if (!btn) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();

                var eventId = parseInt(btn.getAttribute('data-delete-event'), 10);
                var startTs = parseInt(btn.getAttribute('data-delete-ts'), 10);
                if (!eventId || !startTs) {
                    return;
                }

                if (!confirm('Supprimer cette occurrence ? Cette action est irr\u00e9versible.')) {
                    return;
                }

                btn.classList.add('is-loading');
                btn.disabled = true;

                var formData = new FormData();
                formData.append('action', 'mj_calendar_delete_occurrence');
                formData.append('nonce', config.deleteNonce);
                formData.append('event_id', String(eventId));
                formData.append('start_ts', String(startTs));

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        var li = btn.closest('.mj-member-events-calendar__event') || btn.closest('li');
                        if (li) {
                            li.style.transition = 'opacity 0.3s ease';
                            li.style.opacity = '0';
                            setTimeout(function() {
                                li.remove();
                                updateDayStates();
                            }, 300);
                        }
                        var mobileItem = root.querySelector('.mj-member-events-calendar__event-delete[data-delete-event="' + eventId + '"][data-delete-ts="' + startTs + '"]');
                        if (mobileItem && mobileItem !== btn) {
                            var mobileLi = mobileItem.closest('li');
                            if (mobileLi) {
                                mobileLi.style.transition = 'opacity 0.3s ease';
                                mobileLi.style.opacity = '0';
                                setTimeout(function() { mobileLi.remove(); }, 300);
                            }
                        }
                        // Also remove corresponding chip from mobile grid
                        removeMobileChipForEvent(eventId, startTs);
                    } else {
                        var msg = result.data && result.data.message ? result.data.message : 'Erreur lors de la suppression.';
                        alert(msg);
                        btn.classList.remove('is-loading');
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Erreur r\u00e9seau. Veuillez r\u00e9essayer.');
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                });
            });
        }

        // ---- Mobile compact calendar grid: day tap → modal ----
        var mobileModal = root.querySelector('[data-calendar-mobile-modal]');
        var mobileModalDate = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-date') : null;
        var mobileModalBody = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-body') : null;
        var mobileModalClose = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-close') : null;
        var mobileModalBackdrop = mobileModal ? mobileModal.querySelector('.mj-cal-mobile__modal-backdrop') : null;

        function openMobileModal(dayKey) {
            if (!mobileModal || !mobileModalBody) {
                return;
            }
            var tpl = root.querySelector('template[data-mobile-day-events="' + dayKey + '"]');
            mobileModalBody.innerHTML = '';
            if (tpl) {
                var clone = document.importNode(tpl.content, true);
                // Apply active filters to cloned content
                var filterMap = getActiveFilterMap();
                if (filterMap) {
                    toArray(clone.querySelectorAll('[data-calendar-type-item]')).forEach(function(item) {
                        var typeKey = item.getAttribute('data-calendar-type') || '';
                        var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                        if (isKnown && !Object.prototype.hasOwnProperty.call(filterMap, typeKey)) {
                            item.classList.add('is-filtered-out');
                        }
                    });
                }
                mobileModalBody.appendChild(clone);
            } else {
                mobileModalBody.innerHTML = '<p class="mj-cal-mobile__modal-empty">Aucun \u00e9v\u00e9nement</p>';
            }
            // Append "Créer un event" button at the bottom when available
            if (ccmInstance) {
                var addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'mj-cal-mobile__modal-create-event';
                addBtn.setAttribute('data-calendar-create-day', dayKey);
                addBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                    '<span>Cr\u00e9er un event</span>';
                mobileModalBody.appendChild(addBtn);
            }
            if (mobileModalDate) {
                var dateParts = dayKey.split('-');
                var dateObj = new Date(parseInt(dateParts[0], 10), parseInt(dateParts[1], 10) - 1, parseInt(dateParts[2], 10));
                var dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                var monthNames = ['janvier', 'f\u00e9vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao\u00fbt', 'septembre', 'octobre', 'novembre', 'd\u00e9cembre'];
                mobileModalDate.textContent = dayNames[dateObj.getDay()] + ' ' + dateObj.getDate() + ' ' + monthNames[dateObj.getMonth()];
            }
            mobileModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeMobileModal() {
            if (!mobileModal) {
                return;
            }
            mobileModal.hidden = true;
            mobileModalBody.innerHTML = '';
            document.body.style.overflow = '';
        }

        function getActiveFilterMap() {
            if (!filterInputsRef || !filterInputsRef.length) {
                return null;
            }
            var map = {};
            var hasChecked = false;
            for (var i = 0; i < filterInputsRef.length; i++) {
                if (filterInputsRef[i].checked) {
                    map[filterInputsRef[i].value] = true;
                    hasChecked = true;
                }
            }
            return hasChecked ? map : null;
        }

        // Lazy ref to filterInputs (set after var declarations below)
        var filterInputsRef = null;

        function removeMobileChipForEvent(eventId, startTs) {
            // Find and remove the chip in the mobile grid that corresponds to the deleted event
            // Chips don't have direct event ID data, so we find the template, check its contents,
            // and remove matching chips by counting within the day
            var tpls = toArray(root.querySelectorAll('template[data-mobile-day-events]'));
            tpls.forEach(function(tpl) {
                var delBtns = tpl.content.querySelectorAll('.mj-member-events-calendar__event-delete[data-delete-event="' + eventId + '"][data-delete-ts="' + startTs + '"]');
                if (delBtns.length > 0) {
                    // Remove the <li> in the template
                    toArray(delBtns).forEach(function(delBtn) {
                        var li = delBtn.closest('li');
                        if (li) {
                            li.remove();
                        }
                    });
                    // Also remove the chip from the visible grid (by index - find index of removed event)
                    var dayKey = tpl.getAttribute('data-mobile-day-events');
                    var dayCell = root.querySelector('.mj-cal-mobile__day[data-calendar-day="' + dayKey + '"]');
                    if (dayCell) {
                        var remainingEvents = tpl.content.querySelectorAll('.mj-member-events-calendar__mobile-event');
                        var chips = toArray(dayCell.querySelectorAll('.mj-cal-mobile__chip'));
                        // Rebuild chips to match remaining events
                        if (chips.length > remainingEvents.length) {
                            // Remove last chip (simplistic approach - works when only one deleted at a time)
                            for (var c = chips.length - 1; c >= remainingEvents.length; c--) {
                                chips[c].remove();
                            }
                        }
                        if (!remainingEvents.length) {
                            dayCell.classList.remove('has-events');
                        }
                    }
                }
            });
        }

        if (mobileModal) {
            // Day cell click handler
            root.addEventListener('click', function(e) {
                var dayCell = e.target.closest('.mj-cal-mobile__day');
                if (!dayCell) {
                    return;
                }
                // Don't open modal if clicking inside the modal itself
                if (e.target.closest('.mj-cal-mobile__modal')) {
                    return;
                }
                var dayKey = dayCell.getAttribute('data-calendar-day');
                if (!dayKey) {
                    return;
                }

                if (dayCell.classList.contains('is-padding')) {
                    return;
                }

                if (root.querySelector('template[data-mobile-day-events="' + dayKey + '"]')) {
                    openMobileModal(dayKey);
                }
            });

            // Close handlers
            if (mobileModalClose) {
                mobileModalClose.addEventListener('click', closeMobileModal);
            }
            if (mobileModalBackdrop) {
                mobileModalBackdrop.addEventListener('click', closeMobileModal);
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileModal && !mobileModal.hidden) {
                    closeMobileModal();
                }
            });
        }

        var months = toArray(root.querySelectorAll('[data-calendar-month]'));
        if (!months.length) {
            return;
        }

        var monthIndexMap = {};
        months.forEach(function(month, idx) {
            var key = month.getAttribute('data-calendar-month');
            if (key) {
                monthIndexMap[key] = idx;
            }
        });

        var filterInputs = toArray(root.querySelectorAll('[data-calendar-filter]'));
        filterInputsRef = filterInputs;
        var typeItems = toArray(root.querySelectorAll('[data-calendar-type-item]'));
        var dayNodes = toArray(root.querySelectorAll('[data-calendar-day]'));
        var countSingular = root.getAttribute('data-calendar-count-singular') || '%d';
        var countPlural = root.getAttribute('data-calendar-count-plural') || '%d';
        var countEmpty = root.getAttribute('data-calendar-count-empty') || '';

        var prev = root.querySelector('[data-calendar-nav="prev"]');
        var next = root.querySelector('[data-calendar-nav="next"]');
        var label = root.querySelector('[data-calendar-active-label]');
        var todayBtn = root.querySelector('[data-calendar-action="today"]');
        var openPrintBtn = root.querySelector('[data-calendar-action="open-print"]');
        var printNowBtn = root.querySelector('[data-calendar-action="print-now"]');
        var saveImageBtn = root.querySelector('[data-calendar-action="save-image"]');
        var printModal = root.querySelector('[data-calendar-print-modal]');
        var printPreviewFrame = root.querySelector('[data-calendar-print-preview]');
        var printCloseBtns = toArray(root.querySelectorAll('[data-calendar-print-close]'));
        var printModeInput = root.querySelector('[data-print-option="mode"]');
        var printMonthInput = root.querySelector('[data-print-option="month"]');
        var printMonthYearInput = root.querySelector('[data-print-option="month-year"]');
        var printWeekInput = root.querySelector('[data-print-option="week"]');
        var printWeekYearInput = root.querySelector('[data-print-option="week-year"]');
        var printMonthPickerWrap = root.querySelector('[data-print-month-picker]');
        var printMonthYearPickerWrap = root.querySelector('[data-print-month-year-picker]');
        var printWeekPickerWrap = root.querySelector('[data-print-week-picker]');
        var printWeekYearPickerWrap = root.querySelector('[data-print-week-year-picker]');
        var printPadPageInput = root.querySelector('[data-print-option="pad-page"]');
        var printPadDayInput = root.querySelector('[data-print-option="pad-day"]');
        var printPadEventInput = root.querySelector('[data-print-option="pad-event"]');
        var printThemeInput = root.querySelector('[data-print-option="theme"]');
        var printSpanInput = root.querySelector('[data-print-option="span"]');
        var printDetailsInput = root.querySelector('[data-print-option="details"]');
        var printCoverInput = root.querySelector('[data-print-option="cover"]');
        var printTimeRangeInput = root.querySelector('[data-print-option="time-range"]');
        var printEventEmojiInput = root.querySelector('[data-print-option="event-emoji"]');
        var printEventColorInput = root.querySelector('[data-print-option="event-color"]');
        var printHeaderImageInput = root.querySelector('[data-print-option="header-image"]');
        var printFooterImageInput = root.querySelector('[data-print-option="footer-image"]');
        var printPageBreakInput = root.querySelector('[data-print-option="page-break"]');
        var printPageBreakLabel = root.querySelector('[data-print-option-page-break-label]');
        var printTypeFiltersWrap = root.querySelector('[data-print-type-filters]');
        var printTypeFilterInputs = [];
        var printSelectedMonthKey = '';
        var printSelectedWeekStartKey = '';
        var printMonthEntries = [];
        var printWeekEntries = [];
        var printConfig = (config && config.print) ? config.print : {};
        var printPrefsEnabled = !!(printConfig && printConfig.userPrefsEnabled && printConfig.ajaxUrl && printConfig.prefsNonce);
        var printPrefsFromServer = (printConfig && printConfig.userPrefs && typeof printConfig.userPrefs === 'object') ? printConfig.userPrefs : null;
        var printPrefsSaveTimer = null;
        var isApplyingPrintPrefs = false;
        var hasAppliedPrintPrefs = false;
        var lastPrintSnapshot = null;
        var todayMonthKey = root.getAttribute('data-calendar-today') || '';
        if (config && config.todayMonth) {
            todayMonthKey = config.todayMonth;
        }
        var activeIndex = 0;
        var weekOnly = root.getAttribute('data-calendar-week-only') === '1';
        var weekDaysAttr = root.getAttribute('data-calendar-week-days') || '';
        var weekDays = weekOnly && weekDaysAttr ? weekDaysAttr.split(',').map(function(item) {
            return item.trim();
        }).filter(function(value) {
            return value.length > 0;
        }) : [];

        if (weekOnly && weekDays.length) {
            var weekDaySet = {};
            weekDays.forEach(function(dayKey) {
                weekDaySet[dayKey] = true;
            });

            var visibleMonths = [];
            months.forEach(function(month) {
                var keepMonth = false;
                var weeks = toArray(month.querySelectorAll('.mj-member-events-calendar__week'));
                weeks.forEach(function(week) {
                    var containsTargetDay = false;
                    var dayCells = toArray(week.querySelectorAll('[data-calendar-day]'));
                    dayCells.forEach(function(dayCell) {
                        var dayKey = dayCell.getAttribute('data-calendar-day');
                        if (dayKey && Object.prototype.hasOwnProperty.call(weekDaySet, dayKey)) {
                            containsTargetDay = true;
                        }
                    });

                    if (!containsTargetDay) {
                        week.style.display = 'none';
                    } else {
                        keepMonth = true;
                    }
                });

                if (keepMonth) {
                    visibleMonths.push(month);
                } else {
                    month.style.display = 'none';
                    month.classList.remove('is-active');
                }
            });

            if (visibleMonths.length) {
                months = visibleMonths;
                monthIndexMap = {};
                months.forEach(function(month, idx) {
                    var key = month.getAttribute('data-calendar-month');
                    if (key) {
                        monthIndexMap[key] = idx;
                    }
                });
            }

            activeIndex = 0;
            root.setAttribute('data-calendar-preferred', '0');
            if (prev) {
                prev.disabled = true;
                prev.style.display = 'none';
            }
            if (next) {
                next.disabled = true;
                next.style.display = 'none';
            }
            if (todayBtn) {
                todayBtn.disabled = true;
                todayBtn.style.display = 'none';
            }
        }

        var todayIndex = -1;
        if (todayMonthKey && Object.prototype.hasOwnProperty.call(monthIndexMap, todayMonthKey)) {
            todayIndex = monthIndexMap[todayMonthKey];
        }

        activeIndex = 0;
        var preferred = parseInt(root.getAttribute('data-calendar-preferred'), 10);
        if (!isNaN(preferred) && preferred >= 0 && preferred < months.length) {
            activeIndex = preferred;
        } else if (config && typeof config.preferredIndex === 'number' && config.preferredIndex >= 0 && config.preferredIndex < months.length) {
            activeIndex = config.preferredIndex;
        }

        function updateDayStates() {
            if (!dayNodes.length) {
                return;
            }
            dayNodes.forEach(function(dayNode) {
                var items = dayNode.querySelectorAll('[data-calendar-type-item]');
                var visibleCount = 0;
                toArray(items).forEach(function(item) {
                    if (!item.classList.contains('is-filtered-out')) {
                        visibleCount += 1;
                    }
                });
                if (visibleCount === 0) {
                    dayNode.classList.add('is-filtered-empty');
                } else {
                    dayNode.classList.remove('is-filtered-empty');
                }
                var countNode = dayNode.querySelector('[data-calendar-day-count]');
                if (countNode) {
                    var countLabel;
                    if (visibleCount === 0) {
                        countLabel = countEmpty || '';
                    } else if (visibleCount === 1) {
                        countLabel = countSingular.replace('%d', '1');
                    } else {
                        countLabel = countPlural.replace('%d', String(visibleCount));
                    }
                    countNode.textContent = countLabel;
                }
            });
        }

        function applyFilters() {
            if (!filterInputs.length) {
                updateDayStates();
                refreshPrintPreview();
                return;
            }

            var activeMap = {};
            var hasChecked = false;
            filterInputs.forEach(function(input) {
                if (input.checked) {
                    activeMap[input.value] = true;
                    hasChecked = true;
                }
            });

            typeItems.forEach(function(item) {
                var typeKey = item.getAttribute('data-calendar-type') || '';
                var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                if (!hasChecked || !isKnown || Object.prototype.hasOwnProperty.call(activeMap, typeKey)) {
                    item.classList.remove('is-filtered-out');
                } else {
                    item.classList.add('is-filtered-out');
                }
            });

            updateDayStates();
            refreshPrintPreview();
        }

        function escapeHtml(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function startOfWeekMonday(date) {
            var start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            var day = start.getDay();
            var diff = (day + 6) % 7;
            start.setDate(start.getDate() - diff);
            start.setHours(0, 0, 0, 0);
            return start;
        }

        function addDays(date, days) {
            var nextDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            nextDate.setDate(nextDate.getDate() + days);
            return nextDate;
        }

        function formatDayKey(date) {
            var y = String(date.getFullYear());
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        function getPrintMode() {
            if (!printModeInput) {
                return 'week';
            }
            return printModeInput.value === 'month' ? 'month' : 'week';
        }

        function getPrintTheme() {
            if (printThemeInput) {
                return printThemeInput.value === 'dark' ? 'dark' : 'light';
            }
            if (printConfig && printConfig.defaultTheme === 'dark') {
                return 'dark';
            }
            return 'light';
        }

        function parseMonthKey(monthKey) {
            if (!/^\d{4}-\d{2}$/.test(monthKey || '')) {
                return null;
            }
            var parts = monthKey.split('-');
            var year = parseInt(parts[0], 10);
            var monthIndex = parseInt(parts[1], 10) - 1;
            if (isNaN(year) || isNaN(monthIndex) || monthIndex < 0 || monthIndex > 11) {
                return null;
            }
            return new Date(year, monthIndex, 1);
        }

        function parseDayKey(dayKey) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dayKey || '')) {
                return null;
            }
            var parts = dayKey.split('-');
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) - 1;
            var d = parseInt(parts[2], 10);
            if (isNaN(y) || isNaN(m) || isNaN(d)) {
                return null;
            }
            return new Date(y, m, d);
        }

        function getIsoWeekInfo(dateObj) {
            var tmp = new Date(Date.UTC(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate()));
            var weekday = tmp.getUTCDay() || 7;
            tmp.setUTCDate(tmp.getUTCDate() + 4 - weekday);
            var yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
            var week = Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7);
            return {
                year: tmp.getUTCFullYear(),
                week: week
            };
        }

        function createMonthLabel(dateObj) {
            try {
                return dateObj.toLocaleDateString('fr-BE', { month: 'long' });
            } catch (error) {
                return String(dateObj.getMonth() + 1);
            }
        }

        function getDistinctYears(entries) {
            var out = [];
            var seen = {};
            entries.forEach(function(entry) {
                var key = String(entry.year);
                if (Object.prototype.hasOwnProperty.call(seen, key)) {
                    return;
                }
                seen[key] = true;
                out.push(entry.year);
            });
            out.sort(function(a, b) {
                return a - b;
            });
            return out;
        }

        function rebuildSelectOptions(selectEl, options, selectedValue) {
            if (!selectEl) {
                return;
            }
            selectEl.innerHTML = '';
            options.forEach(function(opt) {
                var optionEl = document.createElement('option');
                optionEl.value = String(opt.value);
                optionEl.textContent = String(opt.label);
                if (String(opt.value) === String(selectedValue)) {
                    optionEl.selected = true;
                }
                selectEl.appendChild(optionEl);
            });
        }

        function buildPrintPeriodEntries() {
            var monthSeen = {};
            var monthList = [];
            months.forEach(function(monthEl) {
                var monthKey = (monthEl.getAttribute('data-calendar-month') || '').trim();
                if (!monthKey || Object.prototype.hasOwnProperty.call(monthSeen, monthKey)) {
                    return;
                }
                var monthDate = parseMonthKey(monthKey);
                if (!monthDate) {
                    return;
                }
                monthSeen[monthKey] = true;
                monthList.push({
                    key: monthKey,
                    year: monthDate.getFullYear(),
                    month: monthDate.getMonth() + 1,
                    monthLabel: createMonthLabel(monthDate),
                    sortKey: monthKey
                });
            });
            monthList.sort(function(a, b) {
                return a.sortKey.localeCompare(b.sortKey);
            });
            printMonthEntries = monthList;

            var weekSeen = {};
            var weekList = [];
            dayNodes.forEach(function(dayNode) {
                var dayKey = (dayNode.getAttribute('data-calendar-day') || '').trim();
                var dayDate = parseDayKey(dayKey);
                if (!dayDate) {
                    return;
                }
                var weekStart = startOfWeekMonday(dayDate);
                var weekStartKey = formatDayKey(weekStart);
                if (Object.prototype.hasOwnProperty.call(weekSeen, weekStartKey)) {
                    return;
                }
                weekSeen[weekStartKey] = true;
                var iso = getIsoWeekInfo(weekStart);
                weekList.push({
                    key: weekStartKey,
                    year: iso.year,
                    week: iso.week,
                    weekLabel: 'S' + String(iso.week).padStart(2, '0'),
                    sortDate: weekStart.getTime()
                });
            });
            weekList.sort(function(a, b) {
                return a.sortDate - b.sortDate;
            });
            printWeekEntries = weekList;
        }

        function setDefaultPrintPeriodSelection() {
            var activeMonthEl = months[activeIndex] || months[0] || null;
            var activeMonthKey = activeMonthEl ? (activeMonthEl.getAttribute('data-calendar-month') || '') : '';
            if (!activeMonthKey && printMonthEntries.length) {
                activeMonthKey = printMonthEntries[0].key;
            }

            if (activeMonthKey) {
                printSelectedMonthKey = activeMonthKey;
                var monthDate = parseMonthKey(activeMonthKey);
                if (monthDate) {
                    var weekStart = startOfWeekMonday(monthDate);
                    var weekStartKey = formatDayKey(weekStart);
                    printSelectedWeekStartKey = weekStartKey;
                }
            }

            if (!printSelectedWeekStartKey && printWeekEntries.length) {
                printSelectedWeekStartKey = printWeekEntries[0].key;
            }
        }

        function renderPrintPeriodSelectors() {
            var mode = getPrintMode();

            if (printMonthPickerWrap) {
                printMonthPickerWrap.hidden = mode !== 'month';
            }
            if (printMonthYearPickerWrap) {
                printMonthYearPickerWrap.hidden = mode !== 'month';
            }
            if (printWeekPickerWrap) {
                printWeekPickerWrap.hidden = mode !== 'week';
            }
            if (printWeekYearPickerWrap) {
                printWeekYearPickerWrap.hidden = mode !== 'week';
            }

            if (printMonthYearInput && printMonthInput) {
                var monthYears = getDistinctYears(printMonthEntries);
                var selectedMonthEntry = null;
                if (printSelectedMonthKey) {
                    selectedMonthEntry = printMonthEntries.find(function(entry) {
                        return entry.key === printSelectedMonthKey;
                    }) || null;
                }
                var selectedMonthYear = '';
                if (printMonthYearInput.value) {
                    selectedMonthYear = printMonthYearInput.value;
                } else if (selectedMonthEntry) {
                    selectedMonthYear = selectedMonthEntry.year;
                } else {
                    selectedMonthYear = monthYears.length ? monthYears[0] : '';
                }

                rebuildSelectOptions(printMonthYearInput, monthYears.map(function(year) {
                    return { value: String(year), label: String(year) };
                }), String(selectedMonthYear));

                var monthEntriesForYear = printMonthEntries.filter(function(entry) {
                    return String(entry.year) === String(selectedMonthYear);
                });
                rebuildSelectOptions(printMonthInput, monthEntriesForYear.map(function(entry) {
                    return {
                        value: entry.key,
                        label: entry.monthLabel
                    };
                }), printSelectedMonthKey);

                if (monthEntriesForYear.length) {
                    var hasSelectedMonth = monthEntriesForYear.some(function(entry) {
                        return entry.key === printSelectedMonthKey;
                    });
                    if (!hasSelectedMonth) {
                        printSelectedMonthKey = monthEntriesForYear[0].key;
                        printMonthInput.value = printSelectedMonthKey;
                    }
                }
            }

            if (printWeekYearInput && printWeekInput) {
                var weekYears = getDistinctYears(printWeekEntries);
                var selectedWeekEntry = null;
                if (printSelectedWeekStartKey) {
                    selectedWeekEntry = printWeekEntries.find(function(entry) {
                        return entry.key === printSelectedWeekStartKey;
                    }) || null;
                }
                var selectedWeekYear = '';
                if (printWeekYearInput.value) {
                    selectedWeekYear = printWeekYearInput.value;
                } else if (selectedWeekEntry) {
                    selectedWeekYear = selectedWeekEntry.year;
                } else {
                    selectedWeekYear = weekYears.length ? weekYears[0] : '';
                }

                rebuildSelectOptions(printWeekYearInput, weekYears.map(function(year) {
                    return { value: String(year), label: String(year) };
                }), String(selectedWeekYear));

                var weekEntriesForYear = printWeekEntries.filter(function(entry) {
                    return String(entry.year) === String(selectedWeekYear);
                });
                rebuildSelectOptions(printWeekInput, weekEntriesForYear.map(function(entry) {
                    return {
                        value: entry.key,
                        label: entry.weekLabel
                    };
                }), printSelectedWeekStartKey);

                if (weekEntriesForYear.length) {
                    var hasSelectedWeek = weekEntriesForYear.some(function(entry) {
                        return entry.key === printSelectedWeekStartKey;
                    });
                    if (!hasSelectedWeek) {
                        printSelectedWeekStartKey = weekEntriesForYear[0].key;
                        printWeekInput.value = printSelectedWeekStartKey;
                    }
                }
            }
        }

        function getSelectedMonthStartIndex() {
            if (printSelectedMonthKey && Object.prototype.hasOwnProperty.call(monthIndexMap, printSelectedMonthKey)) {
                return monthIndexMap[printSelectedMonthKey];
            }
            return activeIndex;
        }

        function getSelectedWeekAnchorDate() {
            if (printSelectedWeekStartKey) {
                var selected = parseDayKey(printSelectedWeekStartKey);
                if (selected) {
                    return startOfWeekMonday(selected);
                }
            }

            var monthEl = months[activeIndex] || months[0];
            var monthKey = monthEl ? (monthEl.getAttribute('data-calendar-month') || '') : '';
            var monthDate = parseMonthKey(monthKey);
            if (!monthDate) {
                return null;
            }
            return startOfWeekMonday(monthDate);
        }

        function getPrintSpan() {
            var defaultSpan = (printConfig && typeof printConfig.defaultSpan === 'number') ? printConfig.defaultSpan : 1;
            var span = printSpanInput ? parseInt(printSpanInput.value, 10) : defaultSpan;
            if (isNaN(span)) {
                span = defaultSpan;
            }
            if (span < 1) {
                span = 1;
            }
            if (span > 12) {
                span = 12;
            }
            if (printSpanInput) {
                printSpanInput.value = String(span);
            }
            return span;
        }

        function getPrintPaddingValue(input, min, max, fallback) {
            var value = input ? parseInt(input.value, 10) : fallback;
            if (isNaN(value)) {
                value = fallback;
            }
            if (value < min) {
                value = min;
            }
            if (value > max) {
                value = max;
            }
            if (input) {
                input.value = String(value);
            }
            return value;
        }

        function isDetailsEnabled() {
            return !!(printDetailsInput && printDetailsInput.checked);
        }

        function isCoverEnabled() {
            return !!(printCoverInput && printCoverInput.checked);
        }

        function isTimeRangeEnabled() {
            if (printTimeRangeInput) {
                return !!printTimeRangeInput.checked;
            }
            return !printConfig || typeof printConfig.defaultTimeRange === 'undefined' ? true : !!printConfig.defaultTimeRange;
        }

        function isPageBreakEnabled() {
            return !!(printPageBreakInput && printPageBreakInput.checked);
        }

        function isEventEmojiEnabled() {
            if (printEventEmojiInput) {
                return !!printEventEmojiInput.checked;
            }
            if (!printConfig) {
                return true;
            }
            if (typeof printConfig.defaultEventEmoji !== 'undefined') {
                return !!printConfig.defaultEventEmoji;
            }
            return typeof printConfig.defaultEventStyle === 'undefined' ? true : !!printConfig.defaultEventStyle;
        }

        function isEventColorEnabled() {
            if (printEventColorInput) {
                return !!printEventColorInput.checked;
            }
            if (!printConfig) {
                return true;
            }
            if (typeof printConfig.defaultEventColor !== 'undefined') {
                return !!printConfig.defaultEventColor;
            }
            return typeof printConfig.defaultEventStyle === 'undefined' ? true : !!printConfig.defaultEventStyle;
        }

        function isHeaderImageEnabled() {
            if (printHeaderImageInput) {
                return !!printHeaderImageInput.checked;
            }
            return !!(printConfig && printConfig.defaultHeaderImage && printConfig.headerImageUrl);
        }

        function isFooterImageEnabled() {
            if (printFooterImageInput) {
                return !!printFooterImageInput.checked;
            }
            return !!(printConfig && printConfig.defaultFooterImage && printConfig.footerImageUrl);
        }

        function formatTypeLabel(typeKey) {
            if (!typeKey) {
                return 'Type';
            }
            return String(typeKey)
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/(^|\s)\S/g, function(c) { return c.toUpperCase(); });
        }

        function getAvailablePrintTypes() {
            var types = [];
            var seen = {};

            if (filterInputs && filterInputs.length) {
                filterInputs.forEach(function(input) {
                    var key = (input.value || '').trim();
                    if (!key || Object.prototype.hasOwnProperty.call(seen, key)) {
                        return;
                    }
                    seen[key] = true;
                    var labelNode = input.parentElement ? input.parentElement.querySelector('span') : null;
                    types.push({
                        key: key,
                        label: labelNode ? (labelNode.textContent || '').trim() : formatTypeLabel(key),
                        checked: !!input.checked
                    });
                });
                if (types.length) {
                    return types;
                }
            }

            typeItems.forEach(function(item) {
                var key = (item.getAttribute('data-calendar-type') || '').trim();
                var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                if (!isKnown || !key || Object.prototype.hasOwnProperty.call(seen, key)) {
                    return;
                }
                seen[key] = true;
                types.push({ key: key, label: formatTypeLabel(key), checked: true });
            });

            return types;
        }

        function renderPrintTypeFilters() {
            if (!printTypeFiltersWrap) {
                return;
            }

            var types = getAvailablePrintTypes();
            var labels = (printConfig && printConfig.labels) ? printConfig.labels : {};
            if (!types.length) {
                printTypeFiltersWrap.hidden = true;
                return;
            }

            printTypeFiltersWrap.hidden = false;
            printTypeFiltersWrap.innerHTML = '<legend>' + escapeHtml(labels.typesLegend || 'Types d\'événement') + '</legend>';
            printTypeFilterInputs = [];

            types.forEach(function(typeDef) {
                var labelEl = document.createElement('label');
                labelEl.className = 'mj-cal-print__option';

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = !!typeDef.checked;
                checkbox.setAttribute('data-print-type-filter', typeDef.key);

                var textSpan = document.createElement('span');
                textSpan.textContent = typeDef.label || formatTypeLabel(typeDef.key);

                labelEl.appendChild(checkbox);
                labelEl.appendChild(textSpan);
                printTypeFiltersWrap.appendChild(labelEl);
                printTypeFilterInputs.push(checkbox);

                checkbox.addEventListener('change', function() {
                    refreshPrintPreview();
                    queueSavePrintPrefs();
                });
            });
        }

        function getSelectedPrintTypesMap() {
            if (!printTypeFilterInputs || !printTypeFilterInputs.length) {
                return null;
            }
            var selected = {};
            var hasSelected = false;
            printTypeFilterInputs.forEach(function(input) {
                if (!input || !input.checked) {
                    return;
                }
                var key = (input.getAttribute('data-print-type-filter') || '').trim();
                if (!key) {
                    return;
                }
                selected[key] = true;
                hasSelected = true;
            });

            if (!hasSelected) {
                return {};
            }

            return selected;
        }

        function applyPrintPrefsToInputs(prefs) {
            if (!prefs || typeof prefs !== 'object') {
                return;
            }

            isApplyingPrintPrefs = true;

            if (printModeInput && (prefs.mode === 'week' || prefs.mode === 'month')) {
                printModeInput.value = prefs.mode;
            }
            if (printThemeInput && (prefs.theme === 'light' || prefs.theme === 'dark')) {
                printThemeInput.value = prefs.theme;
            }
            if (printSpanInput && typeof prefs.span !== 'undefined') {
                printSpanInput.value = String(prefs.span);
            }
            if (printDetailsInput && typeof prefs.details !== 'undefined') {
                printDetailsInput.checked = !!prefs.details;
            }
            if (printCoverInput && typeof prefs.cover !== 'undefined') {
                printCoverInput.checked = !!prefs.cover;
            }
            if (printTimeRangeInput && typeof prefs.timeRange !== 'undefined') {
                printTimeRangeInput.checked = !!prefs.timeRange;
            }
            if (printEventEmojiInput && typeof prefs.eventEmoji !== 'undefined') {
                printEventEmojiInput.checked = !!prefs.eventEmoji;
            }
            if (printEventColorInput && typeof prefs.eventColor !== 'undefined') {
                printEventColorInput.checked = !!prefs.eventColor;
            }
            if (printHeaderImageInput && typeof prefs.headerImage !== 'undefined') {
                printHeaderImageInput.checked = !!prefs.headerImage;
            }
            if (printFooterImageInput && typeof prefs.footerImage !== 'undefined') {
                printFooterImageInput.checked = !!prefs.footerImage;
            }
            if (printPageBreakInput && typeof prefs.pageBreak !== 'undefined') {
                printPageBreakInput.checked = !!prefs.pageBreak;
            }
            if (printPadPageInput && typeof prefs.padPage !== 'undefined') {
                printPadPageInput.value = String(prefs.padPage);
            }
            if (printPadDayInput && typeof prefs.padDay !== 'undefined') {
                printPadDayInput.value = String(prefs.padDay);
            }
            if (printPadEventInput && typeof prefs.padEvent !== 'undefined') {
                printPadEventInput.value = String(prefs.padEvent);
            }

            if (typeof prefs.monthKey === 'string' && /^\d{4}-\d{2}$/.test(prefs.monthKey)) {
                printSelectedMonthKey = prefs.monthKey;
            }
            if (typeof prefs.weekStartKey === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(prefs.weekStartKey)) {
                printSelectedWeekStartKey = prefs.weekStartKey;
            }

            renderPrintPeriodSelectors();

            if (Array.isArray(prefs.selectedTypes) && printTypeFilterInputs && printTypeFilterInputs.length) {
                var selectedMap = {};
                prefs.selectedTypes.forEach(function(typeKey) {
                    var clean = String(typeKey || '').trim();
                    if (clean) {
                        selectedMap[clean] = true;
                    }
                });
                printTypeFilterInputs.forEach(function(input) {
                    var key = (input.getAttribute('data-print-type-filter') || '').trim();
                    input.checked = Object.prototype.hasOwnProperty.call(selectedMap, key);
                });
            }

            isApplyingPrintPrefs = false;
        }

        function collectPrintPrefsFromInputs() {
            var selectedTypes = [];
            if (printTypeFilterInputs && printTypeFilterInputs.length) {
                printTypeFilterInputs.forEach(function(input) {
                    if (!input || !input.checked) {
                        return;
                    }
                    var typeKey = (input.getAttribute('data-print-type-filter') || '').trim();
                    if (typeKey) {
                        selectedTypes.push(typeKey);
                    }
                });
            }

            return {
                mode: getPrintMode(),
                theme: getPrintTheme(),
                span: getPrintSpan(),
                details: isDetailsEnabled(),
                cover: isCoverEnabled(),
                timeRange: isTimeRangeEnabled(),
                eventEmoji: isEventEmojiEnabled(),
                eventColor: isEventColorEnabled(),
                headerImage: isHeaderImageEnabled(),
                footerImage: isFooterImageEnabled(),
                pageBreak: isPageBreakEnabled(),
                padPage: getPrintPaddingValue(printPadPageInput, 0, 24, 10),
                padDay: getPrintPaddingValue(printPadDayInput, 0, 16, 6),
                padEvent: getPrintPaddingValue(printPadEventInput, 0, 16, 6),
                monthKey: printSelectedMonthKey || '',
                weekStartKey: printSelectedWeekStartKey || '',
                selectedTypes: selectedTypes
            };
        }

        function savePrintPrefsNow() {
            if (!printPrefsEnabled || isApplyingPrintPrefs || !printConfig || !printConfig.ajaxUrl || !printConfig.prefsNonce) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'mj_member_calendar_print_prefs_save');
            formData.append('nonce', String(printConfig.prefsNonce));
            formData.append('prefs', JSON.stringify(collectPrintPrefsFromInputs()));

            fetch(printConfig.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).catch(function() {
                // Silent fail: preview remains functional even if persistence fails.
            });
        }

        function queueSavePrintPrefs() {
            if (!printPrefsEnabled || isApplyingPrintPrefs) {
                return;
            }
            if (printPrefsSaveTimer) {
                clearTimeout(printPrefsSaveTimer);
            }
            printPrefsSaveTimer = setTimeout(function() {
                printPrefsSaveTimer = null;
                savePrintPrefsNow();
            }, 350);
        }

        function getDayNodeByKey(dayKey) {
            if (!dayKey) {
                return null;
            }
            return root.querySelector('.mj-member-events-calendar__month [data-calendar-day="' + dayKey + '"]');
        }

        function normalizeHexColor(value) {
            if (!value) {
                return '';
            }
            var str = String(value).trim();
            if (/^#[0-9a-fA-F]{3}$/.test(str) || /^#[0-9a-fA-F]{6}$/.test(str)) {
                return str;
            }
            return '';
        }

        function hexToRgba(value, alpha) {
            var hex = normalizeHexColor(value);
            if (!hex) {
                return '';
            }

            var normalized = hex;
            if (normalized.length === 4) {
                normalized = '#' + normalized.charAt(1) + normalized.charAt(1) + normalized.charAt(2) + normalized.charAt(2) + normalized.charAt(3) + normalized.charAt(3);
            }

            var r = parseInt(normalized.slice(1, 3), 16);
            var g = parseInt(normalized.slice(3, 5), 16);
            var b = parseInt(normalized.slice(5, 7), 16);
            if (isNaN(r) || isNaN(g) || isNaN(b)) {
                return '';
            }

            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
        }

        function collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap) {
            if (!dayNode) {
                return [];
            }

            var list = [];
            var items = toArray(dayNode.querySelectorAll('.mj-member-events-calendar__event[data-calendar-type-item]'));
            items.forEach(function(item) {
                if (item.classList.contains('is-filtered-out')) {
                    return;
                }

                if (selectedTypesMap) {
                    var itemType = (item.getAttribute('data-calendar-type') || '').trim();
                    if (!itemType || !Object.prototype.hasOwnProperty.call(selectedTypesMap, itemType)) {
                        return;
                    }
                }

                var titleNode = item.querySelector('.mj-member-events-calendar__event-title-text');
                var metaNode = item.querySelector('.mj-member-events-calendar__event-meta');
                var typeNode = item.querySelector('.mj-member-events-calendar__event-type');
                var detailsNode = item.querySelector('.mj-member-events-calendar__event-preview-description');
                var coverNode = item.querySelector('.mj-member-events-calendar__event-preview-cover img, .mj-member-events-calendar__event-thumb img');
                var emojiNode = item.querySelector('[data-calendar-emoji]');

                var title = titleNode ? (titleNode.textContent || '').trim() : '';
                if (!title) {
                    return;
                }

                var entry = {
                    title: title,
                    meta: metaNode ? (metaNode.textContent || '').trim() : '',
                    type: typeNode ? (typeNode.textContent || '').trim() : '',
                    details: withDetails && detailsNode ? (detailsNode.textContent || '').trim() : '',
                    cover: withCover && coverNode ? (coverNode.getAttribute('src') || '').trim() : '',
                    emoji: emojiNode ? (emojiNode.getAttribute('data-calendar-emoji') || '').trim() : '',
                    accentColor: normalizeHexColor(item.getAttribute('data-calendar-accent-color') || '')
                };
                list.push(entry);
            });

            return list;
        }

        function formatDateLabel(dateObj, withWeekday) {
            var options = withWeekday
                ? { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }
                : { day: '2-digit', month: 'long', year: 'numeric' };
            try {
                return dateObj.toLocaleDateString('fr-BE', options);
            } catch (error) {
                return dateObj.toLocaleDateString(undefined, options);
            }
        }

        function collectWeekPeriods(span, withDetails, withCover, selectedTypesMap, weekAnchorDate) {
            var periods = [];
            if (!months[activeIndex] && !weekAnchorDate) {
                return periods;
            }

            var anchor = weekAnchorDate;
            if (!anchor) {
                var monthEl = months[activeIndex];
                var monthKey = monthEl ? (monthEl.getAttribute('data-calendar-month') || '') : '';
                if (!/^\d{4}-\d{2}$/.test(monthKey)) {
                    return periods;
                }
                var firstOfMonth = new Date(monthKey + '-01T00:00:00');
                anchor = startOfWeekMonday(firstOfMonth);
            }

            if (!anchor) {
                return periods;
            }

            for (var i = 0; i < span; i += 1) {
                var weekStart = addDays(anchor, i * 7);
                var weekEnd = addDays(weekStart, 6);
                var cells = [];

                for (var d = 0; d < 7; d += 1) {
                    var dayDate = addDays(weekStart, d);
                    var dayKey = formatDayKey(dayDate);
                    var dayNode = getDayNodeByKey(dayKey);
                    var events = collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap);
                    cells.push({
                        isPadding: false,
                        dayNumber: String(dayDate.getDate()),
                        label: formatDateLabel(dayDate, true),
                        events: events
                    });
                }

                periods.push({
                    title: 'Semaine du ' + formatDateLabel(weekStart, false) + ' au ' + formatDateLabel(weekEnd, false),
                    weeks: [{ cells: cells }]
                });
            }

            return periods;
        }

        function collectMonthPeriods(span, withDetails, withCover, selectedTypesMap, monthStartIndex) {
            var periods = [];
            var startIndex = typeof monthStartIndex === 'number' ? monthStartIndex : activeIndex;
            for (var i = 0; i < span; i += 1) {
                var monthEl = months[startIndex + i];
                if (!monthEl) {
                    break;
                }

                var period = {
                    title: monthEl.getAttribute('data-calendar-label') || ('Mois ' + (i + 1)),
                    weeks: []
                };

                var weekNodes = toArray(monthEl.querySelectorAll('.mj-member-events-calendar__week'));
                weekNodes.forEach(function(weekNode) {
                    var weekData = { cells: [] };
                    var dayCells = toArray(weekNode.querySelectorAll('.mj-member-events-calendar__day-cell'));
                    dayCells.forEach(function(dayCell) {
                        var dayNode = dayCell.querySelector('.mj-member-events-calendar__day[data-calendar-day]');
                        if (!dayNode) {
                            weekData.cells.push({
                                isPadding: true,
                                dayNumber: '',
                                label: '',
                                events: []
                            });
                            return;
                        }

                        var dayKey = dayNode.getAttribute('data-calendar-day') || '';
                        var dayNumberNode = dayNode.querySelector('.mj-member-events-calendar__day-number');
                        var parts = dayKey.split('-');
                        var dayDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                        var events = collectEventsFromDay(dayNode, withDetails, withCover, selectedTypesMap);
                        weekData.cells.push({
                            isPadding: false,
                            dayNumber: dayNumberNode ? (dayNumberNode.textContent || '').trim() : String(dayDate.getDate()),
                            label: formatDateLabel(dayDate, true),
                            events: events
                        });
                    });

                    period.weeks.push(weekData);
                });

                periods.push(period);
            }

            return periods;
        }

        function hasEventsInPeriod(period) {
            if (!period || !period.weeks || !period.weeks.length) {
                return false;
            }

            for (var wi = 0; wi < period.weeks.length; wi += 1) {
                var week = period.weeks[wi];
                if (!week || !week.cells) {
                    continue;
                }
                for (var ci = 0; ci < week.cells.length; ci += 1) {
                    var cell = week.cells[ci];
                    if (cell && cell.events && cell.events.length) {
                        return true;
                    }
                }
            }

            return false;
        }

        function buildPrintDocumentHtml(periods, options) {
            var blocks = [];
            var labels = (printConfig && printConfig.labels) ? printConfig.labels : {};
            var weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            var headerImageUrl = options.headerImage && options.headerImageUrl ? String(options.headerImageUrl) : '';
            var footerImageUrl = options.footerImage && options.footerImageUrl ? String(options.footerImageUrl) : '';
            var theme = options.theme === 'dark' ? 'dark' : 'light';
            var isDarkTheme = theme === 'dark';
            var pageBg = isDarkTheme ? '#0f1115' : '#ffffff';
            var pageText = isDarkTheme ? '#f5f7fa' : '#111111';
            var titleBorder = isDarkTheme ? '#2a2f36' : '#dddddd';
            var weekdayText = isDarkTheme ? '#aeb6c2' : '#666666';
            var dayBorder = isDarkTheme ? '#2f3742' : '#e4e4e4';
            var dayBg = isDarkTheme ? '#171c23' : '#ffffff';
            var dayPaddingBg = isDarkTheme ? '#141920' : '#fafafa';
            var dayHead = isDarkTheme ? '#d6dde8' : '#444444';
            var eventBorder = isDarkTheme ? '#323a46' : '#efefef';
            var eventBg = isDarkTheme ? '#202733' : '#ffffff';
            var eventTitle = isDarkTheme ? '#f6f8fc' : '#111111';
            var eventMeta = isDarkTheme ? '#c2cad7' : '#444444';
            var typeLabelBorder = isDarkTheme ? '#3c4552' : '#d7d7d7';
            var typeLabelBg = isDarkTheme ? '#2a3240' : '#f6f6f6';
            var typeLabelColor = isDarkTheme ? '#e2e7f0' : '#444444';
            var emptyText = isDarkTheme ? '#aeb6c2' : '#666666';

            if (periods.length) {
                periods.forEach(function(period, idx) {
                    var periodHtml = [];
                    periodHtml.push('<section class="mj-print-period' + (options.pageBreak && idx < periods.length - 1 ? ' has-break' : '') + '">');
                    periodHtml.push('<h2>' + escapeHtml(period.title) + '</h2>');

                    periodHtml.push('<div class="mj-print-cal">');
                    periodHtml.push('<div class="mj-print-cal__weekdays">');
                    weekdayLabels.forEach(function(wd) {
                        periodHtml.push('<span>' + escapeHtml(wd) + '</span>');
                    });
                    periodHtml.push('</div>');

                    period.weeks.forEach(function(week) {
                        periodHtml.push('<div class="mj-print-cal__week">');
                        (week.cells || []).forEach(function(cell) {
                            if (!cell || cell.isPadding) {
                                periodHtml.push('<div class="mj-print-cal__day is-padding"></div>');
                                return;
                            }

                            periodHtml.push('<div class="mj-print-cal__day">');
                            periodHtml.push('<div class="mj-print-cal__day-head" title="' + escapeHtml(cell.label || '') + '">' + escapeHtml(cell.dayNumber || '') + '</div>');
                            periodHtml.push('<div class="mj-print-cal__events">');
                            (cell.events || []).forEach(function(eventItem) {
                                var eventStyleAttr = '';
                                if (options.eventColor && eventItem.accentColor) {
                                    var bgColor = hexToRgba(eventItem.accentColor, 0.16);
                                    var borderColor = hexToRgba(eventItem.accentColor, 0.45);
                                    if (bgColor && borderColor) {
                                        eventStyleAttr = ' style="background:' + escapeHtml(bgColor) + ';border-color:' + escapeHtml(borderColor) + ';"';
                                    }
                                }
                                periodHtml.push('<article class="mj-print-event"' + eventStyleAttr + '>');
                                if (options.cover && eventItem.cover) {
                                    periodHtml.push('<img class="mj-print-event-cover" src="' + escapeHtml(eventItem.cover) + '" alt="' + escapeHtml(eventItem.title || '') + '" />');
                                }
                                periodHtml.push('<div class="mj-print-event-title">');
                                if (options.eventEmoji && eventItem.emoji) {
                                    periodHtml.push('<span class="mj-print-event-emoji">' + escapeHtml(eventItem.emoji) + '</span>');
                                }
                                periodHtml.push('<span class="mj-print-event-title-text">' + escapeHtml(eventItem.title) + '</span>');
                                periodHtml.push('</div>');
                                if (options.timeRange && eventItem.meta) {
                                    periodHtml.push('<div class="mj-print-event-meta">' + escapeHtml(eventItem.meta) + '</div>');
                                }
                                if (eventItem.type) {
                                    var typeLabelStyle = '';
                                    if (eventItem.accentColor) {
                                        var typeBg = hexToRgba(eventItem.accentColor, 0.18);
                                        var typeBorder = hexToRgba(eventItem.accentColor, 0.42);
                                        if (typeBg && typeBorder) {
                                            typeLabelStyle = ' style="background:' + escapeHtml(typeBg) + ';border-color:' + escapeHtml(typeBorder) + ';color:' + escapeHtml(eventItem.accentColor) + ';"';
                                        }
                                    }
                                    periodHtml.push('<div class="mj-print-event-type-label"' + typeLabelStyle + '>' + escapeHtml(eventItem.type) + '</div>');
                                }
                                if (options.details && eventItem.details) {
                                    periodHtml.push('<div class="mj-print-event-details">' + escapeHtml(eventItem.details) + '</div>');
                                }
                                periodHtml.push('</article>');
                            });
                            periodHtml.push('</div>');
                            periodHtml.push('</div>');
                        });
                        periodHtml.push('</div>');
                    });

                    periodHtml.push('</div>');

                    periodHtml.push('</section>');
                    blocks.push(periodHtml.join(''));
                });
            }

            var title = (labels && labels.title) ? labels.title : 'Calendrier - impression';
            var pagePaddingCss = typeof options.pagePadding === 'number' ? options.pagePadding : 10;
            var dayPaddingCss = typeof options.dayPadding === 'number' ? options.dayPadding : 6;
            var eventPaddingCss = typeof options.eventPadding === 'number' ? options.eventPadding : 6;
            return [
                '<!doctype html>',
                '<html lang="fr">',
                '<head>',
                '<meta charset="utf-8" />',
                '<meta name="viewport" content="width=device-width, initial-scale=1" />',
                '<title>' + escapeHtml(title) + '</title>',
                '<style>',
                'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:' + pagePaddingCss + 'px;color:' + pageText + ';background:' + pageBg + ';}',
                'h2{font-size:18px;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid ' + titleBorder + ';color:' + pageText + ';}',
                '.mj-print-cal{display:grid;gap:8px;}',
                '.mj-print-cal__weekdays,.mj-print-cal__week{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;}',
                '.mj-print-cal__weekdays span{font-size:11px;font-weight:700;text-transform:uppercase;color:' + weekdayText + ';padding:2px 4px;}',
                '.mj-print-cal__day{border:1px solid ' + dayBorder + ';border-radius:8px;min-height:80px;padding:' + dayPaddingCss + 'px;display:flex;flex-direction:column;gap:6px;background:' + dayBg + ';}',
                '.mj-print-cal__day.is-padding{background:' + dayPaddingBg + ';border-style:dashed;}',
                '.mj-print-cal__day-head{font-size:11px;font-weight:700;color:' + dayHead + ';}',
                '.mj-print-cal__events{display:grid;gap:6px;}',
                '.mj-print-event{border:1px solid ' + eventBorder + ';border-radius:6px;padding:' + eventPaddingCss + 'px;background:' + eventBg + ';display:grid;gap:4px;}',
                '.mj-print-event-cover{width:100%;aspect-ratio:1 / 1;object-fit:cover;border-radius:4px;display:block;}',
                '.mj-print-event-title{font-size:12px;font-weight:700;line-height:1.2;display:flex;align-items:center;gap:6px;color:' + eventTitle + ';}',
                '.mj-print-event-emoji{font-size:13px;line-height:1;}',
                '.mj-print-event-title-text{display:inline;}',
                '.mj-print-event-meta,.mj-print-event-details{font-size:10px;color:' + eventMeta + ';line-height:1.3;}',
                '.mj-print-event-type-label{display:inline-flex;align-items:center;align-self:flex-start;border:1px solid ' + typeLabelBorder + ';border-radius:999px;padding:2px 8px;font-size:10px;font-weight:600;line-height:1.2;background:' + typeLabelBg + ';color:' + typeLabelColor + ';}',
                '.mj-print-empty{font-size:13px;color:' + emptyText + ';}',
                '.mj-print-period + .mj-print-period{margin-top:14px;}',
                '.mj-print-doc-image{margin:0 0 12px;}',
                '.mj-print-doc-image img{display:block;width:100%;max-height:130px;object-fit:contain;object-position:center;}',
                '.mj-print-doc-image--footer{margin:14px 0 0;}',
                '@media print{body{padding:' + pagePaddingCss + 'px;} .mj-print-period.has-break{page-break-after:always;break-after:page;}}',
                '</style>',
                '</head>',
                '<body>',
                (headerImageUrl ? '<div class="mj-print-doc-image mj-print-doc-image--header"><img src="' + escapeHtml(headerImageUrl) + '" alt="" /></div>' : ''),
                blocks.join(''),
                (footerImageUrl ? '<div class="mj-print-doc-image mj-print-doc-image--footer"><img src="' + escapeHtml(footerImageUrl) + '" alt="" /></div>' : ''),
                '</body>',
                '</html>'
            ].join('');
        }

        function updatePrintPageBreakLabel() {
            if (!printPageBreakLabel) {
                return;
            }
            var labels = (printConfig && printConfig.labels) ? printConfig.labels : {};
            var mode = getPrintMode();
            printPageBreakLabel.textContent = mode === 'month'
                ? (labels.pagePerMonth || 'Une page par mois')
                : (labels.pagePerWeek || 'Une page par semaine');
        }

        function waitForPreviewImages(doc) {
            if (!doc || !doc.images || !doc.images.length) {
                return Promise.resolve();
            }

            var pending = toArray(doc.images).filter(function(img) {
                return !img.complete;
            });

            if (!pending.length) {
                return Promise.resolve();
            }

            return Promise.all(pending.map(function(img) {
                return new Promise(function(resolve) {
                    var done = false;
                    function finish() {
                        if (done) {
                            return;
                        }
                        done = true;
                        resolve();
                    }
                    img.addEventListener('load', finish, { once: true });
                    img.addEventListener('error', finish, { once: true });
                    setTimeout(finish, 2500);
                });
            })).then(function() {
                return undefined;
            });
        }

        function buildImageFileName() {
            var mode = getPrintMode();
            var suffix = mode === 'month' ? 'mois' : 'semaine';
            var periodKey = mode === 'month'
                ? (printSelectedMonthKey || 'calendrier')
                : (printSelectedWeekStartKey || 'horaire');
            return 'horaire-' + suffix + '-' + periodKey + '.jpg';
        }

        function clonePrintSnapshot(periods, options) {
            return {
                periods: JSON.parse(JSON.stringify(periods || [])),
                options: JSON.parse(JSON.stringify(options || {}))
            };
        }

        function drawRoundedRect(ctx, x, y, width, height, radius) {
            var r = Math.max(0, Math.min(radius, width / 2, height / 2));
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + width, y, x + width, y + height, r);
            ctx.arcTo(x + width, y + height, x, y + height, r);
            ctx.arcTo(x, y + height, x, y, r);
            ctx.arcTo(x, y, x + width, y, r);
            ctx.closePath();
        }

        function drawImageCover(ctx, image, x, y, width, height, radius) {
            if (!image || !image.naturalWidth || !image.naturalHeight || width <= 0 || height <= 0) {
                return;
            }

            var sourceWidth = image.naturalWidth;
            var sourceHeight = image.naturalHeight;
            var sourceRatio = sourceWidth / sourceHeight;
            var targetRatio = width / height;
            var cropWidth = sourceWidth;
            var cropHeight = sourceHeight;
            var cropX = 0;
            var cropY = 0;

            if (sourceRatio > targetRatio) {
                cropWidth = sourceHeight * targetRatio;
                cropX = (sourceWidth - cropWidth) / 2;
            } else if (sourceRatio < targetRatio) {
                cropHeight = sourceWidth / targetRatio;
                cropY = (sourceHeight - cropHeight) / 2;
            }

            ctx.save();
            drawRoundedRect(ctx, x, y, width, height, radius);
            ctx.clip();
            ctx.drawImage(
                image,
                cropX,
                cropY,
                cropWidth,
                cropHeight,
                x,
                y,
                width,
                height
            );
            ctx.restore();
        }

        function wrapCanvasText(ctx, text, maxWidth) {
            var content = String(text || '').trim();
            if (!content) {
                return [];
            }

            var words = content.split(/\s+/);
            var lines = [];
            var current = '';

            words.forEach(function(word) {
                var candidate = current ? current + ' ' + word : word;
                if (!current || ctx.measureText(candidate).width <= maxWidth) {
                    current = candidate;
                } else {
                    lines.push(current);
                    current = word;
                }
            });

            if (current) {
                lines.push(current);
            }

            return lines;
        }

        function drawCanvasTextBlock(ctx, lines, x, y, lineHeight, maxLines) {
            var rendered = 0;
            lines.slice(0, maxLines).forEach(function(line, index) {
                var output = line;
                if (index === maxLines - 1 && lines.length > maxLines) {
                    output = line.replace(/[\s.,;:!?-]*$/, '') + '...';
                }
                ctx.fillText(output, x, y + (rendered * lineHeight));
                rendered += 1;
            });
            return rendered * lineHeight;
        }

        function loadImageForCanvas(url) {
            return new Promise(function(resolve) {
                if (!url) {
                    resolve(null);
                    return;
                }

                var img = new Image();
                var done = false;
                function finish(result) {
                    if (done) {
                        return;
                    }
                    done = true;
                    resolve(result);
                }

                img.crossOrigin = 'anonymous';
                img.onload = function() { finish(img); };
                img.onerror = function() { finish(null); };
                setTimeout(function() { finish(null); }, 3000);
                img.src = url;
            });
        }

        async function savePreviewAsJpeg() {
            if (!lastPrintSnapshot || !lastPrintSnapshot.periods) {
                throw new Error('snapshot-unavailable');
            }

            var snapshot = lastPrintSnapshot;
            var periods = snapshot.periods || [];
            var options = snapshot.options || {};
            var pagePadding = typeof options.pagePadding === 'number' ? options.pagePadding : 10;
            var dayPadding = typeof options.dayPadding === 'number' ? options.dayPadding : 6;
            var eventPadding = typeof options.eventPadding === 'number' ? options.eventPadding : 6;
            var theme = options.theme === 'dark' ? 'dark' : 'light';
            var isDarkTheme = theme === 'dark';
            var palette = {
                pageBg: isDarkTheme ? '#0f1115' : '#ffffff',
                heading: isDarkTheme ? '#f5f7fa' : '#111111',
                weekday: isDarkTheme ? '#aeb6c2' : '#666666',
                dayBg: isDarkTheme ? '#171c23' : '#ffffff',
                dayPaddingBg: isDarkTheme ? '#141920' : '#fafafa',
                dayBorder: isDarkTheme ? '#2f3742' : '#e4e4e4',
                dayPaddingBorder: isDarkTheme ? '#3d4653' : '#d8d8d8',
                dayHead: isDarkTheme ? '#d6dde8' : '#444444',
                eventBg: isDarkTheme ? '#202733' : '#ffffff',
                eventBorder: isDarkTheme ? '#323a46' : '#efefef',
                eventTitle: isDarkTheme ? '#f6f8fc' : '#111111',
                eventMeta: isDarkTheme ? '#c2cad7' : '#444444',
                pillBg: isDarkTheme ? '#2a3240' : '#f6f6f6',
                pillBorder: isDarkTheme ? '#3c4552' : '#d7d7d7',
                pillText: isDarkTheme ? '#e2e7f0' : '#444444'
            };
            var headerImageUrl = options.headerImage && options.headerImageUrl ? String(options.headerImageUrl) : '';
            var footerImageUrl = options.footerImage && options.footerImageUrl ? String(options.footerImageUrl) : '';
            var canvasWidth = 1600;
            var weekdayGap = 6;
            var blockGap = 8;
            var periodGap = 18;
            var weekdayHeight = 20;
            var dayWidth = Math.floor((canvasWidth - (pagePadding * 2) - (weekdayGap * 6)) / 7);
            var measureCanvas = document.createElement('canvas');
            var measureCtx = measureCanvas.getContext('2d');
            if (!measureCtx) {
                throw new Error('canvas-unavailable');
            }

            var imageCache = {};
            async function getImage(url) {
                if (!url) {
                    return null;
                }
                if (!Object.prototype.hasOwnProperty.call(imageCache, url)) {
                    imageCache[url] = loadImageForCanvas(url);
                }
                return imageCache[url];
            }

            function getImageFittedHeight(image, width, maxHeight, fallbackHeight) {
                if (!image || !image.naturalWidth || !image.naturalHeight || width <= 0) {
                    return fallbackHeight;
                }
                var raw = width * (image.naturalHeight / image.naturalWidth);
                if (!isFinite(raw) || raw <= 0) {
                    return fallbackHeight;
                }
                return Math.max(30, Math.min(maxHeight, raw));
            }

            var headerImage = headerImageUrl ? await getImage(headerImageUrl) : null;
            var footerImage = footerImageUrl ? await getImage(footerImageUrl) : null;
            var headerDrawHeight = headerImage ? getImageFittedHeight(headerImage, canvasWidth - (pagePadding * 2), 130, 56) : 0;
            var footerDrawHeight = footerImage ? getImageFittedHeight(footerImage, canvasWidth - (pagePadding * 2), 130, 56) : 0;

            function measureEventHeight(eventItem) {
                var innerWidth = dayWidth - (dayPadding * 2) - (eventPadding * 2);
                var height = eventPadding * 2 + 4;

                if (options.cover && eventItem.cover) {
                    height += innerWidth + 4;
                }

                measureCtx.font = '700 12px Arial, sans-serif';
                var titleLines = wrapCanvasText(measureCtx, eventItem.title || '', Math.max(60, innerWidth - 20));
                height += Math.max(14, Math.min(titleLines.length, 3) * 14);

                if (options.timeRange && eventItem.meta) {
                    height += 14;
                }
                if (eventItem.type) {
                    height += 18;
                }
                if (options.details && eventItem.details) {
                    measureCtx.font = '400 10px Arial, sans-serif';
                    var detailLines = wrapCanvasText(measureCtx, eventItem.details, Math.max(60, innerWidth));
                    height += Math.min(detailLines.length, 4) * 12;
                }

                return Math.max(42, height);
            }

            var totalHeight = pagePadding;
            if (headerImage) {
                totalHeight += headerDrawHeight + 12;
            }
            periods.forEach(function(period) {
                totalHeight += 34;
                totalHeight += weekdayHeight + blockGap;
                (period.weeks || []).forEach(function(week) {
                    var weekHeight = 80;
                    (week.cells || []).forEach(function(cell) {
                        if (!cell || cell.isPadding) {
                            return;
                        }
                        var cellHeight = Math.max(80, dayPadding * 2 + 18);
                        (cell.events || []).forEach(function(eventItem, eventIndex) {
                            cellHeight += measureEventHeight(eventItem);
                            if (eventIndex < cell.events.length - 1) {
                                cellHeight += 6;
                            }
                        });
                        weekHeight = Math.max(weekHeight, cellHeight);
                    });
                    totalHeight += weekHeight + blockGap;
                });
                totalHeight += periodGap;
            });
            if (footerImage) {
                totalHeight += 14 + footerDrawHeight;
            }
            totalHeight += pagePadding;

            var canvas = document.createElement('canvas');
            canvas.width = canvasWidth;
            canvas.height = Math.max(600, totalHeight);
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                throw new Error('canvas-unavailable');
            }

            ctx.fillStyle = palette.pageBg;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            var weekdayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            var cursorY = pagePadding;

            if (headerImage) {
                try {
                    ctx.drawImage(headerImage, pagePadding, cursorY, canvasWidth - (pagePadding * 2), headerDrawHeight);
                    cursorY += headerDrawHeight + 12;
                } catch (error) {
                    // Ignore header drawing errors.
                }
            }

            for (var pi = 0; pi < periods.length; pi += 1) {
                var period = periods[pi];
                ctx.font = '700 18px Arial, sans-serif';
                ctx.fillStyle = palette.heading;
                ctx.fillText(String(period.title || ''), pagePadding, cursorY + 18);
                cursorY += 30;

                ctx.font = '700 11px Arial, sans-serif';
                ctx.fillStyle = palette.weekday;
                for (var wdi = 0; wdi < weekdayLabels.length; wdi += 1) {
                    var weekdayX = pagePadding + (wdi * (dayWidth + weekdayGap));
                    ctx.fillText(weekdayLabels[wdi], weekdayX + 4, cursorY + 11);
                }
                cursorY += weekdayHeight + blockGap;

                for (var wi = 0; wi < (period.weeks || []).length; wi += 1) {
                    var week = period.weeks[wi];
                    var computedWeekHeight = 80;
                    (week.cells || []).forEach(function(cell) {
                        if (!cell || cell.isPadding) {
                            return;
                        }
                        var cellHeight = Math.max(80, dayPadding * 2 + 18);
                        (cell.events || []).forEach(function(eventItem, eventIndex) {
                            cellHeight += measureEventHeight(eventItem);
                            if (eventIndex < cell.events.length - 1) {
                                cellHeight += 6;
                            }
                        });
                        computedWeekHeight = Math.max(computedWeekHeight, cellHeight);
                    });

                    for (var ci = 0; ci < (week.cells || []).length; ci += 1) {
                        var cell = week.cells[ci];
                        var cellX = pagePadding + (ci * (dayWidth + weekdayGap));
                        var cellY = cursorY;

                        ctx.save();
                        drawRoundedRect(ctx, cellX, cellY, dayWidth, computedWeekHeight, 8);
                        ctx.fillStyle = cell && cell.isPadding ? palette.dayPaddingBg : palette.dayBg;
                        ctx.fill();
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = cell && cell.isPadding ? palette.dayPaddingBorder : palette.dayBorder;
                        ctx.stroke();
                        ctx.restore();

                        if (!cell || cell.isPadding) {
                            continue;
                        }

                        var innerX = cellX + dayPadding;
                        var innerY = cellY + dayPadding;
                        var innerWidth = dayWidth - (dayPadding * 2);

                        ctx.font = '700 11px Arial, sans-serif';
                        ctx.fillStyle = palette.dayHead;
                        ctx.fillText(String(cell.dayNumber || ''), innerX, innerY + 11);
                        innerY += 18;

                        for (var ei = 0; ei < (cell.events || []).length; ei += 1) {
                            var eventItem = cell.events[ei];
                            var eventHeight = measureEventHeight(eventItem);
                            var eventX = innerX;
                            var eventY = innerY;
                            var eventWidth = innerWidth;

                            ctx.save();
                            drawRoundedRect(ctx, eventX, eventY, eventWidth, eventHeight, 6);
                            ctx.fillStyle = (options.eventColor && eventItem.accentColor)
                                ? (hexToRgba(eventItem.accentColor, isDarkTheme ? 0.2 : 0.16) || palette.eventBg)
                                : palette.eventBg;
                            ctx.fill();
                            ctx.lineWidth = 1;
                            ctx.strokeStyle = (options.eventColor && eventItem.accentColor)
                                ? (hexToRgba(eventItem.accentColor, isDarkTheme ? 0.5 : 0.45) || palette.eventBorder)
                                : palette.eventBorder;
                            ctx.stroke();
                            ctx.restore();

                            var contentX = eventX + eventPadding;
                            var contentY = eventY + eventPadding;
                            var contentWidth = eventWidth - (eventPadding * 2);

                            if (options.cover && eventItem.cover) {
                                var coverImage = await getImage(eventItem.cover);
                                if (coverImage) {
                                    try {
                                        drawImageCover(ctx, coverImage, contentX, contentY, contentWidth, contentWidth, 4);
                                        contentY += contentWidth + 4;
                                    } catch (error) {
                                        // Ignore cover drawing failures, keep export working.
                                    }
                                }
                            }

                            var titleOffsetX = contentX;
                            ctx.font = '700 12px Arial, sans-serif';
                            ctx.fillStyle = palette.eventTitle;
                            if (options.eventEmoji && eventItem.emoji) {
                                ctx.fillText(String(eventItem.emoji), contentX, contentY + 12);
                                titleOffsetX += 18;
                            }
                            var titleLines = wrapCanvasText(ctx, eventItem.title || '', Math.max(60, contentWidth - (titleOffsetX - contentX)));
                            drawCanvasTextBlock(ctx, titleLines, titleOffsetX, contentY + 11, 14, 3);
                            contentY += Math.max(16, Math.min(titleLines.length, 3) * 14);

                            ctx.font = '400 10px Arial, sans-serif';
                            ctx.fillStyle = palette.eventMeta;
                            if (options.timeRange && eventItem.meta) {
                                ctx.fillText(String(eventItem.meta), contentX, contentY + 10);
                                contentY += 14;
                            }

                            if (eventItem.type) {
                                var pillText = String(eventItem.type);
                                ctx.font = '600 10px Arial, sans-serif';
                                var pillWidth = Math.min(contentWidth, ctx.measureText(pillText).width + 16);
                                ctx.save();
                                drawRoundedRect(ctx, contentX, contentY, pillWidth, 16, 999);
                                ctx.fillStyle = eventItem.accentColor
                                    ? (hexToRgba(eventItem.accentColor, isDarkTheme ? 0.22 : 0.18) || palette.pillBg)
                                    : palette.pillBg;
                                ctx.fill();
                                ctx.lineWidth = 1;
                                ctx.strokeStyle = eventItem.accentColor
                                    ? (hexToRgba(eventItem.accentColor, isDarkTheme ? 0.5 : 0.42) || palette.pillBorder)
                                    : palette.pillBorder;
                                ctx.stroke();
                                ctx.restore();
                                ctx.fillStyle = eventItem.accentColor || palette.pillText;
                                ctx.fillText(pillText, contentX + 8, contentY + 11);
                                contentY += 20;
                            }

                            if (options.details && eventItem.details) {
                                ctx.font = '400 10px Arial, sans-serif';
                                ctx.fillStyle = palette.eventMeta;
                                var detailLines = wrapCanvasText(ctx, eventItem.details, Math.max(60, contentWidth));
                                drawCanvasTextBlock(ctx, detailLines, contentX, contentY + 10, 12, 4);
                            }

                            innerY += eventHeight + 6;
                        }
                    }

                    cursorY += computedWeekHeight + blockGap;
                }

                cursorY += periodGap;
            }

            if (footerImage) {
                cursorY += 14;
                try {
                    ctx.drawImage(footerImage, pagePadding, cursorY, canvasWidth - (pagePadding * 2), footerDrawHeight);
                } catch (error) {
                    // Ignore footer drawing errors.
                }
            }

            return new Promise(function(resolve, reject) {
                canvas.toBlob(function(jpegBlob) {
                    if (!jpegBlob) {
                        reject(new Error('blob-unavailable'));
                        return;
                    }
                    var downloadUrl = URL.createObjectURL(jpegBlob);
                    var anchor = document.createElement('a');
                    anchor.href = downloadUrl;
                    anchor.download = buildImageFileName();
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    setTimeout(function() {
                        URL.revokeObjectURL(downloadUrl);
                    }, 1000);
                    resolve();
                }, 'image/jpeg', 0.92);
            });
        }

        function refreshPrintPreview() {
            if (!printConfig || !printConfig.enabled || !printPreviewFrame) {
                return;
            }

            var mode = getPrintMode();
            var theme = getPrintTheme();
            var span = getPrintSpan();
            var details = isDetailsEnabled();
            var cover = isCoverEnabled();
            var timeRange = isTimeRangeEnabled();
            var eventEmoji = isEventEmojiEnabled();
            var eventColor = isEventColorEnabled();
            var headerImage = isHeaderImageEnabled();
            var footerImage = isFooterImageEnabled();
            var pagePadding = getPrintPaddingValue(printPadPageInput, 0, 24, 10);
            var dayPadding = getPrintPaddingValue(printPadDayInput, 0, 16, 6);
            var eventPadding = getPrintPaddingValue(printPadEventInput, 0, 16, 6);
            var pageBreak = isPageBreakEnabled();
            var selectedTypesMap = getSelectedPrintTypesMap();
            var selectedMonthStartIndex = getSelectedMonthStartIndex();
            var selectedWeekAnchorDate = getSelectedWeekAnchorDate();
            var periods = mode === 'month'
                ? collectMonthPeriods(span, details, cover, selectedTypesMap, selectedMonthStartIndex)
                : collectWeekPeriods(span, details, cover, selectedTypesMap, selectedWeekAnchorDate);

            var printRenderOptions = {
                mode: mode,
                theme: theme,
                span: span,
                details: details,
                cover: cover,
                timeRange: timeRange,
                eventEmoji: eventEmoji,
                eventColor: eventColor,
                headerImage: headerImage,
                footerImage: footerImage,
                headerImageUrl: (printConfig && printConfig.headerImageUrl) ? String(printConfig.headerImageUrl) : '',
                footerImageUrl: (printConfig && printConfig.footerImageUrl) ? String(printConfig.footerImageUrl) : '',
                pagePadding: pagePadding,
                dayPadding: dayPadding,
                eventPadding: eventPadding,
                pageBreak: pageBreak
            };

            printPreviewFrame.srcdoc = buildPrintDocumentHtml(periods, printRenderOptions);
            lastPrintSnapshot = clonePrintSnapshot(periods, printRenderOptions);
            updatePrintPageBreakLabel();
        }

        function openPrintModal() {
            if (!printModal) {
                return;
            }
            buildPrintPeriodEntries();
            setDefaultPrintPeriodSelection();
            renderPrintPeriodSelectors();
            if (!hasAppliedPrintPrefs && printPrefsFromServer) {
                applyPrintPrefsToInputs(printPrefsFromServer);
                hasAppliedPrintPrefs = true;
            }
            refreshPrintPreview();
            printModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closePrintModal() {
            if (!printModal) {
                return;
            }
            printModal.hidden = true;
            document.body.style.overflow = '';
        }

        function sync() {
            months.forEach(function(month, idx) {
                if (idx === activeIndex) {
                    month.classList.add('is-active');
                } else {
                    month.classList.remove('is-active');
                }
            });
            if (label && months[activeIndex]) {
                label.textContent = months[activeIndex].getAttribute('data-calendar-label') || '';
            }
            if (prev) {
                prev.disabled = activeIndex === 0;
            }
            if (next) {
                next.disabled = activeIndex === months.length - 1;
            }
            if (todayBtn) {
                todayBtn.disabled = todayIndex === -1 || activeIndex === todayIndex;
            }
            applyFilters();
            refreshPrintPreview();
        }

        if (prev) {
            prev.addEventListener('click', function() {
                if (activeIndex > 0) {
                    activeIndex -= 1;
                    sync();
                }
            });
        }

        if (next) {
            next.addEventListener('click', function() {
                if (activeIndex < months.length - 1) {
                    activeIndex += 1;
                    sync();
                }
            });
        }

        if (todayBtn) {
            todayBtn.addEventListener('click', function() {
                if (todayIndex >= 0 && activeIndex !== todayIndex) {
                    activeIndex = todayIndex;
                    sync();
                }
            });
        }

        if (filterInputs.length) {
            filterInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    applyFilters();
                });
            });
        }

        if (openPrintBtn && printConfig && printConfig.enabled) {
            openPrintBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openPrintModal();
            });
        }

        if (printCloseBtns.length) {
            printCloseBtns.forEach(function(closeBtn) {
                closeBtn.addEventListener('click', function() {
                    closePrintModal();
                });
            });
        }

        if (printNowBtn && printConfig && printConfig.enabled) {
            printNowBtn.addEventListener('click', function(e) {
                e.preventDefault();
                refreshPrintPreview();

                if (!printPreviewFrame || !printPreviewFrame.contentWindow) {
                    return;
                }

                try {
                    printPreviewFrame.contentWindow.focus();
                    printPreviewFrame.contentWindow.print();
                } catch (error) {
                    // Browser-specific restriction fallback.
                    window.print();
                }
            });
        }

        if (saveImageBtn && printConfig && printConfig.enabled) {
            saveImageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (saveImageBtn.disabled) {
                    return;
                }

                refreshPrintPreview();
                saveImageBtn.disabled = true;

                savePreviewAsJpeg()
                    .catch(function() {
                        alert('Impossible de générer l\'image JPEG.');
                    })
                    .finally(function() {
                        saveImageBtn.disabled = false;
                    });
            });
        }

        if (printModeInput) {
            printModeInput.addEventListener('change', function() {
                updatePrintPageBreakLabel();
                renderPrintPeriodSelectors();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printThemeInput) {
            printThemeInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printMonthYearInput) {
            printMonthYearInput.addEventListener('change', function() {
                renderPrintPeriodSelectors();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printMonthInput) {
            printMonthInput.addEventListener('change', function() {
                printSelectedMonthKey = (printMonthInput.value || '').trim();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printWeekYearInput) {
            printWeekYearInput.addEventListener('change', function() {
                renderPrintPeriodSelectors();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printWeekInput) {
            printWeekInput.addEventListener('change', function() {
                printSelectedWeekStartKey = (printWeekInput.value || '').trim();
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printSpanInput) {
            printSpanInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printSpanInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPadPageInput) {
            printPadPageInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printPadPageInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPadDayInput) {
            printPadDayInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printPadDayInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPadEventInput) {
            printPadEventInput.addEventListener('input', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
            printPadEventInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printDetailsInput) {
            printDetailsInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printCoverInput) {
            printCoverInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printTimeRangeInput) {
            printTimeRangeInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printEventEmojiInput) {
            printEventEmojiInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printEventColorInput) {
            printEventColorInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printHeaderImageInput) {
            printHeaderImageInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printFooterImageInput) {
            printFooterImageInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        if (printPageBreakInput) {
            printPageBreakInput.addEventListener('change', function() {
                refreshPrintPreview();
                queueSavePrintPrefs();
            });
        }

        renderPrintTypeFilters();
        buildPrintPeriodEntries();
        setDefaultPrintPeriodSelection();
        renderPrintPeriodSelectors();
        if (!hasAppliedPrintPrefs && printPrefsFromServer) {
            applyPrintPrefsToInputs(printPrefsFromServer);
            hasAppliedPrintPrefs = true;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && printModal && !printModal.hidden) {
                closePrintModal();
            }
        });

        sync();

        if (!filterInputs.length) {
            updateDayStates();
        }
    }

    function drainQueue() {
        if (!window.mjMemberEventsCalendarQueue) {
            return;
        }
        while (window.mjMemberEventsCalendarQueue.length) {
            var item = window.mjMemberEventsCalendarQueue.shift();
            if (!item || !item.id) {
                continue;
            }
            var root = document.getElementById(item.id);
            if (root && item.config && typeof item.config.preferredIndex === 'number') {
                root.setAttribute('data-calendar-preferred', String(item.config.preferredIndex));
            }
            if (root && item.config && item.config.todayMonth) {
                root.setAttribute('data-calendar-today', String(item.config.todayMonth));
            }
            initCalendar(root, item.config || {});
        }
    }

    domReady(drainQueue);
})();