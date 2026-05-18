(function () {
    'use strict';

    function initSlideshow(root) {
        var bgA = root.querySelector('[data-mj-slideshow-bg-a]');
        var bgB = root.querySelector('[data-mj-slideshow-bg-b]');
        var frame = root.querySelector('[data-mj-slideshow-frame]');
        var caption = root.querySelector('[data-mj-photo-slideshow-caption]');
        var counter = root.querySelector('[data-mj-photo-slideshow-counter]');
        var prev = root.querySelector('[data-mj-photo-slideshow-prev]');
        var next = root.querySelector('[data-mj-photo-slideshow-next]');
        var playBtn = root.querySelector('[data-mj-photo-slideshow-play]');
        var randomBtn = root.querySelector('[data-mj-photo-slideshow-random]');
        var dragHint = root.querySelector('[data-mj-slideshow-drag-hint]');
        var thumbsContainer = root.querySelector('[data-mj-photo-slideshow-thumbs]');
        var items = Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-slideshow-item]'));
        var originalOrder = items.slice(); // preserve original DOM order for reset
        var intervalMs = parseInt(root.getAttribute('data-slideshow-interval-ms') || '4000', 10) || 0;

        if (!bgA || !bgB || !items.length) {
            return;
        }
        var activeBgName = 'a';

        var currentIndex = 0;
        var autoplayId = 0;
        var playOrder = [];
        var orderPosition = 0;
        var idleTimerId = 0;
        var preloadedImages = Object.create(null);
        var pendingPreloads = Object.create(null);
        var idleDelayMs = 2200;
        var isPlaying = true;
        var isRandom = true;
        var transitionInProgress = false;
        var dragActive = false;
        var dragStartX = 0;
        var dragCurrentX = 0;
        var dragStartTime = 0;
        var dragHintShown = false;

        // ── Preload ──────────────────────────────────────────────────────────

        function preloadImage(url, callback) {
            if (!url) {
                if (typeof callback === 'function') { callback(false); }
                return;
            }
            if (preloadedImages[url]) {
                if (typeof callback === 'function') { callback(true); }
                return;
            }
            if (pendingPreloads[url]) {
                if (typeof callback === 'function') { pendingPreloads[url].push(callback); }
                return;
            }
            pendingPreloads[url] = [];
            if (typeof callback === 'function') { pendingPreloads[url].push(callback); }

            var img = new Image();
            img.decoding = 'async';
            img.loading = 'eager';
            img.onload = function () {
                preloadedImages[url] = img;
                var cbs = pendingPreloads[url] || [];
                delete pendingPreloads[url];
                cbs.forEach(function (cb) { cb(true); });
            };
            img.onerror = function () {
                var cbs = pendingPreloads[url] || [];
                delete pendingPreloads[url];
                cbs.forEach(function (cb) { cb(false); });
            };
            img.src = url;
        }

        function warmupSlides(startIndex) {
            var queue = items.map(function (item, i) {
                return { index: i, url: item.getAttribute('data-full') || '' };
            }).filter(function (e) { return e.url !== ''; });

            if (typeof startIndex === 'number' && startIndex >= 0 && startIndex < items.length) {
                queue.sort(function (a, b) {
                    if (a.index === startIndex) { return -1; }
                    if (b.index === startIndex) { return 1; }
                    return a.index - b.index;
                });
            }

            function pump() {
                if (!queue.length) { return; }
                var e = queue.shift();
                preloadImage(e.url, function () { window.setTimeout(pump, 60); });
            }
            pump();
        }

        // ── Shuffle / Order ──────────────────────────────────────────────────

        function shuffle(list) {
            var arr = list.slice();
            for (var i = arr.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
            }
            return arr;
        }

        function applyThumbOrder(order) {
            if (!thumbsContainer) { return; }
            order.forEach(function (idx) {
                thumbsContainer.appendChild(originalOrder[idx]);
            });
        }

        function restoreThumbOrder() {
            if (!thumbsContainer) { return; }
            originalOrder.forEach(function (el) {
                thumbsContainer.appendChild(el);
            });
        }

        function buildRandomOrder(anchorIndex) {
            var base = items.map(function (_, i) { return i; });
            if (typeof anchorIndex === 'number' && anchorIndex >= 0 && anchorIndex < items.length) {
                base.splice(anchorIndex, 1);
                playOrder = [anchorIndex].concat(shuffle(base));
                orderPosition = 0;
            } else {
                playOrder = shuffle(base);
                orderPosition = 0;
            }
            if (isRandom) { applyThumbOrder(playOrder); }
        }

        function syncOrderPosition(index) {
            var pos = playOrder.indexOf(index);
            if (pos >= 0) { orderPosition = pos; return; }
            buildRandomOrder(index);
        }

        // ── Autoplay ─────────────────────────────────────────────────────────

        function stopAutoplay() {
            if (!autoplayId) { return; }
            window.clearInterval(autoplayId);
            autoplayId = 0;
        }

        function startAutoplay() {
            stopAutoplay();
            if (!isPlaying || intervalMs <= 0 || items.length < 2) { return; }
            autoplayId = window.setInterval(function () {
                move(1, isRandom);
            }, intervalMs);
        }

        function setPlayState(playing) {
            isPlaying = playing;
            if (playBtn) {
                playBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
                playBtn.classList.toggle('is-active', playing);
            }
            if (playing) {
                startAutoplay();
            } else {
                stopAutoplay();
            }
        }

        function setRandomState(random) {
            isRandom = random;
            if (randomBtn) {
                randomBtn.setAttribute('aria-pressed', random ? 'true' : 'false');
                randomBtn.classList.toggle('is-active', random);
            }
            if (random) {
                buildRandomOrder(currentIndex);
            } else {
                restoreThumbOrder();
            }
        }

        // ── Idle UI ──────────────────────────────────────────────────────────

        function setNavigationIdle(isIdle) {
            root.classList.toggle('is-nav-idle', isIdle);
        }

        function scheduleIdleState() {
            if (idleTimerId) { window.clearTimeout(idleTimerId); }
            setNavigationIdle(false);
            idleTimerId = window.setTimeout(function () {
                setNavigationIdle(true);
            }, idleDelayMs);
        }

        // ── Transition Engine (background layers) ──────────────────────────

        function doTransition(url, direction, onReady) {
            var incoming = activeBgName === 'a' ? bgB : bgA;
            var outgoing = activeBgName === 'a' ? bgA : bgB;

            if (transitionInProgress) {
                // Snap immediately without animation
                incoming.style.backgroundImage = "url('" + url.replace(/'/g, "\\'") + "')";
                incoming.classList.add('is-active');
                outgoing.classList.remove('is-active');
                outgoing.style.backgroundImage = '';
                activeBgName = activeBgName === 'a' ? 'b' : 'a';
                if (typeof onReady === 'function') { onReady(); }
                return;
            }

            function commit() {
                var enterClass = direction === 'left' ? 'is-entering-left'
                    : direction === 'fade' ? 'is-entering-fade'
                    : 'is-entering-right';

                incoming.style.backgroundImage = "url('" + url.replace(/'/g, "\\'") + "')";
                transitionInProgress = true;

                // Force reflow
                void incoming.offsetWidth;

                incoming.classList.add(enterClass);

                incoming.addEventListener('animationend', function handler() {
                    incoming.removeEventListener('animationend', handler);
                    incoming.classList.remove(enterClass);
                    incoming.classList.add('is-active');
                    outgoing.classList.remove('is-active');
                    outgoing.style.backgroundImage = '';
                    activeBgName = activeBgName === 'a' ? 'b' : 'a';
                    transitionInProgress = false;
                    if (typeof onReady === 'function') { onReady(); }
                }, { once: true });
            }

            preloadImage(url, function () { commit(); });
        }

        // ── Update / Move ─────────────────────────────────────────────────────

        function update(index, direction) {
            if (!items[index]) { return; }

            var prevIndex = currentIndex;
            currentIndex = index;
            var item = items[index];
            var full = item.getAttribute('data-full') || '';
            var title = item.getAttribute('data-title') || '';
            var date = item.getAttribute('data-date') || '';

            var captionText = date || '';
            var transDir = direction || (index >= prevIndex ? 'right' : 'left');

            if (full) {
                doTransition(full, transDir);
            }

            if (caption) { caption.textContent = captionText; }
            // Counter shows position in the current visual order (random or sequential)
            var displayPos = isRandom ? (playOrder.indexOf(index) + 1 || orderPosition + 1) : (index + 1);
            if (counter) { counter.textContent = String(displayPos) + ' / ' + String(items.length); }

            items.forEach(function (button, i) {
                var active = i === index;
                button.classList.toggle('is-active', active);
                if (active) {
                    button.setAttribute('aria-current', 'true');
                    if (button.scrollIntoView) {
                        button.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
                    }
                } else {
                    button.removeAttribute('aria-current');
                }
            });

            syncOrderPosition(index);
        }

        function move(step, useRandomOrder, direction) {
            var target = currentIndex;

            if (useRandomOrder) {
                if (!playOrder.length) { buildRandomOrder(currentIndex); }
                orderPosition += step;
                if (orderPosition < 0) { buildRandomOrder(); orderPosition = playOrder.length - 1; }
                else if (orderPosition >= playOrder.length) { buildRandomOrder(); }
                target = playOrder[orderPosition];
            } else {
                target = currentIndex + step;
                if (target < 0) { target = items.length - 1; }
                if (target >= items.length) { target = 0; }
            }

            var dir = direction || (step > 0 ? 'right' : 'left');
            update(target, dir);
            startAutoplay();
        }

        // ── Drag / Swipe ──────────────────────────────────────────────────────

        var dragThreshold = 72;
        var velocityThreshold = 0.28;

        function showDragHint() {
            if (!dragHint || dragHintShown) { return; }
            dragHintShown = true;
            dragHint.classList.add('is-visible');
            window.setTimeout(function () {
                dragHint.classList.remove('is-visible');
            }, 2200);
        }

        var dragSurface = frame || root;
        dragSurface.style.touchAction = 'pan-y';

        dragSurface.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && e.button !== 0) { return; }
            dragActive = true;
            dragStartX = e.clientX;
            dragCurrentX = e.clientX;
            dragStartTime = Date.now();
            dragSurface.setPointerCapture(e.pointerId);
            stopAutoplay();
            root.classList.add('is-dragging');
            setNavigationIdle(false);
        });

        dragSurface.addEventListener('pointermove', function (e) {
            if (!dragActive) { return; }
            dragCurrentX = e.clientX;
            var dx = dragCurrentX - dragStartX;
            // Translate active bg layer as visual feedback
            var activeBgEl = activeBgName === 'a' ? bgA : bgB;
            activeBgEl.style.transform = 'translateX(' + (dx * 0.18) + 'px) scale(1.02)';
            activeBgEl.style.transition = 'none';
        });

        dragSurface.addEventListener('pointerup', function () {
            if (!dragActive) { return; }
            dragActive = false;
            root.classList.remove('is-dragging');
            var dx = dragCurrentX - dragStartX;
            var dt = Math.max(Date.now() - dragStartTime, 1);
            var velocity = Math.abs(dx) / dt;

            // Reset bg layer position
            var activeBgEl = activeBgName === 'a' ? bgA : bgB;
            activeBgEl.style.transform = '';
            activeBgEl.style.transition = '';

            if (Math.abs(dx) > dragThreshold || velocity > velocityThreshold) {
                var dir = dx < 0 ? 'right' : 'left';
                var step = dx < 0 ? 1 : -1;
                move(step, false, dir);
                scheduleIdleState();
            } else {
                // Spring snap back
                activeBgEl.style.transition = 'transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1)';
                activeBgEl.style.transform = '';
                window.setTimeout(function () { activeBgEl.style.transition = ''; }, 380);
                if (isPlaying) { startAutoplay(); }
                scheduleIdleState();
            }
        });

        dragSurface.addEventListener('pointercancel', function () {
            if (!dragActive) { return; }
            dragActive = false;
            root.classList.remove('is-dragging');
            var activeBgEl = activeBgName === 'a' ? bgA : bgB;
            activeBgEl.style.transform = '';
            activeBgEl.style.transition = '';
            if (isPlaying) { startAutoplay(); }
        });

        // ── Controls ──────────────────────────────────────────────────────────

        items.forEach(function (item, index) {
            item.addEventListener('click', function () {
                buildRandomOrder(index);
                update(index, 'fade');
                startAutoplay();
            });
        });

        if (prev) {
            prev.addEventListener('click', function () {
                move(-1, false, 'left');
                scheduleIdleState();
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                move(1, false, 'right');
                scheduleIdleState();
            });
        }

        if (playBtn) {
            playBtn.addEventListener('click', function () {
                setPlayState(!isPlaying);
                scheduleIdleState();
            });
        }

        if (randomBtn) {
            randomBtn.addEventListener('click', function () {
                setRandomState(!isRandom);
                scheduleIdleState();
            });
        }

        function isEditableTarget(target) {
            if (!target || target.nodeType !== 1) {
                return false;
            }
            if (target.isContentEditable) {
                return true;
            }
            var tagName = target.tagName;
            return tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT';
        }

        function canHandleSlideshowShortcut(event) {
            if (!event || event.defaultPrevented) {
                return false;
            }
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return false;
            }

            var target = event.target;
            if (isEditableTarget(target)) {
                return false;
            }

            var activeElement = document.activeElement;
            if (activeElement && isEditableTarget(activeElement)) {
                return false;
            }

            // Restrict global shortcuts to the active/hovered slideshow instance.
            if (activeElement && root.contains(activeElement)) {
                return true;
            }
            if (target && root.contains(target)) {
                return true;
            }
            if (root.matches && root.matches(':hover')) {
                return true;
            }

            return false;
        }

        document.addEventListener('keydown', function (event) {
            if (!canHandleSlideshowShortcut(event)) {
                return;
            }
            if (event.key === 'ArrowLeft') { move(-1, false, 'left'); scheduleIdleState(); return; }
            if (event.key === 'ArrowRight') { move(1, false, 'right'); scheduleIdleState(); return; }
            if (event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                setPlayState(!isPlaying);
            }
        });

        root.addEventListener('mouseleave', function () {
            if (isPlaying) { startAutoplay(); }
        });
        root.addEventListener('mousemove', scheduleIdleState);
        root.addEventListener('pointermove', function (e) {
            if (!dragActive) { scheduleIdleState(); }
        });
        root.addEventListener('touchstart', function () {
            setNavigationIdle(false);
            showDragHint();
        }, { passive: true });

        // ── Init ──────────────────────────────────────────────────────────────

        // bgA already has the first image via inline style (PHP rendered)
        // Mark initial state without triggering a transition
        setRandomState(true);  // builds playOrder + reorders thumbs
        setPlayState(true);
        // Set counter/caption for first slide without transition
        var firstIdx = playOrder[0] || 0;
        if (items[firstIdx]) {
            var firstItem = items[firstIdx];
            var firstTitle = firstItem.getAttribute('data-title') || '';
            var firstDate  = firstItem.getAttribute('data-date') || '';
            if (caption) { caption.textContent = firstDate || ''; }
            if (counter) { counter.textContent = '1 / ' + String(items.length); }
            currentIndex = firstIdx;
            syncOrderPosition(firstIdx);
            items.forEach(function (b, i) {
                b.classList.toggle('is-active', i === firstIdx);
                if (i === firstIdx) { b.setAttribute('aria-current', 'true'); }
                else { b.removeAttribute('aria-current'); }
            });
        }
        warmupSlides(firstIdx);
        scheduleIdleState();

        // Show drag hint briefly on first touch-capable device
        if (window.matchMedia('(pointer: coarse)').matches) {
            window.setTimeout(showDragHint, 1200);
        }
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
