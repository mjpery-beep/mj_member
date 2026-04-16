/**
 * MJ Header Widget — JavaScript
 *
 * Gère les dropdowns, sticky, lazy-loading AJAX (notifications, agenda,
 * nextcloud), login form et burger mobile.
 *
 * @package MjMember
 */
(function () {
    'use strict';

    // =========================================================================
    // MjHeader class
    // =========================================================================

    /**
     * @param {HTMLElement} el       Root element (.mj-header)
     * @param {Object}      config   Config JSON from data-config attribute
     */
    function MjHeader(el, config) {
        this.el             = el;
        this.config         = config || {};
        this.activeDropdown = null;
        this._isStuck       = false;
        this._scrollHandler = null;
        this._refreshTimer  = null;

        /** Track which lazy dropdowns have been loaded already */
        this._loaded = {
            notifications: false,
            agenda:        false,
            nextcloud:     false,
        };

        this._subMenuTimers = [];
        this._init();
    }

    // -------------------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------------------

    MjHeader.prototype._init = function () {
        this._bindTriggers();
        this._bindCloseBtns();
        this._bindOverlay();
        this._bindEscape();
        this._bindBurger();
        this._bindSubMenus();
        this._bindLoginForm();
        this._bindNotifActions();
        this._bindAccCardNotifPreview();

        if (this.config.sticky) {
            this._initSticky();
        }

        if (this.config.notifAutoRefresh && this.config.memberId && !this.config.isPreview) {
            this._startNotifPolling();
        }
    };

    // -------------------------------------------------------------------------
    // Event binding
    // -------------------------------------------------------------------------

    MjHeader.prototype._bindTriggers = function () {
        var self = this;
        this.el.querySelectorAll('[data-mj-header-trigger]').forEach(function (btn) {
            var name = btn.getAttribute('data-mj-header-trigger');

            // Resolve direct URL from the dropdown's header link (if any)
            var dropdown    = self.el.querySelector('[data-mj-header-dropdown="' + name + '"]');
            var headerLink  = dropdown ? dropdown.querySelector('.mj-header-dropdown__header-link') : null;
            var directHref  = headerLink ? headerLink.getAttribute('href') : '';
            var isGuestAccountTrigger = name === 'account' && !self.config.isLoggedIn && !self.config.isPreview;
            if (isGuestAccountTrigger && self.config.accountGuestRedirect) {
                directHref = self.config.accountGuestRedirect;
            }
            var isDirectLink = !!(directHref && directHref !== '#' && directHref !== '');

            // Touch : tap → dropdown, long press (500ms) → navigate
            var longPressTimer = null;
            var longPressFired = false;

            btn.addEventListener('touchstart', function (e) {
                if (!isDirectLink) return;
                longPressFired = false;
                longPressTimer = setTimeout(function () {
                    longPressFired = true;
                    window.location.href = directHref;
                }, 500);
            }, { passive: true });

            btn.addEventListener('touchmove', function () {
                // Cancel long press if the finger moves (scroll intent)
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }, { passive: true });

            btn.addEventListener('touchend', function (e) {
                // Always prevent the synthetic click that follows touchend
                e.preventDefault();
                e.stopPropagation();

                clearTimeout(longPressTimer);
                longPressTimer = null;

                // Long press already triggered navigation — do nothing
                if (longPressFired) return;

                // Short tap → toggle dropdown
                if (self.activeDropdown === name) {
                    self._closeAll();
                } else {
                    self._closeAll();
                    self._openDropdown(name);
                }
            });

            // Click (desktop fallback — ignored on touch devices since touchend fires first)
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (isDirectLink) {
                    window.location.href = directHref;
                    return;
                }
                if (self.activeDropdown === name) {
                    self._closeAll();
                } else {
                    self._closeAll();
                    self._openDropdown(name);
                }
            });

            // Middle-click → open link in new tab
            btn.addEventListener('mousedown', function (e) {
                if (e.button === 1 && isDirectLink) {
                    e.preventDefault(); // prevent auto-scroll
                    e.stopPropagation();
                    window.open(directHref, '_blank', 'noopener');
                }
            });

            // Hover (desktop only — skip touch devices)
            var item = btn.closest('.mj-header__action-item');
            if (!item) return;
            var hoverTimer = null;

            item.addEventListener('mouseenter', function () {
                if (window.matchMedia('(hover: hover)').matches) {
                    clearTimeout(hoverTimer);
                    if (self.activeDropdown !== name) {
                        self._closeAll();
                        self._openDropdown(name, true); // skipOverlay: avoid cursor flicker
                    }
                }
            });

            item.addEventListener('mouseleave', function () {
                if (window.matchMedia('(hover: hover)').matches) {
                    hoverTimer = setTimeout(function () {
                        if (self.activeDropdown === name) {
                            self._closeAll();
                        }
                    }, 320);
                }
            });
        });
    };

    MjHeader.prototype._bindCloseBtns = function () {
        var self = this;
        this.el.querySelectorAll('.mj-header-dropdown__close').forEach(function (btn) {
            btn.addEventListener('click', function () { self._closeAll(); });
        });
    };

    MjHeader.prototype._bindOverlay = function () {
        var self = this;
        var overlay = this.el.querySelector('.mj-header-overlay');
        if (overlay) {
            overlay.addEventListener('click', function () { self._closeAll(); });
        }
    };

    MjHeader.prototype._bindEscape = function () {
        var self = this;
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self.activeDropdown) {
                self._closeAll();
            }
        });
    };

    MjHeader.prototype._bindBurger = function () {
        var self = this;
        var burger = this.el.querySelector('.mj-header__burger');
        var navWrap = this.el.querySelector('.mj-header__nav');
        if (!burger || !navWrap) return;

        burger.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = navWrap.classList.contains('mj-header__nav--active');
            if (isOpen) {
                navWrap.classList.remove('mj-header__nav--active');
                burger.setAttribute('aria-expanded', 'false');
            } else {
                navWrap.classList.add('mj-header__nav--active');
                burger.setAttribute('aria-expanded', 'true');
                self._closeAll();
            }
        });

        // Close burger on outside click
        document.addEventListener('click', function (e) {
            if (!self.el.contains(e.target) && navWrap.classList.contains('mj-header__nav--active')) {
                navWrap.classList.remove('mj-header__nav--active');
                burger.setAttribute('aria-expanded', 'false');
            }
        });
    };

    MjHeader.prototype._bindSubMenus = function () {
        var HOVER_DELAY    = 200;
        var DOUBLE_TAP_MS  = 320;
        var nav = this.el.querySelector('.mj-header__nav');
        if (!nav) return;

        nav.querySelectorAll('li').forEach(function (li) {
            var sub  = li.querySelector(':scope > ul');
            if (!sub) return;

            var link      = li.querySelector(':scope > a');
            var hoverTimer = null;
            var lastTap    = 0;

            // ------------------------------------------------------------------
            // Hover (desktop)
            // ------------------------------------------------------------------
            li.addEventListener('mouseenter', function () {
                clearTimeout(hoverTimer);
                li.classList.add('mj-nav-open');
            });

            li.addEventListener('mouseleave', function () {
                hoverTimer = setTimeout(function () {
                    li.classList.remove('mj-nav-open');
                }, HOVER_DELAY);
            });

            // ------------------------------------------------------------------
            // Touch : 1er tap → ouvre le sous-menu
            //         2ème tap / double-tap → suit le lien principal
            // ------------------------------------------------------------------
            li.addEventListener('touchend', function (e) {
                // En mode burger (mobile), les sous-menus sont toujours visibles
                if (nav.classList.contains('mj-header__nav--active')) return;

                var now          = Date.now();
                var isDoubleTap  = (now - lastTap) < DOUBLE_TAP_MS;
                lastTap          = now;

                if (isDoubleTap) {
                    // Double-tap → navigation immédiate, on laisse le click passer
                    return;
                }

                if (!li.classList.contains('mj-nav-open')) {
                    // 1er tap : ouvre le sous-menu, bloque la navigation
                    e.preventDefault();
                    nav.querySelectorAll('li.mj-nav-open').forEach(function (other) {
                        if (other !== li) other.classList.remove('mj-nav-open');
                    });
                    li.classList.add('mj-nav-open');
                }
                // Sous-menu déjà ouvert → on ne fait rien, le click suit le lien
            }, { passive: false });

            // ------------------------------------------------------------------
            // Force Touch (Safari / macOS trackpad) → suit le lien principal
            // ------------------------------------------------------------------
            if (link) {
                li.addEventListener('webkitmouseforcedown', function () {
                    window.location.href = link.href;
                });
            }
        });

        // Ferme les sous-menus ouverts au touch en dehors de la nav
        document.addEventListener('touchstart', function (e) {
            if (!nav.contains(e.target)) {
                nav.querySelectorAll('li.mj-nav-open').forEach(function (li) {
                    li.classList.remove('mj-nav-open');
                });
            }
        }, { passive: true });
    };

    MjHeader.prototype._bindLoginForm = function () {
        var self = this;
        var form = this.el.querySelector('.mj-header-login-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            self._submitLoginForm(form);
        });

        // Toggle show/hide password
        var toggleBtn = form.querySelector('.mj-header-login-form__toggle-pwd');
        var pwdInput  = form.querySelector('[name="pwd"]');
        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener('click', function () {
                var isHidden = pwdInput.type === 'password';
                pwdInput.type = isHidden ? 'text' : 'password';
                var eyeShow = toggleBtn.querySelector('.mj-header-login-form__eye-show');
                var eyeHide = toggleBtn.querySelector('.mj-header-login-form__eye-hide');
                if (eyeShow) eyeShow.style.display = isHidden ? 'none' : '';
                if (eyeHide) eyeHide.style.display = isHidden ? '' : 'none';
            });
        }
    };

    MjHeader.prototype._bindNotifActions = function () {
        var self = this;
        var dropdown = this.el.querySelector('[data-mj-header-dropdown="notifications"]');
        if (!dropdown) return;

        dropdown.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-notif-action]');
            if (!btn) return;
            e.stopPropagation();

            var action      = btn.getAttribute('data-notif-action');
            var recipientId = parseInt(btn.getAttribute('data-recipient-id') || '0', 10);
            var notifId     = parseInt(btn.getAttribute('data-notification-id') || '0', 10);

            if (action === 'mark-read') {
                self._notifMarkRead(recipientId, btn);
            } else if (action === 'archive') {
                self._notifArchive(recipientId, notifId, btn);
            } else if (action === 'mark-all-read') {
                self._notifMarkAllRead(btn);
            } else if (action === 'archive-all') {
                self._notifArchiveAll(btn);
            }
        });
    };

    // -------------------------------------------------------------------------
    // Notification preview on account card hover
    // -------------------------------------------------------------------------

    MjHeader.prototype._bindAccCardNotifPreview = function () {
        var self       = this;
        var _preview   = null;
        var _hideTimer = null;
        var _hoverTimer = null;
        var PREVIEW_W  = 300;
        var SVG_TRASH = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.519.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
        var SVG_CHECK = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>';

        // Selector: cards that have server-side notification data embedded
        var cards = this.el.querySelectorAll('a.mj-header-acc-card[data-notifications]');
        if (!cards.length) return;

        function getPreview() {
            if (!_preview) {
                _preview = document.createElement('div');
                _preview.className = 'mj-header-acc-notif-preview';
                document.body.appendChild(_preview);

                _preview.addEventListener('mouseenter', function () {
                    clearTimeout(_hideTimer);
                    // Prevent the account dropdown from closing when the mouse
                    // leaves the action-item to enter this fixed-position preview.
                    if (self.activeDropdown !== 'account') {
                        self._openDropdown('account', true);
                    }
                });

                _preview.addEventListener('mouseleave', function () {
                    scheduleHide();
                    // Close the account dropdown after a short delay so that
                    // moving back onto the action-item keeps it open.
                    setTimeout(function () {
                        if (_preview && !_preview.classList.contains('mj-header-acc-notif-preview--open')) {
                            self._closeAll();
                        }
                    }, 420);
                });
            }
            return _preview;
        }

        function scheduleHide() {
            clearTimeout(_hideTimer);
            _hideTimer = setTimeout(function () {
                if (_preview) _preview.classList.remove('mj-header-acc-notif-preview--open');
            }, 420);
        }

        function position(card) {
            var p    = getPreview();
            var rect = card.getBoundingClientRect();

            // Compare to the panel mid-point: left-column cards → preview RIGHT,
            // right-column cards → preview LEFT (panel is a 2-col grid).
            var panel     = card.closest('.mj-header-acc-panel');
            var panelRect = panel ? panel.getBoundingClientRect() : null;
            var mid       = panelRect
                ? (panelRect.left + panelRect.width / 2)
                : window.innerWidth / 2;

            if (rect.left + rect.width / 2 > mid) {
                // RIGHT column → preview LEFT
                p.classList.add('mj-header-acc-notif-preview--left');
                p.classList.remove('mj-header-acc-notif-preview--right');
                p.style.left = Math.max(8, rect.left - PREVIEW_W - 10) + 'px';
            } else {
                // LEFT column → preview RIGHT
                p.classList.add('mj-header-acc-notif-preview--right');
                p.classList.remove('mj-header-acc-notif-preview--left');
                p.style.left = (rect.right + 10) + 'px';
            }

            var maxTop = window.innerHeight - 380;
            p.style.top = Math.max(8, Math.min(rect.top, maxTop)) + 'px';
        }

        // Render using the flat format from fetchNotificationPreviews():
        // { recipient_id, title, excerpt, url, type, created_at }
        function updateCardBadge(card, count) {
            if (!card) return;

            var nextCount = Math.max(0, parseInt(count || 0, 10));
            if (nextCount > 0) {
                card.setAttribute('data-notif-badge', String(nextCount));
            } else {
                card.removeAttribute('data-notif-badge');
            }

            var badgeEl = card.querySelector('.mj-header-acc-card__badge');
            if (nextCount > 0) {
                if (!badgeEl) {
                    badgeEl = document.createElement('span');
                    badgeEl.className = 'mj-header-acc-card__badge';
                    var chevron = card.querySelector('.mj-header-acc-card__chevron');
                    if (chevron && chevron.parentNode) {
                        chevron.parentNode.insertBefore(badgeEl, chevron);
                    } else {
                        card.appendChild(badgeEl);
                    }
                }
                badgeEl.textContent = String(nextCount);
            } else if (badgeEl) {
                badgeEl.remove();
            }
        }

        function postNotifAction(action, recipientId) {
            var data = new FormData();
            data.append('action', action);
            data.append('nonce', self.config.notifNonce || '');
            data.append('recipient_id', recipientId);
            return fetch(self.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (r) { return r.json(); });
        }

        function renderContent(notifications) {
            if (!notifications.length) {
                return '<div class="mj-header-acc-notif-preview__head">Notifications</div>' +
                    '<div class="mj-header-dropdown__empty">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" /></svg>' +
                    '<p>Aucune notification.</p>' +
                    '</div>';
            }

            var html = '<div class="mj-header-acc-notif-preview__head">Notifications récentes</div>';
            html += '<div class="mj-header-notif-list">';

            notifications.forEach(function (item) {
                var emoji   = MjHeader.notifEmoji(item.type || 'info');
                var title   = esc(item.title   || '');
                var excerpt = esc(item.excerpt || '');
                var timeAgo = MjHeader.relativeTime(item.created_at || '');
                var url     = item.url || '';
                var recipientId = parseInt(item.recipient_id || 0, 10);

                html += '<div class="mj-header-notif-swipe-wrap">';
                html += '<div class="mj-header-notif-item mj-header-notif-item--unread">';
                if (url) {
                    html += '<a href="' + esc(url) + '" class="mj-header-notif-item__bg-link" aria-label="' + title + '"></a>';
                }
                html += '<div class="mj-header-notif-icon">' + emoji + '</div>';
                html += '<div class="mj-header-notif-body">';
                html += '<span class="mj-header-notif-title">' + title + '</span>';
                if (excerpt) html += '<p class="mj-header-notif-excerpt">' + excerpt + '</p>';
                html += '<time class="mj-header-notif-time">' + timeAgo + '</time>';
                html += '</div>';

                if (recipientId > 0) {
                    html += '<div class="mj-header-notif-actions">';
                    html += '<button type="button" class="mj-header-notif-action mj-header-notif-action--mark" data-acc-notif-action="mark-read" data-recipient-id="' + recipientId + '" title="Vu">' + SVG_CHECK + '<span>Vu</span></button>';
                    html += '<button type="button" class="mj-header-notif-action mj-header-notif-action--del" data-acc-notif-action="archive" data-recipient-id="' + recipientId + '" title="Supprimer">' + SVG_TRASH + '<span>Supprimer</span></button>';
                    html += '</div>';
                }
                html += '</div>'; // .mj-header-notif-item
                html += '</div>'; // .mj-header-notif-swipe-wrap
            });

            html += '</div>';
            html += '<div class="mj-header-acc-notif-preview__footer">';
            html += '<button type="button" class="mj-header-notif-delete-all mj-header-acc-notif-delete-all" data-acc-notif-action="archive-all">Tout supprimer</button>';
            html += '</div>';
            return html;
        }

        function renderPreviewForCard(card, notifications) {
            var p = getPreview();
            position(card);
            p.innerHTML = renderContent(notifications);
            p.classList.add('mj-header-acc-notif-preview--open');
            p._accCard = card;
            p._accNotifications = notifications;

            var hasActionable = notifications.some(function (n) {
                return parseInt(n && n.recipient_id ? n.recipient_id : 0, 10) > 0;
            });
            var allBtn = p.querySelector('[data-acc-notif-action="archive-all"]');
            if (allBtn) {
                allBtn.disabled = !hasActionable;
            }
        }

        getPreview().addEventListener('click', function (event) {
            var btn = event.target.closest('[data-acc-notif-action]');
            if (!btn) return;

            event.preventDefault();
            event.stopPropagation();

            var previewNode = getPreview();
            var card = previewNode._accCard;
            var notifications = Array.isArray(previewNode._accNotifications)
                ? previewNode._accNotifications
                : [];
            var action = btn.getAttribute('data-acc-notif-action') || '';

            if (!card || !notifications.length) {
                return;
            }

            if (action === 'archive-all') {
                var notificationTypes = [];
                try {
                    notificationTypes = JSON.parse(card.getAttribute('data-notif-types') || '[]');
                } catch (err) {
                    notificationTypes = [];
                }

                if (!Array.isArray(notificationTypes) || !notificationTypes.length || !self.config.memberId) {
                    return;
                }

                btn.disabled = true;

                var bulkData = new FormData();
                bulkData.append('action', 'mj_header_archive_link_notifications');
                bulkData.append('nonce', self.config.notifNonce || '');
                bulkData.append('member_id', self.config.memberId || 0);
                bulkData.append('types', JSON.stringify(notificationTypes));

                fetch(self.config.ajaxUrl, { method: 'POST', body: bulkData, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success) {
                            btn.disabled = false;
                            return;
                        }

                        notifications.length = 0;
                        updateCardBadge(card, 0);
                        renderPreviewForCard(card, notifications);
                        self._fetchNotifCount();
                    })
                    .catch(function () {
                        btn.disabled = false;
                    });
                return;
            }

            var recipientId = parseInt(btn.getAttribute('data-recipient-id') || '0', 10);
            if (recipientId <= 0) {
                return;
            }

            btn.disabled = true;
            var ajaxAction = action === 'mark-read'
                ? 'mj_member_notification_bell_mark_read'
                : 'mj_member_notification_bell_archive';

            postNotifAction(ajaxAction, recipientId).then(function (response) {
                if (!response || !response.success) {
                    btn.disabled = false;
                    return;
                }

                var idx = -1;
                for (var i = 0; i < notifications.length; i += 1) {
                    if (parseInt(notifications[i] && notifications[i].recipient_id ? notifications[i].recipient_id : 0, 10) === recipientId) {
                        idx = i;
                        break;
                    }
                }
                if (idx !== -1) {
                    notifications.splice(idx, 1);
                }

                updateCardBadge(card, notifications.length);
                renderPreviewForCard(card, notifications);
                self._fetchNotifCount();
            }).catch(function () {
                btn.disabled = false;
            });
        });

        cards.forEach(function (card) {
            // Parse the notifications embedded by PHP at page load — no AJAX needed.
            var notifications = [];
            try {
                notifications = JSON.parse(card.getAttribute('data-notifications') || '[]');
            } catch (e) { return; }
            if (!Array.isArray(notifications) || !notifications.length) return;

            card.addEventListener('mouseenter', function () {
                if (!window.matchMedia('(hover: hover)').matches) return;
                clearTimeout(_hideTimer);
                clearTimeout(_hoverTimer);

                _hoverTimer = setTimeout(function () {
                    renderPreviewForCard(card, notifications);
                }, 300);
            });

            card.addEventListener('mouseleave', function () {
                clearTimeout(_hoverTimer);
                scheduleHide();
            });
        });
    };

    // -------------------------------------------------------------------------
    // Dropdown management
    // -------------------------------------------------------------------------

    MjHeader.prototype._isMobile = function () {
        return window.innerWidth <= 1024;
    };

    MjHeader.prototype._lockScroll = function () {
        if (this._isMobile()) {
            this._scrollLockY = window.pageYOffset;
            document.body.style.overflow = 'hidden';
        }
    };

    MjHeader.prototype._unlockScroll = function () {
        document.body.style.overflow = '';
    };

    MjHeader.prototype._openDropdown = function (name, skipOverlay) {
        var dropdown = this.el.querySelector('[data-mj-header-dropdown="' + name + '"]');
        var trigger  = this.el.querySelector('[data-mj-header-trigger="' + name + '"]');
        if (!dropdown) return;

        this.activeDropdown = name;

        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        dropdown.classList.add('mj-header-dropdown--open');

        if (!skipOverlay) {
            var overlay = this.el.querySelector('.mj-header-overlay');
            if (overlay) overlay.classList.add('mj-header-overlay--active');
        }

        // Lazy-load content
        if (!this.config.isPreview && !this._loaded[name]) {
            if (name === 'notifications') {
                this._loadNotifications(dropdown);
            } else if (name === 'agenda') {
                this._loadAgenda(dropdown);
            } else if (name === 'nextcloud') {
                this._renderNextcloud(dropdown);
            }
        }
    };

    MjHeader.prototype._closeAll = function () {
        this.activeDropdown = null;

        this.el.querySelectorAll('[data-mj-header-dropdown]').forEach(function (d) {
            d.classList.remove('mj-header-dropdown--open');
        });
        this.el.querySelectorAll('[data-mj-header-trigger]').forEach(function (t) {
            t.setAttribute('aria-expanded', 'false');
        });

        var overlay = this.el.querySelector('.mj-header-overlay');
        if (overlay) overlay.classList.remove('mj-header-overlay--active');
    };

    // -------------------------------------------------------------------------
    // Sticky
    // -------------------------------------------------------------------------

    MjHeader.prototype._initSticky = function () {
        var self         = this;
        var scrollOffset = this.config.scrollOffset || 20;

        // Create a placeholder to fill the space taken by the fixed header
        var placeholder = document.createElement('div');
        placeholder.className = 'mj-header-sticky-placeholder';
        this.el.parentNode.insertBefore(placeholder, this.el);

        var updatePlaceholder = function () {
            placeholder.style.height = self.el.offsetHeight + 'px';
        };
        updatePlaceholder();
        window.addEventListener('resize', updatePlaceholder, { passive: true });

        var onScroll = function () {
            var scrollY = window.pageYOffset || document.documentElement.scrollTop;
            if (scrollY > scrollOffset && !self._isStuck) {
                self._isStuck = true;
                self.el.classList.add('mj-header--stuck');
                // Update placeholder after height transition completes (0.25s)
                setTimeout(updatePlaceholder, 270);
            } else if (scrollY <= scrollOffset && self._isStuck) {
                self._isStuck = false;
                self.el.classList.remove('mj-header--stuck');
                setTimeout(updatePlaceholder, 270);
            }
        };

        this._scrollHandler = onScroll;
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    };

    // -------------------------------------------------------------------------
    // AJAX : Notifications
    // -------------------------------------------------------------------------

    MjHeader.prototype._loadNotifications = function (dropdown) {
        var self    = this;
        var content = dropdown.querySelector('.mj-header-dropdown__content');
        if (!content) return;

        this._showLoader(content);

        var data = new FormData();
        data.append('action',    'mj_member_notification_bell_fetch');
        data.append('nonce',     this.config.notifNonce || '');
        data.append('member_id', this.config.memberId   || 0);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                self._loaded.notifications = true;
                if (res.success && res.data) {
                    self._renderNotifications(content, res.data);
                    self._updateBadge('notifications', res.data.unread_count || 0);
                } else {
                    self._showError(content);
                }
            })
            .catch(function () { self._showError(content); });
    };

    MjHeader.prototype._renderNotifications = function (container, data) {
        var self          = this;
        var notifications = data.notifications || [];
        var unread        = data.unread_count  || 0;

        if (!notifications.length) {
            container.innerHTML =
                '<div class="mj-header-dropdown__empty">' +
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" /></svg>' +
                '<p>' + esc(self.config.notifEmptyText || 'Aucune notification.') + '</p>' +
                '</div>';
            return;
        }

        var SVG_TRASH = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.519.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
        var SVG_CHECK = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>';

        var html = '<div class="mj-header-notif-list">';

        notifications.forEach(function (item) {
            var notif      = item.notification || {};
            var isUnread   = item.recipient_status === 'unread';
            var emoji      = MjHeader.notifEmoji(notif.type || 'info');
            var title      = esc(notif.title    || '');
            var excerpt    = esc(notif.excerpt  || '');
            var timeAgo    = MjHeader.relativeTime(notif.created_at);
            var url        = notif.url || '';
            var recipId    = item.recipient_id   || 0;
            var notifId    = item.notification_id || 0;

            // Swipe wrapper + hint layers
            html += '<div class="mj-header-notif-swipe-wrap">';
            html += '<div class="mj-header-notif-swipe-hint mj-header-notif-swipe-hint--right" aria-hidden="true">' + SVG_TRASH + '<span>Supprimer</span></div>';
            if (isUnread) {
                html += '<div class="mj-header-notif-swipe-hint mj-header-notif-swipe-hint--left" aria-hidden="true"><span>Marquer lu</span>' + SVG_CHECK + '</div>';
            }

            html += '<div class="mj-header-notif-item' + (isUnread ? ' mj-header-notif-item--unread' : '') + '"' +
                ' data-recipient-id="' + recipId + '"' +
                ' data-notification-id="' + notifId + '">';

            if (url) {
                html += '<a href="' + esc(url) + '" class="mj-header-notif-item__bg-link" aria-label="' + title + '"></a>';
            }

            html += '<div class="mj-header-notif-icon">' + emoji + '</div>';
            html += '<div class="mj-header-notif-body">';
            html += '<span class="mj-header-notif-title">' + title + '</span>';
            if (excerpt) html += '<p class="mj-header-notif-excerpt">' + excerpt + '</p>';
            html += '<time class="mj-header-notif-time">' + timeAgo + '</time>';
            html += '</div>';

            html += '<div class="mj-header-notif-actions">';
            if (isUnread) {
                html += '<button type="button" class="mj-header-notif-action"' +
                    ' data-notif-action="mark-read"' +
                    ' data-recipient-id="' + recipId + '"' +
                    ' title="Marquer comme lu">' + SVG_CHECK + '</button>';
            }
            html += '<button type="button" class="mj-header-notif-action mj-header-notif-action--del"' +
                ' data-notif-action="archive"' +
                ' data-recipient-id="' + recipId + '"' +
                ' data-notification-id="' + notifId + '"' +
                ' title="Supprimer">' + SVG_TRASH + '</button>';
            html += '</div>';

            html += '</div>'; // .mj-header-notif-item
            html += '</div>'; // .mj-header-notif-swipe-wrap
        });

        html += '</div>';
        container.innerHTML = html;

        // Init swipe on all rendered items
        self._initSwipeOnItems(container);

        // Sync mark-all button
        var markAllBtn = container.closest('.mj-header-dropdown').querySelector('[data-notif-action="mark-all-read"]');
        if (markAllBtn) markAllBtn.disabled = unread === 0;
    };

    MjHeader.prototype._updateBadge = function (name, count) {
        var trigger = this.el.querySelector('[data-mj-header-trigger="' + name + '"]');
        if (!trigger) return;
        var badge = trigger.querySelector('.mj-header__trigger-badge');
        if (!badge) return;

        if (count > 0) {
            badge.querySelector('span') && (badge.querySelector('span').textContent = count > 99 ? '99+' : count);
            badge.textContent !== undefined && !badge.querySelector('span') && (badge.textContent = count > 99 ? '99+' : count);
            badge.classList.remove('mj-header__trigger-badge--hidden');
        } else {
            badge.classList.add('mj-header__trigger-badge--hidden');
        }
    };

    MjHeader.prototype._notifMarkRead = function (recipientId, btn) {
        var self = this;
        var data = new FormData();
        data.append('action',       'mj_member_notification_bell_mark_read');
        data.append('nonce',        this.config.notifNonce || '');
        data.append('recipient_id', recipientId);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var item = btn.closest('.mj-header-notif-item');
                if (item) {
                    item.classList.remove('mj-header-notif-item--unread');
                    btn.remove();
                }
                self._fetchNotifCount();
            });
    };

    MjHeader.prototype._notifArchive = function (recipientId, notifId, btn) {
        var self = this;
        var data = new FormData();
        data.append('action',       'mj_member_notification_bell_archive');
        data.append('nonce',        this.config.notifNonce || '');
        data.append('recipient_id', recipientId);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var item   = btn.closest('.mj-header-notif-item');
                var target = (item && item.closest('.mj-header-notif-swipe-wrap')) || item;
                if (target) {
                    target.style.height     = target.offsetHeight + 'px';
                    target.style.overflow   = 'hidden';
                    target.style.transition = 'height 0.2s ease, opacity 0.2s ease';
                    requestAnimationFrame(function () {
                        target.style.height  = '0';
                        target.style.opacity = '0';
                    });
                    setTimeout(function () { target.remove(); }, 220);
                }
                self._fetchNotifCount();
            });
    };

    // -------------------------------------------------------------------------
    // Swipe gestures
    // -------------------------------------------------------------------------

    MjHeader.prototype._initSwipeOnItems = function (container) {
        var self = this;
        container.querySelectorAll('.mj-header-notif-item').forEach(function (item) {
            self._attachSwipe(item);
        });
    };

    MjHeader.prototype._attachSwipe = function (item) {
        var self      = this;
        var THRESHOLD = 80;
        var MAX       = 120;

        var startX = 0, startY = 0, curX = 0;
        var dragging = false, axis = null; // null | 'h' | 'v'

        var wrap     = item.closest('.mj-header-notif-swipe-wrap');
        var hintR    = wrap && wrap.querySelector('.mj-header-notif-swipe-hint--right');
        var hintL    = wrap && wrap.querySelector('.mj-header-notif-swipe-hint--left');
        var isUnread = item.classList.contains('mj-header-notif-item--unread');
        var recipId  = parseInt(item.getAttribute('data-recipient-id') || 0, 10);

        function hint(dx) {
            var pct = Math.min(Math.abs(dx) / THRESHOLD, 1);
            if (dx > 0) {
                if (hintR) hintR.style.opacity = pct;
                if (hintL) hintL.style.opacity = 0;
            } else {
                if (hintL) hintL.style.opacity = isUnread ? pct : 0;
                if (hintR) hintR.style.opacity = 0;
            }
        }

        function spring() {
            item.style.transition = 'transform 0.35s cubic-bezier(0.34,1.56,0.64,1)';
            item.style.transform  = 'translateX(0)';
            if (hintR) hintR.style.opacity = 0;
            if (hintL) hintL.style.opacity = 0;
        }

        item.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) return;
            startX   = e.touches[0].clientX;
            startY   = e.touches[0].clientY;
            curX     = 0;
            dragging = true;
            axis     = null;
            item.style.transition = 'none';
        }, { passive: true });

        item.addEventListener('touchmove', function (e) {
            if (!dragging || e.touches.length !== 1) return;
            var dx = e.touches[0].clientX - startX;
            var dy = e.touches[0].clientY - startY;

            if (axis === null) {
                if (Math.abs(dx) < 5 && Math.abs(dy) < 5) return;
                axis = Math.abs(dx) > Math.abs(dy) ? 'h' : 'v';
            }
            if (axis === 'v') return;

            e.preventDefault();

            if (dx > 0) {
                // Right → archive (always)
                curX = dx > MAX ? MAX + (dx - MAX) * 0.08 : dx;
            } else {
                // Left → mark read (unread only, resist otherwise)
                curX = !isUnread ? dx * 0.08
                     : (dx < -MAX ? -MAX + (dx + MAX) * 0.08 : dx);
            }

            item.style.transform = 'translateX(' + curX + 'px)';
            hint(curX);
        }, { passive: false });

        function onEnd() {
            if (!dragging || axis !== 'h') { dragging = false; return; }
            dragging = false;

            if (curX >= THRESHOLD) {
                // Confirmed right → archive
                item.style.transition = 'transform 0.18s ease-in';
                item.style.transform  = 'translateX(115%)';
                setTimeout(function () { self._swipeArchive(item, wrap, recipId); }, 180);
            } else if (curX <= -THRESHOLD && isUnread) {
                // Confirmed left → mark read
                item.style.transition = 'transform 0.18s ease-in';
                item.style.transform  = 'translateX(-115%)';
                setTimeout(function () { self._swipeMarkRead(item, wrap, recipId); }, 180);
            } else {
                spring();
            }
            curX = 0;
        }

        item.addEventListener('touchend',    onEnd,               { passive: true });
        item.addEventListener('touchcancel', function () { dragging = false; spring(); curX = 0; }, { passive: true });

        // Mouse drag (desktop)
        item.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            startX   = e.clientX;
            startY   = e.clientY;
            curX     = 0;
            dragging = true;
            axis     = null;
            item.style.transition = 'none';
            item.style.userSelect = 'none';

            function onMouseMove(e) {
                if (!dragging) return;
                var dx = e.clientX - startX;
                var dy = e.clientY - startY;

                if (axis === null) {
                    if (Math.abs(dx) < 5 && Math.abs(dy) < 5) return;
                    axis = Math.abs(dx) > Math.abs(dy) ? 'h' : 'v';
                }
                if (axis === 'v') return;

                e.preventDefault();

                if (dx > 0) {
                    curX = dx > MAX ? MAX + (dx - MAX) * 0.08 : dx;
                } else {
                    curX = !isUnread ? dx * 0.08
                         : (dx < -MAX ? -MAX + (dx + MAX) * 0.08 : dx);
                }

                item.style.transform = 'translateX(' + curX + 'px)';
                hint(curX);
            }

            function onMouseUp() {
                item.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup',  onMouseUp);
                onEnd();
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup',   onMouseUp);
        });
    };

    MjHeader.prototype._swipeArchive = function (item, wrap, recipId) {
        var self   = this;
        var target = wrap || item;

        // Collapse immediately (optimistic)
        target.style.height     = target.offsetHeight + 'px';
        target.style.overflow   = 'hidden';
        target.style.transition = 'height 0.22s ease, opacity 0.22s ease';
        requestAnimationFrame(function () {
            target.style.height  = '0';
            target.style.opacity = '0';
        });
        setTimeout(function () { target.remove(); }, 230);

        var data = new FormData();
        data.append('action',       'mj_member_notification_bell_archive');
        data.append('nonce',        self.config.notifNonce || '');
        data.append('recipient_id', recipId);
        fetch(self.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res.success) self._fetchNotifCount(); });
    };

    MjHeader.prototype._swipeMarkRead = function (item, wrap, recipId) {
        var self = this;
        var data = new FormData();
        data.append('action',       'mj_member_notification_bell_mark_read');
        data.append('nonce',        self.config.notifNonce || '');
        data.append('recipient_id', recipId);

        fetch(self.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    // Restore on error
                    item.style.transition = 'transform 0.35s cubic-bezier(0.34,1.56,0.64,1)';
                    item.style.transform  = 'translateX(0)';
                    return;
                }
                // Slide back, update visual state
                item.style.transition = 'transform 0.35s cubic-bezier(0.34,1.56,0.64,1)';
                item.style.transform  = 'translateX(0)';
                item.classList.remove('mj-header-notif-item--unread');
                var markBtn = item.querySelector('[data-notif-action="mark-read"]');
                if (markBtn) markBtn.remove();
                // Hide left hint permanently
                var hintL = wrap && wrap.querySelector('.mj-header-notif-swipe-hint--left');
                if (hintL) hintL.style.display = 'none';
                self._fetchNotifCount();
            });
    };

    MjHeader.prototype._notifMarkAllRead = function (btn) {
        var self = this;
        var data = new FormData();
        data.append('action',    'mj_member_notification_bell_mark_all_read');
        data.append('nonce',     this.config.notifNonce || '');
        data.append('member_id', this.config.memberId   || 0);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                self.el.querySelectorAll('.mj-header-notif-item--unread').forEach(function (item) {
                    item.classList.remove('mj-header-notif-item--unread');
                });
                self.el.querySelectorAll('[data-notif-action="mark-read"]').forEach(function (b) { b.remove(); });
                self._updateBadge('notifications', 0);
                if (btn) btn.disabled = true;
            });
    };

    MjHeader.prototype._notifArchiveAll = function (btn) {
        var self = this;
        if (btn) btn.disabled = true;

        var data = new FormData();
        data.append('action',    'mj_member_notification_bell_archive_all');
        data.append('nonce',     this.config.notifNonce || '');
        data.append('member_id', this.config.memberId   || 0);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) { if (btn) btn.disabled = false; return; }
                self.el.querySelectorAll('.mj-header-notif-item').forEach(function (item) {
                    item.remove();
                });
                self._updateBadge('notifications', 0);
                var list = self.el.querySelector('[data-mj-notif-list]');
                if (list && !list.querySelector('.mj-header-notif-item')) {
                    list.innerHTML = '<div class="mj-header-dropdown__empty"><p>Aucune notification</p></div>';
                }
            });
    };

    MjHeader.prototype._fetchNotifCount = function () {
        var self = this;
        if (!this.config.memberId) return;

        var data = new FormData();
        data.append('action',    'mj_member_notification_bell_count');
        data.append('nonce',     this.config.notifNonce || '');
        data.append('member_id', this.config.memberId   || 0);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && res.data && res.data.unread_count !== undefined) {
                    self._updateBadge('notifications', parseInt(res.data.unread_count, 10) || 0);
                }
            });
    };

    MjHeader.prototype._startNotifPolling = function () {
        var self     = this;
        var interval = (this.config.notifRefreshInterval || 60000);

        this._refreshTimer = setInterval(function () {
            // Only refresh badge when dropdown is closed (avoid jarring UI)
            if (self.activeDropdown !== 'notifications') {
                self._fetchNotifCount();
            }
        }, interval);
    };

    // -------------------------------------------------------------------------
    // AJAX : Agenda
    // -------------------------------------------------------------------------

    MjHeader.prototype._loadAgenda = function (dropdown) {
        // In 'calendrier' mode the content is server-rendered; no AJAX needed.
        if (!dropdown.querySelector('[data-mj-agenda-list]')) {
            this._loaded.agenda = true;
            return;
        }

        var self    = this;
        var content = dropdown.querySelector('.mj-header-dropdown__content');
        if (!content) return;

        this._showLoader(content);

        var data = new FormData();
        data.append('action', 'mj_header_upcoming_events');
        data.append('nonce',  this.config.headerNonce || '');
        data.append('limit',  this.config.agendaLimit || 5);

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                self._loaded.agenda = true;
                if (res.success && Array.isArray(res.data)) {
                    self._renderAgenda(content, res.data);
                } else {
                    self._showError(content);
                }
            })
            .catch(function () { self._showError(content); });
    };

    MjHeader.prototype._renderAgenda = function (container, events) {
        if (!events.length) {
            container.innerHTML =
                '<div class="mj-header-dropdown__empty">' +
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" /></svg>' +
                '<p>Aucun événement à venir.</p>' +
                '</div>';
            return;
        }

        var html = '<div class="mj-header-agenda-list">';

        events.forEach(function (event) {
            var date   = event.date_debut ? new Date(event.date_debut) : null;
            var day    = date ? date.getDate() : '';
            var month  = date ? date.toLocaleString('fr-BE', { month: 'short' }) : '';
            var title  = esc(event.title || '');
            var url    = event.url || '#';
            var type   = esc(event.type || '');
            var emoji  = event.emoji ? esc(event.emoji) : '';
            var color  = event.accent_color ? esc(event.accent_color) : '';

            html += '<a href="' + esc(url) + '" class="mj-header-agenda-item">';

            html += '<div class="mj-header-agenda-date"' + (color ? ' style="border-color:' + color + '"' : '') + '>';
            html += '<span class="mj-header-agenda-date__day">'   + day   + '</span>';
            html += '<span class="mj-header-agenda-date__month">' + month + '</span>';
            html += '</div>';

            html += '<div class="mj-header-agenda-info">';
            html += '<span class="mj-header-agenda-title">' + (emoji ? emoji + ' ' : '') + title + '</span>';
            if (type) {
                html += '<div class="mj-header-agenda-meta">';
                html += '<span class="mj-header-agenda-type"' + (color ? ' style="background:' + color + '22;color:' + color + '"' : '') + '>' + type + '</span>';
                html += '</div>';
            }
            html += '</div>';

            html += '<span class="mj-header-agenda-chevron" aria-hidden="true">' +
                '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>' +
                '</span>';

            html += '</a>';
        });

        html += '</div>';
        container.innerHTML = html;
    };

    // -------------------------------------------------------------------------
    // Nextcloud
    // -------------------------------------------------------------------------

    MjHeader.prototype._renderNextcloud = function (dropdown) {
        var self      = this;
        var container = dropdown.querySelector('[data-mj-nc-apps]');

        if (!container) {
            this._loaded.nextcloud = true;
            return;
        }

        if (this.config.isPreview) {
            this._loaded.nextcloud = true;
            return;
        }

        if (!this.config.isLoggedIn) {
            this._loaded.nextcloud = true;
            container.innerHTML = '<div class="mj-header-dropdown__empty"><p>Connectez-vous pour accéder au cloud.</p></div>';
            return;
        }

        container.innerHTML = '<div class="mj-header-nc-loading">Chargement\u2026</div>';

        var data = new FormData();
        data.append('action', 'mj_header_nextcloud_navigation');
        data.append('nonce', this.config.headerNonce || '');

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                self._loaded.nextcloud = true;

                if (!res || !res.success) {
                    container.innerHTML = '<div class="mj-header-dropdown__empty"><p>Impossible de charger les applications.</p></div>';
                    return;
                }

                var payload = res.data || {};
                if (payload.linked === false) {
                    container.innerHTML = '<div class="mj-header-dropdown__empty"><p>' + esc(payload.message || 'Votre compte Nextcloud n\'est pas lié.') + '</p></div>';
                    return;
                }

                if (!Array.isArray(payload.apps)) {
                    container.innerHTML = '<div class="mj-header-dropdown__empty"><p>Impossible de charger les applications.</p></div>';
                    return;
                }

                self._renderNcApps(container, payload.apps);
            })
            .catch(function () {
                self._loaded.nextcloud = true;
                container.innerHTML = '<div class="mj-header-dropdown__empty"><p>Erreur de chargement.</p></div>';
            });
    };

    MjHeader.prototype._renderNcApps = function (container, apps) {
        if (!apps.length) {
            container.innerHTML = '<div class="mj-header-dropdown__empty"><p>Aucune application disponible.</p></div>';
            return;
        }

        var html = '';
        apps.forEach(function (app) {
            var label = esc(app.name  || '');
            var url   = esc(app.href  || '#');
            var icon  = app.icon || '';

            html += '<a href="' + url + '" class="mj-header-nc-app">';
            html += '<div class="mj-header-nc-app__icon">';
            if (icon) {
                html += '<img src="' + esc(icon) + '" alt="" />';
            } else {
                html += '<svg viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M4.5 9.75a6 6 0 0 1 11.573-2.226 3.75 3.75 0 0 1 4.133 4.303A4.5 4.5 0 0 1 18 20.25H6.75a5.25 5.25 0 0 1-4.233-8.385A6.032 6.032 0 0 1 4.5 9.75Z" clip-rule="evenodd"/></svg>';
            }
            html += '</div>';
            html += '<span class="mj-header-nc-app__label">' + label + '</span>';
            html += '</a>';
        });

        container.innerHTML = html;
    };

    // -------------------------------------------------------------------------
    // Login form
    // -------------------------------------------------------------------------

    MjHeader.prototype._submitLoginForm = function (form) {
        var self      = this;
        var submitBtn = form.querySelector('.mj-header-login-form__submit');
        var errorEl   = form.querySelector('.mj-header-login-form__error');
        var username  = form.querySelector('[name="log"]');
        var password  = form.querySelector('[name="pwd"]');
        var remember  = form.querySelector('[name="rememberme"]');

        if (!username || !password) return;

        submitBtn.disabled    = true;
        submitBtn.textContent = 'Connexion…';
        if (errorEl) errorEl.classList.remove('mj-header-login-form__error--visible');

        var data = new FormData();
        data.append('action',   'mj_member_ajax_login');
        data.append('nonce',    this.config.loginNonce || '');
        data.append('username', username.value);
        data.append('password', password.value);
        data.append('remember', (remember && remember.checked) ? '1' : '0');

        fetch(this.config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    window.location.href = self.config.loginRedirect || window.location.href;
                } else {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = self.config.loginBtnText || 'Se connecter';
                    if (errorEl) {
                        errorEl.textContent = (res.data && res.data.message) ? res.data.message : 'Identifiants incorrects.';
                        errorEl.classList.add('mj-header-login-form__error--visible');
                    }
                }
            })
            .catch(function () {
                submitBtn.disabled    = false;
                submitBtn.textContent = self.config.loginBtnText || 'Se connecter';
                if (errorEl) {
                    errorEl.textContent = 'Une erreur est survenue.';
                    errorEl.classList.add('mj-header-login-form__error--visible');
                }
            });
    };

    // -------------------------------------------------------------------------
    // UI helpers
    // -------------------------------------------------------------------------

    MjHeader.prototype._showLoader = function (container) {
        container.innerHTML =
            '<div class="mj-header-dropdown__loader">' +
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
            '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-linecap="round"/>' +
            '</svg>' +
            '<span>Chargement…</span>' +
            '</div>';
    };

    MjHeader.prototype._showError = function (container) {
        container.innerHTML =
            '<div class="mj-header-dropdown__error">' +
            '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>' +
            '<span>Impossible de charger les données.</span>' +
            '</div>';
    };

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    MjHeader.notifEmoji = function (type) {
        var map = {
            event_registration_created:  '📅',
            event_registration_cancelled:'❌',
            event_reminder:              '⏰',
            event_new_published:         '🗓️',
            payment_completed:           '💰',
            payment_reminder:            '💳',
            member_created:              '👤',
            profile_updated:             '✏️',
            member_profile_updated:      '✏️',
            trophy_earned:               '🏆',
            badge_earned:                '🎖️',
            criterion_earned:            '✓',
            level_up:                    '🚀',
            todo_assigned:               '📋',
            todo_completed:              '✅',
            message_received:            '💬',
            leave_request_approved:      '🏖️',
            leave_request_rejected:      '🚫',
            info:                        'ℹ️',
        };
        return map[type] || map['info'];
    };

    MjHeader.relativeTime = function (dateStr) {
        if (!dateStr) return '';
        var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)     return 'à l\'instant';
        if (diff < 3600)   return 'il y a ' + Math.floor(diff / 60)    + ' min';
        if (diff < 86400)  return 'il y a ' + Math.floor(diff / 3600)  + ' h';
        if (diff < 604800) return 'il y a ' + Math.floor(diff / 86400) + ' j';
        return new Date(dateStr).toLocaleDateString('fr-BE', { day: 'numeric', month: 'short' });
    };

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;');
    }

    // =========================================================================
    // Bootstrap
    // =========================================================================

    function initAll() {
        document.querySelectorAll('[data-mj-header-id]').forEach(function (el) {
            if (el._mjHeader) return; // Already initialized
            var config = {};
            try { config = JSON.parse(el.getAttribute('data-config') || '{}'); } catch (e) {}
            el._mjHeader = new MjHeader(el, config);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Elementor live editor support
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/mj-member-header/default',
            function ($el) {
                var el = $el[0];
                if (!el || el._mjHeader) return;
                var config = {};
                try { config = JSON.parse(el.getAttribute('data-config') || '{}'); } catch (e) {}
                el._mjHeader = new MjHeader(el, config);
            }
        );
    }

    window.MjHeader = MjHeader;

})();
