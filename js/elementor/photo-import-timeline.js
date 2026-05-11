(function () {
    'use strict';

    function initSlideshow(root) {
        var stage = root.querySelector('[data-mj-photo-slideshow-image]');
        var caption = root.querySelector('[data-mj-photo-slideshow-caption]');
        var counter = root.querySelector('[data-mj-photo-slideshow-counter]');
        var prev = root.querySelector('[data-mj-photo-slideshow-prev]');
        var next = root.querySelector('[data-mj-photo-slideshow-next]');
        var items = Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-slideshow-item]'));
        var intervalMs = parseInt(root.getAttribute('data-slideshow-interval-ms') || '4000', 10) || 0;

        if (!stage || !items.length) {
            return;
        }

        var currentIndex = 0;
        var autoplayId = 0;
        var playOrder = [];
        var orderPosition = 0;
        var idleTimerId = 0;
        var preloadedImages = Object.create(null);
        var pendingPreloads = Object.create(null);
        var idleDelayMs = 1800;

        function preloadImage(url, callback) {
            if (!url) {
                if (typeof callback === 'function') {
                    callback(false);
                }
                return;
            }

            if (preloadedImages[url]) {
                if (typeof callback === 'function') {
                    callback(true);
                }
                return;
            }

            if (pendingPreloads[url]) {
                if (typeof callback === 'function') {
                    pendingPreloads[url].push(callback);
                }
                return;
            }

            pendingPreloads[url] = [];
            if (typeof callback === 'function') {
                pendingPreloads[url].push(callback);
            }

            var imageLoader = new Image();
            imageLoader.decoding = 'async';
            imageLoader.loading = 'eager';

            imageLoader.onload = function () {
                preloadedImages[url] = imageLoader;
                var callbacks = pendingPreloads[url] || [];
                delete pendingPreloads[url];
                callbacks.forEach(function (queuedCallback) {
                    queuedCallback(true);
                });
            };

            imageLoader.onerror = function () {
                var callbacks = pendingPreloads[url] || [];
                delete pendingPreloads[url];
                callbacks.forEach(function (queuedCallback) {
                    queuedCallback(false);
                });
            };

            imageLoader.src = url;
        }

        function warmupSlides(startIndex) {
            var queue = items.map(function (item, index) {
                return {
                    index: index,
                    url: item.getAttribute('data-full') || '',
                };
            }).filter(function (entry) {
                return entry.url !== '';
            });

            if (typeof startIndex === 'number' && startIndex >= 0 && startIndex < items.length) {
                queue.sort(function (left, right) {
                    if (left.index === startIndex) {
                        return -1;
                    }
                    if (right.index === startIndex) {
                        return 1;
                    }
                    return left.index - right.index;
                });
            }

            function pump() {
                if (!queue.length) {
                    return;
                }

                var nextEntry = queue.shift();
                preloadImage(nextEntry.url, function () {
                    window.setTimeout(pump, 60);
                });
            }

            pump();
        }

        function shuffle(list) {
            var shuffled = list.slice();
            for (var index = shuffled.length - 1; index > 0; index -= 1) {
                var swapIndex = Math.floor(Math.random() * (index + 1));
                var temp = shuffled[index];
                shuffled[index] = shuffled[swapIndex];
                shuffled[swapIndex] = temp;
            }
            return shuffled;
        }

        function buildRandomOrder(anchorIndex) {
            var base = items.map(function (_, index) {
                return index;
            });

            if (typeof anchorIndex === 'number' && anchorIndex >= 0 && anchorIndex < items.length) {
                base.splice(anchorIndex, 1);
                playOrder = [anchorIndex].concat(shuffle(base));
                orderPosition = 0;
                return;
            }

            playOrder = shuffle(base);
            orderPosition = 0;
        }

        function syncOrderPosition(index) {
            var nextPosition = playOrder.indexOf(index);
            if (nextPosition >= 0) {
                orderPosition = nextPosition;
                return;
            }

            buildRandomOrder(index);
        }

        function stopAutoplay() {
            if (!autoplayId) {
                return;
            }

            window.clearInterval(autoplayId);
            autoplayId = 0;
        }

        function startAutoplay() {
            stopAutoplay();

            if (intervalMs <= 0 || items.length < 2) {
                return;
            }

            autoplayId = window.setInterval(function () {
                move(1, true);
            }, intervalMs);
        }

        function setNavigationIdle(isIdle) {
            root.classList.toggle('is-nav-idle', isIdle);
        }

        function scheduleIdleState() {
            if (idleTimerId) {
                window.clearTimeout(idleTimerId);
            }

            setNavigationIdle(false);

            idleTimerId = window.setTimeout(function () {
                setNavigationIdle(true);
            }, idleDelayMs);
        }

        function update(index) {
            if (!items[index]) {
                return;
            }

            currentIndex = index;
            var item = items[index];
            var full = item.getAttribute('data-full') || '';
            var title = item.getAttribute('data-title') || '';
            var date = item.getAttribute('data-date') || '';
            var fullForCss = full.replace(/'/g, "\\'");

            stage.alt = title;
            if (full) {
                preloadImage(full, function (loaded) {
                    if (!loaded || currentIndex !== index) {
                        return;
                    }

                    stage.src = full;
                    root.style.setProperty('--mj-photo-slideshow-bg', "url('" + fullForCss + "')");
                });
            }
            if (caption) {
                caption.textContent = title + (date ? ' - ' + date : '');
            }
            if (counter) {
                counter.textContent = String(index + 1) + ' / ' + String(items.length);
            }

            items.forEach(function (button, buttonIndex) {
                button.classList.toggle('is-active', buttonIndex === index);
                if (buttonIndex === index) {
                    button.setAttribute('aria-current', 'true');
                } else {
                    button.removeAttribute('aria-current');
                }
            });

            syncOrderPosition(index);
        }

        function move(step, useRandomOrder) {
            var target = currentIndex;

            if (useRandomOrder) {
                if (!playOrder.length) {
                    buildRandomOrder(currentIndex);
                }

                orderPosition += step;
                if (orderPosition < 0) {
                    buildRandomOrder();
                    orderPosition = playOrder.length - 1;
                } else if (orderPosition >= playOrder.length) {
                    buildRandomOrder();
                }

                target = playOrder[orderPosition];
            } else {
                target = currentIndex + step;
                if (target < 0) {
                    target = items.length - 1;
                }
                if (target >= items.length) {
                    target = 0;
                }
            }

            update(target);
            startAutoplay();
        }

        items.forEach(function (item, index) {
            item.addEventListener('click', function () {
                buildRandomOrder(index);
                update(index);
                startAutoplay();
            });
        });

        if (prev) {
            prev.addEventListener('click', function () {
                move(-1, false);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                move(1, false);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                move(-1, false);
                return;
            }
            if (event.key === 'ArrowRight') {
                move(1, false);
            }
        });

        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);
        root.addEventListener('mousemove', scheduleIdleState);
        root.addEventListener('pointermove', scheduleIdleState);
        root.addEventListener('touchstart', function () {
            setNavigationIdle(false);
        }, { passive: true });

        buildRandomOrder();
        update(playOrder[0] || 0);
        warmupSlides(playOrder[0] || 0);
        scheduleIdleState();
        startAutoplay();
    }

    function initTimeline(root) {
        var yearSections = Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-year-section]'));
        if (!yearSections.length) {
            return;
        }

        var cards = [];
        var lazyImages = Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-image]'));
        var yearButtons = Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-year-jump]'));
        var yearsContainer = root.querySelector('[data-mj-photo-years]');
        var ajaxUrl = root.getAttribute('data-ajax-url') || '';
        var nonce = root.getAttribute('data-nonce') || '';
        var isPreview = root.getAttribute('data-preview') === '1';
        var batchSize = parseInt(root.getAttribute('data-batch-size') || '60', 10) || 60;
        var initialYear = parseInt(root.getAttribute('data-initial-year') || '0', 10) || 0;
        var imageObserver = null;
        var yearObserver = null;
        var yearState = {};
        var activeYear = initialYear;
        var rafId = 0;

        yearSections.forEach(function (section) {
            var year = parseInt(section.getAttribute('data-year') || '0', 10);
            if (!year) {
                return;
            }

            yearState[year] = {
                loading: false,
                loaded: section.getAttribute('data-loaded') === '1',
                count: parseInt(section.getAttribute('data-year-count') || '0', 10) || 0,
            };
        });

        var modal = root.querySelector('[data-mj-photo-modal]');
        var image = root.querySelector('[data-mj-photo-modal-image]');
        var closeTriggers = root.querySelectorAll('[data-mj-photo-close]');
        var prev = root.querySelector('[data-mj-photo-prev]');
        var next = root.querySelector('[data-mj-photo-next]');
        var currentIndex = 0;

        if (!modal || !image) {
            return;
        }

        function bindCard(card) {
            if (!card || card.dataset.bound === '1') {
                return;
            }

            card.dataset.bound = '1';
            cards.push(card);
            card.addEventListener('click', function () {
                var idx = cards.indexOf(card);
                if (idx >= 0) {
                    open(idx);
                }
            });
        }

        function startImageLoad(img) {
            if (!img || img.dataset.loaded === '1') {
                return;
            }

            var source = img.getAttribute('data-src') || '';
            if (!source) {
                return;
            }

            var card = img.closest('.mj-photo-timeline__card');
            if (card) {
                card.classList.add('is-loading');
            }

            img.addEventListener('load', function handleLoad() {
                img.removeEventListener('load', handleLoad);
                img.dataset.loaded = '1';
                if (card) {
                    card.classList.remove('is-loading');
                    card.classList.add('is-loaded');
                }
            });

            img.addEventListener('error', function handleError() {
                img.removeEventListener('error', handleError);
                img.dataset.loaded = '1';
                if (card) {
                    card.classList.remove('is-loading');
                }
            });

            img.src = source;
        }

        function parseMonthKey(ts) {
            var date = new Date((parseInt(ts || '0', 10) || 0) * 1000);
            if (Number.isNaN(date.getTime())) {
                date = new Date();
            }
            return String(date.getFullYear()) + '-' + String(date.getMonth() + 1).padStart(2, '0');
        }

        function parseMonthLabel(ts) {
            var date = new Date((parseInt(ts || '0', 10) || 0) * 1000);
            if (Number.isNaN(date.getTime())) {
                date = new Date();
            }
            var label = date.toLocaleDateString('fr-BE', { month: 'long', year: 'numeric' });
            return label.charAt(0).toUpperCase() + label.slice(1);
        }

        function setActiveYear(year) {
            if (!year || activeYear === year) {
                return;
            }
            activeYear = year;

            yearButtons.forEach(function (button) {
                var buttonYear = parseInt(button.getAttribute('data-year') || '0', 10);
                var isActive = buttonYear === year;
                button.classList.toggle('is-active', isActive);
                if (isActive) {
                    button.setAttribute('aria-current', 'true');
                } else {
                    button.removeAttribute('aria-current');
                }
            });
        }

        function inferActiveYearFromViewport() {
            if (rafId) {
                window.cancelAnimationFrame(rafId);
            }

            rafId = window.requestAnimationFrame(function () {
                var targetYear = activeYear;
                var bestDelta = Number.POSITIVE_INFINITY;
                var viewportAnchor = window.innerHeight * 0.24;

                yearSections.forEach(function (section) {
                    var rect = section.getBoundingClientRect();
                    var year = parseInt(section.getAttribute('data-year') || '0', 10);
                    if (!year) {
                        return;
                    }
                    var delta = Math.abs(rect.top - viewportAnchor);
                    if (delta < bestDelta) {
                        bestDelta = delta;
                        targetYear = year;
                    }
                });

                setActiveYear(targetYear);
            });
        }

        function createCardNode(item) {
            var li = document.createElement('li');
            li.className = 'mj-photo-timeline__item';

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'mj-photo-timeline__card';
            button.setAttribute('data-mj-photo-open', '1');
            button.setAttribute('data-full', item.display_url || '');
            button.setAttribute('data-title', item.title || item.source_name || 'Photo importee');
            button.setAttribute('data-date', item.taken_at_label || '');

            var media = document.createElement('span');
            media.className = 'mj-photo-timeline__media';

            var loader = document.createElement('span');
            loader.className = 'mj-photo-timeline__image-loader';
            loader.setAttribute('aria-hidden', 'true');

            var img = document.createElement('img');
            img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
            img.setAttribute('data-src', item.thumb_url || '');
            img.setAttribute('loading', 'lazy');
            img.setAttribute('decoding', 'async');
            img.setAttribute('fetchpriority', 'low');
            img.setAttribute('data-mj-photo-image', '1');
            img.alt = item.title || item.source_name || 'Photo importee';

            media.appendChild(loader);
            media.appendChild(img);
            button.appendChild(media);
            li.appendChild(button);

            return {
                li: li,
                card: button,
                image: img,
            };
        }

        function ensureMonthSection(yearSection, item) {
            if (!yearSection) {
                return null;
            }

            var monthsContainer = yearSection.querySelector('[data-mj-photo-year-months]');
            if (!monthsContainer) {
                return null;
            }

            var monthKey = parseMonthKey(item.taken_at_ts);
            var monthSection = monthsContainer.querySelector('[data-month-key="' + monthKey + '"]');
            if (monthSection) {
                return monthSection;
            }

            monthSection = document.createElement('section');
            monthSection.className = 'mj-photo-timeline__month';
            monthSection.setAttribute('data-month-key', monthKey);
            monthSection.setAttribute('data-month-label', parseMonthLabel(item.taken_at_ts));

            var title = document.createElement('h4');
            title.className = 'mj-photo-timeline__month-title';
            title.textContent = parseMonthLabel(item.taken_at_ts);

            var grid = document.createElement('ul');
            grid.className = 'mj-photo-timeline__grid';

            monthSection.appendChild(title);
            monthSection.appendChild(grid);
            monthsContainer.appendChild(monthSection);

            return monthSection;
        }

        function appendItemsToYear(yearSection, items) {
            items.forEach(function (item) {
                var section = ensureMonthSection(yearSection, item);
                if (!section) {
                    return;
                }

                var grid = section.querySelector('.mj-photo-timeline__grid');
                if (!grid) {
                    return;
                }

                var nodes = createCardNode(item);
                grid.appendChild(nodes.li);

                bindCard(nodes.card);
                lazyImages.push(nodes.image);
                if (imageObserver) {
                    imageObserver.observe(nodes.image);
                } else {
                    startImageLoad(nodes.image);
                }
            });
        }

        function setYearLoading(yearSection, isLoading) {
            if (!yearSection) {
                return;
            }

            yearSection.classList.toggle('is-loading', isLoading);
        }

        function markYearLoaded(yearSection) {
            if (!yearSection) {
                return;
            }

            yearSection.classList.add('is-loaded');
            yearSection.setAttribute('data-loaded', '1');

            var placeholder = yearSection.querySelector('[data-mj-photo-year-placeholder]');
            if (placeholder) {
                placeholder.classList.add('is-hidden');
            }
        }

        function loadYear(year, source) {
            var state = yearState[year];
            if (!state || state.loaded || state.loading || isPreview || !ajaxUrl || !nonce) {
                return;
            }

            var yearSection = root.querySelector('[data-mj-photo-year-section][data-year="' + year + '"]');
            if (!yearSection) {
                return;
            }

            state.loading = true;
            setYearLoading(yearSection, true);

            var formData = new FormData();
            formData.append('action', 'mj_member_photo_timeline_chunk');
            formData.append('mode', 'year');
            formData.append('nonce', nonce);
            formData.append('year', String(year));
            formData.append('limit', String(Math.max(batchSize * 2, state.count || 1000)));

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.items)) {
                        return;
                    }

                    appendItemsToYear(yearSection, payload.data.items);
                    markYearLoaded(yearSection);
                    state.loaded = true;
                })
                .catch(function () {
                    return null;
                })
                .finally(function () {
                    state.loading = false;
                    setYearLoading(yearSection, false);
                    if (source === 'jump') {
                        inferActiveYearFromViewport();
                    }
                });
        }

        function bindYearNavigation() {
            yearButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var year = parseInt(button.getAttribute('data-year') || '0', 10);
                    if (!year) {
                        return;
                    }

                    var section = root.querySelector('[data-mj-photo-year-section][data-year="' + year + '"]');
                    if (!section) {
                        return;
                    }

                    setActiveYear(year);
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    loadYear(year, 'jump');
                });
            });
        }

        function initYearObserver() {
            if (!('IntersectionObserver' in window)) {
                return;
            }

            yearObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    var section = entry.target;
                    var year = parseInt(section.getAttribute('data-year') || '0', 10);
                    if (!year) {
                        return;
                    }

                    setActiveYear(year);
                    loadYear(year, 'scroll');
                });
            }, {
                root: null,
                rootMargin: '220px 0px',
                threshold: 0.02,
            });

            yearSections.forEach(function (section) {
                yearObserver.observe(section);
            });
        }

        if ('IntersectionObserver' in window) {
            imageObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    var img = entry.target;
                    observer.unobserve(img);
                    startImageLoad(img);
                });
            }, {
                rootMargin: '90px 0px',
                threshold: 0.01,
            });

            lazyImages.forEach(function (img) {
                imageObserver.observe(img);
            });
        } else {
            lazyImages.forEach(startImageLoad);
        }

        function render(index) {
            if (!cards[index]) {
                return;
            }
            currentIndex = index;
            var card = cards[index];
            var full = card.getAttribute('data-full') || '';
            var title = card.getAttribute('data-title') || '';

            image.src = full;
            image.alt = title;
        }

        function open(index) {
            render(index);
            modal.hidden = false;
            document.body.classList.add('mj-photo-timeline-modal-open');
        }

        function close() {
            modal.hidden = true;
            image.src = '';
            document.body.classList.remove('mj-photo-timeline-modal-open');
        }

        function move(step) {
            var target = currentIndex + step;
            if (target < 0) {
                target = cards.length - 1;
            }
            if (target >= cards.length) {
                target = 0;
            }
            render(target);
        }

        Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-open]')).forEach(bindCard);

        Array.prototype.forEach.call(closeTriggers, function (trigger) {
            trigger.addEventListener('click', close);
        });

        if (prev) {
            prev.addEventListener('click', function () {
                move(-1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                move(1);
            });
        }

        bindYearNavigation();
        initYearObserver();

        if (yearsContainer) {
            yearsContainer.addEventListener('scroll', inferActiveYearFromViewport, { passive: true });
        }
        window.addEventListener('scroll', inferActiveYearFromViewport, { passive: true });
        window.addEventListener('resize', inferActiveYearFromViewport, { passive: true });

        document.addEventListener('keydown', function (event) {
            if (modal.hidden) {
                return;
            }
            if (event.key === 'Escape') {
                close();
                return;
            }
            if (event.key === 'ArrowLeft') {
                move(-1);
                return;
            }
            if (event.key === 'ArrowRight') {
                move(1);
            }
        });

        if (initialYear && yearState[initialYear] && !yearState[initialYear].loaded) {
            loadYear(initialYear, 'initial');
        }

        inferActiveYearFromViewport();
    }

    function bootstrap() {
        var roots = document.querySelectorAll('[data-mj-photo-timeline]');
        Array.prototype.forEach.call(roots, function (root) {
            var mode = root.getAttribute('data-render-mode') || 'timeline';
            if (mode === 'slideshow_fullscreen') {
                initSlideshow(root);
                return;
            }
            initTimeline(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
    } else {
        bootstrap();
    }
})();
