(function () {
    'use strict';

    var settings = window.mjMemberEventsWidget || {};
    var ajaxUrl = settings.ajaxUrl || '';
    var nonce = settings.nonce || '';
    var loginUrl = settings.loginUrl || '';
    var strings = settings.strings || {};
    var activeSignup = null;
    var defaultCtaLabel = strings.cta || "S'inscrire";
    var registeredCtaLabel = strings.registered || strings.confirm || "Confirmer l'inscription";

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function setFeedback(element, message, isError) {
        if (!element) {
            return;
        }
        element.textContent = message || '';
        if (isError) {
            element.classList.add('is-error');
        } else {
            element.classList.remove('is-error');
        }
    }

    function closeSignup(signup) {
        if (!signup) {
            return;
        }
        signup.classList.remove('is-open');
        signup.setAttribute('hidden', 'hidden');
        if (activeSignup === signup) {
            activeSignup = null;
        }
    }

    function openLoginModal() {
        var trigger = document.querySelector('[data-mj-login-trigger]');
        if (trigger) {
            trigger.click();
            return;
        }
        if (loginUrl) {
            window.location.href = loginUrl;
        }
    }

    function parsePayload(button) {
        var raw = button.getAttribute('data-registration');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function recomputePayloadState(payload) {
        if (!payload) {
            return { total: 0, available: 0 };
        }

        var participants = Array.isArray(payload.participants) ? payload.participants : [];
        var available = 0;
        var total = 0;

        for (var i = 0; i < participants.length; i++) {
            var participant = participants[i];
            if (!participant) {
                continue;
            }
            total += 1;
            if (!participant.isRegistered) {
                available += 1;
            }
        }

        payload.hasParticipants = total > 0;
        payload.hasAvailableParticipants = available > 0;
        payload.allRegistered = total > 0 && available === 0;

        return { total: total, available: available };
    }

    function updateButtonState(button, payload) {
        if (!button) {
            return;
        }

        var label = defaultCtaLabel;
        var registeredLabel = registeredCtaLabel;

        if (payload && payload.hasAvailableParticipants === false) {
            button.classList.add('is-registered');
            button.textContent = registeredLabel;
            return;
        }

        button.classList.remove('is-registered');
        button.textContent = label;
    }

    function buildSignup(card, button, signup, feedback, payload) {
        if (activeSignup && activeSignup !== signup) {
            closeSignup(activeSignup);
        }

        signup.removeAttribute('hidden');
        signup.classList.add('is-open');
        signup.innerHTML = '';
        activeSignup = signup;

        recomputePayloadState(payload);
        updateButtonState(button, payload);

        var form = document.createElement('form');
        form.className = 'mj-member-events__signup-form';
        form.setAttribute('data-event-id', payload.eventId || '');

        var title = document.createElement('p');
        title.className = 'mj-member-events__signup-title';
        title.textContent = strings.chooseParticipant || 'Choisissez le participant';
        form.appendChild(title);

        var participants = Array.isArray(payload.participants) ? payload.participants : [];
        var selectableCount = 0;
        var firstSelectable = null;
        var infoMessage = null;
        var noteField = null;
        var noteMaxLength = parseInt(payload.noteMaxLength, 10);
        if (!noteMaxLength || noteMaxLength <= 0) {
            noteMaxLength = 400;
        }

        if (!participants.length) {
            var empty = document.createElement('p');
            empty.className = 'mj-member-events__signup-empty';
            empty.textContent = strings.noParticipant || "Aucun profil disponible pour l'instant.";
            form.appendChild(empty);
        } else {
            var list = document.createElement('ul');
            list.className = 'mj-member-events__signup-options';

            for (var i = 0; i < participants.length; i++) {
                var entry = participants[i] || {};
                var participantId = entry.id !== undefined ? String(entry.id) : '';
                if (participantId === '') {
                    continue;
                }

                var isRegistered = !!entry.isRegistered;
                var registrationId = parseInt(entry.registrationId, 10);
                if (Number.isNaN(registrationId)) {
                    registrationId = 0;
                }

                var option = document.createElement('li');
                option.className = 'mj-member-events__signup-option';
                if (isRegistered) {
                    option.classList.add('is-registered');
                }

                var label = document.createElement('label');
                label.className = 'mj-member-events__signup-label';

                var input = document.createElement('input');
                input.type = 'radio';
                input.name = 'participant';
                input.value = participantId;
                input.required = true;
                input.disabled = isRegistered;

                if (!isRegistered) {
                    selectableCount += 1;
                    if (!firstSelectable) {
                        firstSelectable = input;
                    }
                }

                var span = document.createElement('span');
                span.className = 'mj-member-events__signup-name';
                span.textContent = entry.label || ('#' + participantId);

                label.appendChild(input);
                label.appendChild(span);

                if (isRegistered) {
                    var status = document.createElement('span');
                    status.className = 'mj-member-events__signup-status';
                    status.textContent = strings.alreadyRegistered || 'Déjà inscrit';
                    label.appendChild(status);
                }

                option.appendChild(label);

                if (isRegistered && ajaxUrl && nonce) {
                    (function (participantEntry, currentRegistrationId) {
                        var controls = document.createElement('div');
                        controls.className = 'mj-member-events__signup-controls';

                        var unregisterButton = document.createElement('button');
                        unregisterButton.type = 'button';
                        unregisterButton.className = 'mj-member-events__signup-toggle';
                        unregisterButton.textContent = strings.unregister || 'Se désinscrire';

                        controls.appendChild(unregisterButton);
                        option.appendChild(controls);

                        unregisterButton.addEventListener('click', function () {
                            if (form.dataset.submitting === '1') {
                                return;
                            }

                            if (!ajaxUrl || !nonce) {
                                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                                return;
                            }

                            if (strings.unregisterConfirm && !window.confirm(strings.unregisterConfirm)) {
                                return;
                            }

                            form.dataset.submitting = '1';
                            unregisterButton.disabled = true;
                            setFeedback(formFeedback, '', false);
                            if (feedback) {
                                setFeedback(feedback, '', false);
                            }

                            var payloadData = new window.FormData();
                            payloadData.append('action', 'mj_member_unregister_event');
                            payloadData.append('nonce', nonce);
                            payloadData.append('event_id', payload.eventId || '');
                            payloadData.append('member_id', participantEntry.id || '');
                            if (currentRegistrationId > 0) {
                                payloadData.append('registration_id', currentRegistrationId);
                            }

                            window.fetch(ajaxUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                body: payloadData,
                            }).then(function (response) {
                                return response.json().then(function (json) {
                                    return { ok: response.ok, status: response.status, json: json };
                                }).catch(function () {
                                    return { ok: response.ok, status: response.status, json: null };
                                });
                            }).then(function (result) {
                                if (!result.ok || !result.json) {
                                    var messageNetwork = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                    setFeedback(formFeedback, messageNetwork, true);
                                    return;
                                }

                                if (!result.json.success) {
                                    var messageFail = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                    if (result.json.data && result.json.data.message) {
                                        messageFail = result.json.data.message;
                                    }
                                    setFeedback(formFeedback, messageFail, true);
                                    return;
                                }

                                participantEntry.isRegistered = false;
                                participantEntry.registrationId = 0;
                                participantEntry.registrationStatus = '';
                                participantEntry.registrationCreatedAt = '';

                                payload.participants = participants;
                                recomputePayloadState(payload);

                                try {
                                    button.setAttribute('data-registration', JSON.stringify(payload));
                                } catch (error) {
                                    // Ignore serialization errors silently
                                }

                                updateButtonState(button, payload);

                                if (feedback) {
                                    setFeedback(feedback, strings.unregisterSuccess || 'Inscription annulée.', false);
                                }

                                form.dataset.submitting = '0';
                                unregisterButton.disabled = false;

                                buildSignup(card, button, signup, feedback, payload);
                            }).catch(function () {
                                var messageError = strings.unregisterError || strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                                setFeedback(formFeedback, messageError, true);
                            }).then(function () {
                                if (form.dataset.submitting !== '0') {
                                    form.dataset.submitting = '0';
                                }
                                unregisterButton.disabled = false;
                            });
                        });
                    })(entry, registrationId);
                }

                list.appendChild(option);
            }

            if (firstSelectable && selectableCount > 0) {
                firstSelectable.checked = true;
            }

            form.appendChild(list);
        }

        if (selectableCount === 0 && participants.length) {
            infoMessage = document.createElement('p');
            infoMessage.className = 'mj-member-events__signup-info';
            infoMessage.textContent = strings.allRegistered || 'Tous les profils sont déjà inscrits pour cet événement.';
            form.appendChild(infoMessage);
        }

        if (selectableCount > 0) {
            var noteWrapper = document.createElement('div');
            noteWrapper.className = 'mj-member-events__signup-note';
            var noteLabel = document.createElement('label');
            var noteId = 'mj-member-events-note-' + (payload.eventId || Date.now());
            noteLabel.setAttribute('for', noteId);
            noteLabel.textContent = strings.noteLabel || "Message pour l'équipe (optionnel)";
            noteField = document.createElement('textarea');
            noteField.id = noteId;
            noteField.name = 'note';
            noteField.maxLength = noteMaxLength;
            noteField.placeholder = strings.notePlaceholder || 'Précisez une remarque utile.';
            noteWrapper.appendChild(noteLabel);
            noteWrapper.appendChild(noteField);
            form.appendChild(noteWrapper);
        }

        var actions = document.createElement('div');
        actions.className = 'mj-member-events__signup-actions';

        var submit = document.createElement('button');
        submit.type = 'submit';
        submit.className = 'mj-member-events__signup-submit';
        submit.textContent = strings.confirm || "Confirmer l'inscription";

        if (selectableCount === 0) {
            submit.disabled = true;
            submit.textContent = strings.registered || strings.confirm || "Confirmer l'inscription";
        }

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'mj-member-events__signup-cancel';
        cancel.textContent = strings.cancel || 'Annuler';

        actions.appendChild(submit);
        actions.appendChild(cancel);
        form.appendChild(actions);

        var formFeedback = document.createElement('div');
        formFeedback.className = 'mj-member-events__signup-feedback';
        formFeedback.setAttribute('aria-live', 'polite');
        form.appendChild(formFeedback);

        signup.appendChild(form);

        cancel.addEventListener('click', function () {
            closeSignup(signup);
            setFeedback(formFeedback, '', false);
            if (feedback) {
                setFeedback(feedback, '', false);
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (form.dataset.submitting === '1') {
                return;
            }

            if (!participants.length) {
                setFeedback(formFeedback, strings.noParticipant || "Aucun profil disponible pour l'instant.", true);
                return;
            }

            var enabledOptions = form.querySelectorAll('input[name="participant"]:not(:disabled)');
            if (!enabledOptions.length) {
                setFeedback(formFeedback, strings.allRegistered || 'Tous les profils sont déjà inscrits pour cet événement.', true);
                return;
            }

            var selected = form.querySelector('input[name="participant"]:checked:not(:disabled)');
            if (!selected && enabledOptions.length) {
                selected = enabledOptions[0];
                selected.checked = true;
            }

            if (!selected) {
                setFeedback(formFeedback, strings.selectParticipant || 'Merci de sélectionner un participant.', true);
                return;
            }

            if (!ajaxUrl || !nonce) {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                return;
            }

            form.dataset.submitting = '1';
            submit.disabled = true;
            submit.textContent = strings.loading || 'En cours...';
            setFeedback(formFeedback, '', false);

            var payloadData = new window.FormData();
            payloadData.append('action', 'mj_member_register_event');
            payloadData.append('nonce', nonce);
            payloadData.append('event_id', payload.eventId || '');
            payloadData.append('member_id', selected.value);

            if (noteField) {
                var noteValue = noteField.value || '';
                if (noteValue.length > noteMaxLength) {
                    noteValue = noteValue.slice(0, noteMaxLength);
                }
                payloadData.append('note', noteValue);
            } else {
                payloadData.append('note', '');
            }

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payloadData,
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, status: response.status, json: json };
                }).catch(function () {
                    return { ok: response.ok, status: response.status, json: null };
                });
            }).then(function (result) {
                if (!result.ok || !result.json) {
                    var messageError = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json && result.json.data && result.json.data.message) {
                        messageError = result.json.data.message;
                    }
                    setFeedback(formFeedback, messageError, true);
                    return;
                }

                if (!result.json.success) {
                    var messageFail = strings.genericError || 'Une erreur est survenue. Merci de réessayer.';
                    if (result.json.data && result.json.data.message) {
                        messageFail = result.json.data.message;
                    }
                    setFeedback(formFeedback, messageFail, true);
                    return;
                }

                setFeedback(formFeedback, '', false);
                if (feedback) {
                    setFeedback(feedback, strings.success || 'Inscription enregistrée !', false);
                }

                if (noteField) {
                    noteField.value = '';
                }

                var selectedId = parseInt(selected.value, 10);
                var createdRegistrationId = 0;
                if (result.json.data && typeof result.json.data.registration_id !== 'undefined') {
                    createdRegistrationId = parseInt(result.json.data.registration_id, 10);
                    if (Number.isNaN(createdRegistrationId)) {
                        createdRegistrationId = 0;
                    }
                }

                for (var idx = 0; idx < participants.length; idx++) {
                    var participantItem = participants[idx];
                    if (!participantItem) {
                        continue;
                    }

                    if (parseInt(participantItem.id, 10) === selectedId) {
                        participantItem.isRegistered = true;
                        if (createdRegistrationId > 0) {
                            participantItem.registrationId = createdRegistrationId;
                            participantItem.registrationStatus = 'en_attente';
                        }
                    }
                }

                payload.participants = participants;
                payload.noteMaxLength = noteMaxLength;
                recomputePayloadState(payload);

                try {
                    button.setAttribute('data-registration', JSON.stringify(payload));
                } catch (error) {
                    // Ignore JSON errors silently
                }

                updateButtonState(button, payload);

                closeSignup(signup);
            }).catch(function () {
                setFeedback(formFeedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
            }).then(function () {
                form.dataset.submitting = '0';
                submit.disabled = false;
                submit.textContent = strings.confirm || "Confirmer l'inscription";
                if (!form.querySelectorAll('input[name="participant"]:not(:disabled)').length) {
                    submit.disabled = true;
                    submit.textContent = strings.registered || strings.confirm || "Confirmer l'inscription";
                }
            });
        });

        var firstRadio = signup.querySelector('input[name="participant"]');
        if (firstRadio) {
            firstRadio.focus();
        }
    }

    ready(function () {
        var buttons = document.querySelectorAll('.mj-member-events__cta');

        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function () {
                if (button.disabled) {
                    return;
                }

                var card = button.closest('.mj-member-events__item');
                if (!card) {
                    return;
                }

                var signup = card.querySelector('.mj-member-events__signup');
                var feedback = card.querySelector('.mj-member-events__feedback');

                if (!signup) {
                    return;
                }

                if (button.getAttribute('data-requires-login') === '1') {
                    setFeedback(feedback, strings.loginRequired || 'Connectez-vous pour continuer.', false);
                    openLoginModal();
                    return;
                }

                var payload = parsePayload(button);
                if (!payload) {
                    setFeedback(feedback, strings.genericError || 'Une erreur est survenue. Merci de réessayer.', true);
                    return;
                }

                buildSignup(card, button, signup, feedback, payload);
            });
        });

        document.addEventListener('click', function (event) {
            if (!activeSignup) {
                return;
            }

            if (!activeSignup.contains(event.target)) {
                var card = activeSignup.closest('.mj-member-events__item');
                var button = card ? card.querySelector('.mj-member-events__cta') : null;
                if (button && event.target === button) {
                    return;
                }

                closeSignup(activeSignup);
            }
        });
    });
})();
