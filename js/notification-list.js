/**
 * Widget Liste Notifications
 *
 * Affiche les notifications en liste avec filtrage des types
 * configure depuis les controles Elementor.
 *
 * @package MjMember
 */

(function () {
    'use strict';

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function relativeTime(dateString) {
        if (!dateString) {
            return '';
        }

        var timestamp = Date.parse(dateString);
        if (!Number.isFinite(timestamp)) {
            return '';
        }

        var now = Date.now();
        var diff = Math.floor((now - timestamp) / 1000);

        if (diff < 60) {
            return "a l'instant";
        }
        if (diff < 3600) {
            return 'il y a ' + Math.floor(diff / 60) + ' min';
        }
        if (diff < 86400) {
            return 'il y a ' + Math.floor(diff / 3600) + ' h';
        }
        if (diff < 604800) {
            return 'il y a ' + Math.floor(diff / 86400) + ' j';
        }

        return new Date(timestamp).toLocaleDateString('fr-FR');
    }

    function emojiByType(type) {
        var emojis = {
            event_registration_created: '📅',
            event_registration_cancelled: '❌',
            event_reminder: '⏰',
            event_new_published: '🗓️',
            payment_completed: '💰',
            payment_reminder: '💳',
            member_created: '👤',
            member_profile_updated: '✏️',
            profile_updated: '✏️',
            photo_uploaded: '📷',
            photo_approved: '✅',
            idea_published: '💡',
            idea_voted: '👍',
            trophy_earned: '🏆',
            badge_earned: '🎖️',
            criterion_earned: '✅',
            level_up: '🚀',
            action_awarded: '✨',
            avatar_applied: '🎭',
            attendance_recorded: '⏱️',
            message_received: '💬',
            todo_assigned: '📋',
            todo_note_added: '📝',
            todo_media_added: '📎',
            todo_completed: '✅',
            testimonial_approved: '✅',
            testimonial_rejected: '❌',
            testimonial_reaction: '👍',
            testimonial_comment: '💬',
            testimonial_comment_reply: '↩️',
            testimonial_new_pending: '📝',
            leave_request_created: '⛵',
            leave_request_approved: '🏖️',
            leave_request_rejected: '🚫',
            expense_created: '🧾',
            expense_reimbursed: '💶',
            expense_rejected: '⛔',
            mileage_created: '🚗',
            mileage_approved: '✅',
            mileage_reimbursed: '💸',
            employee_document_uploaded: '📄',
            post_published: '📢',
            info: 'ℹ️'
        };

        return emojis[type] || emojis.info;
    }

    function NotificationListWidget(container) {
        this.container = container;
        this.config = JSON.parse(container.getAttribute('data-config') || '{}');

        this.listNode = container.querySelector('[data-mj-notif-list]');
        this.markAllBtn = container.querySelector('[data-notif-action="mark-all-read"]');
        this.archiveAllBtn = container.querySelector('[data-notif-action="archive-all"]');

        this.allNotifications = Array.isArray(this.config.initialNotifications)
            ? this.config.initialNotifications.slice()
            : [];
        this.hideWhenEmpty = Boolean(this.config.hideWhenEmpty);
        this.allowedTypes = new Set(
            Array.isArray(this.config.allowedTypes)
                ? this.config.allowedTypes
                    .map(function (type) { return String(type || '').trim(); })
                    .filter(function (type) { return type.length > 0; })
                : []
        );
        this.refreshTimer = null;

        this.bindEvents();
        this.renderList();

        if (!this.config.preview) {
            this.fetchNotifications();
            this.startAutoRefresh();
        }
    }

    NotificationListWidget.prototype.bindEvents = function () {
        var self = this;

        if (this.listNode) {
            this.listNode.addEventListener('click', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                var markBtn = target.closest('[data-notif-action="mark-read"]');
                if (markBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.markRead(parseInt(markBtn.getAttribute('data-recipient-id') || '0', 10));
                    return;
                }

                var archiveBtn = target.closest('[data-notif-action="archive"]');
                if (archiveBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.archiveOne(parseInt(archiveBtn.getAttribute('data-recipient-id') || '0', 10));
                }
            });
        }

        if (this.markAllBtn) {
            this.markAllBtn.addEventListener('click', function () {
                self.markAllRead();
            });
        }

        if (this.archiveAllBtn) {
            this.archiveAllBtn.addEventListener('click', function () {
                self.archiveAll();
            });
        }
    };

    NotificationListWidget.prototype.startAutoRefresh = function () {
        if (!this.config.autoRefresh) {
            return;
        }

        var self = this;
        var interval = Number(this.config.refreshInterval || 60000);
        if (!Number.isFinite(interval) || interval < 30000) {
            interval = 60000;
        }

        this.refreshTimer = setInterval(function () {
            self.fetchNotifications();
        }, interval);
    };

    NotificationListWidget.prototype.fetchNotifications = function () {
        var self = this;
        if (!this.config.memberId) {
            return Promise.resolve();
        }

        return this.ajax('mj_member_notification_bell_fetch', {
            member_id: this.config.memberId
        }).then(function (response) {
            if (!response.success || !response.data) {
                return;
            }

            self.allNotifications = Array.isArray(response.data.notifications)
                ? response.data.notifications
                : [];
            self.renderList();
        }).catch(function () {
            // Silent fail to keep widget usable in case of transient AJAX errors.
        });
    };

    NotificationListWidget.prototype.isTypeAllowed = function (type) {
        if (!(this.allowedTypes instanceof Set) || this.allowedTypes.size === 0) {
            return true;
        }

        return this.allowedTypes.has(type);
    };

    NotificationListWidget.prototype.getFilteredNotifications = function () {
        var self = this;

        return this.allNotifications.filter(function (item) {
            var notif = item && item.notification ? item.notification : {};
            var type = notif.type || 'info';
            return self.isTypeAllowed(type);
        });
    };

    NotificationListWidget.prototype.setWidgetVisible = function (isVisible) {
        if (!(this.container instanceof HTMLElement)) {
            return;
        }

        this.container.style.display = isVisible ? '' : 'none';
    };

    NotificationListWidget.prototype.renderList = function () {
        if (!this.listNode) {
            return;
        }

        var notifications = this.getFilteredNotifications();
        if (!notifications.length) {
            if (this.hideWhenEmpty) {
                this.setWidgetVisible(false);
                return;
            }

            this.setWidgetVisible(true);
            this.listNode.innerHTML = '' +
                '<div class="mj-header-dropdown__empty">' +
                    '<p>' + esc(this.config.emptyMessage || 'Aucune notification pour le moment.') + '</p>' +
                '</div>';
            this.syncActionButtons();
            return;
        }

        this.setWidgetVisible(true);

        var svgTrash = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.519.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>';
        var svgCheck = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>';

        var html = '<div class="mj-header-notif-list">';

        notifications.forEach(function (item) {
            var notif = item && item.notification ? item.notification : {};
            var isUnread = item.recipient_status === 'unread';
            var title = esc(notif.title || '');
            var excerpt = esc(notif.excerpt || '');
            var type = notif.type || 'info';
            var url = notif.url || '';
            var recipId = parseInt(item.recipient_id || 0, 10);

            html += '<div class="mj-header-notif-item' + (isUnread ? ' mj-header-notif-item--unread' : '') + '" data-recipient-id="' + recipId + '">';
            if (url) {
                html += '<a href="' + esc(url) + '" class="mj-header-notif-item__bg-link" aria-label="' + title + '"></a>';
            }

            html += '<div class="mj-header-notif-icon" title="' + esc(type) + '">' + emojiByType(type) + '</div>';
            html += '<div class="mj-header-notif-body">';
            html += '<span class="mj-header-notif-title">' + title + '</span>';
            if (excerpt) {
                html += '<p class="mj-header-notif-excerpt">' + excerpt + '</p>';
            }
            html += '</div>';
            html += '<div class="mj-header-notif-meta">';
            html += '<time class="mj-header-notif-time">' + esc(relativeTime(notif.created_at)) + '</time>';
            html += '</div>';
            html += '<div class="mj-header-notif-actions">';
            if (isUnread) {
                html += '<button type="button" class="mj-header-notif-action" data-notif-action="mark-read" data-recipient-id="' + recipId + '" title="Lu">' + svgCheck + '</button>';
            }
            html += '<button type="button" class="mj-header-notif-action mj-header-notif-action--del" data-notif-action="archive" data-recipient-id="' + recipId + '" title="Supprimer">' + svgTrash + '</button>';
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        this.listNode.innerHTML = html;
        this.syncActionButtons();
    };

    NotificationListWidget.prototype.syncActionButtons = function () {
        var unreadCount = this.allNotifications.filter(function (item) {
            return item && item.recipient_status === 'unread';
        }).length;
        var visibleCount = this.getFilteredNotifications().length;

        if (this.markAllBtn) {
            this.markAllBtn.disabled = unreadCount === 0;
        }
        if (this.archiveAllBtn) {
            this.archiveAllBtn.disabled = visibleCount === 0;
        }
    };

    NotificationListWidget.prototype.markRead = function (recipientId) {
        var self = this;
        if (!recipientId || this.config.preview) {
            this.allNotifications = this.allNotifications.map(function (item) {
                if (parseInt(item.recipient_id || 0, 10) === recipientId) {
                    item.recipient_status = 'read';
                }
                return item;
            });
            this.renderList();
            return Promise.resolve();
        }

        return this.ajax('mj_member_notification_bell_mark_read', {
            recipient_id: recipientId
        }).then(function (response) {
            if (!response.success) {
                return;
            }

            self.allNotifications = self.allNotifications.map(function (item) {
                if (parseInt(item.recipient_id || 0, 10) === recipientId) {
                    item.recipient_status = 'read';
                }
                return item;
            });
            self.renderList();
        });
    };

    NotificationListWidget.prototype.archiveOne = function (recipientId) {
        var self = this;
        if (!recipientId) {
            return Promise.resolve();
        }

        if (this.config.preview) {
            this.allNotifications = this.allNotifications.filter(function (item) {
                return parseInt(item.recipient_id || 0, 10) !== recipientId;
            });
            this.renderList();
            return Promise.resolve();
        }

        return this.ajax('mj_member_notification_bell_archive', {
            recipient_id: recipientId
        }).then(function (response) {
            if (!response.success) {
                return;
            }

            self.allNotifications = self.allNotifications.filter(function (item) {
                return parseInt(item.recipient_id || 0, 10) !== recipientId;
            });
            self.renderList();
        });
    };

    NotificationListWidget.prototype.markAllRead = function () {
        var self = this;
        if (this.config.preview) {
            this.allNotifications = this.allNotifications.map(function (item) {
                item.recipient_status = 'read';
                return item;
            });
            this.renderList();
            return Promise.resolve();
        }

        if (!this.config.memberId) {
            return Promise.resolve();
        }

        return this.ajax('mj_member_notification_bell_mark_all_read', {
            member_id: this.config.memberId
        }).then(function (response) {
            if (!response.success) {
                return;
            }

            self.allNotifications = self.allNotifications.map(function (item) {
                item.recipient_status = 'read';
                return item;
            });
            self.renderList();
        });
    };

    NotificationListWidget.prototype.archiveAll = function () {
        var self = this;
        var visibleNotifications = this.getFilteredNotifications();
        var recipientIds = visibleNotifications
            .map(function (item) {
                return parseInt(item && item.recipient_id ? item.recipient_id : 0, 10);
            })
            .filter(function (id) {
                return Number.isFinite(id) && id > 0;
            });

        if (!recipientIds.length) {
            return Promise.resolve();
        }

        if (this.config.preview) {
            var previewIdSet = new Set(recipientIds);
            this.allNotifications = this.allNotifications.filter(function (item) {
                var id = parseInt(item && item.recipient_id ? item.recipient_id : 0, 10);
                return !previewIdSet.has(id);
            });
            this.renderList();
            return Promise.resolve();
        }

        if (!this.config.memberId) {
            return Promise.resolve();
        }

        var requests = recipientIds.map(function (recipientId) {
            return self.ajax('mj_member_notification_bell_archive', {
                recipient_id: recipientId
            }).then(function (response) {
                return {
                    recipientId: recipientId,
                    success: !!(response && response.success)
                };
            }).catch(function () {
                return {
                    recipientId: recipientId,
                    success: false
                };
            });
        });

        return Promise.all(requests).then(function (results) {
            var archivedIdSet = new Set(
                results
                    .filter(function (entry) { return entry.success; })
                    .map(function (entry) { return entry.recipientId; })
            );

            if (!archivedIdSet.size) {
                return;
            }

            self.allNotifications = self.allNotifications.filter(function (item) {
                var id = parseInt(item && item.recipient_id ? item.recipient_id : 0, 10);
                return !archivedIdSet.has(id);
            });
            self.renderList();
        });
    };

    NotificationListWidget.prototype.ajax = function (action, payload) {
        var ajaxCfg = window.mjNotificationList || {};
        var ajaxUrl = ajaxCfg.ajaxUrl || '';
        var nonce = ajaxCfg.nonce || '';

        if (!ajaxUrl) {
            return Promise.resolve({ success: false });
        }

        var data = new FormData();
        data.append('action', action);
        data.append('nonce', nonce);

        Object.keys(payload || {}).forEach(function (key) {
            data.append(key, payload[key]);
        });

        return fetch(ajaxUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    };

    function init() {
        document.querySelectorAll('.mj-notification-list-widget[data-config]').forEach(function (container) {
            if (container.__mjNotifListInited) {
                return;
            }
            container.__mjNotifListInited = true;
            new NotificationListWidget(container);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
