/**
 * Registration Manager - API Services
 * Gestion des appels AJAX vers le backend
 */

(function (global) {
    'use strict';

    /**
     * Crée un service API pour le Registration Manager
     * @param {Object} config - Configuration du widget
     * @returns {Object} Service API
     */
    function createApiService(config) {
        var ajaxUrl = config.ajaxUrl || '';
        var nonce = config.nonce || '';
        var abortControllers = {};

        /**
         * Annule une requête en cours
         * @param {string} key - Clé de la requête
         */
        function abort(key) {
            if (abortControllers[key]) {
                abortControllers[key].abort();
                delete abortControllers[key];
            }
        }

        function appendNested(formData, baseKey, value) {
            if (value === undefined || value === null) {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach(function (item, index) {
                    appendNested(formData, baseKey + '[' + index + ']', item);
                });
                if (value.length === 0) {
                    formData.append(baseKey + '[]', '');
                }
                return;
            }

            if (typeof value === 'object') {
                Object.keys(value).forEach(function (subKey) {
                    appendNested(formData, baseKey + '[' + subKey + ']', value[subKey]);
                });
                return;
            }

            if (typeof value === 'boolean') {
                formData.append(baseKey, value ? '1' : '0');
                return;
            }

            formData.append(baseKey, value);
        }

        /**
         * Effectue une requête POST AJAX
         * @param {string} action - Action WordPress AJAX
         * @param {Object} data - Données à envoyer
         * @param {string} [abortKey] - Clé pour annulation
         * @returns {Promise<Object>}
         */
        function post(action, data, abortKey) {
            if (abortKey) {
                abort(abortKey);
            }

            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            if (abortKey && controller) {
                abortControllers[abortKey] = controller;
            }

            var formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', nonce);

            Object.keys(data || {}).forEach(function (key) {
                var value = data[key];
                if (value === undefined || value === null) {
                    return;
                }
                if (key === 'form' || key === 'meta') {
                    appendNested(formData, key, value);
                } else if (Array.isArray(value)) {
                    // Check if it's an array of objects
                    if (value.length > 0 && typeof value[0] === 'object') {
                        // Send as JSON string for arrays of objects
                        formData.append(key, JSON.stringify(value));
                    } else {
                        // Simple array of primitives
                        value.forEach(function (item, index) {
                            formData.append(key + '[' + index + ']', item);
                        });
                    }
                } else if (typeof value === 'object') {
                    formData.append(key, JSON.stringify(value));
                } else {
                    formData.append(key, value);
                }
            });

            var fetchOptions = {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            };

            if (controller) {
                fetchOptions.signal = controller.signal;
            }

            return fetch(ajaxUrl, fetchOptions)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (result) {
                    if (abortKey) {
                        delete abortControllers[abortKey];
                    }
                    if (!result.success) {
                        var message = result.data && result.data.message 
                            ? result.data.message 
                            : 'Erreur inconnue';
                        var error = new Error(message);
                        if (result.data) {
                            error.data = result.data;
                        }
                        throw error;
                    }
                    return result.data;
                })
                .catch(function (error) {
                    if (abortKey) {
                        delete abortControllers[abortKey];
                    }
                    if (error.name === 'AbortError') {
                        return Promise.reject({ aborted: true });
                    }
                    throw error;
                });
        }

        return {
            /**
             * Récupère la liste des événements
             */
            getEvents: function (params) {
                return post('mj_regmgr_get_events', params, 'events');
            },

            /**
             * Récupère les détails d'un événement
             */
            getEventDetails: function (eventId) {
                return post('mj_regmgr_get_event_details', { eventId: eventId }, 'event-' + eventId);
            },

            /**
             * Récupère les données pour l'éditeur d'événement
             */
            getEventEditor: function (eventId) {
                return post('mj_regmgr_get_event_editor', { eventId: eventId }, 'event-editor-' + eventId);
            },

            /**
             * Met à jour un événement
             */
            updateEvent: function (eventId, form, meta) {
                return post('mj_regmgr_update_event', {
                    eventId: eventId,
                    form: form,
                    meta: meta || {},
                });
            },

            /**
             * Crée un nouvel événement en brouillon
             */
            createEvent: function (params) {
                var payload = params || {};
                return post('mj_regmgr_create_event', {
                    title: payload.title || '',
                    type: payload.type || '',
                });
            },

            /**
             * Supprime un événement
             */
            deleteEvent: function (eventId) {
                return post('mj_regmgr_delete_event', {
                    eventId: eventId,
                });
            },

            /**
             * Récupère les inscriptions d'un événement
             */
            getRegistrations: function (eventId) {
                return post('mj_regmgr_get_registrations', { eventId: eventId }, 'registrations-' + eventId);
            },

            /**
             * Recherche des membres
             */
            searchMembers: function (params) {
                return post('mj_regmgr_search_members', params, 'search-members');
            },

            /**
             * Ajoute des inscriptions
             */
            addRegistration: function (eventId, memberIds, occurrences) {
                return post('mj_regmgr_add_registration', {
                    eventId: eventId,
                    memberIds: memberIds,
                    occurrences: occurrences || [],
                });
            },

            /**
             * Met à jour le statut d'une inscription
             */
            updateRegistration: function (registrationId, status) {
                return post('mj_regmgr_update_registration', {
                    registrationId: registrationId,
                    status: status,
                });
            },

            /**
             * Supprime une inscription
             */
            deleteRegistration: function (registrationId) {
                return post('mj_regmgr_delete_registration', {
                    registrationId: registrationId,
                });
            },

            /**
             * Met à jour la présence
             */
            updateAttendance: function (eventId, memberId, occurrence, status) {
                return post('mj_regmgr_update_attendance', {
                    eventId: eventId,
                    memberId: memberId,
                    occurrence: occurrence,
                    status: status,
                });
            },

            /**
             * Met à jour la présence en masse
             */
            bulkAttendance: function (eventId, occurrence, updates) {
                return post('mj_regmgr_bulk_attendance', {
                    eventId: eventId,
                    occurrence: occurrence,
                    updates: updates,
                });
            },

            /**
             * Valide un paiement
             */
            validatePayment: function (registrationId, method, reference) {
                return post('mj_regmgr_validate_payment', {
                    registrationId: registrationId,
                    paymentMethod: method || 'manual',
                    paymentReference: reference || '',
                });
            },

            /**
             * Annule un paiement
             */
            cancelPayment: function (registrationId) {
                return post('mj_regmgr_cancel_payment', {
                    registrationId: registrationId,
                });
            },

            /**
             * Crée un membre rapide
             */
            createQuickMember: function (firstName, lastName, email, role, birthDate) {
                return post('mj_regmgr_create_quick_member', {
                    firstName: firstName,
                    lastName: lastName,
                    email: email || '',
                    role: role || 'jeune',
                    birthDate: birthDate || '',
                });
            },

            /**
             * Récupère les notes d'un membre
             */
            getMemberNotes: function (memberId) {
                return post('mj_regmgr_get_member_notes', { memberId: memberId });
            },

            /**
             * Sauvegarde une note
             */
            saveMemberNote: function (memberId, content, noteId) {
                return post('mj_regmgr_save_member_note', {
                    memberId: memberId,
                    content: content,
                    noteId: noteId || 0,
                });
            },

            /**
             * Supprime une note
             */
            deleteMemberNote: function (noteId) {
                return post('mj_regmgr_delete_member_note', { noteId: noteId });
            },

            /**
             * Récupère le QR code de paiement
             */
            getPaymentQR: function (registrationId) {
                return post('mj_regmgr_get_payment_qr', { registrationId: registrationId });
            },

            /**
             * Met à jour les occurrences sélectionnées
             */
            updateOccurrences: function (registrationId, occurrences) {
                return post('mj_regmgr_update_occurrences', {
                    registrationId: registrationId,
                    occurrences: occurrences,
                });
            },

            /**
             * Récupère la liste des membres
             */
            getMembers: function (params) {
                return post('mj_regmgr_get_members', params, 'members');
            },

            /**
             * Récupère les détails d'un membre
             */
            getMemberDetails: function (memberId) {
                return post('mj_regmgr_get_member_details', { memberId: memberId }, 'member-' + memberId);
            },

            /**
             * Met à jour un membre
             */
            updateMember: function (memberId, data) {
                return post('mj_regmgr_update_member', {
                    memberId: memberId,
                    data: data,
                });
            },

            /**
             * Met à jour une idée liée à un membre
             */
            updateMemberIdea: function (ideaId, memberId, data) {
                return post('mj_regmgr_update_member_idea', {
                    ideaId: ideaId,
                    memberId: memberId,
                    data: data,
                });
            },

            /**
             * Met à jour une photo partagée par un membre
             */
            updateMemberPhoto: function (photoId, memberId, data) {
                return post('mj_regmgr_update_member_photo', {
                    photoId: photoId,
                    memberId: memberId,
                    data: data,
                });
            },

            /**
             * Supprime une photo partagée par un membre
             */
            deleteMemberPhoto: function (photoId, memberId) {
                return post('mj_regmgr_delete_member_photo', {
                    photoId: photoId,
                    memberId: memberId,
                });
            },

            /**
             * Supprime un message lié au membre
             */
            deleteMemberMessage: function (messageId, memberId) {
                return post('mj_regmgr_delete_member_message', {
                    messageId: messageId,
                    memberId: memberId,
                });
            },

            /**
             * Récupère l'historique d'inscriptions d'un membre
             */
            getMemberRegistrations: function (memberId) {
                return post('mj_regmgr_get_member_registrations', { memberId: memberId });
            },

            /**
             * Marque la cotisation d'un membre comme payée
             */
            markMembershipPaid: function (memberId, paymentMethod, year) {
                return post('mj_regmgr_mark_membership_paid', {
                    memberId: memberId,
                    paymentMethod: paymentMethod || 'cash',
                    year: year || new Date().getFullYear(),
                });
            },

            /**
             * Crée un lien de paiement Stripe pour la cotisation
             */
            createMembershipPaymentLink: function (memberId) {
                return post('mj_regmgr_create_membership_payment_link', {
                    memberId: memberId,
                });
            },

            /**
             * Annule toutes les requêtes en cours
             */
            abortAll: function () {
                Object.keys(abortControllers).forEach(function (key) {
                    abort(key);
                });
            },
        };
    }

    // Export
    global.MjRegMgrServices = { createApiService: createApiService };

})(window);
