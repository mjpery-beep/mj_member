/**
 * MJ Member – Service Worker pour les notifications Web Push.
 *
 * Ce fichier est servi via la rewrite rule /mj-sw.js
 * afin d'avoir le scope "/" nécessaire pour recevoir les push.
 *
 * @version 3 – 2026-03-09 – Logging détaillé pour diagnostiquer les push silencieux
 */

/* eslint-env serviceworker */
/* global self, clients */

self.addEventListener('install', function (event) {
    console.log('[MJ SW] install – skipWaiting');
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
    console.log('[MJ SW] activate – clients.claim');
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
    console.log('[MJ SW] push event received, has data:', !!event.data);

    var payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
            console.log('[MJ SW] push payload parsed OK:', JSON.stringify(payload).substring(0, 200));
        } catch (e) {
            console.warn('[MJ SW] push JSON parse failed:', e.message);
            try {
                var rawText = event.data.text();
                console.log('[MJ SW] push raw text:', rawText.substring(0, 200));
                payload = { title: 'MJ Péry', body: rawText };
            } catch (e2) {
                console.warn('[MJ SW] push text() also failed:', e2.message);
                payload = { title: 'MJ Péry', body: 'Vous avez une nouvelle notification.' };
            }
        }
    } else {
        console.warn('[MJ SW] push event has NO data');
        payload = { title: 'MJ Péry', body: 'Nouvelle notification.' };
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

    console.log('[MJ SW] showNotification: title="' + title + '" body="' + (options.body || '').substring(0, 80) + '"');

    event.waitUntil(
        self.registration.showNotification(title, options).then(function () {
            console.log('[MJ SW] showNotification resolved OK');
        }).catch(function (err) {
            console.error('[MJ SW] showNotification FAILED:', err);
        })
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
