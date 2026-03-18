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
            if(document.activeElement && state.panel.contains(document.activeElement)) {
                return;
            }
            closeState(state, false);
        }, 900);
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

    function initNotifPreview(panel) {
        var preview = panel.querySelector('.mj-member-login-component__notif-preview');
        if (!preview) {
            console.log('[MJ notif-preview] No preview element found in', panel.className);
            return;
        }

        var items = panel.querySelectorAll('.mj-member-login-component__account-item[data-notif-key]');
        if (!items || items.length === 0) {
            console.log('[MJ notif-preview] No items with data-notif-key found in', panel.className);
            return;
        }

        console.log('[MJ notif-preview] Initialized on', panel.className, 'with', items.length, 'items');

        var groups = preview.querySelectorAll('.mj-member-login-component__notif-group');
        var hideTimer = null;

        function showGroup(key, hoveredItem) {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            for (var g = 0; g < groups.length; g += 1) {
                var groupKey = groups[g].getAttribute('data-notif-group');
                groups[g].classList.toggle('is-active', groupKey === key);
            }
            // Position preview at the level of the hovered link
            if (hoveredItem) {
                var panelRect = panel.getBoundingClientRect();
                var itemRect = hoveredItem.getBoundingClientRect();
                var topOffset = itemRect.top - panelRect.top;
                preview.style.top = Math.max(0, topOffset) + 'px';
            }
            preview.classList.add('is-visible');
            preview.setAttribute('aria-hidden', 'false');
        }

        function scheduleHide() {
            if (hideTimer) {
                clearTimeout(hideTimer);
            }
            hideTimer = setTimeout(function () {
                preview.classList.remove('is-visible');
                preview.setAttribute('aria-hidden', 'true');
                for (var g = 0; g < groups.length; g += 1) {
                    groups[g].classList.remove('is-active');
                }
                hideTimer = null;
            }, 300);
        }

        function cancelHide() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
        }

        for (var i = 0; i < items.length; i += 1) {
            (function (item) {
                item.addEventListener('mouseenter', function () {
                    var key = item.getAttribute('data-notif-key');
                    if (key) {
                        showGroup(key, item);
                    }
                });
                item.addEventListener('mouseleave', function () {
                    scheduleHide();
                });
            })(items[i]);
        }

        preview.addEventListener('mouseenter', function () {
            cancelHide();
        });

        preview.addEventListener('mouseleave', function () {
            scheduleHide();
        });

        // Notification action buttons (mark-read / delete)
        initNotifActions(preview);
    }

    function initNotifActions(preview) {
        var ajaxUrl = preview.getAttribute('data-ajax-url') || '';
        var nonce = preview.getAttribute('data-nonce') || '';
        if (!ajaxUrl || !nonce) {
            return;
        }

        preview.addEventListener('click', function (e) {
            var markReadBtn = e.target.closest('.mj-member-login-component__notif-mark-read');
            if (markReadBtn) {
                e.preventDefault();
                e.stopPropagation();
                handleNotifAction(markReadBtn, 'mj_member_notification_bell_mark_read', ajaxUrl, nonce);
                return;
            }

            var deleteBtn = e.target.closest('.mj-member-login-component__notif-delete');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                handleNotifAction(deleteBtn, 'mj_member_notification_bell_archive', ajaxUrl, nonce);
                return;
            }
        });
    }

    function handleNotifAction(btn, action, ajaxUrl, nonce) {
        var recipientId = btn.getAttribute('data-recipient-id');
        if (!recipientId) {
            return;
        }

        var item = btn.closest('.mj-member-login-component__notif-item');

        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);
        formData.append('recipient_id', recipientId);

        // Animate out
        if (item) {
            item.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateX(10px)';
        }

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (response.success) {
                if (item) {
                    // Capture group reference BEFORE removing the item from DOM
                    var group = item.closest('.mj-member-login-component__notif-group');
                    var groupKey = group ? group.getAttribute('data-notif-group') : '';
                    setTimeout(function () {
                        item.remove();
                        if (group && groupKey) {
                            updateBadgeCount(groupKey, group);
                        }
                    }, 250);
                }
            } else {
                // Restore on error
                if (item) {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }
            }
        })
        .catch(function () {
            if (item) {
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }
        });
    }

    function updateBadgeCount(groupKey, groupEl) {
        if (!groupKey) {
            return;
        }
        // Find ALL associated account link items across widgets and decrement their badge
        var accountItems = document.querySelectorAll('.mj-member-login-component__account-item[data-notif-key="' + groupKey + '"]');
        for (var i = 0; i < accountItems.length; i += 1) {
            var badge = accountItems[i].querySelector('.mj-member-login-component__account-badge');
            if (!badge) {
                continue;
            }
            var currentCount = parseInt(badge.textContent, 10) || 0;
            var newCount = Math.max(0, currentCount - 1);
            if (newCount > 0) {
                badge.textContent = newCount;
                badge.setAttribute('aria-label', newCount + ' notification' + (newCount > 1 ? 's' : ''));
            } else {
                badge.style.display = 'none';
                accountItems[i].classList.remove('has-badge-tooltip');
            }
        }

        // Update the "more" text
        var remaining = groupEl.querySelectorAll('.mj-member-login-component__notif-item');
        if (remaining.length === 0) {
            var moreEl = groupEl.querySelector('.mj-member-login-component__notif-more');
            if (moreEl) {
                moreEl.remove();
            }
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

        var preview = state.panel.querySelector('.mj-member-login-component__notif-preview');
        if (preview) {
            preview.classList.remove('is-visible');
            preview.setAttribute('aria-hidden', 'true');
        }

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
        initNotifPreview(panel);

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            if (panel.classList.contains('is-active')) {
                closeState(state, true);
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

        // Init notification preview on account-links widgets (standalone, outside login modal)
        var accountLinksWidgets = document.querySelectorAll('.mj-member-account-links');
        console.log('[MJ notif-preview] Found', accountLinksWidgets.length, 'account-links widgets');
        for (var j = 0; j < accountLinksWidgets.length; j += 1) {
            initNotifPreview(accountLinksWidgets[j]);
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

        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-account-links.default', function (scope) {
            if (!scope || !scope.length) {
                return;
            }

            var root = scope[0];
            if (!root) {
                return;
            }

            var widgets = root.querySelectorAll('.mj-member-account-links');
            for (var i = 0; i < widgets.length; i += 1) {
                initNotifPreview(widgets[i]);
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
