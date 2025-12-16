(function(){
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function(callback) {
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
        : function(collection) {
            if (!collection) {
                return [];
            }
            if (Array.isArray(collection)) {
                return collection.slice();
            }
            try {
                return Array.prototype.slice.call(collection);
            } catch (error) {
                var arr = [];
                var idx = 0;
                while (collection[idx]) {
                    arr.push(collection[idx]);
                    idx += 1;
                }
                return arr;
            }
        };

    var instances = new WeakMap();

    function parseConfig(root) {
        var raw = root.getAttribute('data-config');
        if (!raw) {
            return {
                layout: root.getAttribute('data-layout') || 'list',
                slidesPerView: 1,
                autoplay: false,
                autoplayDelay: 6000
            };
        }
        try {
            var parsed = JSON.parse(raw);
            parsed.layout = parsed.layout || root.getAttribute('data-layout') || 'list';
            parsed.slidesPerView = Math.max(1, parseInt(parsed.slidesPerView, 10) || 1);
            parsed.autoplay = !!parsed.autoplay;
            parsed.autoplayDelay = Math.max(2000, parseInt(parsed.autoplayDelay, 10) || 6000);
            return parsed;
        } catch (error) {
            return {
                layout: root.getAttribute('data-layout') || 'list',
                slidesPerView: 1,
                autoplay: false,
                autoplayDelay: 6000
            };
        }
    }

    function disableNav(button) {
        if (!button) {
            return;
        }
        button.setAttribute('disabled', 'disabled');
    }

    function enableNav(button) {
        if (!button) {
            return;
        }
        button.removeAttribute('disabled');
    }

    function setupSlider(state) {
        var root = state.root;
        var track = state.track;
        if (!track) {
            return;
        }

        var prev = state.prev;
        var next = state.next;
        var autoplayTimer = null;

        function canSlide() {
            return track.scrollWidth - track.clientWidth > 2;
        }

        function updateNav() {
            if (!canSlide()) {
                disableNav(prev);
                disableNav(next);
                return;
            }
            var maxScroll = track.scrollWidth - track.clientWidth;
            var position = track.scrollLeft;
            if (position <= 1) {
                disableNav(prev);
            } else {
                enableNav(prev);
            }
            if (position >= maxScroll - 1) {
                disableNav(next);
            } else {
                enableNav(next);
            }
        }

        function scrollByStep(direction) {
            if (!canSlide()) {
                return;
            }
            var delta = track.clientWidth || track.scrollWidth;
            if (direction < 0) {
                delta = -delta;
            }
            track.scrollBy({ left: delta, behavior: 'smooth' });
        }

        function startAutoplay() {
            if (!state.config.autoplay || !canSlide()) {
                return;
            }
            stopAutoplay();
            autoplayTimer = window.setInterval(function() {
                if (!canSlide()) {
                    stopAutoplay();
                    return;
                }
                var maxScroll = track.scrollWidth - track.clientWidth;
                if (track.scrollLeft >= maxScroll - 1) {
                    track.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    scrollByStep(1);
                }
            }, state.config.autoplayDelay);
            state.autoplayTimer = autoplayTimer;
        }

        function stopAutoplay() {
            if (autoplayTimer !== null) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
            state.autoplayTimer = null;
        }

        function attachListeners() {
            if (prev) {
                prev.addEventListener('click', function(event) {
                    event.preventDefault();
                    scrollByStep(-1);
                });
            }
            if (next) {
                next.addEventListener('click', function(event) {
                    event.preventDefault();
                    scrollByStep(1);
                });
            }
            track.addEventListener('scroll', function() {
                window.requestAnimationFrame(updateNav);
            }, { passive: true });
            root.addEventListener('mouseenter', stopAutoplay);
            root.addEventListener('mouseleave', startAutoplay);
            root.addEventListener('focusin', stopAutoplay);
            root.addEventListener('focusout', startAutoplay);

            if (typeof ResizeObserver !== 'undefined') {
                var observer = new ResizeObserver(function() {
                    updateNav();
                    if (state.config.autoplay) {
                        startAutoplay();
                    }
                });
                observer.observe(track);
                state.resizeObserver = observer;
            } else {
                state.resizeHandler = function() {
                    updateNav();
                    if (state.config.autoplay) {
                        startAutoplay();
                    }
                };
                window.addEventListener('resize', state.resizeHandler);
            }
        }

        state.destroy = function() {
            stopAutoplay();
            if (state.resizeObserver) {
                state.resizeObserver.disconnect();
                state.resizeObserver = null;
            }
            if (state.resizeHandler) {
                window.removeEventListener('resize', state.resizeHandler);
                state.resizeHandler = null;
            }
        };

        attachListeners();
        updateNav();
        if (state.config.autoplay) {
            startAutoplay();
        }
    }

    function init(root) {
        if (!root || instances.has(root)) {
            return;
        }

        var config = parseConfig(root);
        var track = root.querySelector('[data-upcoming-track]');
        var state = {
            root: root,
            config: config,
            track: track,
            prev: root.querySelector('[data-action="prev"]'),
            next: root.querySelector('[data-action="next"]'),
            destroy: null,
            resizeObserver: null,
            resizeHandler: null,
            autoplayTimer: null
        };

        if (config.layout === 'slider' && track) {
            setupSlider(state);
        }

        instances.set(root, state);
    }

    function boot(context) {
        var scope = context && context.querySelectorAll ? context : document;
        var nodes = toArray(scope.querySelectorAll('[data-mj-upcoming-events]'));
        if (!nodes.length && scope !== document && scope.matches && scope.matches('[data-mj-upcoming-events]')) {
            nodes.push(scope);
        }
        nodes.forEach(init);
    }

    domReady(function() {
        boot(document);
    });

    function registerElementorHook() {
        if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
            return;
        }
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-upcoming-events.default', function($scope) {
            var element = null;
            if ($scope && typeof $scope.find === 'function') {
                element = $scope.find('[data-mj-upcoming-events]').get(0) || null;
            }
            if (!element && $scope && $scope[0] && $scope[0].querySelector) {
                element = $scope[0].querySelector('[data-mj-upcoming-events]');
            }
            if (element) {
                init(element);
            }
        });
    }

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        registerElementorHook();
    } else {
        window.addEventListener('elementor/frontend/init', registerElementorHook);
    }
})();
