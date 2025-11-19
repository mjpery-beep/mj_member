(function () {
    'use strict';

    var activeModal = null;
    var lastTrigger = null;
    var openCount = 0;
    var focusableSelectors = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function openModal(modal, trigger) {
        if (!modal) {
            return;
        }

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal, false);
        }

        activeModal = modal;
        lastTrigger = trigger || null;
        openCount++;

        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');

        if (openCount === 1) {
            document.body.classList.add('mj-member-login-open');
        }

        setTimeout(function () {
            focusFirstElement(modal);
        }, 20);

        document.addEventListener('keydown', handleKeyDown, true);
    }

    function closeModal(modal, restoreFocus) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');

        if (openCount > 0) {
            openCount--;
        }

        if (openCount === 0) {
            document.body.classList.remove('mj-member-login-open');
            document.removeEventListener('keydown', handleKeyDown, true);
        }

        if (restoreFocus !== false && lastTrigger) {
            lastTrigger.focus();
        }

        if (activeModal === modal) {
            activeModal = null;
        }
    }

    function focusFirstElement(modal) {
        var focusable = modal.querySelectorAll(focusableSelectors);
        if (focusable.length) {
            focusable[0].focus();
        }
    }

    function handleKeyDown(event) {
        if (!activeModal) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(activeModal, true);
            return;
        }

        if (event.key === 'Tab') {
            trapFocus(event, activeModal);
        }
    }

    function trapFocus(event, modal) {
        var focusable = modal.querySelectorAll(focusableSelectors);
        if (!focusable.length) {
            event.preventDefault();
            modal.focus();
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        var active = document.activeElement;

        if (event.shiftKey) {
            if (active === first || !modal.contains(active)) {
                event.preventDefault();
                last.focus();
            }
        } else {
            if (active === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-mj-login-trigger]');
        if (trigger) {
            event.preventDefault();
            var targetId = trigger.getAttribute('data-target');
            if (!targetId) {
                return;
            }
            var modal = document.getElementById(targetId);
            if (!modal) {
                return;
            }
            openModal(modal, trigger);
            return;
        }

        var closer = event.target.closest('[data-mj-login-close]');
        if (closer && activeModal) {
            event.preventDefault();
            closeModal(activeModal, true);
        }
    });

    function bootstrapAutoModal() {
        var autoModal = document.querySelector('.mj-member-login-component__modal.is-active');
        if (!autoModal) {
            return;
        }
        var wrapper = autoModal.closest('[data-mj-member-login]');
        var trigger = wrapper ? wrapper.querySelector('[data-mj-login-trigger]') : null;
        openModal(autoModal, trigger);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapAutoModal);
    } else {
        bootstrapAutoModal();
    }
})();
