/**
 * MJ Member – Service Worker pour les notifications Web Push.
 *
 * Ce fichier est servi via la rewrite rule /mj-sw.js
 * afin d'avoir le scope "/" nécessaire pour recevoir les push.
 */

/* eslint-env serviceworker */
/* global self, clients */

self.addEventListener('install', function (event) {
    // Activer immédiatement sans attendre les anciens clients
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
    // Prendre le contrôle de tous les onglets ouverts
    event.waitUntil(self.clients.claim());
});

/**
 * Réception d'une notification push.
 */
self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    var payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = {
            title: 'Notification',
            body: event.data.text()
        };
    }

    var title = payload.title || 'Notification';
    var options = {
        body: payload.body || '',
        icon: payload.icon || '',
        badge: payload.badge || '',
        tag: payload.tag || 'mj-member-notification',
        data: {
            url: payload.url || '/'
        },
        // Vibration pour mobile
        vibrate: [200, 100, 200],
        // Afficher même si l'onglet est actif
        requireInteraction: false
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

/**
 * Clic sur une notification : ouvrir l'URL associée.
 */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            // Si un onglet est déjà ouvert sur ce site, on le focus
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if (client.url && client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    client.focus();
                    client.navigate(targetUrl);
                    return;
                }
            }
            // Sinon on ouvre un nouvel onglet
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

/**
 * Fermeture d'une notification (swipe dismiss).
 */
self.addEventListener('notificationclose', function () {
    // Pas d'action spéciale pour l'instant
});
