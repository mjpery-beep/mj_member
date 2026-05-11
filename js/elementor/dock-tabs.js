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

    function setActive(root, index, focusTarget) {
        var tabs = toArray(root.querySelectorAll('.mj-dock-tabs__tab'));
        var panels = toArray(root.querySelectorAll('.mj-dock-tabs__panel'));
        var targetIndex = Math.max(1, Math.min(index, tabs.length));

        tabs.forEach(function (tab) {
            var current = Number(tab.getAttribute('data-tab-target') || '0');
            var isActive = current === targetIndex;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            if (isActive && focusTarget && typeof tab.focus === 'function') {
                tab.focus();
            }
        });

        panels.forEach(function (panel) {
            var current = Number(panel.getAttribute('data-tab-panel') || '0');
            var isActive = current === targetIndex;
            panel.classList.toggle('is-active', isActive);
            if (isActive) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });

        root.setAttribute('data-active-index', String(targetIndex));
    }

    function getOrientation(root) {
        var dockPosition = root.getAttribute('data-dock-position') || 'bottom';
        if (dockPosition === 'left' || dockPosition === 'right') {
            return 'vertical';
        }
        return 'horizontal';
    }

    function parseJsonAttribute(node, attrName) {
        if (!node || !attrName) {
            return null;
        }
        var raw = node.getAttribute(attrName);
        if (!raw) {
            return null;
        }
        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function buildMosaicPanel(panel) {
        if (!panel || panel.__mjMosaicBound) {
            return;
        }

        var config = parseJsonAttribute(panel, 'data-mosaic-config');
        if (!config || !Array.isArray(config.tiles) || !config.tiles.length) {
            return;
        }

        var transition = typeof config.transition === 'string' ? config.transition : 'hover';
        if (['hover', 'flip', 'fade'].indexOf(transition) === -1) {
            transition = 'hover';
        }

        var speed = Number(config.speed || 5);
        if (!speed || speed < 1) {
            speed = 5;
        }

        var mosaic = document.createElement('div');
        mosaic.className = 'mj-dock-tabs__mosaic';
        mosaic.setAttribute('aria-hidden', 'true');

        config.tiles.forEach(function (tile, index) {
            if (!tile || (typeof tile.before !== 'string' && typeof tile.after !== 'string')) {
                return;
            }

            var tileNode = document.createElement('div');
            tileNode.className = 'mj-dock-tabs__mosaic-tile mj-dock-tabs__mosaic-tile--' + transition;

            var tileDelay = Math.round((((index % 6) * 0.8) + (Math.floor(index / 6) * 0.5)) * 100) / 100;
            var tileDuration = Math.round((speed + (index % 3) * (speed * 0.3)) * 100) / 100;
            tileNode.style.setProperty('--tile-delay', String(tileDelay) + 's');
            tileNode.style.setProperty('--tile-duration', String(tileDuration) + 's');

            if (typeof tile.before === 'string' && tile.before) {
                var before = document.createElement('div');
                before.className = 'mj-dock-tabs__mosaic-before';
                before.style.backgroundImage = 'url("' + tile.before.replace(/"/g, '\\"') + '")';
                tileNode.appendChild(before);
            }

            if (typeof tile.after === 'string' && tile.after) {
                var after = document.createElement('div');
                after.className = 'mj-dock-tabs__mosaic-after';
                after.style.backgroundImage = 'url("' + tile.after.replace(/"/g, '\\"') + '")';
                tileNode.appendChild(after);
            }

            mosaic.appendChild(tileNode);
        });

        panel.insertBefore(mosaic, panel.firstChild);
        panel.__mjMosaicBound = true;
    }

    function initMosaicBackgrounds(root) {
        if (!root) {
            return;
        }
        toArray(root.querySelectorAll('.mj-dock-tabs__panel--mosaic[data-mosaic-config]')).forEach(function (panel) {
            buildMosaicPanel(panel);
        });
    }

    function bindTabs(root) {
        if (!root || root.__mjDockTabsBound) {
            return;
        }

        var tabs = toArray(root.querySelectorAll('.mj-dock-tabs__tab'));
        if (!tabs.length) {
            return;
        }

        root.__mjDockTabsBound = true;

        var defaultIndex = Number(root.getAttribute('data-default-index') || '1');
        if (!defaultIndex || defaultIndex < 1) {
            defaultIndex = 1;
        }

        setActive(root, defaultIndex, false);

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                var target = Number(tab.getAttribute('data-tab-target') || '0');
                if (target > 0) {
                    setActive(root, target, false);
                }
            });

            tab.addEventListener('keydown', function (event) {
                var orientation = getOrientation(root);
                var currentIndex = Number(tab.getAttribute('data-tab-target') || '0');

                if (!currentIndex) {
                    return;
                }

                var targetIndex = 0;
                if (event.key === 'Home') {
                    targetIndex = 1;
                } else if (event.key === 'End') {
                    targetIndex = tabs.length;
                } else if (orientation === 'horizontal' && event.key === 'ArrowRight') {
                    targetIndex = currentIndex + 1 > tabs.length ? 1 : currentIndex + 1;
                } else if (orientation === 'horizontal' && event.key === 'ArrowLeft') {
                    targetIndex = currentIndex - 1 < 1 ? tabs.length : currentIndex - 1;
                } else if (orientation === 'vertical' && event.key === 'ArrowDown') {
                    targetIndex = currentIndex + 1 > tabs.length ? 1 : currentIndex + 1;
                } else if (orientation === 'vertical' && event.key === 'ArrowUp') {
                    targetIndex = currentIndex - 1 < 1 ? tabs.length : currentIndex - 1;
                } else if (event.key === 'Enter' || event.key === ' ') {
                    targetIndex = currentIndex;
                }

                if (targetIndex > 0) {
                    event.preventDefault();
                    setActive(root, targetIndex, true);
                }
            });
        });
    }

    function bindDockHoverEffect(root) {
        if (!root || root.__mjDockHoverBound) {
            return;
        }

        var magnifyEnabled = root.getAttribute('data-magnify') !== '0';
        if (!magnifyEnabled) {
            return;
        }

        var dock = root.querySelector('.mj-dock-tabs__dock');
        if (!dock) {
            return;
        }

        root.__mjDockHoverBound = true;

        function clearHoverState() {
            var tabs = toArray(root.querySelectorAll('.mj-dock-tabs__tab'));
            tabs.forEach(function (tab) {
                tab.style.removeProperty('margin');
            });

            toArray(root.querySelectorAll('.mj-dock-tabs__icon')).forEach(function (icon) {
                icon.style.transform = 'scale(1) translateY(0px)';
            });
        }

        function focus(index) {
            var icons = toArray(root.querySelectorAll('.mj-dock-tabs__icon'));
            if (!icons.length) {
                return;
            }

            clearHoverState();

            var strengthRaw = Number(root.getAttribute('data-magnify-strength') || '1');
            var strength = strengthRaw > 0 ? strengthRaw : 1;

            var farScaleRaw = Number(root.getAttribute('data-magnify-far-scale') || '1.1');
            var nearScaleRaw = Number(root.getAttribute('data-magnify-near-scale') || '1.2');
            var centerScaleRaw = Number(root.getAttribute('data-magnify-center-scale') || '1.5');
            var nearLiftRaw = Number(root.getAttribute('data-magnify-near-lift') || '6');
            var centerLiftRaw = Number(root.getAttribute('data-magnify-center-lift') || '10');

            var farScale = (farScaleRaw > 0 ? farScaleRaw : 1.1) * strength;
            var nearScale = (nearScaleRaw > 0 ? nearScaleRaw : 1.2) * strength;
            var centerScale = (centerScaleRaw > 0 ? centerScaleRaw : 1.5) * strength;
            var nearLift = nearLiftRaw >= 0 ? nearLiftRaw : 6;
            var centerLift = centerLiftRaw >= 0 ? centerLiftRaw : 10;

            var transformations = [
                { idx: index - 2, scale: farScale, translateY: 0 },
                { idx: index - 1, scale: nearScale, translateY: -nearLift },
                { idx: index, scale: centerScale, translateY: -centerLift },
                { idx: index + 1, scale: nearScale, translateY: -nearLift },
                { idx: index + 2, scale: farScale, translateY: 0 }
            ];

            transformations.forEach(function (item) {
                if (!icons[item.idx]) {
                    return;
                }
                icons[item.idx].style.transform = 'scale(' + item.scale + ') translateY(' + item.translateY + 'px)';
            });
        }

        var tabs = toArray(root.querySelectorAll('.mj-dock-tabs__tab'));
        tabs.forEach(function (tab, index) {
            tab.addEventListener('mouseover', function () {
                focus(index);
            });
            tab.addEventListener('mouseleave', clearHoverState);
        });

        dock.addEventListener('mouseleave', clearHoverState);
        dock.addEventListener('blur', clearHoverState, true);
    }

    function syncFullscreenHeaderState(context) {
        var scope = context && context.nodeType === 1 ? context : document;

        // Tous les widgets dock-tabs présents
        var allWidgets = toArray(document.querySelectorAll('.mj-dock-tabs[data-mj-dock-tabs="1"]'));
        var hasWidget = allWidgets.length > 0;

        // Widgets en fullscreen
        var fullscreenWidgets = toArray(document.querySelectorAll('.mj-dock-tabs[data-mj-dock-tabs="1"][data-fullscreen="1"]'));
        var hasFullscreenWidget = fullscreenWidgets.length > 0;

        document.body.classList.toggle('mj-dock-tabs--has-widget', hasWidget);
        document.body.classList.toggle('mj-dock-tabs--has-fullscreen', hasFullscreenWidget);
        document.documentElement.classList.toggle('mj-dock-tabs--has-widget', hasWidget);
        document.documentElement.classList.toggle('mj-dock-tabs--has-fullscreen', hasFullscreenWidget);
    }

    function initAll(context) {
        var scope = context && context.nodeType === 1 ? context : document;
        toArray(scope.querySelectorAll('.mj-dock-tabs[data-mj-dock-tabs="1"]')).forEach(function (widgetRoot) {
            bindTabs(widgetRoot);
            bindDockHoverEffect(widgetRoot);
            initMosaicBackgrounds(widgetRoot);
        });
        syncFullscreenHeaderState(scope);
    }

    domReady(function () {
        initAll(document);

        // En mode editeur Elementor, des re-renders partiels peuvent remplacer le DOM.
        // Observer garantit que chaque nouveau widget dock-tabs est re-initialise.
        if (typeof MutationObserver === 'function') {
            var observer = new MutationObserver(function (mutations) {
                var shouldInit = false;
                for (var i = 0; i < mutations.length; i += 1) {
                    var mutation = mutations[i];
                    if (!mutation.addedNodes || !mutation.addedNodes.length) {
                        continue;
                    }
                    for (var j = 0; j < mutation.addedNodes.length; j += 1) {
                        var node = mutation.addedNodes[j];
                        if (!node || node.nodeType !== 1) {
                            continue;
                        }
                        if (
                            (node.matches && node.matches('.mj-dock-tabs[data-mj-dock-tabs="1"]')) ||
                            (node.querySelector && node.querySelector('.mj-dock-tabs[data-mj-dock-tabs="1"]'))
                        ) {
                            shouldInit = true;
                            break;
                        }
                    }
                    if (shouldInit) {
                        break;
                    }
                }
                if (shouldInit) {
                    initAll(document);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });

    if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-dock-tabs.default', function ($element) {
            if ($element && $element[0]) {
                initAll($element[0]);
            }
        });

        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($element) {
            if ($element && $element[0] && $element[0].querySelector && $element[0].querySelector('.mj-dock-tabs[data-mj-dock-tabs="1"]')) {
                initAll($element[0]);
            }
        });
    }
})();
