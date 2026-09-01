/**
 * Agenda widget — AJAX service.
 *
 * Single nonce, FormData POST, WordPress { success, data } convention.
 * Exposed as window.MjAgendaServices.createApiService(config).
 */
(function (global) {
    'use strict';

    function createApiService(config) {
        var ajaxUrl = (config && config.ajaxUrl) || '';
        var nonce = (config && config.nonce) || '';
        var controllers = {};

        function abort(key) {
            if (key && controllers[key]) {
                try { controllers[key].abort(); } catch (e) {}
                delete controllers[key];
            }
        }

        function post(action, data, options) {
            options = options || {};
            var abortKey = typeof options === 'string' ? options : options.abortKey;
            if (abortKey) {
                abort(abortKey);
            }

            var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            if (abortKey && controller) {
                controllers[abortKey] = controller;
            }

            var form = new FormData();
            form.append('action', action);
            form.append('nonce', nonce);
            Object.keys(data || {}).forEach(function (key) {
                var value = data[key];
                if (value === undefined || value === null) {
                    return;
                }
                if (Array.isArray(value) || (typeof value === 'object' && !(value instanceof Blob))) {
                    form.append(key, JSON.stringify(value));
                } else {
                    form.append(key, value);
                }
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                body: form,
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (abortKey) {
                        delete controllers[abortKey];
                    }
                    if (!response.ok || !payload || payload.success !== true) {
                        var message = (payload && payload.data && payload.data.message)
                            || 'Erreur ' + response.status;
                        var err = new Error(message);
                        err.status = response.status;
                        err.data = payload && payload.data;
                        throw err;
                    }
                    return payload.data;
                });
            });
        }

        return {
            post: post,
            abort: abort,
            fetchRange: function (params, abortKey) {
                return post('mj_member_agenda_fetch', params, abortKey || 'fetch');
            },
            saveEntry: function (payload) {
                return post('mj_member_agenda_save_entry', payload);
            },
            moveEntry: function (payload) {
                return post('mj_member_agenda_move_entry', payload);
            },
            deleteEntry: function (payload) {
                return post('mj_member_agenda_delete_entry', payload);
            },
            savePrefs: function (payload) {
                return post('mj_member_agenda_save_prefs', payload, 'prefs');
            }
        };
    }

    global.MjAgendaServices = { createApiService: createApiService };
})(window);
