(function(){
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function setupRangeHover(root) {
        var rangeElements = Array.prototype.slice.call(root.querySelectorAll('[data-calendar-range]'));
        if (!rangeElements.length) {
            return;
        }

        var toggleRange = function(rangeKey, enable) {
            if (!rangeKey) {
                return;
            }
            rangeElements.forEach(function(element) {
                if (element.getAttribute('data-calendar-range') !== rangeKey) {
                    return;
                }
                if (enable) {
                    element.classList.add('is-range-hover');
                } else {
                    element.classList.remove('is-range-hover');
                }
                var listItem = element.closest('.mj-member-events-calendar__event');
                if (listItem) {
                    if (enable) {
                        listItem.classList.add('is-range-hover');
                    } else {
                        listItem.classList.remove('is-range-hover');
                    }
                }
            });
        };

        rangeElements.forEach(function(element) {
            element.addEventListener('mouseenter', function() {
                toggleRange(element.getAttribute('data-calendar-range'), true);
            });
            element.addEventListener('mouseleave', function() {
                toggleRange(element.getAttribute('data-calendar-range'), false);
            });
            element.addEventListener('focus', function() {
                toggleRange(element.getAttribute('data-calendar-range'), true);
            });
            element.addEventListener('blur', function() {
                toggleRange(element.getAttribute('data-calendar-range'), false);
            });
        });
    }

    function createRangeSizing(root) {
        var rafId = null;

        function applySizing() {
            rafId = null;

            var rangeElements = Array.prototype.slice.call(root.querySelectorAll('[data-calendar-range]'));
            if (!rangeElements.length) {
                return;
            }

            var groups = {};
            rangeElements.forEach(function(element) {
                var rangeKey = element.getAttribute('data-calendar-range');
                if (!rangeKey) {
                    return;
                }
                var listItem = element.closest('.mj-member-events-calendar__event');
                if (!listItem) {
                    return;
                }
                if (!groups[rangeKey]) {
                    groups[rangeKey] = { head: null, items: [] };
                }
                var entry = { trigger: element, listItem: listItem };
                if (listItem.classList.contains('is-multi-head')) {
                    groups[rangeKey].head = entry;
                }
                groups[rangeKey].items.push(entry);
            });

            Object.keys(groups).forEach(function(rangeKey) {
                var group = groups[rangeKey];
                if (!group.head || !group.head.listItem) {
                    return;
                }
                if (!group.head.listItem.offsetParent) {
                    return;
                }

                group.items.forEach(function(entry) {
                    entry.listItem.style.minHeight = '';
                    entry.listItem.style.height = '';
                    entry.trigger.style.minHeight = '';
                    entry.trigger.style.height = '';
                    var continuationNode = entry.trigger.querySelector('.mj-member-events-calendar__event-continuation');
                    if (continuationNode) {
                        continuationNode.style.height = '';
                        continuationNode.style.minHeight = '';
                    }
                });

                var referenceHeight = group.head.listItem.getBoundingClientRect().height;
                if (!referenceHeight) {
                    return;
                }

                var heightValue = String(referenceHeight) + 'px';

                group.items.forEach(function(entry) {
                    entry.listItem.style.minHeight = heightValue;
                    entry.listItem.style.height = heightValue;
                    entry.trigger.style.minHeight = heightValue;
                    entry.trigger.style.height = heightValue;
                    var continuationNode = entry.trigger.querySelector('.mj-member-events-calendar__event-continuation');
                    if (continuationNode) {
                        continuationNode.style.height = heightValue;
                        continuationNode.style.minHeight = heightValue;
                    }
                });
            });
        }

        function schedule() {
            if (rafId !== null) {
                cancelAnimationFrame(rafId);
            }
            rafId = requestAnimationFrame(applySizing);
        }

        window.addEventListener('resize', schedule);

        var resizeObserver = null;
        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(schedule);
            resizeObserver.observe(root);
        }

        return {
            schedule: schedule,
            destroy: function() {
                if (resizeObserver) {
                    resizeObserver.disconnect();
                }
                window.removeEventListener('resize', schedule);
                if (rafId !== null) {
                    cancelAnimationFrame(rafId);
                    rafId = null;
                }
            }
        };
    }

    function initCalendar(root, config) {
        if (!root) {
            return;
        }

        var months = Array.prototype.slice.call(root.querySelectorAll('[data-calendar-month]'));
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

        var prev = root.querySelector('[data-calendar-nav="prev"]');
        var next = root.querySelector('[data-calendar-nav="next"]');
        var label = root.querySelector('[data-calendar-active-label]');
        var todayBtn = root.querySelector('[data-calendar-action="today"]');
        var todayMonthKey = root.getAttribute('data-calendar-today') || '';
        if (config && config.todayMonth) {
            todayMonthKey = config.todayMonth;
        }
        var todayIndex = -1;
        if (todayMonthKey && Object.prototype.hasOwnProperty.call(monthIndexMap, todayMonthKey)) {
            todayIndex = monthIndexMap[todayMonthKey];
        }

        var activeIndex = 0;
        var preferred = parseInt(root.getAttribute('data-calendar-preferred'), 10);
        if (!isNaN(preferred) && preferred >= 0 && preferred < months.length) {
            activeIndex = preferred;
        } else if (config && typeof config.preferredIndex === 'number' && config.preferredIndex >= 0 && config.preferredIndex < months.length) {
            activeIndex = config.preferredIndex;
        }

        var rangeSizing;

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
            if (rangeSizing) {
                rangeSizing.schedule();
            }
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

        rangeSizing = createRangeSizing(root);

        sync();

        setupRangeHover(root);
        if (rangeSizing) {
            rangeSizing.schedule();
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

    ready(drainQueue);
})();
