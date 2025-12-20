(function () {
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
            if (typeof callback !== 'function') {
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, { once: true });
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
            var arr = [];
            for (var i = 0; i < collection.length; i += 1) {
                arr.push(collection[i]);
            }
            return arr;
        };

    function setActivePane(root, paneId) {
        var tabs = toArray(root.querySelectorAll('.mj-payments-overview__tab'));
        var panes = toArray(root.querySelectorAll('.mj-payments-overview__pane'));
        var targetId = paneId || 'confirmed';

        tabs.forEach(function (tab) {
            var matches = tab.getAttribute('data-pane') === targetId;
            tab.classList.toggle('is-active', matches);
            tab.setAttribute('aria-selected', matches ? 'true' : 'false');
            tab.setAttribute('tabindex', matches ? '0' : '-1');
        });

        panes.forEach(function (pane) {
            var matches = pane.getAttribute('data-pane') === targetId;
            pane.classList.toggle('is-active', matches);
            if (matches) {
                pane.removeAttribute('hidden');
            } else {
                pane.setAttribute('hidden', 'hidden');
            }
        });
    }

    function generateQrUrl(url) {
        if (!url) {
            return '';
        }
        try {
            return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(url) + '&format=png&ecc=M&margin=0';
        } catch (error) {
            return '';
        }
    }

    function bindQrModal(root) {
        if (!root || root.__mjPaymentsOverviewModal) {
            return;
        }

        var modal = root.querySelector('[data-qr-modal]');
        if (!modal) {
            return;
        }

        root.__mjPaymentsOverviewModal = true;

        var closeTriggers = toArray(modal.querySelectorAll('[data-qr-modal-close]'));
        var qrImage = modal.querySelector('[data-qr-image]');
        var qrLink = modal.querySelector('[data-qr-link]');
        var closeButton = modal.querySelector('.mj-payments-overview__qr-modal-close');
        var altText = modal.getAttribute('data-qr-alt') || '';
        var lastTrigger = null;

        function setLinkVisibility(url) {
            if (!qrLink) {
                return;
            }
            if (url) {
                qrLink.removeAttribute('hidden');
                qrLink.setAttribute('href', url);
            } else {
                qrLink.setAttribute('hidden', 'hidden');
                qrLink.setAttribute('href', '#');
            }
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        }

        function openModal(trigger, checkoutUrl, qrUrl) {
            lastTrigger = trigger || null;

            if (qrImage) {
                qrImage.setAttribute('src', qrUrl || '');
                if (altText) {
                    qrImage.setAttribute('alt', altText);
                }
            }

            setLinkVisibility(checkoutUrl);

            modal.removeAttribute('hidden');
            modal.classList.add('is-visible');
            document.addEventListener('keydown', onKeydown);

            if (closeButton && typeof closeButton.focus === 'function') {
                closeButton.focus();
            }
        }

        function closeModal() {
            modal.setAttribute('hidden', 'hidden');
            modal.classList.remove('is-visible');
            document.removeEventListener('keydown', onKeydown);

            if (qrImage) {
                qrImage.setAttribute('src', '');
            }

            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus();
            }

            lastTrigger = null;
        }

        closeTriggers.forEach(function (element) {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });
        });

        toArray(root.querySelectorAll('[data-action="show-qr"]')).forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                var qrUrl = button.getAttribute('data-qr-url') || '';
                var checkoutUrl = button.getAttribute('data-checkout-url') || '';

                if (!qrUrl && checkoutUrl) {
                    qrUrl = generateQrUrl(checkoutUrl);
                }

                if (!qrUrl) {
                    return;
                }

                openModal(button, checkoutUrl, qrUrl);
            });
        });
    }

    function bindTabs(root) {
        if (!root || root.__mjPaymentsOverviewTabs) {
            return;
        }

        root.__mjPaymentsOverviewTabs = true;

        var displayMode = root.getAttribute('data-display-mode') || '';
        if (displayMode === 'stack') {
            root.classList.add('is-stack');
            return;
        }

        var tabs = toArray(root.querySelectorAll('.mj-payments-overview__tab'));
        if (!tabs.length) {
            return;
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                var paneId = tab.getAttribute('data-pane');
                if (paneId) {
                    setActivePane(root, paneId);
                }
            });

            tab.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    var paneId = tab.getAttribute('data-pane');
                    if (paneId) {
                        setActivePane(root, paneId);
                    }
                }
            });
        });

        var defaultPane = root.getAttribute('data-default-pane');
        if (defaultPane) {
            setActivePane(root, defaultPane);
            return;
        }

        var initial = null;
        for (var i = 0; i < tabs.length; i += 1) {
            if (tabs[i].classList.contains('is-active')) {
                initial = tabs[i];
                break;
            }
        }
        if (!initial) {
            initial = tabs[0];
        }
        if (initial) {
            setActivePane(root, initial.getAttribute('data-pane'));
        }
    }

    function initAll(context) {
        var scope = context && context.nodeType === 1 ? context : document;
        toArray(scope.querySelectorAll('.mj-payments-overview')).forEach(function (widgetRoot) {
            bindTabs(widgetRoot);
            bindQrModal(widgetRoot);
        });
    }

    domReady(function () {
        initAll(document);
    });

    if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-payments-overview.default', function ($element) {
            if ($element && $element[0]) {
                initAll($element[0]);
            }
        });
    }
})();
