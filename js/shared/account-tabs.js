(function () {
    'use strict';

    function initTabs(nav) {
        var scope = nav.parentNode;
        if (!scope) {
            return;
        }

        var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-mj-tab]'));
        var panels = Array.prototype.slice.call(scope.querySelectorAll('[data-mj-tab-panel]'));
        if (!tabs.length || !panels.length) {
            return;
        }

        function activate(key) {
            tabs.forEach(function (t) {
                var isActive = t.getAttribute('data-mj-tab') === key;
                t.classList.toggle('mj-account-tab--active', isActive);
                t.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                var isActive = p.getAttribute('data-mj-tab-panel') === key;
                if (isActive) {
                    p.removeAttribute('hidden');
                } else {
                    p.setAttribute('hidden', '');
                }
            });
        }

        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-mj-tab]');
            if (!btn || !nav.contains(btn)) {
                return;
            }
            e.preventDefault();
            activate(btn.getAttribute('data-mj-tab'));
        });
    }

    function setup() {
        var navs = document.querySelectorAll('[data-mj-account-tabs]');
        for (var i = 0; i < navs.length; i++) {
            if (navs[i].dataset.mjTabsInit === '1') {
                continue;
            }
            navs[i].dataset.mjTabsInit = '1';
            initTabs(navs[i]);
        }
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
    }
})();
