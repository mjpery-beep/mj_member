/* global jQuery, mjProjectHoursModal */
(function ($) {
    'use strict';

    var $modal = null;
    var $overlay = null;
    var $body = null;
    var $title = null;
    var $loading = null;
    var currentProjectId = 0;

    function init() {
        buildModal();
        $(document).on('click', '.mj-project-hours-detail', onDetailClick);
        $(document).on('change', '.mj-phm-project-select', onProjectChange);
        $(document).on('click', '.mj-phm-task-label', onTaskLabelClick);
        $(document).on('keydown', '.mj-phm-task-input', onTaskInputKey);
        $(document).on('blur', '.mj-phm-task-input', onTaskInputBlur);
    }

    function buildModal() {
        $overlay = $('<div class="mj-phm-overlay"></div>');
        $modal = $(
            '<div class="mj-phm-modal" role="dialog" aria-modal="true">' +
                '<div class="mj-phm-modal__header">' +
                    '<h2 class="mj-phm-modal__title"></h2>' +
                    '<button type="button" class="mj-phm-modal__close" aria-label="' + esc(mjProjectHoursModal.i18n.close) + '">&times;</button>' +
                '</div>' +
                '<div class="mj-phm-modal__body"></div>' +
            '</div>'
        );

        $title = $modal.find('.mj-phm-modal__title');
        $body = $modal.find('.mj-phm-modal__body');
        $loading = $('<p class="mj-phm-modal__loading">' + esc(mjProjectHoursModal.i18n.loading) + '</p>');

        $modal.find('.mj-phm-modal__close').on('click', closeModal);
        $overlay.on('click', closeModal);

        $(document.body).append($overlay).append($modal);
    }

    function onDetailClick(e) {
        e.preventDefault();
        var $btn = $(this);
        var projectId = parseInt($btn.data('project-id'), 10) || 0;
        var projectTitle = $btn.data('project-title') || '';

        currentProjectId = projectId;
        $title.text(projectTitle ? projectTitle + ' — ' + mjProjectHoursModal.i18n.title : mjProjectHoursModal.i18n.title);
        $body.empty().append($loading.clone());
        openModal();

        $.post(mjProjectHoursModal.ajaxUrl, {
            action: 'mj_member_project_hours_list',
            nonce: mjProjectHoursModal.nonce,
            project_id: projectId
        })
        .done(function (response) {
            if (response && response.success && response.data && response.data.entries) {
                renderEntries(response.data.entries);
            } else {
                var msg = (response && response.data && response.data.message) || mjProjectHoursModal.i18n.error;
                $body.html('<p class="mj-phm-modal__error">' + esc(msg) + '</p>');
            }
        })
        .fail(function () {
            $body.html('<p class="mj-phm-modal__error">' + esc(mjProjectHoursModal.i18n.error) + '</p>');
        });
    }

    function buildProjectSelect(entryId, selectedProjectId) {
        var projects = mjProjectHoursModal.projects || [];
        var html = '<select class="mj-phm-project-select" data-entry-id="' + entryId + '" data-original="' + selectedProjectId + '">';
        html += '<option value="0"' + (selectedProjectId === 0 ? ' selected' : '') + '>' + esc(mjProjectHoursModal.i18n.noProject) + '</option>';
        for (var i = 0; i < projects.length; i++) {
            var p = projects[i];
            var sel = (p.id === selectedProjectId) ? ' selected' : '';
            html += '<option value="' + p.id + '"' + sel + '>' + esc(p.title) + '</option>';
        }
        html += '</select>';
        return html;
    }

    function renderEntries(entries) {
        if (!entries.length) {
            $body.html('<p class="mj-phm-modal__empty">' + esc(mjProjectHoursModal.i18n.empty) + '</p>');
            return;
        }

        var html = '<table class="widefat fixed striped mj-phm-table">';
        html += '<thead><tr>';
        html += '<th>' + esc(mjProjectHoursModal.i18n.colDate) + '</th>';
        html += '<th>' + esc(mjProjectHoursModal.i18n.colMember) + '</th>';
        html += '<th>' + esc(mjProjectHoursModal.i18n.colTask) + '</th>';
        html += '<th>' + esc(mjProjectHoursModal.i18n.colDuration) + '</th>';
        html += '<th>' + esc(mjProjectHoursModal.i18n.colNotes) + '</th>';
        html += '<th>' + esc(mjProjectHoursModal.i18n.colProject) + '</th>';
        html += '</tr></thead><tbody>';

        for (var i = 0; i < entries.length; i++) {
            var e = entries[i];
            var pid = parseInt(e.project_id, 10) || 0;
            html += '<tr data-entry-id="' + e.id + '">';
            html += '<td>' + esc(e.activity_date_display || e.activity_date || '') + '</td>';
            html += '<td>' + esc(e.member_label || '') + '</td>';
            html += '<td><span class="mj-phm-task-label" data-entry-id="' + e.id + '" title="' + esc(mjProjectHoursModal.i18n.renameTaskTitle) + '">' + esc(e.task_label || '') + '</span></td>';
            html += '<td>' + esc(e.duration_human || '') + '</td>';
            html += '<td>' + esc(e.notes || '—') + '</td>';
            html += '<td>' + buildProjectSelect(e.id, pid) + '</td>';
            html += '</tr>';
        }

        html += '</tbody></table>';

        $body.html(html);
    }

    function onProjectChange(e) {
        var $select = $(e.target);
        var entryId = parseInt($select.data('entry-id'), 10) || 0;
        var newProjectId = parseInt($select.val(), 10) || 0;
        var originalProjectId = parseInt($select.data('original'), 10) || 0;

        if (entryId <= 0 || newProjectId === originalProjectId) {
            return;
        }

        var $row = $select.closest('tr');
        $select.prop('disabled', true);
        $row.css('opacity', '0.5');

        $.post(mjProjectHoursModal.ajaxUrl, {
            action: 'mj_member_project_hours_reassign',
            nonce: mjProjectHoursModal.nonce,
            entry_id: entryId,
            new_project_id: newProjectId
        })
        .done(function (response) {
            if (response && response.success) {
                // If moved to another project, fade the row out
                if (newProjectId !== currentProjectId) {
                    $row.fadeOut(300, function () {
                        $(this).remove();
                        if ($body.find('.mj-phm-table tbody tr').length === 0) {
                            $body.html('<p class="mj-phm-modal__empty">' + esc(mjProjectHoursModal.i18n.empty) + '</p>');
                        }
                    });
                } else {
                    $select.data('original', newProjectId);
                    $row.css('opacity', '');
                    $select.prop('disabled', false);
                }
            } else {
                var msg = (response && response.data && response.data.message) || mjProjectHoursModal.i18n.reassignError;
                alert(msg);
                $select.val(originalProjectId);
                $row.css('opacity', '');
                $select.prop('disabled', false);
            }
        })
        .fail(function () {
            alert(mjProjectHoursModal.i18n.reassignError);
            $select.val(originalProjectId);
            $row.css('opacity', '');
            $select.prop('disabled', false);
        });
    }

    function onTaskLabelClick(e) {
        var $label = $(e.target);
        if ($label.hasClass('mj-phm-task-label--editing')) {
            return;
        }
        var currentText = $label.text();
        var entryId = $label.data('entry-id');
        $label.addClass('mj-phm-task-label--editing');
        var $input = $('<input type="text" class="mj-phm-task-input" value="' + esc(currentText).replace(/"/g, '&quot;') + '" data-entry-id="' + entryId + '" data-original="' + esc(currentText).replace(/"/g, '&quot;') + '" />');
        $label.empty().append($input);
        $input.focus().select();
    }

    function onTaskInputKey(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $(e.target).blur();
        } else if (e.key === 'Escape' || e.keyCode === 27) {
            e.preventDefault();
            var $input = $(e.target);
            var original = $input.data('original');
            var $label = $input.closest('.mj-phm-task-label');
            $label.removeClass('mj-phm-task-label--editing').text(original);
        }
    }

    function onTaskInputBlur(e) {
        var $input = $(e.target);
        var $label = $input.closest('.mj-phm-task-label');
        if (!$label.length || !$label.hasClass('mj-phm-task-label--editing')) {
            return;
        }

        var newLabel = $.trim($input.val());
        var original = $input.data('original');
        var entryId = parseInt($input.data('entry-id'), 10) || 0;

        // No change or empty → revert
        if (!newLabel || newLabel === original) {
            $label.removeClass('mj-phm-task-label--editing').text(original);
            return;
        }

        $label.removeClass('mj-phm-task-label--editing').text(newLabel).css('opacity', '0.5');

        $.post(mjProjectHoursModal.ajaxUrl, {
            action: 'mj_member_project_hours_rename_task',
            nonce: mjProjectHoursModal.nonce,
            entry_id: entryId,
            new_label: newLabel
        })
        .done(function (response) {
            if (response && response.success) {
                $label.css('opacity', '').text(response.data.task_label || newLabel);
            } else {
                var msg = (response && response.data && response.data.message) || mjProjectHoursModal.i18n.renameTaskError;
                alert(msg);
                $label.css('opacity', '').text(original);
            }
        })
        .fail(function () {
            alert(mjProjectHoursModal.i18n.renameTaskError);
            $label.css('opacity', '').text(original);
        });
    }

    function openModal() {
        $overlay.addClass('mj-phm-overlay--open');
        $modal.addClass('mj-phm-modal--open');

        $(document).on('keydown.mjPhm', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeModal();
            }
        });
    }

    function closeModal() {
        $overlay.removeClass('mj-phm-overlay--open');
        $modal.removeClass('mj-phm-modal--open');
        $(document).off('keydown.mjPhm');
    }

    function esc(str) {
        if (typeof window.escapeHtml === 'function') {
            return window.escapeHtml(str);
        }
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    $(init);
})(jQuery);
