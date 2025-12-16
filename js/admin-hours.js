(function () {
    'use strict';

    var root = document.querySelector('[data-mj-hours]');
    if (!root) {
        return;
    }

    function setRootLoading(isLoading) {
        root.classList.toggle('is-loading', Boolean(isLoading));
    }

    function extractErrorMessage(payload) {
        if (!payload) {
            return '';
        }
        if (payload.data && typeof payload.data.message === 'string' && payload.data.message !== '') {
            return payload.data.message;
        }
        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }
        return '';
    }

    function ensureSuccessResponse(payload) {
        if (!payload || typeof payload !== 'object') {
            var invalid = new Error('invalid_response');
            invalid.payload = payload;
            throw invalid;
        }

        if (!payload.success) {
            var requestError = new Error('request_failed');
            requestError.payload = payload;
            requestError.message = extractErrorMessage(payload) || requestError.message;
            throw requestError;
        }

        return payload.data || {};
    }

    function showError(message, fallback) {
        var fallbackMessage = fallback || (config.i18n && config.i18n.backendError) || 'Une erreur est survenue.';
        window.alert(message || fallbackMessage);
    }

    function showSuccess(message) {
        if (message && message !== '') {
            window.alert(message);
        }
    }

    function updateFiltersUI(filters) {
        if (!filters || typeof filters !== 'object') {
            return;
        }

        if (typeof filters.selectedMemberId !== 'undefined') {
            filtersState.selectedMemberId = parseInt(filters.selectedMemberId, 10);
            if (Number.isNaN(filtersState.selectedMemberId)) {
                filtersState.selectedMemberId = memberId;
            }
        }

        if (typeof filters.selectedProjectKey !== 'undefined') {
            filtersState.selectedProjectKey = String(filters.selectedProjectKey || '');
        }

        if (typeof filters.projectEmptyKey === 'string') {
            projectEmptyKey = filters.projectEmptyKey;
        }

        if (Array.isArray(filters.members) && filterMemberSelect) {
            var previousFocus = document.activeElement === filterMemberSelect;
            var memberOptionsFragment = document.createDocumentFragment();
            filters.members.forEach(function (option) {
                if (!option) {
                    return;
                }
                var opt = document.createElement('option');
                var optionId = typeof option.id !== 'undefined' ? option.id : option.value;
                opt.value = String(optionId);
                opt.textContent = String(option.label || '');
                if (typeof option.total_human === 'string') {
                    opt.dataset.totalHuman = option.total_human;
                }
                if (typeof option.entries !== 'undefined') {
                    opt.dataset.entries = String(option.entries);
                }
                memberOptionsFragment.appendChild(opt);
            });
            filterMemberSelect.innerHTML = '';
            filterMemberSelect.appendChild(memberOptionsFragment);
            filterMemberSelect.value = String(filtersState.selectedMemberId);
            if (previousFocus) {
                filterMemberSelect.focus();
            }
        }

        if (Array.isArray(filters.projects) && filterProjectSelect) {
            var restoreFocus = document.activeElement === filterProjectSelect;
            var projectFragment = document.createDocumentFragment();
            filters.projects.forEach(function (option) {
                if (!option) {
                    return;
                }
                var opt = document.createElement('option');
                var optionKey = typeof option.key === 'string' ? option.key : '';
                opt.value = optionKey;
                var optionLabel = typeof option.label === 'string' ? option.label : '';
                opt.textContent = optionLabel;
                if (typeof option.total_human === 'string') {
                    opt.dataset.totalHuman = option.total_human;
                }
                if (typeof option.entries !== 'undefined') {
                    opt.dataset.entries = String(option.entries);
                }
                var rawProjectLabel = optionKey === projectEmptyKey ? '' : optionLabel;
                opt.dataset.rawLabel = rawProjectLabel;
                projectFragment.appendChild(opt);
            });
            filterProjectSelect.innerHTML = '';
            filterProjectSelect.appendChild(projectFragment);
            filterProjectSelect.value = filtersState.selectedProjectKey;
            if (restoreFocus) {
                filterProjectSelect.focus();
            }
        }
    }

    function updateRecentEntries(entries) {
        if (!recentTableBody) {
            return;
        }

        recentTableBody.innerHTML = '';

        if (!Array.isArray(entries) || !entries.length) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 8;
            emptyCell.className = 'mj-member-hours-table__empty';
            emptyCell.textContent = (config.i18n && config.i18n.emptyState) ? config.i18n.emptyState : '';
            emptyRow.appendChild(emptyCell);
            recentTableBody.appendChild(emptyRow);
            return;
        }

        var buttonLabel = (config.i18n && config.i18n.renameAction) ? config.i18n.renameAction : 'Renommer';

        entries.forEach(function (entry) {
            if (!entry) {
                return;
            }

            var projectRaw = typeof entry.notes === 'string' ? entry.notes : '';
            var projectKey = projectRaw !== '' ? projectRaw : projectEmptyKey;
            var projectDisplay = projectRaw !== '' ? projectRaw : projectWithoutLabel;
            var taskLabel = typeof entry.task_label === 'string' ? entry.task_label : '';
            var entryId = typeof entry.id !== 'undefined' ? parseInt(entry.id, 10) : 0;
            var memberIdValue = typeof entry.member_id !== 'undefined' ? parseInt(entry.member_id, 10) : 0;

            var row = document.createElement('tr');
            row.dataset.entryId = String(entryId);
            row.dataset.memberId = String(memberIdValue);
            row.dataset.taskLabel = taskLabel;
            row.dataset.projectKey = projectKey;
            row.dataset.projectLabel = projectRaw;

            function cell(text) {
                var td = document.createElement('td');
                td.textContent = text || '';
                return td;
            }

            row.appendChild(cell(entry.member_label || ''));
            row.appendChild(cell(entry.activity_date_display || ''));
            row.appendChild(cell(entry.time_range_display || '--'));

            var taskCell = document.createElement('td');
            var taskSpan = document.createElement('span');
            taskSpan.className = 'mj-member-hours__label';
            taskSpan.dataset.role = 'task-label';
            taskSpan.dataset.taskLabel = taskLabel;
            taskSpan.textContent = taskLabel !== '' ? taskLabel : '--';
            taskCell.appendChild(taskSpan);
            if (taskLabel !== '') {
                var taskBtn = document.createElement('button');
                taskBtn.type = 'button';
                taskBtn.className = 'button-link mj-member-hours__rename-button';
                taskBtn.dataset.role = 'rename-task';
                taskBtn.dataset.taskLabel = taskLabel;
                taskBtn.dataset.memberId = String(memberIdValue);
                taskBtn.textContent = buttonLabel;
                taskCell.appendChild(taskBtn);
            }
            row.appendChild(taskCell);

            row.appendChild(cell(entry.duration_human || ''));

            var projectCell = document.createElement('td');
            var projectSpan = document.createElement('span');
            projectSpan.className = 'mj-member-hours__label';
            projectSpan.dataset.role = 'project-label';
            projectSpan.dataset.projectKey = projectKey;
            projectSpan.dataset.projectLabel = projectRaw;
            projectSpan.textContent = projectDisplay;
            projectCell.appendChild(projectSpan);

            var projectBtn = document.createElement('button');
            projectBtn.type = 'button';
            projectBtn.className = 'button-link mj-member-hours__rename-button';
            projectBtn.dataset.role = 'rename-project';
            projectBtn.dataset.projectKey = projectKey;
            projectBtn.dataset.projectLabel = projectRaw;
            projectBtn.dataset.memberId = String(memberIdValue);
            projectBtn.textContent = buttonLabel;
            projectCell.appendChild(projectBtn);
            row.appendChild(projectCell);

            row.appendChild(cell(entry.recorded_by_label || ''));
            row.appendChild(cell(entry.created_at_display || ''));

            recentTableBody.appendChild(row);
        });
    }

    function updateProjectSummary(summary) {
        if (!projectSummaryBody) {
            return;
        }

        projectSummaryBody.innerHTML = '';

        if (!Array.isArray(summary) || !summary.length) {
            if (projectSummaryEmpty) {
                projectSummaryEmpty.removeAttribute('hidden');
            }
            if (projectSummaryTable) {
                projectSummaryTable.setAttribute('hidden', 'hidden');
            }
            return;
        }

        if (projectSummaryEmpty) {
            projectSummaryEmpty.setAttribute('hidden', 'hidden');
        }
        if (projectSummaryTable) {
            projectSummaryTable.removeAttribute('hidden');
        }

        var buttonLabel = (config.i18n && config.i18n.renameAction) ? config.i18n.renameAction : 'Renommer';

        summary.forEach(function (item) {
            if (!item) {
                return;
            }
            var projectKey = typeof item.key === 'string' ? item.key : '';
            var displayLabel = typeof item.label === 'string' && item.label !== '' ? item.label : projectWithoutLabel;
            var rawLabel = projectKey === projectEmptyKey ? '' : displayLabel;
            var entriesCount = typeof item.entries !== 'undefined' ? item.entries : '';
            var totalHuman = typeof item.total_human === 'string' ? item.total_human : '';

            var tr = document.createElement('tr');
            tr.dataset.projectKey = projectKey;

            var labelCell = document.createElement('td');
            var labelSpan = document.createElement('span');
            labelSpan.className = 'mj-member-hours__label';
            labelSpan.dataset.role = 'project-summary-label';
            labelSpan.dataset.projectLabel = rawLabel;
            labelSpan.textContent = displayLabel;
            labelCell.appendChild(labelSpan);

            var renameBtn = document.createElement('button');
            renameBtn.type = 'button';
            renameBtn.className = 'button-link mj-member-hours__rename-button';
            renameBtn.dataset.role = 'rename-project';
            renameBtn.dataset.projectKey = projectKey;
            renameBtn.dataset.projectLabel = rawLabel;
            renameBtn.dataset.memberId = String(filtersState.selectedMemberId || 0);
            renameBtn.textContent = buttonLabel;
            labelCell.appendChild(renameBtn);

            tr.appendChild(labelCell);

            var totalCell = document.createElement('td');
            totalCell.dataset.role = 'project-summary-total';
            totalCell.textContent = totalHuman;
            tr.appendChild(totalCell);

            var entriesCell = document.createElement('td');
            entriesCell.dataset.role = 'project-summary-entries';
            entriesCell.textContent = String(entriesCount);
            tr.appendChild(entriesCell);

            projectSummaryBody.appendChild(tr);
        });
    }

    function applyState(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        if (data.filters) {
            updateFiltersUI(data.filters);
        }

        if (Array.isArray(data.recentEntries)) {
            recentEntries = data.recentEntries.slice(0);
            updateRecentEntries(recentEntries);
        }

        if (Array.isArray(data.weeklySummary)) {
            renderWeeklySummary(data.weeklySummary);
        }

        if (Array.isArray(data.projectSummary)) {
            updateProjectSummary(data.projectSummary);
        }

        if (typeof data.projectWithoutLabel === 'string') {
            projectWithoutLabel = data.projectWithoutLabel;
        }

        var hasCalendar = Boolean(data.hasCalendar);
        toggleCalendar(hasCalendar);
        if (hasCalendar && data.calendar) {
            renderCalendar(data.calendar, filtersState.selectedMemberId);
            setCalendarLoading(false);
        } else {
            setCalendarLoading(false);
            clearCalendarUI();
        }

        if (!config.filters) {
            config.filters = {};
        }
        config.filters.selectedMemberId = filtersState.selectedMemberId;
        config.filters.selectedProjectKey = filtersState.selectedProjectKey;
        config.filters.projectEmptyKey = projectEmptyKey;
        config.filters.canManageOthers = canManageOthers;

        config.recentEntries = recentEntries.slice(0);
        config.weeklySummary = Array.isArray(data.weeklySummary) ? data.weeklySummary.slice(0) : [];
        config.projectSummary = Array.isArray(data.projectSummary) ? data.projectSummary.slice(0) : [];
        config.projectWithoutLabel = projectWithoutLabel;
        config.calendar = data.calendar || {};
        config.hasCalendar = hasCalendar;
    }

    function requestState(extra) {
        var actions = config.ajaxActions || {};
        var action = typeof actions.list === 'string' ? actions.list : 'mj_member_hours_list';
        if (!action) {
            return Promise.reject(new Error('missing_action'));
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', config.nonce || '');
        formData.append('member_id', String(filtersState.selectedMemberId));
        formData.append('project', filtersState.selectedProjectKey || '');
        if (extra && typeof extra.calendarMonth === 'string' && extra.calendarMonth !== '') {
            formData.append('calendar_month', extra.calendarMonth);
        }
        formData.append('include_calendar', filtersState.selectedMemberId > 0 ? '1' : '0');

        return postJson(formData);
    }

    function refreshState() {
        setRootLoading(true);
        if (filtersState.selectedMemberId > 0) {
            setCalendarLoading(true);
        } else {
            toggleCalendar(false);
            clearCalendarUI();
        }

        var monthKey = '';
        if (filtersState.selectedMemberId > 0 && typeof calendarState.currentMonth === 'string' && calendarState.currentMonth !== '') {
            monthKey = calendarState.currentMonth;
        }

        return requestState(monthKey ? { calendarMonth: monthKey } : null)
            .then(function (payload) {
                var data = ensureSuccessResponse(payload);
                applyState(data);
            })
            .catch(function (error) {
                var message = '';
                if (error && error.payload) {
                    message = extractErrorMessage(error.payload);
                } else if (error && error.message && error.message !== 'request_failed') {
                    message = error.message;
                }
                showError(message);
            })
            .finally(function () {
                setRootLoading(false);
                setCalendarLoading(false);
            });
    }

    function labelsEqual(a, b) {
        if (typeof a !== 'string' || typeof b !== 'string') {
            return false;
        }
        return a.trim().toLowerCase() === b.trim().toLowerCase();
    }

    function promptLabel(type, currentLabel) {
        var promptKey = type === 'project' ? 'renameProjectPrompt' : 'renameTaskPrompt';
        var i18n = config.i18n || {};
        var promptText = i18n[promptKey] || 'Nouveau libellé';
        var defaultValue = typeof currentLabel === 'string' ? currentLabel : '';
        var result = window.prompt(promptText, defaultValue);
        if (result === null) {
            return null;
        }
        result = result.trim();
        if (result === '') {
            showError(i18n.renameError);
            return null;
        }
        return result;
    }

    function requestRenameTask(memberIdValue, oldLabel, newLabel) {
        var actions = config.ajaxActions || {};
        var action = typeof actions.renameTask === 'string' ? actions.renameTask : 'mj_member_hours_rename_task';
        if (!action) {
            return Promise.reject(new Error('missing_action'));
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', config.nonce || '');
        formData.append('old_label', oldLabel);
        formData.append('new_label', newLabel);
        if (memberIdValue > 0) {
            formData.append('member_id', String(memberIdValue));
        }

        return postJson(formData).then(ensureSuccessResponse);
    }

    function requestRenameProject(memberIdValue, projectKey, oldLabel, newLabel) {
        var actions = config.ajaxActions || {};
        var action = typeof actions.renameProject === 'string' ? actions.renameProject : 'mj_member_hours_rename_project';
        if (!action) {
            return Promise.reject(new Error('missing_action'));
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', config.nonce || '');
        formData.append('project_key', projectKey || '');
        formData.append('old_label', oldLabel);
        formData.append('new_label', newLabel);
        if (memberIdValue > 0) {
            formData.append('member_id', String(memberIdValue));
        }

        return postJson(formData).then(ensureSuccessResponse);
    }

    function handleRenameTask(button) {
        var dataset = button.dataset || {};
        var oldLabel = typeof dataset.taskLabel === 'string' ? dataset.taskLabel.trim() : '';
        if (oldLabel === '') {
            return;
        }

        var memberIdValue = parseInt(dataset.memberId || '', 10);
        if (Number.isNaN(memberIdValue)) {
            memberIdValue = 0;
        }

        var newLabel = promptLabel('task', oldLabel);
        if (newLabel === null || labelsEqual(oldLabel, newLabel)) {
            return;
        }

        var renameSuccessMessage = (config.i18n && config.i18n.renameSuccess) ? config.i18n.renameSuccess : 'Libellé mis à jour.';
        var renameErrorMessage = (config.i18n && config.i18n.renameError) ? config.i18n.renameError : '';

        setRootLoading(true);

        requestRenameTask(memberIdValue, oldLabel, newLabel)
            .then(function (data) {
                return refreshState().then(function () {
                    if (typeof data.updated === 'number' && data.updated > 0) {
                        showSuccess(renameSuccessMessage);
                    }
                });
            })
            .catch(function (error) {
                var message = '';
                if (error && error.payload) {
                    message = extractErrorMessage(error.payload);
                } else if (error && typeof error.message === 'string') {
                    message = error.message;
                }
                showError(message, renameErrorMessage);
            })
            .finally(function () {
                setRootLoading(false);
            });
    }

    function handleRenameProject(button) {
        var dataset = button.dataset || {};
        var projectKey = typeof dataset.projectKey === 'string' ? dataset.projectKey : '';
        var rawLabel = typeof dataset.projectLabel === 'string' ? dataset.projectLabel : '';
        if (projectKey === projectEmptyKey) {
            rawLabel = '';
        }

        var memberIdValue = parseInt(dataset.memberId || '', 10);
        if (Number.isNaN(memberIdValue)) {
            memberIdValue = 0;
        }

        var newLabel = promptLabel('project', rawLabel);
        if (newLabel === null || labelsEqual(rawLabel, newLabel)) {
            return;
        }

        var previousKey = projectKey;
        var renameSuccessMessage = (config.i18n && config.i18n.renameSuccess) ? config.i18n.renameSuccess : 'Libellé mis à jour.';
        var renameErrorMessage = (config.i18n && config.i18n.renameError) ? config.i18n.renameError : '';

        setRootLoading(true);

        requestRenameProject(memberIdValue, projectKey, rawLabel, newLabel)
            .then(function (data) {
                if (filtersState.selectedProjectKey === previousKey && newLabel !== '') {
                    filtersState.selectedProjectKey = newLabel;
                }

                return refreshState().then(function () {
                    if (typeof data.updated === 'number' && data.updated > 0) {
                        showSuccess(renameSuccessMessage);
                    }
                });
            })
            .catch(function (error) {
                var message = '';
                if (error && error.payload) {
                    message = extractErrorMessage(error.payload);
                } else if (error && typeof error.message === 'string') {
                    message = error.message;
                }
                showError(message, renameErrorMessage);
            })
            .finally(function () {
                setRootLoading(false);
            });
    }
    function decodeHtmlEntities(raw) {
        if (!raw || raw.indexOf('&') === -1) {
            return raw || '';
        }

        var textarea = document.createElement('textarea');
        textarea.innerHTML = raw;
        return textarea.value;
    }

    var configRaw = root.getAttribute('data-config') || root.dataset.config || '{}';
    configRaw = decodeHtmlEntities(configRaw);

    var config;
    try {
        config = JSON.parse(configRaw);
    } catch (parseError) {
        try {
            config = JSON.parse(decodeHtmlEntities(configRaw));
        } catch (secondError) {
            config = {};
        }
    }

    if (!config || typeof config !== 'object') {
        config = {};
    }

    var isReadOnly = true;

    var form = root.querySelector('.mj-member-hours-form');
    if (form) {
        form.parentNode.removeChild(form);
    }

    var memberConfig = config.member || {};
    var memberId = parseInt(memberConfig.id, 10) || 0;
    var memberLabel = String(memberConfig.label || '');

    var memberIdInput = null;
    var memberLabelElm = null;
    var taskInput = null;
    var dateInput = null;
    var durationInput = null;
    var notesInput = null;
    var statusElm = null;
    var recentTableBody = root.querySelector('[data-role="recent-entries"]');
    var taskSuggestionsElm = null;
    var durationDisplay = null;
    var scheduleButtons = [];
    var weeklySummaryBody = root.querySelector('[data-role="weekly-summary"]');
    var weeklySummaryEmpty = root.querySelector('[data-role="weekly-summary-empty"]');
    var weeklySummaryTable = weeklySummaryBody ? weeklySummaryBody.closest('table') : null;
    var calendarRoot = root.querySelector('[data-role="hours-calendar"]');
    var calendarMonthLabel = calendarRoot ? calendarRoot.querySelector('[data-role="calendar-month-label"]') : null;
    var calendarPrevBtn = calendarRoot ? calendarRoot.querySelector('[data-role="calendar-prev"]') : null;
    var calendarNextBtn = calendarRoot ? calendarRoot.querySelector('[data-role="calendar-next"]') : null;
    var calendarWeekdaysRow = calendarRoot ? calendarRoot.querySelector('[data-role="calendar-weekdays"]') : null;
    var calendarBody = calendarRoot ? calendarRoot.querySelector('[data-role="calendar-body"]') : null;
    var calendarLoadingElm = calendarRoot ? calendarRoot.querySelector('[data-role="calendar-loading"]') : null;
    var calendarPlaceholder = root.querySelector('[data-role="calendar-placeholder"]');
    var filterMemberSelect = root.querySelector('[data-role="filter-member"]');
    var filterProjectSelect = root.querySelector('[data-role="filter-project"]');
    var projectSummaryBody = root.querySelector('[data-role="project-summary"]');
    var projectSummaryEmpty = root.querySelector('[data-role="project-summary-empty"]');
    var projectSummaryTable = projectSummaryBody ? projectSummaryBody.closest('table') : null;

    var tasks = Array.isArray(config.tasks) ? config.tasks : [];
    var recentEntries = Array.isArray(config.recentEntries) ? config.recentEntries.slice(0) : [];
    var schedulePresets = Array.isArray(config.schedulePresets) ? config.schedulePresets : [];
    var filtersConfig = config.filters || {};
    var canManageOthers = Boolean(filtersConfig.canManageOthers);
    var selectedMemberId = parseInt(filtersConfig.selectedMemberId, 10);
    if (Number.isNaN(selectedMemberId)) {
        selectedMemberId = memberId;
    }
    var selectedProjectKey = typeof filtersConfig.selectedProjectKey === 'string' ? filtersConfig.selectedProjectKey : '';
    var projectEmptyKey = typeof filtersConfig.projectEmptyKey === 'string' ? filtersConfig.projectEmptyKey : '';
    var projectWithoutLabel = typeof config.projectWithoutLabel === 'string' ? config.projectWithoutLabel : '';

    var filtersState = {
        selectedMemberId: selectedMemberId,
        selectedProjectKey: selectedProjectKey,
    };

    var calendarState = {
        currentMonth: calendarRoot ? (calendarRoot.getAttribute('data-month-key') || '') : '',
        cache: {},
        member: filtersState.selectedMemberId,
    };

    var defaultDuration = config.defaultDuration || {};
    var defaultDurationMinutes = parseInt(defaultDuration.minutes, 10) || 0;

    if (config.calendar && config.calendar.month_key) {
        calendarState.currentMonth = String(config.calendar.month_key);
        var initialCalendarStore = getCalendarCacheForMember(filtersState.selectedMemberId);
        initialCalendarStore[calendarState.currentMonth] = config.calendar;
        if (calendarRoot) {
            calendarRoot.setAttribute('data-month-key', calendarState.currentMonth);
        }
    }
    if (durationInput && !durationInput.value && defaultDurationMinutes > 0) {
        durationInput.value = String(defaultDurationMinutes);
    }

    var taskState = {
        items: tasks.map(function (task) {
            return {
                label: String(task || ''),
                search: normalize(String(task || '')),
            };
        }),
        filtered: [],
        index: -1,
    };

    function getAjaxUrl() {
        if (config && typeof config.ajaxUrl === 'string' && config.ajaxUrl !== '') {
            return config.ajaxUrl;
        }
        if (typeof window.ajaxurl === 'string' && window.ajaxurl !== '') {
            return window.ajaxurl;
        }
        return '';
    }

    function postJson(formData) {
        var url = getAjaxUrl();
        if (!url) {
            return Promise.reject(new Error('missing_ajax_url'));
        }

        if (typeof window.fetch === 'function') {
            return window.fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            }).then(function (response) {
                if (!response.ok) {
                    var fetchError = new Error('http_error');
                    fetchError.status = response.status;
                    throw fetchError;
                }
                return response.json();
            });
        }

        if (typeof window.jQuery !== 'function' || !window.jQuery.ajax) {
            return Promise.reject(new Error('no_transport'));
        }

        return new Promise(function (resolve, reject) {
            window.jQuery.ajax({
                url: url,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                xhrFields: {
                    withCredentials: true,
                },
                success: function (data) {
                    resolve(data);
                },
                error: function (jqXHR) {
                    var xhrError = new Error('http_error');
                    if (jqXHR && typeof jqXHR.status !== 'undefined') {
                        xhrError.status = jqXHR.status;
                    }
                    reject(xhrError);
                },
            });
        });
    }

    function normalize(str) {
        if (!str) {
            return '';
        }
        str = str.toLowerCase();
        if (typeof str.normalize === 'function') {
            str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return str;
    }

    function clearStatus() {
        if (statusElm) {
            statusElm.textContent = '';
        }
    }

    function setStatus(message, isError) {
        if (!statusElm) {
            return;
        }
        statusElm.textContent = message || '';
        statusElm.classList.toggle('mj-member-hours-form__status--error', Boolean(isError));
    }

    function setFieldError() {
        return;
    }

    function clearErrors() {
        return;
    }

    function hideSuggestions(box) {
        if (!box) {
            return;
        }
        box.innerHTML = '';
        box.setAttribute('hidden', 'hidden');
    }

    function renderSuggestions(state, box, formatter) {
        if (!box) {
            return;
        }

        hideSuggestions(box);

        if (!state.filtered.length) {
            return;
        }

        var list = document.createElement('ul');
        list.className = 'mj-member-hours-suggestions__list';

        state.filtered.slice(0, 8).forEach(function (item, idx) {
            var li = document.createElement('li');
            li.className = 'mj-member-hours-suggestions__item';

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'mj-member-hours-suggestions__button';
            button.textContent = formatter(item);
            button.dataset.index = String(idx);

            if (idx === state.index) {
                button.classList.add('is-active');
            }

            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                state.index = idx;
                box.dispatchEvent(new CustomEvent('suggestion-select', {
                    bubbles: true,
                    detail: item,
                }));
            });

            li.appendChild(button);
            list.appendChild(li);
        });

        box.appendChild(list);
        box.removeAttribute('hidden');
    }

    function filterTasks(query) {
        var term = normalize(query);
        taskState.index = -1;
        if (!term) {
            taskState.filtered = taskState.items.slice(0, 8);
        } else {
            taskState.filtered = taskState.items.filter(function (item) {
                return item.search.indexOf(term) !== -1;
            });
        }
        renderSuggestions(taskState, taskSuggestionsElm, function (item) {
            return item.label;
        });
    }

    function selectTask(item) {
        if (!item) {
            return;
        }
        taskInput.value = item.label;
        hideSuggestions(taskSuggestionsElm);
    }

    if (!isReadOnly && taskSuggestionsElm) {
        taskSuggestionsElm.addEventListener('suggestion-select', function (event) {
            selectTask(event.detail);
            if (dateInput) {
                dateInput.focus();
            }
        });
    }

    if (!isReadOnly && taskInput) {
        taskInput.addEventListener('focus', function () {
            filterTasks(taskInput.value);
        });

        taskInput.addEventListener('input', function () {
            filterTasks(taskInput.value);
        });

        taskInput.addEventListener('keydown', function (event) {
            if (!taskState.filtered.length) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'Down') {
                event.preventDefault();
                taskState.index = (taskState.index + 1) % taskState.filtered.length;
                renderSuggestions(taskState, taskSuggestionsElm, function (item) {
                    return item.label;
                });
            } else if (event.key === 'ArrowUp' || event.key === 'Up') {
                event.preventDefault();
                taskState.index = taskState.index <= 0 ? taskState.filtered.length - 1 : taskState.index - 1;
                renderSuggestions(taskState, taskSuggestionsElm, function (item) {
                    return item.label;
                });
            } else if (event.key === 'Enter') {
                if (taskState.index >= 0 && taskState.index < taskState.filtered.length) {
                    event.preventDefault();
                    selectTask(taskState.filtered[taskState.index]);
                }
            } else if (event.key === 'Escape') {
                hideSuggestions(taskSuggestionsElm);
            }
        });

        taskInput.addEventListener('blur', function () {
            setTimeout(function () {
                hideSuggestions(taskSuggestionsElm);
            }, 100);
        });
    }

    function formatMinutesHuman(minutes) {
        minutes = parseInt(minutes, 10) || 0;
        if (minutes <= 0) {
            return config.i18n && config.i18n.durationUnknown ? config.i18n.durationUnknown : '';
        }

        var hours = Math.floor(minutes / 60);
        var rest = minutes % 60;
        var parts = [];

        if (hours > 0) {
            parts.push(hours + ' h');
        }
        if (rest > 0) {
            parts.push(rest + ' min');
        }

        return parts.join(' ');
    }

    function applyDurationLabel(minutes) {
        if (!durationDisplay) {
            return;
        }

        if (minutes > 0) {
            var label = formatMinutesHuman(minutes);
            var pattern = config.i18n && config.i18n.durationComputedLabel ? config.i18n.durationComputedLabel : 'Durée estimée : %s';
            durationDisplay.textContent = pattern.indexOf('%s') !== -1 ? pattern.replace('%s', label) : pattern + ' ' + label;
        } else {
            durationDisplay.textContent = config.i18n && config.i18n.durationUnknown ? config.i18n.durationUnknown : '';
        }
    }

    function getDurationMinutes() {
        if (!durationInput) {
            return 0;
        }

        var raw = parseInt(durationInput.value, 10);
        if (Number.isNaN(raw) || raw <= 0) {
            return 0;
        }

        return raw;
    }

    function refreshDurationDisplay() {
        var minutes = getDurationMinutes();
        applyDurationLabel(minutes);
        return minutes;
    }

    if (!isReadOnly && durationInput) {
        durationInput.addEventListener('change', refreshDurationDisplay);
        durationInput.addEventListener('input', refreshDurationDisplay);
    }

    if (!isReadOnly && scheduleButtons && typeof scheduleButtons.length === 'number') {
        Array.prototype.forEach.call(scheduleButtons, function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var durationValue = parseInt(button.getAttribute('data-schedule-duration') || '0', 10);

                if (!Number.isNaN(durationValue) && durationValue > 0 && durationInput) {
                    durationInput.value = String(durationValue);
                    refreshDurationDisplay();
                    if (notesInput) {
                        notesInput.focus();
                    }
                }
            });
        });
    }

    function slugify(text) {
        var normalized = normalize(text);
        normalized = normalized.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (!normalized) {
            return '';
        }
        return normalized.substring(0, 80);
    }

    function validate() {
        return false;
    }

    function formatEntry(entry) {
        var row = document.createElement('tr');
        row.dataset.entryId = String(entry.id);

        function cell(text) {
            var td = document.createElement('td');
            td.textContent = text || '';
            return td;
        }

        row.appendChild(cell(entry.member_label || ''));
        row.appendChild(cell(entry.activity_date_display || ''));
        row.appendChild(cell(entry.time_range_display || '--'));
        row.appendChild(cell(entry.task_label || ''));
        row.appendChild(cell(entry.duration_human || ''));
        row.appendChild(cell(entry.notes || ''));
        row.appendChild(cell(entry.recorded_by_label || ''));
        row.appendChild(cell(entry.created_at_display || ''));

        return row;
    }

    function prependEntry(entry) {
        if (!recentTableBody) {
            return;
        }

        return;
    }

    function renderWeeklySummary(rows) {
        if (!weeklySummaryBody) {
            return;
        }

        weeklySummaryBody.innerHTML = '';

        if (!rows || !rows.length) {
            if (weeklySummaryEmpty) {
                weeklySummaryEmpty.removeAttribute('hidden');
            }
            if (weeklySummaryTable) {
                weeklySummaryTable.setAttribute('hidden', 'hidden');
            }
            return;
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.dataset.weekKey = String(row.week_key || '');

            var weekCell = document.createElement('td');
            weekCell.textContent = row.week_label || '';
            tr.appendChild(weekCell);

            var durationCell = document.createElement('td');
            durationCell.textContent = row.duration_human || '';
            tr.appendChild(durationCell);

            weeklySummaryBody.appendChild(tr);
        });

        if (weeklySummaryEmpty) {
            weeklySummaryEmpty.setAttribute('hidden', 'hidden');
        }
        if (weeklySummaryTable) {
            weeklySummaryTable.removeAttribute('hidden');
        }
    }

    function getCalendarCacheForMember(memberId) {
        var memberKey = String(memberId || 0);
        if (!calendarState.cache[memberKey]) {
            calendarState.cache[memberKey] = {};
        }
        return calendarState.cache[memberKey];
    }

    function cacheCalendar(calendar, memberId) {
        if (!calendar || typeof calendar !== 'object') {
            return;
        }
        var key = String(calendar.month_key || calendar.monthKey || '');
        if (!key) {
            return;
        }
        var store = getCalendarCacheForMember(memberId);
        store[key] = calendar;
    }

    function setCalendarLoading(isLoading) {
        if (!calendarRoot) {
            return;
        }

        calendarRoot.classList.toggle('is-loading', Boolean(isLoading));

        if (calendarLoadingElm) {
            if (isLoading) {
                calendarLoadingElm.removeAttribute('hidden');
                calendarLoadingElm.style.display = 'flex';
            } else {
                calendarLoadingElm.setAttribute('hidden', 'hidden');
                calendarLoadingElm.style.display = 'none';
            }
        }

        var prevTarget = calendarPrevBtn ? calendarPrevBtn.getAttribute('data-target-month') : '';
        var nextTarget = calendarNextBtn ? calendarNextBtn.getAttribute('data-target-month') : '';

        if (calendarPrevBtn) {
            calendarPrevBtn.disabled = Boolean(isLoading) || !prevTarget;
        }
        if (calendarNextBtn) {
            calendarNextBtn.disabled = Boolean(isLoading) || !nextTarget;
        }
    }

    function clearCalendarUI() {
        if (!calendarRoot) {
            return;
        }
        if (calendarMonthLabel) {
            calendarMonthLabel.textContent = '';
        }
        if (calendarPrevBtn) {
            calendarPrevBtn.setAttribute('data-target-month', '');
            calendarPrevBtn.disabled = true;
        }
        if (calendarNextBtn) {
            calendarNextBtn.setAttribute('data-target-month', '');
            calendarNextBtn.disabled = true;
        }
        if (calendarWeekdaysRow) {
            calendarWeekdaysRow.innerHTML = '';
        }
        if (calendarBody) {
            calendarBody.innerHTML = '';
        }
    }

    function toggleCalendar(hasCalendar) {
        if (!calendarRoot) {
            return;
        }
        if (hasCalendar) {
            calendarRoot.removeAttribute('hidden');
            if (calendarPlaceholder) {
                calendarPlaceholder.setAttribute('hidden', 'hidden');
            }
        } else {
            calendarRoot.setAttribute('hidden', 'hidden');
            if (calendarPlaceholder) {
                calendarPlaceholder.removeAttribute('hidden');
            }
        }
    }

    function renderCalendar(calendar, memberId) {
        if (!calendarRoot || !calendar) {
            return;
        }

        var effectiveMemberId = typeof memberId === 'number' ? memberId : filtersState.selectedMemberId;
        calendarState.member = effectiveMemberId;
        cacheCalendar(calendar, effectiveMemberId);

        toggleCalendar(true);

        var monthKey = String(calendar.month_key || calendar.monthKey || '');
        if (monthKey) {
            calendarState.currentMonth = monthKey;
            calendarRoot.setAttribute('data-month-key', monthKey);
        }

        if (calendarMonthLabel) {
            calendarMonthLabel.textContent = String(calendar.month_label || calendar.monthLabel || '');
        }

        var navigation = calendar.navigation || {};
        var prevKey = navigation.previous ? String(navigation.previous) : '';
        var nextKey = navigation.next ? String(navigation.next) : '';

        if (calendarPrevBtn) {
            calendarPrevBtn.setAttribute('data-target-month', prevKey);
            calendarPrevBtn.disabled = !prevKey;
        }
        if (calendarNextBtn) {
            calendarNextBtn.setAttribute('data-target-month', nextKey);
            calendarNextBtn.disabled = !nextKey;
        }

        if (calendarWeekdaysRow) {
            calendarWeekdaysRow.innerHTML = '';
            var weekdays = Array.isArray(calendar.weekdays) ? calendar.weekdays : [];
            weekdays.forEach(function (weekday) {
                var th = document.createElement('th');
                th.scope = 'col';
                th.textContent = weekday.short_label || weekday.label || '';
                calendarWeekdaysRow.appendChild(th);
            });
        }

        if (calendarBody) {
            calendarBody.innerHTML = '';
            var weeks = Array.isArray(calendar.weeks) ? calendar.weeks : [];

            weeks.forEach(function (week) {
                var tr = document.createElement('tr');
                tr.dataset.weekKey = String(week.week_key || '');

                var days = Array.isArray(week.days) ? week.days : [];
                days.forEach(function (day) {
                    var td = document.createElement('td');
                    var classes = ['mj-member-hours-calendar__day'];
                    if (!day.is_current_month) {
                        classes.push('is-outside');
                    }
                    if (day.is_today) {
                        classes.push('is-today');
                    }
                    td.className = classes.join(' ');
                    td.dataset.date = day.date ? String(day.date) : '';

                    var dateElm = document.createElement('div');
                    dateElm.className = 'mj-member-hours-calendar__date';
                    dateElm.textContent = day.day_label ? String(day.day_label) : String(day.day_number || '');
                    td.appendChild(dateElm);

                    var entries = Array.isArray(day.entries) ? day.entries : [];
                    if (entries.length) {
                        var list = document.createElement('ul');
                        list.className = 'mj-member-hours-calendar__entries';

                        entries.forEach(function (entry) {
                            var li = document.createElement('li');
                            li.className = 'mj-member-hours-calendar__entry';

                            var pieces = [];
                            if (entry.time_range_display) {
                                pieces.push(String(entry.time_range_display));
                            } else {
                                var timeParts = [];
                                if (entry.start_time_display) {
                                    timeParts.push(String(entry.start_time_display));
                                }
                                if (entry.end_time_display) {
                                    timeParts.push(String(entry.end_time_display));
                                }
                                if (!timeParts.length && entry.start_time && entry.end_time) {
                                    timeParts.push(String(entry.start_time) + ' - ' + String(entry.end_time));
                                }
                                if (timeParts.length) {
                                    pieces.push(timeParts.join(' - '));
                                }
                            }

                            if (entry.task_label) {
                                pieces.push(String(entry.task_label));
                            }

                            if (!pieces.length && entry.duration_human) {
                                pieces.push(String(entry.duration_human));
                            }

                            li.textContent = pieces.join(' • ');
                            if (entry.notes) {
                                li.title = String(entry.notes);
                            }

                            list.appendChild(li);
                        });

                        td.appendChild(list);

                        if (day.total_human) {
                            var total = document.createElement('div');
                            total.className = 'mj-member-hours-calendar__total';
                            var pattern = config.i18n && config.i18n.calendarDayTotal ? config.i18n.calendarDayTotal : 'Total : %s';
                            total.textContent = pattern.indexOf('%s') !== -1 ? pattern.replace('%s', String(day.total_human)) : pattern + ' ' + String(day.total_human);
                            td.appendChild(total);
                        }
                    }

                    tr.appendChild(td);
                });

                calendarBody.appendChild(tr);
            });
        }
    }

    function fetchCalendar(monthKey, memberIdForCalendar) {
        if (!calendarRoot || !monthKey) {
            return;
        }

        var targetMemberId = typeof memberIdForCalendar === 'number' ? memberIdForCalendar : filtersState.selectedMemberId;
        if (targetMemberId <= 0) {
            return;
        }

        clearStatus();

        var cache = getCalendarCacheForMember(targetMemberId);
        if (cache[monthKey]) {
            renderCalendar(cache[monthKey], targetMemberId);
            setCalendarLoading(false);
            return;
        }

        setCalendarLoading(true);

        var formData = new FormData();
        formData.append('action', config.calendarAjaxAction || 'mj_member_hours_calendar');
        formData.append('nonce', config.nonce || '');
        formData.append('month', monthKey);
        formData.append('member_id', String(targetMemberId));

        calendarState.member = targetMemberId;

        postJson(formData)
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data || !payload.data.calendar) {
                    var calendarError = new Error('calendar_error');
                    calendarError.payload = payload;
                    throw calendarError;
                }
                renderCalendar(payload.data.calendar, targetMemberId);
                setCalendarLoading(false);
            })
            .catch(function (error) {
                setCalendarLoading(false);
                if (config.i18n && config.i18n.calendarError) {
                    var message = config.i18n.calendarError;
                    if (error && error.payload && error.payload.data && error.payload.data.message) {
                        message = error.payload.data.message;
                    }
                    setStatus(message, true);
                }
            });
        }

    if (form && form.parentNode) {
        form.parentNode.removeChild(form);
    }

    refreshDurationDisplay();

    if (taskInput && !isReadOnly) {
        taskInput.focus();
    }

    updateFiltersUI(config.filters || {});
    updateRecentEntries(recentEntries);
    updateProjectSummary(Array.isArray(config.projectSummary) ? config.projectSummary : []);

    if (Array.isArray(config.weeklySummary)) {
        renderWeeklySummary(config.weeklySummary);
    }

    if (calendarRoot) {
        toggleCalendar(Boolean(config.hasCalendar));
        if (config.hasCalendar && config.calendar) {
            renderCalendar(config.calendar, filtersState.selectedMemberId);
            setCalendarLoading(false);
        } else {
            clearCalendarUI();
            setCalendarLoading(false);
        }
    }

    if (calendarPrevBtn) {
        calendarPrevBtn.addEventListener('click', function (event) {
            event.preventDefault();
            var targetMonth = calendarPrevBtn.getAttribute('data-target-month') || '';
            if (targetMonth) {
                fetchCalendar(targetMonth);
            }
        });
    }

    if (calendarNextBtn) {
        calendarNextBtn.addEventListener('click', function (event) {
            event.preventDefault();
            var targetMonth = calendarNextBtn.getAttribute('data-target-month') || '';
            if (targetMonth) {
                fetchCalendar(targetMonth);
            }
        });
    }

    if (filterMemberSelect) {
        filterMemberSelect.addEventListener('change', function () {
            var selected = parseInt(filterMemberSelect.value || '', 10);
            if (Number.isNaN(selected)) {
                selected = 0;
            }

            if (filtersState.selectedMemberId === selected) {
                return;
            }

            filtersState.selectedMemberId = selected;
            calendarState.member = selected;
            calendarState.currentMonth = '';
            if (selected <= 0) {
                toggleCalendar(false);
                clearCalendarUI();
            }

            refreshState();
        });
    }

    if (filterProjectSelect) {
        filterProjectSelect.addEventListener('change', function () {
            var value = filterProjectSelect.value || '';
            if (filtersState.selectedProjectKey === value) {
                return;
            }

            filtersState.selectedProjectKey = value;
            refreshState();
        });
    }

    root.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var taskButton = target.closest('[data-role="rename-task"]');
        if (taskButton) {
            event.preventDefault();
            handleRenameTask(taskButton);
            return;
        }

        var projectButton = target.closest('[data-role="rename-project"]');
        if (projectButton) {
            event.preventDefault();
            handleRenameProject(projectButton);
        }
    });
})();
