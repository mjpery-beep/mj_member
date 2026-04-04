(function ($) {
    'use strict';

    var currentRunId = '';
    var progressTimer = null;
    var emptyStatePolls = 0;
    var pendingTags = [];
    var directFallbackStarted = false;

    function getDerivedAjaxUrl() {
        var currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
        if (!currentPath) {
            return '/wp-admin/admin-ajax.php';
        }

        // Turn /.../wp-admin/admin.php?page=... into /.../wp-admin/admin-ajax.php
        if (currentPath.indexOf('/wp-admin/') !== -1) {
            return currentPath.replace(/\/wp-admin\/[^/]*$/, '/wp-admin/admin-ajax.php');
        }

        return '/wp-admin/admin-ajax.php';
    }

    function getAjaxUrl(config) {
        if (config && typeof config.ajaxUrl === 'string' && config.ajaxUrl) {
            return config.ajaxUrl;
        }

        if (typeof window.ajaxurl === 'string' && window.ajaxurl) {
            return window.ajaxurl;
        }

        return getDerivedAjaxUrl();
    }

    function ensureConfig() {
        if (!window.mjMemberPhotoImportAdmin) {
            return null;
        }
        return window.mjMemberPhotoImportAdmin;
    }

    function setStatus(message, type) {
        var node = document.getElementById('mj-photo-import-status');
        if (!node) {
            return;
        }
        node.textContent = message;
        node.className = 'mj-photo-import-status is-' + (type || 'info');
    }

    function getSelectedTags() {
        var select = document.getElementById('mj-photo-import-tags');
        if (!select) {
            return [];
        }

        var values = [];
        Array.prototype.forEach.call(select.options, function (option) {
            if (option.selected) {
                values.push(option.value);
            }
        });

        return values;
    }

    function fillTags(tags) {
        var select = document.getElementById('mj-photo-import-tags');
        if (!select) {
            return;
        }

        var defaultsRaw = select.getAttribute('data-default-tags') || '';
        var defaults = defaultsRaw.split(/[,;\n\r]+/).map(function (item) {
            return item.trim().toLowerCase();
        }).filter(Boolean);

        select.innerHTML = '';

        tags.forEach(function (tag) {
            if (!tag) {
                return;
            }

            var tagId = (tag.id || '').toString().trim();
            var tagName = (tag.name || '').toString().trim();
            if (!tagId && !tagName) {
                return;
            }

            var option = document.createElement('option');
            option.value = tagId || tagName;
            option.textContent = tagName || tagId;
            if (tagName && defaults.indexOf(tagName.toLowerCase()) !== -1) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    function loadTags() {
        var config = ensureConfig();
        if (!config) {
            return;
        }

        var ajaxUrl = getAjaxUrl(config);

        setStatus('Chargement des étiquettes Nextcloud...', 'info');

        $.post(ajaxUrl, {
            action: 'mj_member_photo_import_tags',
            nonce: config.nonce
        }).done(function (response) {
            if (!response || !response.success) {
                var errorMessage = response && response.data && response.data.message ? response.data.message : 'Impossible de charger les étiquettes.';
                setStatus(errorMessage, 'error');
                return;
            }

            var tags = response && response.data && Array.isArray(response.data.tags) ? response.data.tags : [];
            fillTags(tags);

            if (!tags.length) {
                setStatus('Aucune étiquette trouvée côté Nextcloud pour ce compte. Vérifiez les logs [mj-member][photo-import] et les permissions des étiquettes.', 'warning');
                return;
            }

            setStatus('Étiquettes chargées. Sélectionnez une ou plusieurs étiquettes puis lancez l\'import.', 'success');
        }).fail(function (xhr) {
            if (xhr && xhr.status === 404) {
                setStatus('Erreur 404 sur admin-ajax.php. URL utilisée : ' + ajaxUrl, 'error');
                return;
            }

            setStatus('Erreur de communication avec le serveur.', 'error');
        });
    }

    function generateRunId() {
        return 'photoimport-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
    }

    function renderLiveState(state) {
        var node = document.getElementById('mj-photo-import-live');
        if (!node) {
            return;
        }

        if (!state || typeof state !== 'object') {
            node.textContent = 'Aucun import en cours.';
            return;
        }

        var lines = [];
        var status = state.status || 'running';
        var step = state.step || '...';
        lines.push('Statut: ' + status + ' | étape: ' + step);

        if (typeof state.imported !== 'undefined' || typeof state.matched !== 'undefined' || typeof state.skipped !== 'undefined') {
            lines.push('Résumé: importées=' + (state.imported || 0) + ' | correspondances=' + (state.matched || 0) + ' | ignorées=' + (state.skipped || 0));
        }

        lines.push('');
        var events = Array.isArray(state.events) ? state.events : [];
        events.forEach(function (event) {
            if (!event || !event.message) {
                return;
            }

            var ts = event.ts ? new Date(event.ts * 1000).toLocaleTimeString() : '--:--:--';
            lines.push('[' + ts + '] ' + event.message);
        });

        node.textContent = lines.join('\n');
        node.scrollTop = node.scrollHeight;
    }

    function stopProgressPolling() {
        if (progressTimer) {
            window.clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    function pollProgress() {
        var config = ensureConfig();
        if (!config || !currentRunId) {
            return;
        }

        var ajaxUrl = getAjaxUrl(config);
        $.post(ajaxUrl, {
            action: 'mj_member_photo_import_progress',
            nonce: config.nonce,
            runId: currentRunId
        }).done(function (response) {
            if (!response || !response.success) {
                return;
            }

            var state = response.data && response.data.state ? response.data.state : null;
            if (!state || typeof state !== 'object' || !Array.isArray(state.events)) {
                emptyStatePolls += 1;
                if (emptyStatePolls >= 6) {
                    setStatus('Worker indisponible, bascule en mode direct...', 'warning');
                    stopProgressPolling();
                    runImportDirectFallback();
                }
                return;
            }

            emptyStatePolls = 0;
            renderLiveState(state);
            if (state && (state.status === 'done' || state.status === 'error')) {
                stopProgressPolling();
            }
        });
    }

    function startProgressPolling(runId) {
        currentRunId = runId;
        emptyStatePolls = 0;
        directFallbackStarted = false;
        stopProgressPolling();
        pollProgress();
        progressTimer = window.setInterval(pollProgress, 1500);
    }

    function runImportDirectFallback() {
        if (directFallbackStarted) {
            return;
        }

        var config = ensureConfig();
        if (!config || !currentRunId || !pendingTags.length) {
            setStatus('Impossible de lancer le fallback direct (contexte incomplet).', 'error');
            return;
        }

        directFallbackStarted = true;

        var ajaxUrl = getAjaxUrl(config);
        renderLiveState({
            status: 'running',
            step: 'direct_fallback',
            events: [{ ts: Math.floor(Date.now() / 1000), message: 'Fallback direct lancé (sans worker loopback)...' }]
        });

        $.post(ajaxUrl, {
            action: 'mj_member_run_photo_import',
            nonce: config.nonce,
            runId: currentRunId,
            tags: pendingTags
        }).done(function (response) {
            if (!response || !response.success) {
                var errorMessage = response && response.data && response.data.message ? response.data.message : 'Échec du fallback direct.';
                setStatus(errorMessage, 'error');
                renderLiveState({
                    status: 'error',
                    step: 'direct_fallback_error',
                    events: [{ ts: Math.floor(Date.now() / 1000), message: errorMessage }]
                });
                return;
            }

            var message = response && response.data && response.data.message ? response.data.message : 'Import terminé (fallback direct).';
            setStatus(message, 'success');
            pollProgress();
        }).fail(function () {
            setStatus('Erreur de communication pendant le fallback direct.', 'error');
            renderLiveState({
                status: 'error',
                step: 'direct_fallback_error',
                events: [{ ts: Math.floor(Date.now() / 1000), message: 'Erreur de communication pendant le fallback direct.' }]
            });
        });
    }

    function runImport() {
        var config = ensureConfig();
        if (!config) {
            return;
        }

        var ajaxUrl = getAjaxUrl(config);

        var tags = getSelectedTags();
        if (!tags.length) {
            setStatus('Sélectionnez au moins une étiquette.', 'warning');
            return;
        }

        var runId = generateRunId();
        pendingTags = tags.slice(0);
        renderLiveState({ status: 'running', step: 'start', events: [{ ts: Math.floor(Date.now() / 1000), message: 'Demande de démarrage envoyée...' }] });
        setStatus('Démarrage de l\'import en arrière-plan...', 'info');

        $.post(ajaxUrl, {
            action: 'mj_member_photo_import_start',
            nonce: config.nonce,
            runId: runId,
            tags: tags
        }).done(function (response) {
            if (!response || !response.success) {
                var errorMessage = response && response.data && response.data.message ? response.data.message : 'Import impossible.';
                setStatus(errorMessage, 'error');
                stopProgressPolling();
                return;
            }

            var data = response.data || {};
            var startedRunId = data.runId || runId;
            currentRunId = startedRunId;
            setStatus(data.message || 'Import démarré.', 'info');
            startProgressPolling(startedRunId);
        }).fail(function (xhr) {
            if (xhr && xhr.status === 404) {
                setStatus('Erreur 404 sur admin-ajax.php pendant l\'import. URL utilisée : ' + ajaxUrl, 'error');
                stopProgressPolling();
                return;
            }

            setStatus('Erreur de communication avec le serveur pendant l\'import.', 'error');
            stopProgressPolling();
        });
    }

    $(function () {
        var loadButton = document.getElementById('mj-photo-import-load-tags');
        var importButton = document.getElementById('mj-photo-import-run');
        var refreshLiveButton = document.getElementById('mj-photo-import-refresh-live');

        if (loadButton) {
            loadButton.addEventListener('click', function (event) {
                event.preventDefault();
                loadTags();
            });
        }

        if (importButton) {
            importButton.addEventListener('click', function (event) {
                event.preventDefault();
                runImport();
            });
        }

        if (refreshLiveButton) {
            refreshLiveButton.addEventListener('click', function (event) {
                event.preventDefault();
                pollProgress();
            });
        }

        renderLiveState(null);
    });
})(jQuery);
