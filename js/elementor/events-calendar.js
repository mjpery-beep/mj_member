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

        // ---- Create modal (delegated to shared module) ----
        var ccmInstance = null;
        if (window.MjCreateEventModal && root.querySelector('[data-ccm-modal]')) {
            ccmInstance = window.MjCreateEventModal.init(root, config);
        }
        if (!ccmInstance && root.querySelector('[data-ccm-modal]')) {
            console.warn('[Calendar] CCM modal markup found but MjCreateEventModal.init() returned null.',
                'MjCreateEventModal available:', !!window.MjCreateEventModal);
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
                var dayCell = e.target.closest('.mj-cal-mobile__day.has-events');
                if (!dayCell) {
                    return;
                }
                // Don't open modal if clicking inside the modal itself
                if (e.target.closest('.mj-cal-mobile__modal')) {
                    return;
                }
                var dayKey = dayCell.getAttribute('data-calendar-day');
                if (dayKey) {
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