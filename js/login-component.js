(function () {
    'use strict';

    var focusableSelectors = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var activeState = null;
    var listeningKeydown = false;
    var panelStateMap = new WeakMap();
    var canHover = true;

    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        try {
            canHover = window.matchMedia('(hover: hover)').matches;
        } catch (error) {
            canHover = true;
        }
    }

    function focusFirstElement(container) {
        if (!container) {
            return;
        }

        var focusable = container.querySelectorAll(focusableSelectors);
        if (focusable.length > 0) {
            focusable[0].focus();
        } else if (typeof container.focus === 'function') {
            container.focus();
        }
    }

    function trapFocus(event, container) {
        if (!container) {
            return;
        }

        var focusable = container.querySelectorAll(focusableSelectors);
        if (focusable.length === 0) {
            event.preventDefault();
            container.focus();
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        var active = document.activeElement;

        if (event.shiftKey) {
            if (active === first || !container.contains(active)) {
                event.preventDefault();
                last.focus();
            }
        } else if (active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function cancelScheduledClose(state) {
        if (state.closeTimer) {
            clearTimeout(state.closeTimer);
            state.closeTimer = null;
        }
    }

    function scheduleClose(state) {
        cancelScheduledClose(state);
        state.closeTimer = setTimeout(function () {
            if (!state.panel.classList.contains('is-active')) {
                return;
            }
            if (state.wrapper.matches(':hover') || state.panel.matches(':hover')) {
                return;
            }
            closeState(state, false);
        }, 200);
    }

    function initAccountMenuAccordions(container) {
        if (!container) {
            return;
        }

        var toggles = container.querySelectorAll('[data-mj-account-toggle]');
        if (!toggles || toggles.length === 0) {
            return;
        }

        for (var i = 0; i < toggles.length; i += 1) {
            (function (toggle) {
                if (toggle.getAttribute('data-mj-account-toggle-init') === '1') {
                    return;
                }

                toggle.setAttribute('data-mj-account-toggle-init', '1');

                toggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var expanded = toggle.getAttribute('aria-expanded') === 'true';
                    var newState = !expanded;
                    toggle.setAttribute('aria-expanded', newState ? 'true' : 'false');

                    var listItem = typeof toggle.closest === 'function' ? toggle.closest('.mj-member-account-menu__item') : null;
                    if (listItem) {
                        listItem.classList.toggle('is-expanded', newState);
                    }

                    var controls = toggle.getAttribute('aria-controls') || '';
                    var sublist = controls !== '' ? document.getElementById(controls) : null;

                    if (!sublist && listItem) {
                        sublist = listItem.querySelector('[data-mj-account-sublist]');
                    }

                    if (sublist) {
                        sublist.classList.toggle('is-open', newState);
                        sublist.setAttribute('aria-hidden', newState ? 'false' : 'true');
                    }
                });
            })(toggles[i]);
        }
    }

    function openState(state, options) {
        var opts = options || {};

        if (activeState && activeState !== state) {
            closeState(activeState, false);
        }

        state.panel.classList.add('is-active');
        state.wrapper.classList.add('is-open');
        state.trigger.classList.add('is-active');
        state.trigger.setAttribute('aria-expanded', 'true');
        state.panel.setAttribute('aria-hidden', 'false');
        cancelScheduledClose(state);
        activeState = state;

        if (!listeningKeydown) {
            document.addEventListener('keydown', handleDocumentKeydown, true);
            listeningKeydown = true;
        }

        if (opts.focus !== false) {
            setTimeout(function () {
                focusFirstElement(state.panel);
            }, 20);
        }
    }

    function closeState(state, restoreFocus) {
        cancelScheduledClose(state);

        state.panel.classList.remove('is-active');
        state.wrapper.classList.remove('is-open');
        state.trigger.classList.remove('is-active');
        state.trigger.setAttribute('aria-expanded', 'false');
        state.panel.setAttribute('aria-hidden', 'true');

        if (restoreFocus !== false) {
            state.trigger.focus();
        }

        if (activeState === state) {
            activeState = null;
            if (listeningKeydown) {
                document.removeEventListener('keydown', handleDocumentKeydown, true);
                listeningKeydown = false;
            }
        }
    }

    function handleDocumentClick(event) {
        if (!activeState) {
            return;
        }

        if (activeState.wrapper.contains(event.target)) {
            var closer = event.target.closest('[data-mj-login-close]');
            if (closer) {
                event.preventDefault();
                closeState(activeState, true);
            }
            return;
        }

        closeState(activeState, false);
    }

    function handleDocumentKeydown(event) {
        if (!activeState) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeState(activeState, true);
            return;
        }

        if (event.key === 'Tab') {
            trapFocus(event, activeState.panel);
        }
    }

    function initComponent(wrapper) {
        if (!wrapper) {
            return null;
        }

        var trigger = wrapper.querySelector('[data-mj-login-trigger]');
        if (!trigger) {
            return null;
        }

        var targetId = trigger.getAttribute('data-target') || '';
        var panel = targetId ? document.getElementById(targetId) : null;
        if (!panel) {
            panel = wrapper.querySelector('[data-mj-login-panel]');
        }

        if (!panel) {
            return null;
        }

        var existing = panelStateMap.get(panel);
        if (existing) {
            return existing;
        }

        var state = {
            wrapper: wrapper,
            trigger: trigger,
            panel: panel,
            closeTimer: null
        };

        panelStateMap.set(panel, state);

        initAccountMenuAccordions(panel);

        trigger.addEventListener('click', function (event) {
            var loginState = trigger.getAttribute('data-login-state') || 'logged-out';
            var accountUrl = trigger.getAttribute('data-account-url') || '';
            var isLoggedIn = loginState === 'logged-in';
            var isKeyboard = event.detail === 0;

            if (isLoggedIn && accountUrl !== '' && canHover && !isKeyboard) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button === 1) {
                    window.open(accountUrl, '_blank', 'noopener');
                    return;
                }

                window.location.assign(accountUrl);
                return;
            }

            event.preventDefault();
            if (panel.classList.contains('is-active')) {
                focusFirstElement(state.panel);
                return;
            }
            openState(state);
        });

        if (canHover) {
            trigger.addEventListener('mouseenter', function () {
                openState(state, { focus: false });
            });
            wrapper.addEventListener('mouseenter', function () {
                cancelScheduledClose(state);
            });
            wrapper.addEventListener('mouseleave', function () {
                scheduleClose(state);
            });
            panel.addEventListener('mouseenter', function () {
                cancelScheduledClose(state);
            });
            panel.addEventListener('mouseleave', function () {
                scheduleClose(state);
            });
        }

        var closers = panel.querySelectorAll('[data-mj-login-close]');
        for (var i = 0; i < closers.length; i += 1) {
            closers[i].addEventListener('click', function (event) {
                event.preventDefault();
                closeState(state, true);
            });
        }

        if (panel.classList.contains('is-active')) {
            openState(state);
        } else {
            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('aria-hidden', 'true');
        }

        return state;
    }

    function initAll() {
        var wrappers = document.querySelectorAll('[data-mj-member-login]');
        for (var i = 0; i < wrappers.length; i += 1) {
            initComponent(wrappers[i]);
        }
    }

    function hookElementorIntegration() {
        if (hookElementorIntegration.initialized) {
            return;
        }

        if (typeof window.elementorFrontend === 'undefined' || !window.elementorFrontend || !window.elementorFrontend.hooks || typeof window.elementorFrontend.hooks.addAction !== 'function') {
            setTimeout(hookElementorIntegration, 400);
            return;
        }

        hookElementorIntegration.initialized = true;

        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-account-menu-mobile.default', function (scope) {
            if (!scope || !scope.length) {
                return;
            }

            var root = scope[0];
            if (!root) {
                return;
            }

            var wrappers = root.querySelectorAll('[data-mj-member-login]');
            for (var i = 0; i < wrappers.length; i += 1) {
                initComponent(wrappers[i]);
            }
        });
    }

    document.addEventListener('click', handleDocumentClick);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll();
            hookElementorIntegration();
        });
    } else {
        initAll();
        hookElementorIntegration();
    }

    hookElementorIntegration();

    window.mjMemberOpenLoginModal = function (targetId) {
        var panel = null;
        if (typeof targetId === 'string' && targetId !== '') {
            panel = document.getElementById(targetId);
        }

        if (!panel) {
            return false;
        }

        var state = panelStateMap.get(panel);
        if (!state) {
            var wrapper = panel.closest('[data-mj-member-login]');
            if (!wrapper) {
                return false;
            }
            state = initComponent(wrapper);
        }

        if (!state) {
            return false;
        }

        openState(state);
        return true;
    };
})();
