(function () {
    'use strict';

    function parseConfig(element) {
        var raw = element.getAttribute('data-config');
        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            console.warn('MJ Member notifications: unable to parse configuration.', error);
            return {};
        }
    }

    function setFeedback(container, status, message) {
        var feedback = container.querySelector('[data-role="feedback"]');
        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.classList.remove('is-success', 'is-error');

        if (status === 'success') {
            feedback.classList.add('is-success');
        } else if (status === 'error') {
            feedback.classList.add('is-error');
        }
    }

    function gatherPreferences(checkboxes) {
        var payload = {};

        checkboxes.forEach(function (checkbox) {
            var key = checkbox.getAttribute('data-preference-key');
            if (!key) {
                return;
            }

            payload[key] = checkbox.checked ? 1 : 0;
        });

        return payload;
    }

    function applyPreferences(checkboxes, preferences) {
        if (!preferences) {
            return;
        }

        checkboxes.forEach(function (checkbox) {
            var key = checkbox.getAttribute('data-preference-key');
            if (!key) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(preferences, key)) {
                checkbox.checked = !!preferences[key];
            }
        });
    }

    function toggleLoading(container, submitButton, state, strings) {
        if (state) {
            container.classList.add('is-loading');
        } else {
            container.classList.remove('is-loading');
        }

        if (!submitButton) {
            return;
        }

        if (state) {
            if (!submitButton.hasAttribute('data-original-label')) {
                submitButton.setAttribute('data-original-label', submitButton.textContent);
            }
            submitButton.textContent = strings.saving || submitButton.textContent;
            submitButton.disabled = true;
            submitButton.setAttribute('data-loading', '1');
        } else {
            var original = submitButton.getAttribute('data-original-label');
            if (original) {
                submitButton.textContent = original;
            }
            submitButton.disabled = false;
            submitButton.removeAttribute('data-loading');
        }
    }

    function postPreferences(config, payload, onSuccess, onError) {
        var globalConfig = window.MjMemberNotificationPreferences || {};
        var ajaxUrl = globalConfig.ajaxUrl || window.ajaxurl;
        var nonce = globalConfig.nonce || '';

        if (!ajaxUrl) {
            onError('missing_ajax');
            return;
        }

        var body = new window.URLSearchParams();
        body.append('action', 'mj_member_update_notification_preferences');
        body.append('nonce', nonce);
        body.append('preferences', JSON.stringify(payload));

        var request = {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        };

        if (window.fetch) {
            window.fetch(ajaxUrl, request)
                .then(function (response) {
                    return response.json().catch(function () {
                        return { success: false };
                    });
                })
                .then(function (json) {
                    onSuccess(json);
                })
                .catch(function () {
                    onError('network');
                });
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function () {
            try {
                var json = JSON.parse(xhr.responseText);
                onSuccess(json);
            } catch (error) {
                onError('parse');
            }
        };
        xhr.onerror = function () {
            onError('network');
        };
        xhr.send(body.toString());
    }

    function initComponent(container) {
        var config = parseConfig(container);
        var form = container.querySelector('[data-role="notifications-form"]');
        if (!form) {
            return;
        }

        var submitButton = form.querySelector('[data-role="submit"]');
        var checkboxes = Array.prototype.slice.call(form.querySelectorAll('[data-preference-key]'));
        var globalConfig = window.MjMemberNotificationPreferences || {};
        var strings = globalConfig.strings || {};

        if (config.preferences) {
            applyPreferences(checkboxes, config.preferences);
        }

        if (config.preview) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
            });
            return;
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                setFeedback(container, '', '');
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (container.classList.contains('is-loading')) {
                return;
            }

            var payload = gatherPreferences(checkboxes);
            setFeedback(container, '', '');
            toggleLoading(container, submitButton, true, strings);

            postPreferences(config, payload, function (response) {
                toggleLoading(container, submitButton, false, strings);

                if (response && response.success) {
                    var data = response.data || {};
                    if (data.preferences) {
                        applyPreferences(checkboxes, data.preferences);
                    }
                    setFeedback(container, 'success', data.message || strings.success || '');
                } else {
                    var errorMessage = strings.error || '';
                    if (response && response.data && response.data.message) {
                        errorMessage = response.data.message;
                    } else if (response && response.message) {
                        errorMessage = response.message;
                    }
                    if (!errorMessage) {
                        errorMessage = strings.genericError || 'Une erreur est survenue.';
                    }
                    setFeedback(container, 'error', errorMessage);
                }
            }, function () {
                toggleLoading(container, submitButton, false, strings);
                setFeedback(container, 'error', strings.networkError || 'Impossible de contacter le serveur.');
            });
        });
    }

    function boot() {
        var components = document.querySelectorAll('[data-mj-notification-preferences]');
        if (!components.length) {
            return;
        }

        components.forEach(function (component) {
            initComponent(component);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
