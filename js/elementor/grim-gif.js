(function () {
    'use strict';

    function parseGifPool(rawValue) {
        if (!rawValue || typeof rawValue !== 'string') {
            return [];
        }

        try {
            var decoded = JSON.parse(rawValue);
            if (!Array.isArray(decoded)) {
                return [];
            }

            return decoded
                .map(function (item) {
                    if (!item || typeof item !== 'object') {
                        return null;
                    }

                    var url = typeof item.url === 'string' ? item.url.trim() : '';
                    var name = typeof item.name === 'string' ? item.name.trim() : '';
                    if (url === '') {
                        return null;
                    }

                    return {
                        url: url,
                        name: name,
                    };
                })
                .filter(function (item) {
                    return item !== null;
                });
        } catch (error) {
            return [];
        }
    }

    function buildImageUrl(baseUrl) {
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
        return baseUrl + separator + 'mjts=' + Date.now();
    }

    function parseMessagePool(rawValue) {
        if (!rawValue || typeof rawValue !== 'string') {
            return [];
        }

        try {
            var decoded = JSON.parse(rawValue);
            if (!Array.isArray(decoded)) {
                return [];
            }

            return decoded
                .map(function (item) {
                    return typeof item === 'string' ? item.trim() : '';
                })
                .filter(function (item) {
                    return item !== '';
                });
        } catch (error) {
            return [];
        }
    }

    function pickNextGif(pool, currentUrl) {
        if (!Array.isArray(pool) || pool.length < 2) {
            return null;
        }

        var candidates = pool.filter(function (item) {
            return item && item.url && item.url !== currentUrl;
        });

        if (!candidates.length) {
            return null;
        }

        var index = Math.floor(Math.random() * candidates.length);
        return candidates[index] || null;
    }

    function pickRandomMessage(pool, currentMessage) {
        if (!Array.isArray(pool) || pool.length === 0) {
            return '';
        }

        if (pool.length === 1) {
            return pool[0] || '';
        }

        var candidates = pool.filter(function (item) {
            return item !== currentMessage;
        });

        if (!candidates.length) {
            candidates = pool.slice();
        }

        var index = Math.floor(Math.random() * candidates.length);
        return candidates[index] || '';
    }

    function normalizeMessage(text) {
        if (typeof text !== 'string') {
            return '';
        }

        return text.replace(/^\s*["“”']?/, '').replace(/["“”']?\s*$/, '').trim();
    }

    function GrimGifSwitcher(root) {
        this.root = root;
        this.image = root.querySelector('[data-mj-grim-gif-image]');
        this.caption = root.querySelector('[data-mj-grim-gif-caption]');
        this.message = root.querySelector('[data-mj-grim-gif-message]');
        this.pool = parseGifPool(root.getAttribute('data-gif-pool'));
        this.messagePool = parseMessagePool(root.getAttribute('data-message-pool'));
        this.enabled = root.getAttribute('data-switch-enabled') === '1';
        this.showFilename = root.getAttribute('data-show-filename') === '1';
        this.altLabel = root.getAttribute('data-alt-label') || 'Animation Grimlins';

        var intervalRaw = parseInt(root.getAttribute('data-switch-interval') || '0', 10);
        this.intervalMs = Number.isFinite(intervalRaw) && intervalRaw > 0 ? intervalRaw * 1000 : 0;

        this.currentUrl = this.image && this.image.getAttribute('src') ? this.image.getAttribute('src').split('?')[0] : '';
        this.currentMessage = this.message ? normalizeMessage(this.message.textContent || '') : '';
        this.timer = null;
    }

    GrimGifSwitcher.prototype.start = function () {
        if (!this.root || !this.image || !this.enabled || this.pool.length < 2 || this.intervalMs <= 0) {
            return;
        }

        this.stop();
        var self = this;

        this.timer = window.setInterval(function () {
            self.switchGif();
        }, this.intervalMs);
    };

    GrimGifSwitcher.prototype.stop = function () {
        if (this.timer !== null) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    };

    GrimGifSwitcher.prototype.switchGif = function () {
        var next = pickNextGif(this.pool, this.currentUrl);
        if (!next) {
            return;
        }

        this.currentUrl = next.url;
        this.image.src = buildImageUrl(next.url);

        if (next.name) {
            this.image.alt = this.altLabel + ' : ' + next.name;
        } else {
            this.image.alt = this.altLabel;
        }

        if (this.caption && this.showFilename) {
            this.caption.textContent = next.name ? '#' + next.name : '';
        }

        if (this.message && this.messagePool.length > 0) {
            var nextMessage = pickRandomMessage(this.messagePool, this.currentMessage);
            if (nextMessage) {
                this.currentMessage = nextMessage;
                this.message.textContent = '“' + nextMessage + '”';
            }
        }
    };

    function initSwitchers(scope) {
        var container = scope && scope.querySelectorAll ? scope : document;
        var nodes = container.querySelectorAll('[data-mj-grim-gif]');

        nodes.forEach(function (node) {
            if (node.__mjGrimGifSwitcherInitialized) {
                return;
            }

            node.__mjGrimGifSwitcherInitialized = true;
            var switcher = new GrimGifSwitcher(node);
            switcher.start();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initSwitchers(document);
        });
    } else {
        initSwitchers(document);
    }

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-grim-gif.default', function ($scope) {
            var root = $scope && $scope[0] ? $scope[0] : null;
            if (root) {
                initSwitchers(root);
            }
        });
    }
})();
