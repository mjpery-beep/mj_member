/**
 * MJ Member – Service Worker pour les notifications Web Push.
 *
 * Ce fichier est servi via la rewrite rule /mj-sw.js
 * afin d'avoir le scope "/" nécessaire pour recevoir les push.
 *
 * @version 2 – 2026-03-05 – Toujours afficher la notification (Chrome l'exige)
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
 *
 * Chrome exige qu'un showNotification() soit appelé pour chaque push reçu.
 * Si les données sont absentes ou invalides (ex: échec de décryptage),
 * on affiche quand même une notification générique.
 */
self.addEventListener('push', function (event) {
    var payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            try {
                payload = { title: 'MJ Péry', body: event.data.text() };
            } catch (e2) {
                payload = { title: 'MJ Péry', body: 'Vous avez une nouvelle notification.' };
            }
        }
    }

    // Toujours afficher, même si pas de données (Chrome l'exige)
    var title = payload.title || 'MJ Péry';
    var options = {
        body: payload.body || 'Vous avez une nouvelle notification.',
        icon: payload.icon || '',
        badge: payload.badge || '',
        tag: payload.tag || 'mj-member-notification',
        data: {
            url: payload.url || '/'
        },
        vibrate: [200, 100, 200],
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
