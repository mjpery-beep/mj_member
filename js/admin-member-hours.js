/**
 * MJ Member - Encodage des heures dans la fiche admin membre
 */
(function() {
    'use strict';

    var section = document.getElementById('hours-encode-section');
    if (!section) {
        return;
    }

    var config = window.mjMemberHours || {};
    var ajaxurl = config.ajaxurl || (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '/wp-admin/admin-ajax.php');
    var nonce = config.nonce || '';
    var memberId = config.memberId || 0;
    var i18n = config.i18n || {};

    // Elements
    var dateInput = document.getElementById('mj_hour_date');
    var startInput = document.getElementById('mj_hour_start');
    var endInput = document.getElementById('mj_hour_end');
    var taskInput = document.getElementById('mj_hour_task');
    var projectInput = document.getElementById('mj_hour_project');
    var addBtn = document.getElementById('mj-hours-add-btn');
    var durationPreview = document.getElementById('mj-hours-duration-preview');
    var totalDisplay = document.getElementById('mj-hours-total');
    var tbody = document.getElementById('mj-hours-tbody');
    var feedback = document.getElementById('mj-hours-feedback');

    /**
     * Affiche un message de feedback
     */
    function showFeedback(message, type) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.className = 'mj-hours-feedback';
        if (type === 'error') {
            feedback.classList.add('mj-hours-feedback--error');
        } else if (type === 'success') {
            feedback.classList.add('mj-hours-feedback--success');
        }

        // Auto-hide après 4 secondes
        setTimeout(function() {
            feedback.textContent = '';
            feedback.className = 'mj-hours-feedback';
        }, 4000);
    }

    /**
     * Calcule la durée entre deux heures
     */
    function calculateDuration(start, end) {
        if (!start || !end) {
            return 0;
        }

        var startParts = start.split(':');
        var endParts = end.split(':');
        
        if (startParts.length < 2 || endParts.length < 2) {
            return 0;
        }

        var startMinutes = parseInt(startParts[0], 10) * 60 + parseInt(startParts[1], 10);
        var endMinutes = parseInt(endParts[0], 10) * 60 + parseInt(endParts[1], 10);

        var diff = endMinutes - startMinutes;
        return diff > 0 ? diff : 0;
    }

    /**
     * Formate les minutes en chaîne HhMM
     */
    function formatDuration(minutes) {
        if (!minutes || minutes <= 0) {
            return '--';
        }
        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;
        return hours + 'h' + String(mins).padStart(2, '0');
    }

    /**
     * Met à jour l'aperçu de la durée
     */
    function updateDurationPreview() {
        var start = startInput ? startInput.value : '';
        var end = endInput ? endInput.value : '';
        var duration = calculateDuration(start, end);
        
        if (durationPreview) {
            durationPreview.textContent = formatDuration(duration);
        }

        updateAddButtonState();
    }

    /**
     * Met à jour l'état du bouton Ajouter
     */
    function updateAddButtonState() {
        if (!addBtn) return;

        var date = dateInput ? dateInput.value.trim() : '';
        var task = taskInput ? taskInput.value.trim() : '';
        var start = startInput ? startInput.value : '';
        var end = endInput ? endInput.value : '';
        var duration = calculateDuration(start, end);

        var isValid = date !== '' && task !== '' && duration > 0;
        addBtn.disabled = !isValid;
    }

    /**
     * Réinitialise le formulaire
     */
    function resetForm() {
        if (startInput) startInput.value = '';
        if (endInput) endInput.value = '';
        if (taskInput) taskInput.value = '';
        if (projectInput) projectInput.value = '';
        if (durationPreview) durationPreview.textContent = '--';
        updateAddButtonState();
    }

    /**
     * Crée une ligne de tableau pour une entrée
     */
    function createTableRow(entry) {
        var tr = document.createElement('tr');
        tr.dataset.entryId = String(entry.id);

        var dateDisplay = entry.activity_date_display || '--';
        var timeDisplay = entry.time_range_display || '--';
        var taskLabel = entry.task_label || '--';
        var project = entry.notes || '--';
        var durationDisplay = entry.duration_human || '--';

        tr.innerHTML = [
            '<td class="column-date">' + escapeHtml(dateDisplay) + '</td>',
            '<td class="column-time">' + escapeHtml(timeDisplay) + '</td>',
            '<td class="column-task">' + escapeHtml(taskLabel) + '</td>',
            '<td class="column-project">' + escapeHtml(project || '--') + '</td>',
            '<td class="column-duration">' + escapeHtml(durationDisplay) + '</td>',
            '<td class="column-actions">',
            '<button type="button" class="button-link mj-hours-delete-btn" data-entry-id="' + entry.id + '">',
            escapeHtml(i18n.deleteLabel || 'Supprimer'),
            '</button>',
            '</td>'
        ].join('');

        return tr;
    }

    /**
     * Échappe les caractères HTML
     */
    function escapeHtml(str) {
        if (typeof str !== 'string') {
            str = String(str || '');
        }
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Met à jour le total affiché
     */
    function updateTotal(totalMinutes) {
        if (!totalDisplay) return;
        totalDisplay.textContent = formatDuration(totalMinutes);
    }

    /**
     * Supprime la ligne "aucune heure" si elle existe
     */
    function removeEmptyRow() {
        if (!tbody) return;
        var emptyRow = tbody.querySelector('.mj-hours-empty-row');
        if (emptyRow) {
            emptyRow.remove();
        }
    }

    /**
     * Ajoute une nouvelle entrée d'heures
     */
    function addHours() {
        if (!memberId || memberId <= 0) {
            showFeedback(i18n.errorNoMember || 'Membre non identifié.', 'error');
            return;
        }

        var date = dateInput ? dateInput.value.trim() : '';
        var start = startInput ? startInput.value : '';
        var end = endInput ? endInput.value : '';
        var task = taskInput ? taskInput.value.trim() : '';
        var project = projectInput ? projectInput.value.trim() : '';

        if (!date || !task || !start || !end) {
            showFeedback(i18n.errorMissingFields || 'Veuillez remplir tous les champs obligatoires.', 'error');
            return;
        }

        addBtn.disabled = true;
        addBtn.textContent = i18n.loadingLabel || 'Ajout...';

        var formData = new FormData();
        formData.append('action', 'mj_member_hours_create');
        formData.append('nonce', nonce);
        formData.append('member_id', memberId);
        formData.append('activity_date', date);
        formData.append('start_time', start);
        formData.append('end_time', end);
        formData.append('task_label', task);
        formData.append('notes', project);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            addBtn.textContent = i18n.addLabel || 'Ajouter ces heures';
            
            if (result.success && result.data) {
                removeEmptyRow();
                
                var entry = result.data.entry || result.data;
                var newRow = createTableRow(entry);
                
                if (tbody && tbody.firstChild) {
                    tbody.insertBefore(newRow, tbody.firstChild);
                } else if (tbody) {
                    tbody.appendChild(newRow);
                }

                if (typeof result.data.total_minutes !== 'undefined') {
                    updateTotal(result.data.total_minutes);
                }

                resetForm();
                showFeedback(i18n.successAdded || 'Heures ajoutées avec succès.', 'success');
            } else {
                var message = (result.data && result.data.message) ? result.data.message : (i18n.errorGeneric || 'Une erreur est survenue.');
                showFeedback(message, 'error');
                updateAddButtonState();
            }
        })
        .catch(function(error) {
            addBtn.textContent = i18n.addLabel || 'Ajouter ces heures';
            showFeedback(i18n.errorGeneric || 'Une erreur est survenue.', 'error');
            updateAddButtonState();
            console.error('MJ Member Hours error:', error);
        });
    }

    /**
     * Supprime une entrée d'heures
     */
    function deleteHours(entryId) {
        if (!entryId || entryId <= 0) {
            return;
        }

        var confirmMsg = i18n.confirmDelete || 'Supprimer cette entrée ?';
        if (!window.confirm(confirmMsg)) {
            return;
        }

        var formData = new FormData();
        formData.append('action', 'mj_member_hours_delete');
        formData.append('nonce', nonce);
        formData.append('id', entryId);
        formData.append('member_id', memberId);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result.success) {
                var row = tbody ? tbody.querySelector('tr[data-entry-id="' + entryId + '"]') : null;
                if (row) {
                    row.remove();
                }

                if (typeof result.data.total_minutes !== 'undefined') {
                    updateTotal(result.data.total_minutes);
                }

                // Si plus d'entrées, afficher le message vide
                if (tbody && tbody.children.length === 0) {
                    var emptyRow = document.createElement('tr');
                    emptyRow.className = 'mj-hours-empty-row';
                    emptyRow.innerHTML = '<td colspan="6">' + escapeHtml(i18n.emptyList || 'Aucune heure encodée pour ce membre.') + '</td>';
                    tbody.appendChild(emptyRow);
                }

                showFeedback(i18n.successDeleted || 'Entrée supprimée.', 'success');
            } else {
                var message = (result.data && result.data.message) ? result.data.message : (i18n.errorGeneric || 'Une erreur est survenue.');
                showFeedback(message, 'error');
            }
        })
        .catch(function(error) {
            showFeedback(i18n.errorGeneric || 'Une erreur est survenue.', 'error');
            console.error('MJ Member Hours delete error:', error);
        });
    }

    // Event listeners
    if (startInput) {
        startInput.addEventListener('change', updateDurationPreview);
        startInput.addEventListener('input', updateDurationPreview);
    }

    if (endInput) {
        endInput.addEventListener('change', updateDurationPreview);
        endInput.addEventListener('input', updateDurationPreview);
    }

    if (dateInput) {
        dateInput.addEventListener('change', updateAddButtonState);
    }

    if (taskInput) {
        taskInput.addEventListener('input', updateAddButtonState);
    }

    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addHours();
        });
    }

    // Délégation pour les boutons de suppression
    if (section) {
        section.addEventListener('click', function(e) {
            var target = e.target;
            if (target.classList.contains('mj-hours-delete-btn')) {
                e.preventDefault();
                var entryId = parseInt(target.dataset.entryId, 10);
                if (entryId > 0) {
                    deleteHours(entryId);
                }
            }
        });
    }

    // Initialisation
    updateAddButtonState();

})();
