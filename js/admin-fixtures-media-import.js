(function ($) {
    'use strict';

    var currentRunId = '';
    var progressTimer = null;

    function getConfig() {
        return window.mjMemberFixturesMediaAdmin || null;
    }

    function setStatus(message, type) {
        var node = document.getElementById('mj-fixtures-media-status');
        if (!node) {
            return;
        }

        node.textContent = message;
        node.className = 'mj-photo-import-status is-' + (type || 'info');
    }

    function setMeta(state) {
        var node = document.getElementById('mj-fixtures-media-progress-meta');
        if (!node) {
            return;
        }

        if (!state || typeof state !== 'object') {
            node.textContent = '';
            return;
        }

        var total = state.total || 0;
        var processed = state.processed || 0;
        var imported = state.imported || 0;
        var skipped = state.skipped || 0;
        var failed = state.failed || 0;
        node.textContent = 'Progression: ' + processed + '/' + total + ' | importees=' + imported + ' | ignorees=' + skipped + ' | echecs=' + failed;
    }

    function renderConsole(state) {
        var node = document.getElementById('mj-fixtures-media-live');
        if (!node) {
            return;
        }

        if (!state || typeof state !== 'object') {
            node.value = 'Aucun import en cours.';
            return;
        }

        var lines = [];
        lines.push('Statut: ' + (state.status || 'running') + ' | etape: ' + (state.step || '...'));
        lines.push('Total: ' + (state.total || 0) + ' | Traite: ' + (state.processed || 0));
        lines.push('Importees: ' + (state.imported || 0) + ' | Ignorees: ' + (state.skipped || 0) + ' | Echecs: ' + (state.failed || 0));

        if (state.current) {
            lines.push('Courant: ' + state.current);
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

        node.value = lines.join('\n');
        node.scrollTop = node.scrollHeight;
    }

    function stopPolling() {
        if (progressTimer) {
            window.clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    function pollProgress() {
        var config = getConfig();
        if (!config || !currentRunId) {
            return;
        }

        $.post(config.ajaxUrl, {
            action: 'mj_member_fixture_media_restore_progress',
            nonce: config.nonce,
            runId: currentRunId
        }).done(function (response) {
            if (!response || !response.success) {
                return;
            }

            var state = response.data && response.data.state ? response.data.state : null;
            renderConsole(state);
            setMeta(state);

            if (!state) {
                setStatus('Aucun etat runtime detecte.', 'warning');
                return;
            }

            if (state.status === 'done') {
                setStatus('Import wp_media termine.', 'success');
                stopPolling();
            } else if (state.status === 'error') {
                setStatus('Import wp_media en erreur. Consulte la console ci-dessous.', 'error');
                stopPolling();
            } else {
                setStatus('Import wp_media en cours...', 'info');
            }
        }).fail(function () {
            setStatus('Erreur de communication avec le serveur.', 'error');
        });
    }

    function startPolling(runId) {
        currentRunId = runId;
        stopPolling();
        pollProgress();
        progressTimer = window.setInterval(pollProgress, 1500);
    }

    function isWpMediaSelected() {
        var node = document.querySelector('input[name="mj_fixtures_restore_tables[]"][value="wp_media"]');
        return !!(node && node.checked);
    }

    function isWpMediaCleanBeforeSelected() {
        var node = document.querySelector('input[name="mj_fixtures_clean_before_tables[]"][value="wp_media"]');
        return !!(node && node.checked);
    }

    function generateRunId() {
        return 'fixtures-media-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
    }

    function startImport() {
        var config = getConfig();
        if (!config) {
            setStatus('Configuration JS fixtures media introuvable.', 'error');
            return;
        }

        if (!isWpMediaSelected()) {
            setStatus('Coche la source wp_media dans la colonne Restaurer avant de lancer.', 'warning');
            return;
        }

        var runId = generateRunId();
        setStatus('Demarrage du worker wp_media...', 'info');
        renderConsole({ status: 'running', step: 'start', events: [{ ts: Math.floor(Date.now() / 1000), message: 'Demarrage en cours...' }] });

        $.post(config.ajaxUrl, {
            action: 'mj_member_fixture_media_restore_start',
            nonce: config.nonce,
            runId: runId,
            cleanBefore: isWpMediaCleanBeforeSelected() ? '1' : ''
        }).done(function (response) {
            if (!response || !response.success) {
                var errorMessage = response && response.data && response.data.message ? response.data.message : 'Impossible de lancer l\'import wp_media.';
                setStatus(errorMessage, 'error');
                return;
            }

            var data = response.data || {};
            currentRunId = data.runId || runId;
            setStatus(data.message || 'Import wp_media demarre.', 'info');
            startPolling(currentRunId);
        }).fail(function () {
            setStatus('Erreur de communication au demarrage de l\'import wp_media.', 'error');
        });
    }

    $(function () {
        var btn = document.getElementById('mj-fixtures-media-start');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            startImport();
        });
    });
})(jQuery);
