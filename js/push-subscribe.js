/**
 * MJ Member – Client-side Push Subscription Manager.
 *
 * Enregistre le Service Worker, demande la permission de notification,
 * et envoie la souscription au serveur via AJAX.
 *
 * Affiche un bandeau soft-prompt quand la permission est 'default',
 * et injecte un raccourci dans le panneau cloche de notifications.
 *
 * Dépend de la variable globale `mjPushSubscribe` localisée via wp_localize_script.
 */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return; // Navigateur non compatible
    }

    var config = window.mjPushSubscribe || {};
    if (!config.vapidPublicKey || !config.ajaxUrl) {
        return;
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Convertit la clé VAPID base64url en Uint8Array pour l'API Push.
     */
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var array = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            array[i] = raw.charCodeAt(i);
        }
        return array;
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX – souscription / désinscription                              */
    /* ------------------------------------------------------------------ */

    /**
     * Envoie la souscription au serveur.
     */
    function sendSubscriptionToServer(subscription) {
        var key = subscription.getKey('p256dh');
        var auth = subscription.getKey('auth');

        var body = JSON.stringify({
            endpoint: subscription.endpoint,
            public_key: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : '',
            auth_token: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : '',
            content_encoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
            nonce: config.nonce
        });

        return fetch(config.ajaxUrl + '?action=mj_push_subscribe&nonce=' + encodeURIComponent(config.nonce), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                // 403 = nonce expiré, 0 = pas connecté, etc.
                if (window.console) console.warn('[MJ Push] HTTP ' + response.status + ' on subscribe AJAX');
                return { success: false, data: { error: 'HTTP ' + response.status } };
            }
            return response.json();
        });
    }

    /**
     * Envoie la désinscription au serveur.
     */
    function sendUnsubscribeToServer(endpoint) {
        var body = JSON.stringify({
            endpoint: endpoint,
            nonce: config.nonce
        });

        return fetch(config.ajaxUrl + '?action=mj_push_unsubscribe&nonce=' + encodeURIComponent(config.nonce), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Push subscription                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Souscrit aux notifications push.
     */
    function subscribePush(registration) {
        var options = {
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(config.vapidPublicKey)
        };

        return registration.pushManager.subscribe(options).then(function (subscription) {
            return sendSubscriptionToServer(subscription);
        });
    }

    /**
     * Vérifie l'état de la souscription et souscrit si nécessaire.
     *
     * Appelle toujours pushManager.subscribe() au lieu de getSubscription()
     * pour obtenir un endpoint garanti valide.  Si l'abonnement existant est
     * encore bon, subscribe() le retourne tel quel ; sinon le push service
     * (FCM/WNS) génère un nouvel endpoint.  Cela corrige le cas où
     * getSubscription() renvoie un objet dont l'endpoint est périmé côté
     * serveur (le push service répond 201 mais ne délivre plus).
     */
    function checkAndSubscribe(registration) {
        if (Notification.permission !== 'granted') {
            // Permission pas encore accordée – laisser le soft-prompt gérer
            if (window.console) console.debug('[MJ Push] Permission=' + Notification.permission + ', skipping auto-subscribe');
            return;
        }

        // subscribe() avec la même applicationServerKey retourne
        // l'abonnement actif ou en crée un nouveau si l'ancien est expiré.
        subscribePush(registration).then(function (response) {
            if (response && response.success) {
                try { localStorage.setItem('mj_push_endpoint', response.data && response.data.id ? String(response.data.id) : ''); } catch (e) { /* noop */ }
                if (window.console) console.debug('[MJ Push] Subscribe/sync OK, id=' + (response.data && response.data.id));
            } else {
                if (window.console) console.warn('[MJ Push] Subscribe/sync refused:', response);
            }
        }).catch(function (err) {
            if (window.console) console.warn('[MJ Push] Subscribe/sync error:', err);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Soft-prompt UI                                                     */
    /* ------------------------------------------------------------------ */

    var DISMISS_KEY = 'mj_push_prompt_dismissed';
    var DISMISS_DAYS = 7; // jours avant de re-proposer

    function isDismissed() {
        try {
            var ts = parseInt(localStorage.getItem(DISMISS_KEY), 10);
            if (!ts) return false;
            return (Date.now() - ts) < DISMISS_DAYS * 86400000;
        } catch (e) {
            return false;
        }
    }

    function setDismissed() {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) { /* noop */ }
    }

    function clearDismissed() {
        try { localStorage.removeItem(DISMISS_KEY); } catch (e) { /* noop */ }
    }

    /**
     * Gère le résultat de la demande de permission côté UI.
     */
    function handlePermissionResult(permission) {
        // Retirer tous les prompts
        removeSoftPrompt();
        removeBellPrompt();

        if (permission === 'granted') {
            clearDismissed();
        } else if (permission === 'denied') {
            setDismissed();
        } else {
            setDismissed();
        }
    }

    /* ---------- Floating toast (bas de page) ---------- */

    var toastEl = null;

    function showSoftPrompt() {
        if (toastEl) return;
        if (Notification.permission !== 'default') return;
        if (isDismissed()) return;

        toastEl = document.createElement('div');
        toastEl.className = 'mj-push-prompt';
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML =
            '<div class="mj-push-prompt__inner">' +
                '<span class="mj-push-prompt__icon" aria-hidden="true">🔔</span>' +
                '<span class="mj-push-prompt__text">Recevoir les notifications MJ Péry sur votre appareil\u00a0?</span>' +
                '<button type="button" class="mj-push-prompt__btn mj-push-prompt__btn--accept">Activer</button>' +
                '<button type="button" class="mj-push-prompt__btn mj-push-prompt__btn--dismiss" aria-label="Plus tard">&times;</button>' +
            '</div>';

        document.body.appendChild(toastEl);

        // Forcer le reflow avant d'ajouter la classe visible
        void toastEl.offsetHeight;
        toastEl.classList.add('mj-push-prompt--visible');

        toastEl.querySelector('.mj-push-prompt__btn--accept').addEventListener('click', function () {
            window.mjPushManager.requestPermission().then(handlePermissionResult);
        });

        toastEl.querySelector('.mj-push-prompt__btn--dismiss').addEventListener('click', function () {
            setDismissed();
            removeSoftPrompt();
        });
    }

    function removeSoftPrompt() {
        if (!toastEl) return;
        toastEl.classList.remove('mj-push-prompt--visible');
        var el = toastEl;
        toastEl = null;
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 400);
    }

    /* ---------- Banner inside notification-bell panel ---------- */

    var bellBannerInjected = false;

    function injectBellPrompt() {
        if (bellBannerInjected) return;
        if (Notification.permission !== 'default') return;

        var panel = document.querySelector('.mj-notification-bell__panel');
        if (!panel) return;

        var header = panel.querySelector('.mj-notification-bell__header');
        if (!header) return;

        bellBannerInjected = true;

        var banner = document.createElement('div');
        banner.className = 'mj-push-bell-prompt';
        banner.innerHTML =
            '<span class="mj-push-bell-prompt__icon" aria-hidden="true">📲</span>' +
            '<span class="mj-push-bell-prompt__text">Activer les notifications push</span>' +
            '<svg class="mj-push-bell-prompt__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">' +
                '<path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />' +
            '</svg>';

        banner.style.cursor = 'pointer';
        banner.setAttribute('role', 'button');
        banner.setAttribute('tabindex', '0');

        // Insérer après le header
        header.parentNode.insertBefore(banner, header.nextSibling);

        banner.addEventListener('click', function () {
            window.mjPushManager.requestPermission().then(handlePermissionResult);
        });
        banner.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.mjPushManager.requestPermission().then(handlePermissionResult);
            }
        });
    }

    function removeBellPrompt() {
        var el = document.querySelector('.mj-push-bell-prompt');
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
        bellBannerInjected = false;
    }

    /**
     * Observe les ouvertures du panneau cloche pour injecter le prompt.
     */
    function watchBellPanel() {
        // Injecter au premier rendu si le panneau existe
        injectBellPrompt();

        // Observer les changements de classe (ouverture du panneau)
        var bellContainer = document.querySelector('.mj-notification-bell');
        if (!bellContainer) return;

        var observer = new MutationObserver(function () {
            if (bellContainer.classList.contains('mj-notification-bell--open')) {
                injectBellPrompt();
            }
        });
        observer.observe(bellContainer, { attributes: true, attributeFilter: ['class'] });
    }

    /* ------------------------------------------------------------------ */
    /*  Initialisation                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Point d'entrée : enregistrer le SW puis vérifier/souscrire.
     */
    if (window.console) console.debug('[MJ Push] Init: swUrl=' + config.swUrl + ' permission=' + Notification.permission);

    navigator.serviceWorker.register(config.swUrl, { scope: '/', updateViaCache: 'none' })
        .then(function (registration) {
            if (window.console) console.debug('[MJ Push] SW registered OK, active=' + !!registration.active + ' installing=' + !!registration.installing + ' waiting=' + !!registration.waiting);
            // Forcer la vérification d'une nouvelle version du SW à chaque page
            if (registration.update) {
                registration.update().catch(function () { /* ignore update check errors */ });
            }
            // Attendre que le SW soit actif
            if (registration.active) {
                checkAndSubscribe(registration);
            } else {
                navigator.serviceWorker.ready.then(function (reg) {
                    if (window.console) console.debug('[MJ Push] SW ready (waited), active=' + !!reg.active);
                    checkAndSubscribe(reg);
                });
            }
        })
        .catch(function (err) {
            if (window.console) {
                console.warn('[MJ Push] Service Worker registration failed:', err);
            }
        });

    // Afficher le soft-prompt et le prompt dans la cloche après le chargement
    function initPrompts() {
        if (Notification.permission === 'default') {
            // Léger délai pour ne pas bloquer le rendu initial
            setTimeout(showSoftPrompt, 2500);
            watchBellPanel();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPrompts);
    } else {
        initPrompts();
    }

    /* ------------------------------------------------------------------ */
    /*  API publique                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * API publique exposée pour déclencher la demande de permission depuis l'UI.
     * Usage : window.mjPushManager.requestPermission()
     */
    window.mjPushManager = {
        /**
         * Demande la permission de notification et souscrit.
         * @returns {Promise<string>} 'granted', 'denied', ou 'default'
         */
        requestPermission: function () {
            return Notification.requestPermission().then(function (permission) {
                if (permission === 'granted') {
                    return navigator.serviceWorker.ready.then(function (registration) {
                        return subscribePush(registration).then(function () {
                            return 'granted';
                        });
                    });
                }
                return permission;
            });
        },

        /**
         * Se désabonner des notifications push.
         * @returns {Promise<boolean>}
         */
        unsubscribe: function () {
            return navigator.serviceWorker.ready.then(function (registration) {
                return registration.pushManager.getSubscription().then(function (subscription) {
                    if (!subscription) {
                        return false;
                    }
                    var endpoint = subscription.endpoint;
                    return subscription.unsubscribe().then(function (success) {
                        if (success) {
                            sendUnsubscribeToServer(endpoint);
                        }
                        return success;
                    });
                });
            });
        },

        /**
         * Vérifie si les push sont activées.
         * @returns {Promise<boolean>}
         */
        isSubscribed: function () {
            return navigator.serviceWorker.ready.then(function (registration) {
                return registration.pushManager.getSubscription().then(function (subscription) {
                    return subscription !== null;
                });
            });
        },

        /**
         * Permission actuelle.
         * @returns {string} 'granted', 'denied', ou 'default'
         */
        getPermission: function () {
            return Notification.permission;
        }
    };
})();
