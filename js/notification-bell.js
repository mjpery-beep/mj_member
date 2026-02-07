/**
 * Notification Bell Widget
 * 
 * Gère l'affichage de la cloche de notifications avec badge,
 * panneau déroulant et interactions AJAX.
 * 
 * @package MjMember
 */

(function() {
    'use strict';

    /**
     * Classe principale du widget cloche
     */
    class NotificationBell {
        constructor(container) {
            this.container = container;
            this.config = JSON.parse(container.dataset.config || '{}');
            
            // Éléments DOM
            this.trigger = container.querySelector('.mj-notification-bell__trigger');
            this.panel = container.querySelector('.mj-notification-bell__panel');
            this.overlay = container.querySelector('.mj-notification-bell__overlay');
            this.badge = container.querySelector('.mj-notification-bell__badge');
            this.countEl = container.querySelector('.mj-notification-bell__count');
            this.list = container.querySelector('.mj-notification-bell__list');
            this.loader = container.querySelector('.mj-notification-bell__loader');
            this.markAllBtn = container.querySelector('.mj-notification-bell__mark-all');
            this.closeBtn = container.querySelector('.mj-notification-bell__close');

            // État
            this.isOpen = false;
            this.unreadCount = this.config.unreadCount || 0;
            this.isLoading = false;
            this.refreshTimer = null;

            this.init();
        }

        init() {
            if (!this.trigger || !this.panel) return;

            // Événements
            this.trigger.addEventListener('click', () => this.toggle());
            this.overlay?.addEventListener('click', () => this.close());
            this.closeBtn?.addEventListener('click', () => this.close());
            this.markAllBtn?.addEventListener('click', () => this.markAllAsRead());

            // Délégation pour les boutons de lecture individuelle
            this.list?.addEventListener('click', (e) => this.handleItemClick(e));

            // Fermer avec Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Rafraîchissement automatique
            if (this.config.autoRefresh && !this.config.preview) {
                this.startAutoRefresh();
            }

            // Clic à l'extérieur
            document.addEventListener('click', (e) => {
                if (this.isOpen && !this.container.contains(e.target)) {
                    this.close();
                }
            });
        }

        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        open() {
            if (this.config.preview) {
                // Mode preview Elementor - juste toggle visuel
                this.isOpen = true;
                this.container.classList.add('mj-notification-bell--open');
                this.trigger.setAttribute('aria-expanded', 'true');
                return;
            }

            this.isOpen = true;
            this.container.classList.add('mj-notification-bell--open');
            this.trigger.setAttribute('aria-expanded', 'true');

            // Charger les notifications fraîches
            this.fetchNotifications();
        }

        close() {
            this.isOpen = false;
            this.container.classList.remove('mj-notification-bell--open');
            this.trigger.setAttribute('aria-expanded', 'false');
        }

        async fetchNotifications() {
            if (this.isLoading || this.config.preview) return;

            this.isLoading = true;
            this.showLoader(true);

            try {
                const response = await this.ajaxRequest('mj_member_notification_bell_fetch', {
                    member_id: this.config.memberId
                });

                if (response.success && response.data) {
                    this.updateNotifications(response.data.notifications || []);
                    this.updateCount(response.data.unread_count || 0);
                }
            } catch (error) {
                console.error('NotificationBell: Erreur fetch', error);
            } finally {
                this.isLoading = false;
                this.showLoader(false);
            }
        }

        updateNotifications(notifications) {
            if (!this.list) return;

            if (notifications.length === 0) {
                this.list.innerHTML = this.getEmptyHtml();
                return;
            }

            this.list.innerHTML = notifications.map(item => this.getNotificationItemHtml(item)).join('');
        }

        getNotificationItemHtml(item) {
            const notif = item.notification || {};
            const recipientId = item.recipient_id || 0;
            const notificationId = item.notification_id || 0;
            const isUnread = item.recipient_status === 'unread';
            const title = this.escapeHtml(notif.title || '');
            const excerpt = this.escapeHtml(notif.excerpt || '');
            const type = notif.type || 'info';
            const url = notif.url || '';
            const createdAt = notif.created_at || '';
            const timeAgo = this.getTimeAgo(createdAt);
            const iconClass = 'mj-notification-bell__item-icon--' + this.escapeHtml(type);

            const isClickable = !!url;
            const clickableClass = isClickable ? ' mj-notification-bell__item--clickable' : '';

            const linkOverlay = isClickable 
                ? `<a href="${this.escapeHtml(url)}" class="mj-notification-bell__item-link" aria-label="${title}"></a>`
                : '';

            const titleHtml = `<span class="mj-notification-bell__item-title">${title}</span>`;

            const excerptHtml = excerpt 
                ? `<p class="mj-notification-bell__item-excerpt">${excerpt}</p>` 
                : '';

            const chevronHtml = isClickable 
                ? `<div class="mj-notification-bell__item-chevron" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg></div>`
                : '';

            const markReadBtn = isUnread 
                ? `<button type="button" class="mj-notification-bell__item-mark-read" data-recipient-id="${recipientId}" title="Marquer comme lu"><span class="mj-notification-bell__unread-dot"></span></button>` 
                : '';

            return `
                <div class="mj-notification-bell__item ${isUnread ? 'mj-notification-bell__item--unread' : ''}${clickableClass}" 
                     role="listitem" 
                     data-recipient-id="${recipientId}"
                     data-notification-id="${notificationId}"
                     ${isClickable ? `data-url="${this.escapeHtml(url)}"` : ''}>
                    ${linkOverlay}
                    <div class="mj-notification-bell__item-icon ${iconClass}">
                        ${this.getTypeIcon(type)}
                    </div>
                    <div class="mj-notification-bell__item-content">
                        ${titleHtml}
                        ${excerptHtml}
                        <time class="mj-notification-bell__item-time" datetime="${this.escapeHtml(createdAt)}">
                            ${this.escapeHtml(timeAgo)}
                        </time>
                    </div>
                    ${chevronHtml}
                    ${markReadBtn}
                </div>
            `;
        }

        getEmptyHtml() {
            return `
                <div class="mj-notification-bell__empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mj-notification-bell__empty-icon">
                        <path d="M5.85 3.5a.75.75 0 0 0-1.117-1 9.719 9.719 0 0 0-2.348 4.876.75.75 0 0 0 1.479.248A8.219 8.219 0 0 1 5.85 3.5ZM19.267 2.5a.75.75 0 1 0-1.118 1 8.22 8.22 0 0 1 1.987 4.124.75.75 0 0 0 1.48-.248A9.72 9.72 0 0 0 19.266 2.5Z" />
                        <path fill-rule="evenodd" d="M12 2.25A6.75 6.75 0 0 0 5.25 9v.75a8.217 8.217 0 0 1-2.119 5.52.75.75 0 0 0 .298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 1 0 7.48 0 24.583 24.583 0 0 0 4.83-1.244.75.75 0 0 0 .298-1.205 8.217 8.217 0 0 1-2.118-5.52V9A6.75 6.75 0 0 0 12 2.25ZM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 0 0 4.496 0l.002.1a2.25 2.25 0 1 1-4.5 0Z" clip-rule="evenodd" />
                    </svg>
                    <p class="mj-notification-bell__empty-text">Aucune notification pour le moment.</p>
                </div>
            `;
        }

        getTypeIcon(type) {
            const icons = {
                'event_registration_created': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>',
                'event_registration_cancelled': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2ZM7.28 11.22a.75.75 0 0 0-1.06 1.06L8.94 15l2.72-2.72a.75.75 0 0 0-1.06-1.06L8.94 12.88l-1.66-1.66Z" clip-rule="evenodd" /></svg>',
                'payment_completed': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4Zm12 4a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm13-1a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM1.75 14.5a.75.75 0 0 0 0 1.5c4.417 0 8.693.603 12.749 1.73 1.111.309 2.251-.512 2.251-1.696v-.784a.75.75 0 0 0-1.5 0v.784a.272.272 0 0 1-.35.25A49.043 49.043 0 0 0 1.75 14.5Z" clip-rule="evenodd" /></svg>',
                'member_created': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" /></svg>',
                'profile_updated': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" /></svg>',
                'photo_uploaded': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06L6.53 8.091a.75.75 0 0 0-1.06 0l-2.97 2.97Zm12-3.31a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" clip-rule="evenodd" /></svg>',
                'idea_published': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.644a.75.75 0 0 0 .75.75h2.5a.75.75 0 0 0 .75-.75v-.644c0-1.013.762-1.957 1.815-2.825A6 6 0 0 0 10 1ZM8.863 17.414a.75.75 0 0 0-.226 1.483 9.066 9.066 0 0 0 2.726 0 .75.75 0 0 0-.226-1.483 7.553 7.553 0 0 1-2.274 0Z" /></svg>',
                'info': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" /></svg>'
            };
            return icons[type] || icons['info'];
        }

        getTimeAgo(dateString) {
            if (!dateString) return '';
            
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return "à l'instant";
            if (diff < 3600) {
                const mins = Math.floor(diff / 60);
                return `il y a ${mins} min`;
            }
            if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return `il y a ${hours} h`;
            }
            if (diff < 604800) {
                const days = Math.floor(diff / 86400);
                return `il y a ${days} j`;
            }
            
            return date.toLocaleDateString('fr-FR');
        }

        handleItemClick(e) {
            // Vérifier si on clique sur le bouton mark-read ou son enfant (le dot)
            const markReadBtn = e.target.closest('.mj-notification-bell__item-mark-read');
            if (markReadBtn) {
                e.preventDefault();
                e.stopPropagation();
                const recipientId = markReadBtn.dataset.recipientId || markReadBtn.getAttribute('data-recipient-id');
                if (recipientId) {
                    this.markAsRead(recipientId, markReadBtn.closest('.mj-notification-bell__item'));
                }
                return;
            }

            // Vérifier si on clique sur le dot directement (fallback)
            const unreadDot = e.target.closest('.mj-notification-bell__unread-dot');
            if (unreadDot) {
                e.preventDefault();
                e.stopPropagation();
                const parentBtn = unreadDot.closest('.mj-notification-bell__item-mark-read');
                if (parentBtn) {
                    const recipientId = parentBtn.dataset.recipientId || parentBtn.getAttribute('data-recipient-id');
                    if (recipientId) {
                        this.markAsRead(recipientId, parentBtn.closest('.mj-notification-bell__item'));
                    }
                }
            }
        }

        async markAsRead(recipientId, itemElement) {
            if (this.config.preview) return;

            try {
                const response = await this.ajaxRequest('mj_member_notification_bell_mark_read', {
                    recipient_id: recipientId
                });

                if (response.success) {
                    // Mettre à jour l'UI
                    itemElement?.classList.remove('mj-notification-bell__item--unread');
                    const markBtn = itemElement?.querySelector('.mj-notification-bell__item-mark-read');
                    markBtn?.remove();

                    // Décrémenter le compteur
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateBadge();
                }
            } catch (error) {
                console.error('NotificationBell: Erreur mark read', error);
            }
        }

        async markAllAsRead() {
            if (this.config.preview || this.unreadCount === 0) return;

            this.markAllBtn.disabled = true;

            try {
                const response = await this.ajaxRequest('mj_member_notification_bell_mark_all_read', {
                    member_id: this.config.memberId
                });

                if (response.success) {
                    // Marquer tous les items comme lus
                    this.list?.querySelectorAll('.mj-notification-bell__item--unread').forEach(item => {
                        item.classList.remove('mj-notification-bell__item--unread');
                        item.querySelector('.mj-notification-bell__item-mark-read')?.remove();
                    });

                    this.unreadCount = 0;
                    this.updateBadge();
                }
            } catch (error) {
                console.error('NotificationBell: Erreur mark all read', error);
            } finally {
                this.markAllBtn.disabled = this.unreadCount === 0;
            }
        }

        updateCount(count) {
            this.unreadCount = count;
            this.updateBadge();
        }

        updateBadge() {
            if (!this.badge || !this.countEl) return;

            if (this.unreadCount > 0) {
                this.countEl.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                this.badge.classList.remove('mj-notification-bell__badge--hidden');
            } else {
                this.badge.classList.add('mj-notification-bell__badge--hidden');
            }

            // Mettre à jour le bouton mark all
            if (this.markAllBtn) {
                this.markAllBtn.disabled = this.unreadCount === 0;
            }
        }

        showLoader(show) {
            if (this.loader) {
                this.loader.classList.toggle('mj-notification-bell__loader--visible', show);
            }
        }

        startAutoRefresh() {
            if (this.refreshTimer) clearInterval(this.refreshTimer);

            this.refreshTimer = setInterval(() => {
                if (!this.isOpen) {
                    this.fetchCountOnly();
                }
            }, this.config.refreshInterval || 60000);
        }

        async fetchCountOnly() {
            if (this.config.preview) return;

            try {
                const response = await this.ajaxRequest('mj_member_notification_bell_count', {
                    member_id: this.config.memberId
                });

                if (response.success && typeof response.data.unread_count !== 'undefined') {
                    this.updateCount(response.data.unread_count);
                }
            } catch (error) {
                // Silencieux pour le polling
            }
        }

        ajaxRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', window.mjNotificationBell?.nonce || '');
            
            for (const key in data) {
                formData.append(key, data[key]);
            }

            return fetch(window.mjNotificationBell?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(res => res.json());
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        destroy() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
            }
        }
    }

    // Initialisation au chargement du DOM
    function init() {
        document.querySelectorAll('.mj-notification-bell').forEach(container => {
            if (!container._notificationBell) {
                container._notificationBell = new NotificationBell(container);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Réinitialiser après mise à jour Elementor
    if (window.elementorFrontend) {
        jQuery(window).on('elementor/frontend/init', function() {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-notification-bell.default', function($scope) {
                const container = $scope[0].querySelector('.mj-notification-bell');
                if (container && !container._notificationBell) {
                    container._notificationBell = new NotificationBell(container);
                }
            });
        });
    }

})();
