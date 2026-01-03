            (function() {
                'use strict';

                var PREACT_URL = 'https://cdn.jsdelivr.net/npm/preact@10.19.3/dist/preact.min.js';
                var PREACT_HOOKS_URL = 'https://cdn.jsdelivr.net/npm/preact@10.19.3/hooks/dist/hooks.umd.js';

                var runtimeCache = null;
                var runtimePromise = null;

                var Utils = window.MjMemberUtils || {};
                var domReady = typeof Utils.domReady === 'function'
                    ? Utils.domReady
                    : function(callback) {
                        if (typeof callback !== 'function') {
                            return;
                        }
                        if (typeof document === 'undefined') {
                            callback();
                            return;
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', callback);
                        } else {
                            callback();
                        }
                    };

                var toArray = typeof Utils.toArray === 'function'
                    ? Utils.toArray
                    : function(collection) {
                        if (!collection) {
                            return [];
                        }
                        return Array.prototype.slice.call(collection);
                    };

                var instances = new WeakMap();

                var HOURS_START = 0;
                var HOURS_END = 24;
                var MIN_EVENT_HEIGHT = 15;
                var MINUTES_PER_PIXEL = 2;
                var DEFAULT_SLOT_MINUTES = 60;
                var SLOT_STEP_MINUTES = 15;
                var DAY_RANGE_MINUTES = (HOURS_END - HOURS_START) * 60;
                var DAY_CANVAS_HEIGHT = DAY_RANGE_MINUTES / MINUTES_PER_PIXEL;
                var DEFAULT_VIEW_START_MINUTES = 8 * 60;
                var DEFAULT_VIEW_END_MINUTES = 21 * 60;
                var SCROLL_VIEWPORT_HEIGHT = Math.max(360, Math.round((DEFAULT_VIEW_END_MINUTES - DEFAULT_VIEW_START_MINUTES) / MINUTES_PER_PIXEL));

                var DEFAULT_CONFIG = {
                    locale: 'fr',
                    weekStart: '',
                    timezone: 'UTC',
                    introText: '',
                    accentColor: '#2a55ff',
                    ajax: {},
                    entries: [],
                    commonTasks: [],
                    projects: [],
                    events: [],
                    labels: {},
                    capabilities: {},
                    isPreview: false
                };

                function loadScript(src) {
                    return new Promise(function(resolve, reject) {
                        if (typeof document === 'undefined') {
                            reject(new Error('Document not available.'));
                            return;
                        }
                        if (!src) {
                            reject(new Error('Missing script source.'));
                            return;
                        }

                        var existing = document.querySelector('script[data-mj-hour-encode="' + src + '"]');
                        if (existing) {
                            if (existing.getAttribute('data-loaded') === '1') {
                                resolve();
                                return;
                            }
                            existing.addEventListener('load', function() {
                                resolve();
                            }, { once: true });
                            existing.addEventListener('error', function() {
                                reject(new Error('Failed to load script: ' + src));
                            }, { once: true });
                            return;
                        }

                        var script = document.createElement('script');
                        script.src = src;
                        script.async = true;
                        script.defer = true;
                        script.setAttribute('data-mj-hour-encode', src);
                        script.addEventListener('load', function() {
                            script.setAttribute('data-loaded', '1');
                            resolve();
                        }, { once: true });
                        script.addEventListener('error', function() {
                            reject(new Error('Failed to load script: ' + src));
                        }, { once: true });
                        document.head.appendChild(script);
                    });
                }

                function ensureRuntime() {
                    if (runtimeCache) {
                        return Promise.resolve(runtimeCache);
                    }
                    if (!runtimePromise) {
                        runtimePromise = loadScript(PREACT_URL)
                            .then(function() {
                                var preact = window.preact || window.Preact || null;
                                if (!preact || typeof preact.h !== 'function') {
                                    throw new Error('Preact global not found after loading.');
                                }
                                return loadScript(PREACT_HOOKS_URL).then(function() {
                                    var hooks = window.preactHooks || (preact && preact.hooks) || null;
                                    if (!hooks) {
                                        throw new Error('Preact hooks global not found after loading.');
                                    }
                                    runtimeCache = {
                                        preact: preact,
                                        hooks: hooks
                                    };
                                    return runtimeCache;
                                });
                            })
                            .catch(function(error) {
                                runtimePromise = null;
                                throw error;
                            });
                    }
                    return runtimePromise;
                }

                function showError(root, message) {
                    if (!root) {
                        return;
                    }
                    root.innerHTML = '';
                    var container = document.createElement('div');
                    container.className = 'mj-hour-encode__placeholder';
                    container.textContent = message || 'Une erreur est survenue.';
                    root.appendChild(container);
                }

                function parseConfig(root) {
                    var config = Object.assign({}, DEFAULT_CONFIG);
                    var raw = root.getAttribute('data-config');
                    if (raw) {
                        try {
                            var parsed = JSON.parse(raw);
                            if (parsed && typeof parsed === 'object') {
                                config = Object.assign(config, parsed);
                            }
                        } catch (error) {
                            console.error('MJ Hour Encode – invalid JSON config', error);
                        }
                    }

                    config.locale = typeof config.locale === 'string' && config.locale !== '' ? config.locale : DEFAULT_CONFIG.locale;
                    config.weekStart = typeof config.weekStart === 'string' ? config.weekStart : DEFAULT_CONFIG.weekStart;
                    config.timezone = typeof config.timezone === 'string' ? config.timezone : DEFAULT_CONFIG.timezone;
                    config.introText = typeof config.introText === 'string' ? config.introText : DEFAULT_CONFIG.introText;
                    config.accentColor = typeof config.accentColor === 'string' && config.accentColor !== '' ? config.accentColor : DEFAULT_CONFIG.accentColor;
                    config.ajax = config.ajax && typeof config.ajax === 'object' ? config.ajax : {};
                    config.ajax.weekAction = typeof config.ajax.weekAction === 'string' && config.ajax.weekAction !== ''
                        ? config.ajax.weekAction
                        : (typeof config.ajax.action === 'string' && config.ajax.action !== '' ? config.ajax.action : '');
                    config.ajax.createAction = typeof config.ajax.createAction === 'string' && config.ajax.createAction !== ''
                        ? config.ajax.createAction
                        : '';
                    config.ajax.updateAction = typeof config.ajax.updateAction === 'string' && config.ajax.updateAction !== ''
                        ? config.ajax.updateAction
                        : '';
                    config.ajax.deleteAction = typeof config.ajax.deleteAction === 'string' && config.ajax.deleteAction !== ''
                        ? config.ajax.deleteAction
                        : '';
                    if (!config.ajax.action && config.ajax.weekAction) {
                        config.ajax.action = config.ajax.weekAction;
                    }
                    config.entries = Array.isArray(config.entries) ? config.entries : [];
                    config.commonTasks = Array.isArray(config.commonTasks) ? config.commonTasks : [];
                    config.projects = Array.isArray(config.projects) ? config.projects : [];
                    config.events = Array.isArray(config.events) ? config.events : [];
                    config.workSchedule = Array.isArray(config.workSchedule) ? config.workSchedule : [];
                    config.cumulativeBalance = config.cumulativeBalance && typeof config.cumulativeBalance === 'object' ? config.cumulativeBalance : null;
                    config.labels = Object.assign({
                        title: 'Encodage des Heures de Travail',
                        subtitle: '',
                        weekRange: 'Semaine du %s au %s',
                        previousWeek: 'Semaine précédente',
                        nextWeek: 'Semaine suivante',
                        today: 'Aujourd’hui',
                        calendarTitle: 'Calendrier',
                        calendarPrevious: 'Mois précédent',
                        calendarNext: 'Mois suivant',
                        calendarWeekHours: 'Heures',
                        calendarEventsTitle: 'Événements & fermetures',
                        calendarEventsEmpty: 'Aucun événement ou fermeture cette semaine.',
                        calendarClosureTitle: 'Fermeture',
                        calendarClosureAllDay: 'Toute la journée',
                        calendarEventLabel: 'Événement',
                        totalWeek: 'Total semaine',
                        totalMonth: 'Total mois',
                        totalYear: 'Total année',
                        totalLifetime: 'Total cumulé',
                        statsWeek: 'Semaine',
                        statsMonth: 'Mois',
                        statsYear: 'Année',
                        statsTotal: 'Total',
                        export: 'Exporter',
                        hoursShort: 'h',
                        minutesShort: 'min',
                        emptyCalendar: 'Le calendrier se chargera une fois les données récupérées.',
                        suggestedTasks: 'Tâches suggérées',
                        pinnedProjects: 'Projets épinglés',
                        weekProjectsOnly: 'Afficher uniquement les projets de la semaine',
                        noWeeklyProjects: 'Aucun projet encodé cette semaine.',
                        projectPlaceholder: 'Ajouter un projet…',
                        addProjectAction: 'Ajouter',
                        addProjectShort: 'Ajouter',
                        loading: 'Chargement…',
                        noEvents: 'Aucun événement planifié pour cette semaine.',
                        fetchError: 'Impossible de charger les données de la semaine.',
                        noTasks: 'Aucune suggestion disponible pour le moment.',
                        noProjects: 'Aucun projet enregistré pour le moment.',
                        selectionTitle: 'Encoder une nouvelle plage',
                        selectionEditTitle: 'Modifier la plage encodée',
                        selectionDescription: '',
                        selectionTaskLabel: 'Intitulé de la tâche',
                        selectionProjectLabel: 'Projet associé',
                        selectionStartLabel: 'Début',
                        selectionEndLabel: 'Fin',
                        selectionDurationLabel: 'Durée estimée',
                        selectionConfirm: 'Encoder cette plage',
                        selectionUpdate: 'Mettre à jour la plage',
                        selectionCancel: 'Annuler',
                        selectionErrorRange: 'Veuillez choisir une heure de fin postérieure à l’heure de début.',
                        selectionErrorTask: 'Veuillez saisir un intitulé.',
                        selectionErrorOverlap: 'Une plage est déjà encodée sur ces horaires.',
                        selectionDelete: 'Supprimer',
                        selectionDeleteConfirm: 'Voulez-vous vraiment supprimer cette plage ?',
                        selectionDeleteSuccess: 'Plage supprimée avec succès.',
                        projectWithoutLabel: 'Sans projet',
                        selectProjectForTasks: 'Sélectionnez un projet pour afficher les tâches associées.',
                        projectTasksEmpty: 'Aucune tâche enregistrée pour ce projet.',
                        contractualHours: 'Heures contractuelles',
                        weekDifference: 'Différence semaine',
                        cumulativeDifference: 'Solde cumulé',
                        hoursToRecover: 'à récupérer',
                        hoursExtra: 'en plus'
                    }, config.labels && typeof config.labels === 'object' ? config.labels : {});
                    config.capabilities = Object.assign({
                        canManage: false
                    }, config.capabilities && typeof config.capabilities === 'object' ? config.capabilities : {});
                    config.isPreview = Boolean(config.isPreview);

                    return config;
                }

                function isString(value) {
                    return typeof value === 'string';
                }

                function isValidDate(value) {
                    return value instanceof Date && !Number.isNaN(value.getTime());
                }

                function parseISODate(value) {
                    if (!isString(value) || value === '') {
                        return null;
                    }
                    var date = new Date(value + 'T00:00:00');
                    if (!isValidDate(date)) {
                        return null;
                    }
                    date.setHours(0, 0, 0, 0);
                    return date;
                }

                function parseDateTime(value) {
                    if (!isString(value) || value === '') {
                        return null;
                    }
                    var date = new Date(value);
                    if (!isValidDate(date)) {
                        return null;
                    }
                    return date;
                }

                function startOfDay(date) {
                    var result = new Date(date.getTime());
                    result.setHours(0, 0, 0, 0);
                    return result;
                }

                function endOfDay(date) {
                    var result = new Date(date.getTime());
                    result.setHours(23, 59, 59, 999);
                    return result;
                }

                function startOfMonth(date) {
                    var result = new Date(date.getTime());
                    result.setDate(1);
                    result.setHours(0, 0, 0, 0);
                    return result;
                }

                function addMonths(date, amount) {
                    var result = new Date(date.getTime());
                    result.setDate(1);
                    result.setMonth(result.getMonth() + amount);
                    return startOfMonth(result);
                }

                function addDays(date, amount) {
                    var result = new Date(date.getTime());
                    result.setDate(result.getDate() + amount);
                    return result;
                }

                function startOfWeek(date) {
                    var base = startOfDay(date);
                    var day = base.getDay();
                    var diff = (day === 0 ? -6 : 1) - day;
                    base.setDate(base.getDate() + diff);
                    return base;
                }

                function toISODate(date) {
                    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                        return '';
                    }
                    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
                }

                function minutesSinceMidnight(date) {
                    return (date.getHours() * 60) + date.getMinutes();
                }

                function clamp(value, min, max) {
                    if (value < min) {
                        return min;
                    }
                    if (value > max) {
                        return max;
                    }
                    return value;
                }

                function capitalize(text) {
                    if (!isString(text) || text.length === 0) {
                        return '';
                    }
                    return text.charAt(0).toUpperCase() + text.slice(1);
                }

                function uniqueStrings(list) {
                    if (!Array.isArray(list)) {
                        return [];
                    }
                    var seen = Object.create(null);
                    var result = [];
                    list.forEach(function(value) {
                        if (!isString(value)) {
                            return;
                        }
                        var trimmed = value.trim();
                        if (trimmed === '' || seen[trimmed]) {
                            return;
                        }
                        seen[trimmed] = true;
                        result.push(trimmed);
                    });
                    return result;
                }

                function getFormatter(locale, options) {
                    try {
                        return new Intl.DateTimeFormat(locale, options);
                    } catch (error) {
                        return new Intl.DateTimeFormat('fr', options);
                    }
                }

                function formatDisplayDate(date, locale, includeYear) {
                    var options = { day: '2-digit', month: 'long' };
                    if (includeYear) {
                        options.year = 'numeric';
                    }
                    return capitalize(getFormatter(locale, options).format(date));
                }

                function buildPeriodLabel(template, locale, startDate) {
                    var endDate = addDays(startDate, 6);
                    var startLabel = formatDisplayDate(startDate, locale, false);
                    var endLabel = formatDisplayDate(endDate, locale, true);
                    if (isString(template) && template.indexOf('%s') !== -1) {
                        var first = template.replace('%s', startLabel);
                        return first.replace('%s', endLabel);
                    }
                    return startLabel + ' - ' + endLabel;
                }

                function pad(value) {
                    return value < 10 ? '0' + value : String(value);
                }

                function buildHourMarks() {
                    var marks = [];
                    for (var hour = HOURS_START; hour < HOURS_END; hour++) {
                        marks.push(pad(hour) + ':00');
                    }
                    return marks;
                }

                var HOUR_MARKS = buildHourMarks();
                var NO_PROJECT_KEY = '__mj_hour_encode_no_project__';
                var MOBILE_BREAKPOINT_QUERY = '(max-width: 900px)';

                var HAS_STRING_NORMALIZE = typeof String.prototype.normalize === 'function';

                function normalizeProjectValue(value) {
                    if (!isString(value)) {
                        return '';
                    }
                    return value.trim();
                }

                function ensureProjectKey(value) {
                    var normalized = normalizeProjectValue(value);
                    return normalized !== '' ? normalized : NO_PROJECT_KEY;
                }

                function normalizeSearchValue(value) {
                    if (!isString(value)) {
                        return '';
                    }
                    var base = value.trim().toLowerCase();
                    if (HAS_STRING_NORMALIZE) {
                        base = base.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                    return base;
                }

                function normalizeNumberMapValues(source) {
                    var result = Object.create(null);
                    if (!source || typeof source !== 'object') {
                        return result;
                    }
                    Object.keys(source).forEach(function(key) {
                        var numericValue = Number(source[key]);
                        if (!Number.isFinite(numericValue)) {
                            return;
                        }
                        var normalizedKey = isString(key) ? key : String(key);
                        result[normalizedKey] = Math.max(0, Math.round(numericValue));
                    });
                    return result;
                }

                function normalizeTaskTotalsMap(source) {
                    var result = Object.create(null);
                    if (!source || typeof source !== 'object') {
                        return result;
                    }
                    Object.keys(source).forEach(function(key) {
                        var label = normalizeProjectValue(key);
                        if (!label) {
                            return;
                        }
                        var numericValue = Number(source[key]);
                        if (!Number.isFinite(numericValue)) {
                            return;
                        }
                        var minutes = Math.max(0, Math.round(numericValue));
                        if (minutes === 0) {
                            return;
                        }
                        result[label] = minutes;
                    });
                    return result;
                }

                function normalizeProjectTotalsList(list) {
                    var map = Object.create(null);
                    if (!Array.isArray(list)) {
                        return map;
                    }
                    list.forEach(function(entry) {
                        if (!entry || typeof entry !== 'object') {
                            return;
                        }
                        var projectValue = '';
                        if (isString(entry.project)) {
                            projectValue = entry.project;
                        } else if (isString(entry.label)) {
                            projectValue = entry.label;
                        }
                        projectValue = normalizeProjectValue(projectValue);
                        var key = ensureProjectKey(projectValue);
                        var totalRaw = 0;
                        if (entry.totalMinutes !== undefined) {
                            totalRaw = Number(entry.totalMinutes);
                        } else if (entry.total_minutes !== undefined) {
                            totalRaw = Number(entry.total_minutes);
                        } else if (entry.total !== undefined) {
                            totalRaw = Number(entry.total);
                        }
                        var totalMinutes = Number.isFinite(totalRaw) ? Math.max(0, Math.round(totalRaw)) : 0;
                        var months = normalizeNumberMapValues(entry.months || entry.monthTotals || entry.month_totals || {});
                        var years = normalizeNumberMapValues(entry.years || entry.yearTotals || entry.year_totals || {});
                        var weeks = normalizeNumberMapValues(entry.weeks || entry.weekTotals || entry.week_totals || {});
                        var tasks = normalizeTaskTotalsMap(entry.tasks || entry.taskTotals || entry.task_totals || {});
                        map[key] = {
                            project: projectValue,
                            totalMinutes: totalMinutes,
                            months: months,
                            years: years,
                            weeks: weeks,
                            tasks: tasks
                        };
                    });
                    return map;
                }

                function areProjectTotalsMapsEqual(current, previous) {
                    var first = current || {};
                    var second = previous || {};
                    var firstKeys = Object.keys(first);
                    var secondKeys = Object.keys(second);
                    if (firstKeys.length !== secondKeys.length) {
                        return false;
                    }
                    for (var index = 0; index < firstKeys.length; index++) {
                        var key = firstKeys[index];
                        if (!Object.prototype.hasOwnProperty.call(second, key)) {
                            return false;
                        }
                        var firstEntry = first[key] || {};
                        var secondEntry = second[key] || {};
                        if (normalizeProjectValue(firstEntry.project || '') !== normalizeProjectValue(secondEntry.project || '')) {
                            return false;
                        }
                        if (Number(firstEntry.totalMinutes || 0) !== Number(secondEntry.totalMinutes || 0)) {
                            return false;
                        }
                        if (!areNumberMapsEqual(firstEntry.months, secondEntry.months)) {
                            return false;
                        }
                        if (!areNumberMapsEqual(firstEntry.years, secondEntry.years)) {
                            return false;
                        }
                        if (!areNumberMapsEqual(firstEntry.weeks, secondEntry.weeks)) {
                            return false;
                        }
                        if (!areNumberMapsEqual(firstEntry.tasks, secondEntry.tasks)) {
                            return false;
                        }
                    }
                    return true;
                }

                function computeProjectSummaries(entries) {
                    var summaries = Object.create(null);
                    if (!Array.isArray(entries)) {
                        return summaries;
                    }

                    entries.forEach(function(entry) {
                        if (!entry) {
                            return;
                        }
                        var start = parseDateTime(entry.start);
                        var end = parseDateTime(entry.end);
                        if (!isValidDate(start) || !isValidDate(end) || end <= start) {
                            return;
                        }

                        var minutes = Math.max(0, Math.round((end.getTime() - start.getTime()) / 60000));
                        if (minutes === 0) {
                            return;
                        }

                        var projectValue = normalizeProjectValue(entry.project);
                        var projectKey = ensureProjectKey(projectValue);

                        if (!summaries[projectKey]) {
                            summaries[projectKey] = {
                                project: projectValue,
                                totalMinutes: 0,
                                tasks: Object.create(null)
                            };
                        }

                        summaries[projectKey].totalMinutes += minutes;

                        var taskValue = normalizeProjectValue(entry.task);
                        if (taskValue) {
                            summaries[projectKey].tasks[taskValue] = (summaries[projectKey].tasks[taskValue] || 0) + minutes;
                        }
                    });

                    return summaries;
                }

                function mapTaskTotalsToList(taskTotals, labels) {
                    if (!taskTotals) {
                        return [];
                    }
                    return Object.keys(taskTotals).sort(function(a, b) {
                        if (taskTotals[b] === taskTotals[a]) {
                            return a.localeCompare(b);
                        }
                        return taskTotals[b] - taskTotals[a];
                    }).map(function(taskName) {
                        var minutes = taskTotals[taskName] || 0;
                        return {
                            key: taskName,
                            name: taskName,
                            totalMinutes: minutes,
                            totalLabel: formatTotalMinutes(minutes, labels)
                        };
                    });
                }

                function buildProjectSummaryItem(key, value, summary, labels, currentWeekIso) {
                    var safeValue = normalizeProjectValue(value);
                    var totalMinutes = summary && Number.isFinite(summary.totalMinutes) ? summary.totalMinutes : 0;
                    if (!Number.isFinite(totalMinutes) || totalMinutes < 0) {
                        totalMinutes = 0;
                    }
                    var lifetimeMinutes = summary && Number.isFinite(summary.lifetimeMinutes) ? summary.lifetimeMinutes : totalMinutes;
                    if (!Number.isFinite(lifetimeMinutes) || lifetimeMinutes < 0) {
                        lifetimeMinutes = Math.max(0, totalMinutes);
                    }
                    var serverTotalMinutes = summary && Number.isFinite(summary.serverTotalMinutes) ? summary.serverTotalMinutes : null;
                    if (serverTotalMinutes !== null) {
                        lifetimeMinutes = Math.max(lifetimeMinutes, serverTotalMinutes);
                    }
                    var tasks = summary ? summary.tasks : null;
                    var monthTotals = summary ? summary.months : null;
                    var yearTotals = summary ? summary.years : null;

                    var referenceDate = parseISODate(currentWeekIso);
                    if (!referenceDate) {
                        referenceDate = new Date();
                    }
                    var referenceMonth = referenceDate.getMonth();
                    var referenceYear = referenceDate.getFullYear();
                    var monthKey = referenceYear + '-' + pad(referenceMonth + 1);
                    var yearKey = String(referenceYear);

                    var weekMinutes = Math.max(0, totalMinutes);
                    if (summary && summary.weeks && currentWeekIso && Object.prototype.hasOwnProperty.call(summary.weeks, currentWeekIso)) {
                        var stored = summary.weeks[currentWeekIso];
                        if (Number.isFinite(stored)) {
                            weekMinutes = Math.max(0, stored);
                        }
                    }

                    var monthMinutes = 0;
                    var yearMinutes = 0;
                    if (monthTotals && monthKey && Object.prototype.hasOwnProperty.call(monthTotals, monthKey)) {
                        var storedMonth = Number(monthTotals[monthKey] || 0);
                        if (Number.isFinite(storedMonth)) {
                            monthMinutes = Math.max(0, storedMonth);
                        }
                    }
                    if (yearTotals && yearKey && Object.prototype.hasOwnProperty.call(yearTotals, yearKey)) {
                        var storedYear = Number(yearTotals[yearKey] || 0);
                        if (Number.isFinite(storedYear)) {
                            yearMinutes = Math.max(0, storedYear);
                        }
                    }
                    var monthFallbackMinutes = 0;
                    var yearFallbackMinutes = 0;
                    if (summary && summary.weeks) {
                        Object.keys(summary.weeks).forEach(function(weekIso) {
                            var minutes = Number(summary.weeks[weekIso] || 0);
                            if (!Number.isFinite(minutes) || minutes <= 0) {
                                return;
                            }
                            var weekDate = parseISODate(weekIso);
                            if (!weekDate) {
                                return;
                            }
                            if (weekDate.getFullYear() === referenceYear) {
                                yearFallbackMinutes += minutes;
                                if (weekDate.getMonth() === referenceMonth) {
                                    monthFallbackMinutes += minutes;
                                }
                            }
                        });
                    }
                    if (monthMinutes === 0 && monthFallbackMinutes > 0) {
                        monthMinutes = monthFallbackMinutes;
                    }
                    if (yearMinutes === 0 && yearFallbackMinutes > 0) {
                        yearMinutes = yearFallbackMinutes;
                    }

                    if (monthMinutes === 0) {
                        monthMinutes = weekMinutes;
                    }
                    if (yearMinutes === 0) {
                        yearMinutes = lifetimeMinutes;
                    }

                    return {
                        key: key,
                        label: safeValue || (labels.projectWithoutLabel || 'Sans projet'),
                        value: safeValue,
                        totalMinutes: totalMinutes,
                        totalLabel: formatTotalMinutes(totalMinutes, labels),
                        lifetimeMinutes: lifetimeMinutes,
                        lifetimeLabel: formatTotalMinutes(lifetimeMinutes, labels),
                        monthMinutes: monthMinutes,
                        monthLabel: formatTotalMinutes(monthMinutes, labels),
                        yearMinutes: yearMinutes,
                        yearLabel: formatTotalMinutes(yearMinutes, labels),
                        weekMinutes: weekMinutes,
                        weekLabel: formatTotalMinutes(weekMinutes, labels),
                        tasks: mapTaskTotalsToList(tasks, labels),
                        hasWeekActivity: weekMinutes > 0
                    };
                }

                function formatTotalMinutes(totalMinutes, labels) {
                    var total = Math.max(0, Math.round(totalMinutes));
                    var hours = Math.floor(total / 60);
                    var minutes = total % 60;
                    var hourLabel = labels && labels.hoursShort ? labels.hoursShort : 'h';
                    var minuteLabel = labels && labels.minutesShort ? labels.minutesShort : 'min';

                    if (hours === 0 && minutes === 0) {
                        return '0' + hourLabel;
                    }

                    var parts = [];
                    parts.push(hours + hourLabel);
                    if (minutes > 0) {
                        parts.push(minutes + minuteLabel);
                    }
                    return parts.join(' ');
                }

                function isoToTimeValue(iso) {
                    var date = parseDateTime(iso);
                    if (!isValidDate(date)) {
                        return pad(HOURS_START) + ':00';
                    }
                    return pad(date.getHours()) + ':' + pad(date.getMinutes());
                }

                function timeValueToMinutes(value) {
                    if (!isString(value) || value.indexOf(':') === -1) {
                        return null;
                    }
                    var parts = value.split(':');
                    if (parts.length < 2) {
                        return null;
                    }
                    var hours = parseInt(parts[0], 10);
                    var minutes = parseInt(parts[1], 10);
                    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
                        return null;
                    }
                    return (hours * 60) + minutes;
                }

                function isMidnightTimeValue(value) {
                    if (!isString(value)) {
                        return false;
                    }
                    var trimmed = value.trim();
                    if (trimmed === '') {
                        return false;
                    }
                    if (trimmed === '24:00' || trimmed === '24:00:00') {
                        return true;
                    }
                    if (trimmed === '00:00' || trimmed === '00:00:00' || trimmed === '0:00') {
                        return true;
                    }
                    var minutes = timeValueToMinutes(trimmed);
                    return minutes === 0;
                }

                function normalizeMidnightEndDate(dayIso, startDate, endDate, endValue) {
                    if (!isMidnightTimeValue(endValue)) {
                        return endDate;
                    }
                    if (!isValidDate(startDate) || !isValidDate(endDate) || endDate > startDate) {
                        return endDate;
                    }
                    if (minutesSinceMidnight(startDate) === 0) {
                        return endDate;
                    }
                    var baseDay = parseISODate(dayIso);
                    if (baseDay) {
                        var nextDay = addDays(baseDay, 1);
                        nextDay.setHours(0, 0, 0, 0);
                        return nextDay;
                    }
                    var startDay = startOfDay(startDate);
                    var fallback = addDays(startDay, 1);
                    fallback.setHours(0, 0, 0, 0);
                    return fallback;
                }

                function createDateFromDayAndTime(dayIso, timeValue) {
                    var base = parseISODate(dayIso);
                    var minutes = timeValueToMinutes(timeValue);
                    if (!base || minutes === null) {
                        return null;
                    }
                    base.setMinutes(minutes, 0, 0);
                    return base;
                }

                function dateFromDayMinutes(dayDate, minutes) {
                    var base = new Date(dayDate.getTime());
                    base.setHours(0, 0, 0, 0);
                    base.setMinutes(minutes, 0, 0);
                    return base;
                }

                function formatTimeValue(timeValue, locale) {
                    var minutes = timeValueToMinutes(timeValue);
                    if (minutes === null) {
                        return '--:--';
                    }
                    var temp = new Date();
                    temp.setHours(0, 0, 0, 0);
                    temp.setMinutes(minutes, 0, 0);
                    return getFormatter(locale || 'fr', { hour: '2-digit', minute: '2-digit' }).format(temp);
                }

                function formatDurationFromValues(dayIso, startValue, endValue, labels) {
                    var start = createDateFromDayAndTime(dayIso, startValue);
                    var end = createDateFromDayAndTime(dayIso, endValue);
                    end = normalizeMidnightEndDate(dayIso, start, end, endValue);
                    if (!isValidDate(start) || !isValidDate(end) || end <= start) {
                        return formatTotalMinutes(0, labels);
                    }
                    var minutes = Math.max(0, Math.round((end - start) / 60000));
                    return formatTotalMinutes(minutes, labels);
                }

                function computeEntryTotals(entries) {
                    var totals = Object.create(null);
                    if (!Array.isArray(entries)) {
                        return totals;
                    }

                    entries.forEach(function(entry) {
                        if (!entry) {
                            return;
                        }
                        var start = parseDateTime(entry.start);
                        var end = parseDateTime(entry.end);
                        if (!isValidDate(start) || !isValidDate(end) || end <= start) {
                            return;
                        }

                        var segmentStart = new Date(start.getTime());
                        while (segmentStart < end) {
                            var dayStart = startOfDay(segmentStart);
                            var nextDayStart = addDays(dayStart, 1);
                            var segmentEnd = end < nextDayStart ? end : nextDayStart;
                            if (segmentEnd <= segmentStart) {
                                break;
                            }
                            var minutes = Math.max(0, Math.round((segmentEnd - segmentStart) / 60000));
                            if (minutes > 0) {
                                var iso = toISODate(dayStart);
                                totals[iso] = (totals[iso] || 0) + minutes;
                            }
                            segmentStart = new Date(segmentEnd.getTime());
                        }
                    });

                    return totals;
                }

                function areTaskMapsEqual(current, previous) {
                    var first = current || {};
                    var second = previous || {};
                    var keysFirst = Object.keys(first);
                    var keysSecond = Object.keys(second);
                    if (keysFirst.length !== keysSecond.length) {
                        return false;
                    }
                    for (var index = 0; index < keysFirst.length; index++) {
                        var key = keysFirst[index];
                        if (!Object.prototype.hasOwnProperty.call(second, key)) {
                            return false;
                        }
                        if (first[key] !== second[key]) {
                            return false;
                        }
                    }
                    return true;
                }

                function areNumberMapsEqual(current, previous) {
                    var first = current || {};
                    var second = previous || {};
                    var keysFirst = Object.keys(first);
                    var keysSecond = Object.keys(second);
                    if (keysFirst.length !== keysSecond.length) {
                        return false;
                    }
                    for (var index = 0; index < keysFirst.length; index++) {
                        var key = keysFirst[index];
                        if (!Object.prototype.hasOwnProperty.call(second, key)) {
                            return false;
                        }
                        var firstValue = Number(first[key] || 0);
                        var secondValue = Number(second[key] || 0);
                        if (firstValue !== secondValue) {
                            return false;
                        }
                    }
                    return true;
                }

                function isValidHex(value) {
                    return isString(value) && /^#?[0-9a-fA-F]{3,6}$/.test(value);
                }

                function normalizeHex(value) {
                    if (!isValidHex(value)) {
                        return null;
                    }
                    var hex = value.charAt(0) === '#' ? value.slice(1) : value;
                    if (hex.length === 3) {
                        hex = hex.split('').map(function(char) {
                            return char + char;
                        }).join('');
                    }
                    if (hex.length !== 6) {
                        return null;
                    }
                    return hex;
                }

                function hexToRgba(value, alpha) {
                    var hex = normalizeHex(value);
                    if (!hex) {
                        return 'rgba(42, 85, 255, ' + (typeof alpha === 'number' ? alpha : 0.18) + ')';
                    }
                    var r = parseInt(hex.slice(0, 2), 16);
                    var g = parseInt(hex.slice(2, 4), 16);
                    var b = parseInt(hex.slice(4, 6), 16);
                    var a = typeof alpha === 'number' ? alpha : 0.18;
                    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + a + ')';
                }

                function resolveAccentColor(primary, fallback) {
                    if (isValidHex(primary)) {
                        return primary.charAt(0) === '#' ? primary : '#' + primary;
                    }
                    if (isValidHex(fallback)) {
                        return fallback.charAt(0) === '#' ? fallback : '#' + fallback;
                    }
                    return '#2a55ff';
                }

                function buildEventColors(accent) {
                    return {
                        border: accent,
                        background: hexToRgba(accent, 0.82)
                    };
                }

                function resolveEventCover(event) {
                    if (!event || typeof event !== 'object') {
                        return '';
                    }
                    var candidates = [
                        event.cover,
                        event.coverUrl,
                        event.cover_url,
                        event.coverThumb,
                        event.cover_thumb,
                        event.articleCover,
                        event.article_cover,
                        event.articleCoverUrl,
                        event.article_cover_url,
                        event.articleCoverThumb,
                        event.article_cover_thumb,
                        event.image,
                        event.imageUrl,
                        event.image_url,
                        event.thumbnail,
                        event.thumbnailUrl,
                        event.thumbnail_url
                    ];
                    for (var index = 0; index < candidates.length; index++) {
                        var value = candidates[index];
                        if (isString(value) && value.trim() !== '') {
                            return value.trim();
                        }
                    }
                    if (event.assets && typeof event.assets === 'object') {
                        var assetCover = event.assets.cover || event.assets.coverUrl || event.assets.cover_url;
                        if (isString(assetCover) && assetCover.trim() !== '') {
                            return assetCover.trim();
                        }
                    }
                    return '';
                }

                function formatEventMeta(formatter, start, end, location) {
                    var segments = [];
                    segments.push(formatter.format(start) + ' - ' + formatter.format(end));
                    if (location) {
                        segments.push(location);
                    }
                    return segments.join(' • ');
                }

                function buildEventsForDay(events, dayDate, defaultColor, locale) {
                    if (!Array.isArray(events) || events.length === 0) {
                        return [];
                    }

                    var dayStart = startOfDay(dayDate);
                    var dayEnd = endOfDay(dayDate);
                    var rangeStart = HOURS_START * 60;
                    var rangeEnd = HOURS_END * 60;
                    var timeFormatter = getFormatter(locale, { hour: '2-digit', minute: '2-digit' });
                    var result = [];

                    events.forEach(function(event, index) {
                        if (!event) {
                            return;
                        }
                        var start = parseDateTime(event.start);
                        var end = parseDateTime(event.end);
                        if (!isValidDate(start) || !isValidDate(end) || end <= start) {
                            return;
                        }
                        if (end <= dayStart || start >= dayEnd) {
                            return;
                        }

                        var clampedStart = start < dayStart ? dayStart : start;
                        var clampedEnd = end > dayEnd ? dayEnd : end;
                        var startMinutes = minutesSinceMidnight(clampedStart);
                        var endMinutes = minutesSinceMidnight(clampedEnd);

                        if (endMinutes <= rangeStart || startMinutes >= rangeEnd) {
                            return;
                        }

                        var topMinutes = clamp(startMinutes, rangeStart, rangeEnd) - rangeStart;
                        var bottomMinutes = clamp(endMinutes, rangeStart, rangeEnd) - rangeStart;
                        var durationMinutes = Math.max(bottomMinutes - topMinutes, MIN_EVENT_HEIGHT);
                        var accent = resolveAccentColor(event.accentColor || event.color, defaultColor);
                        var isEntry = Boolean(event.isEntry);
                        var eventType = isString(event.type) ? event.type : '';
                        var isClosure = eventType === 'closure';
                        var locationLabel = event.location ? String(event.location) : '';
                        var titleLabel = isString(event.title) ? event.title : '';
                        var typeLabel = isString(event.typeLabel) ? event.typeLabel : '';
                        if (isClosure) {
                            if (!typeLabel) {
                                typeLabel = 'Fermeture';
                            }
                            if (!titleLabel) {
                                titleLabel = typeLabel;
                            }
                        }
                        var timeRangeLabel = '';
                        var metaLabel = '';
                        var tooltipLabelParts = [];
                        if (isClosure) {
                            timeRangeLabel = typeLabel || titleLabel || 'Fermeture';
                            metaLabel = timeRangeLabel;
                            tooltipLabelParts.push(timeRangeLabel);
                            if (titleLabel && titleLabel !== timeRangeLabel) {
                                tooltipLabelParts.push(titleLabel);
                            }
                            if (locationLabel) {
                                tooltipLabelParts.push(locationLabel);
                            }
                        } else {
                            timeRangeLabel = timeFormatter.format(start) + ' - ' + timeFormatter.format(end);
                            if (!titleLabel) {
                                titleLabel = timeRangeLabel;
                            }
                            metaLabel = formatEventMeta(timeFormatter, start, end, locationLabel);
                            tooltipLabelParts.push(timeRangeLabel);
                            if (titleLabel && titleLabel !== timeRangeLabel) {
                                tooltipLabelParts.push(titleLabel);
                            }
                            if (locationLabel) {
                                tooltipLabelParts.push(locationLabel);
                            }
                        }
                        var tooltipLabel = tooltipLabelParts.filter(Boolean).join(' • ');
                        var coverUrl = resolveEventCover(event);

                        result.push({
                            id: event.id || (titleLabel ? titleLabel + '-' + index : 'event-' + index),
                            title: titleLabel || '',
                            meta: metaLabel,
                            position: {
                                top: topMinutes / MINUTES_PER_PIXEL,
                                height: durationMinutes / MINUTES_PER_PIXEL
                            },
                            colors: buildEventColors(accent),
                            tooltip: {
                                time: timeRangeLabel,
                                title: titleLabel || '',
                                location: locationLabel,
                                cover: coverUrl,
                                accent: accent
                            },
                            tooltipLabel: tooltipLabel,
                            isEntry: isEntry,
                            kind: isEntry ? 'entry' : (isClosure ? 'closure' : 'event'),
                            source: event
                        });
                    });

                    return result;
                }

                function normalizeEntriesForEvents(entries, defaultColor) {
                    var normalized = [];
                    if (!Array.isArray(entries) || entries.length === 0) {
                        return normalized;
                    }

                    entries.forEach(function(entry, index) {
                        if (!entry) {
                            return;
                        }

                        var start = parseDateTime(entry.start);
                        var end = parseDateTime(entry.end);
                        if (!isValidDate(start) || !isValidDate(end) || end <= start) {
                            return;
                        }

                        normalized.push({
                            id: entry.id || ('entry-' + index),
                            hourId: entry.hourId || null,
                            title: entry.task ? String(entry.task) : '',
                            task: entry.task ? String(entry.task) : '',
                            project: entry.project ? String(entry.project) : '',
                            start: isString(entry.start) ? entry.start : start.toISOString(),
                            end: isString(entry.end) ? entry.end : end.toISOString(),
                            location: entry.project ? String(entry.project) : '',
                            accentColor: entry.color || defaultColor,
                            durationMinutes: typeof entry.durationMinutes === 'number' ? entry.durationMinutes : Math.max(0, Math.round((end - start) / 60000)),
                            isEntry: true
                        });
                    });

                    return normalized;
                }

                function buildCalendarModel(config, weekStartIso, entries, events) {
                    var locale = config.locale || 'fr';
                    var startDate = parseISODate(weekStartIso);
                    if (!startDate) {
                        startDate = startOfWeek(new Date());
                    }

                    var dayFormatter = getFormatter(locale, { weekday: 'short' });
                    var dateFormatter = getFormatter(locale, { day: '2-digit', month: 'short' });
                    var totals = computeEntryTotals(entries);
                    var days = [];
                    var totalMinutes = 0;
                    var todayIso = toISODate(startOfDay(new Date()));
                    var baseEvents = Array.isArray(events) ? events : [];
                    var entryEvents = normalizeEntriesForEvents(entries, config.accentColor);
                    var combinedEvents = baseEvents.length > 0 || entryEvents.length > 0
                        ? baseEvents.concat(entryEvents)
                        : [];

                    for (var index = 0; index < 7; index++) {
                        var dayDate = addDays(startDate, index);
                        var dayIso = toISODate(dayDate);
                        var minutes = totals[dayIso] || 0;
                        totalMinutes += minutes;
                        var weekday = dayDate.getDay();
                        var isSaturday = weekday === 6;
                        var isSunday = weekday === 0;
                        var isWeekend = isSaturday || isSunday;

                        days.push({
                            iso: dayIso,
                            date: dayDate,
                            weekday: weekday,
                            isWeekend: isWeekend,
                            isSaturday: isSaturday,
                            isSunday: isSunday,
                            label: {
                                short: capitalize(dayFormatter.format(dayDate)),
                                date: capitalize(dateFormatter.format(dayDate))
                            },
                            totalMinutes: minutes,
                            totalLabel: formatTotalMinutes(minutes, config.labels),
                            events: buildEventsForDay(combinedEvents, dayDate, config.accentColor, locale),
                            isToday: dayIso === todayIso
                        });
                    }

                    return {
                        start: startDate,
                        periodLabel: buildPeriodLabel(config.labels.weekRange, locale, startDate),
                        days: days,
                        totalMinutes: totalMinutes,
                        hasEvents: days.some(function(day) {
                            return day.events.length > 0;
                        })
                    };
                }

                function buildMiniCalendarModel(locale, monthIso, activeWeekIso, weekTotalsMap, labels, weekContractualMinutes) {
                    var safeLocale = locale || 'fr';
                    var monthDate = parseISODate(monthIso);
                    if (!monthDate) {
                        monthDate = startOfMonth(new Date());
                    }
                    var monthStart = startOfMonth(monthDate);
                    var calendarStart = startOfWeek(monthStart);
                    var activeWeek = ensureWeekStart(activeWeekIso);
                    var todayIso = toISODate(startOfDay(new Date()));
                    var contractualMinutes = Number.isFinite(weekContractualMinutes) && weekContractualMinutes > 0 ? weekContractualMinutes : 0;

                    var monthLabel = capitalize(getFormatter(safeLocale, { month: 'long', year: 'numeric' }).format(monthStart));

                    var weekdayFormatter = getFormatter(safeLocale, { weekday: 'short' });
                    var weekdayBase = startOfWeek(new Date());
                    var weekdayLabels = [];
                    for (var index = 0; index < 7; index++) {
                        weekdayLabels.push(capitalize(weekdayFormatter.format(addDays(weekdayBase, index))));
                    }

                    var weeks = [];
                    var totals = weekTotalsMap && typeof weekTotalsMap === 'object' ? weekTotalsMap : Object.create(null);
                    var safeLabels = labels && typeof labels === 'object' ? labels : {};
                    for (var weekIndex = 0; weekIndex < 6; weekIndex++) {
                        var weekStartDate = addDays(calendarStart, weekIndex * 7);
                        var weekStart = toISODate(weekStartDate);
                        var days = [];
                        for (var dayIndex = 0; dayIndex < 7; dayIndex++) {
                            var currentDate = addDays(weekStartDate, dayIndex);
                            var currentIso = toISODate(currentDate);
                            days.push({
                                iso: currentIso,
                                label: String(currentDate.getDate()),
                                inMonth: currentDate.getMonth() === monthStart.getMonth(),
                                isToday: currentIso === todayIso
                            });
                        }
                        var weekTotalMinutes = 0;
                        if (Object.prototype.hasOwnProperty.call(totals, weekStart)) {
                            var candidate = Number(totals[weekStart] || 0);
                            if (Number.isFinite(candidate) && candidate > 0) {
                                weekTotalMinutes = Math.round(candidate);
                            }
                        }
                        
                        // Calculer le différentiel pour cette semaine
                        var weekDifferenceMinutes = 0;
                        var weekDifferenceLabel = '';
                        if (contractualMinutes > 0 && weekTotalMinutes > 0) {
                            weekDifferenceMinutes = weekTotalMinutes - contractualMinutes;
                            var absDiff = Math.abs(weekDifferenceMinutes);
                            var diffLabel = formatTotalMinutes(absDiff, safeLabels);
                            if (weekDifferenceMinutes >= 0) {
                                weekDifferenceLabel = '+' + diffLabel;
                            } else {
                                weekDifferenceLabel = '-' + diffLabel;
                            }
                        }
                        
                        weeks.push({
                            startIso: weekStart,
                            days: days,
                            isActive: weekStart === activeWeek,
                            totalMinutes: weekTotalMinutes,
                            totalLabel: formatTotalMinutes(weekTotalMinutes, safeLabels),
                            differenceMinutes: weekDifferenceMinutes,
                            differenceLabel: weekDifferenceLabel,
                            hasHours: weekTotalMinutes > 0
                        });
                    }

                    return {
                        monthLabel: monthLabel,
                        monthIso: toISODate(monthStart),
                        prevMonthIso: toISODate(startOfMonth(addMonths(monthStart, -1))),
                        nextMonthIso: toISODate(startOfMonth(addMonths(monthStart, 1))),
                        weekdayLabels: weekdayLabels,
                        weeks: weeks,
                        weekContractualMinutes: contractualMinutes
                    };
                }

                function shiftWeek(isoDate, offset) {
                    var base = parseISODate(isoDate);
                    if (!base) {
                        base = new Date();
                    }
                    var shifted = addDays(base, offset * 7);
                    return toISODate(startOfWeek(shifted));
                }

                function ensureWeekStart(isoDate) {
                    var base = parseISODate(isoDate);
                    if (!base) {
                        return toISODate(startOfWeek(new Date()));
                    }
                    return toISODate(startOfWeek(base));
                }

                function createHourEncodeApp(runtime) {
                    var h = runtime.preact.h;
                    var hooks = runtime.hooks;

                    function SelectionForm(props) {
                        var selection = props.selection;
                        if (!selection) {
                            return null;
                        }
                        var labels = props.labels || {};
                        var isEditing = Boolean(selection.isEditing);
                        var canDelete = isEditing && typeof props.onDelete === 'function';
                        var title = isEditing ? (labels.selectionEditTitle || labels.selectionTitle) : labels.selectionTitle;
                        var primaryLabel = isEditing ? (labels.selectionUpdate || labels.selectionConfirm) : labels.selectionConfirm;
                        var taskSuggestions = Array.isArray(props.taskSuggestions) ? props.taskSuggestions : [];
                        var projectSuggestions = Array.isArray(props.projectSuggestions) ? props.projectSuggestions : [];
                        var allProjectOptions = Array.isArray(props.allProjectOptions) ? props.allProjectOptions : projectSuggestions;
                        var taskSuggestionsMessage = isString(props.taskSuggestionsMessage) ? props.taskSuggestionsMessage : '';
                        var projectQuery = normalizeSearchValue(selection.formProject);
                        var projectPool = projectQuery === '' ? projectSuggestions : allProjectOptions;
                        var filteredProjectSuggestions = projectPool.filter(function(project) {
                            if (!isString(project)) {
                                return false;
                            }
                            if (projectQuery === '') {
                                return true;
                            }
                            return normalizeSearchValue(project).indexOf(projectQuery) === 0;
                        });
                        var displayedProjectSuggestions = projectQuery === '' ? filteredProjectSuggestions : uniqueStrings(filteredProjectSuggestions);
                        var taskQuery = normalizeSearchValue(selection.formTask);
                        var displayedTaskSuggestions = taskQuery === '' ? taskSuggestions : taskSuggestions.filter(function(task) {
                            return normalizeSearchValue(task).indexOf(taskQuery) === 0;
                        });

                        return h('form', {
                            className: 'mj-hour-encode-app__card mj-hour-encode-app__card--selection',
                            onSubmit: function(event) {
                                event.preventDefault();
                                props.onSubmit();
                            }
                        }, [
                            h('h2', null, title),
                            labels.selectionDescription ? h('p', { className: 'mj-hour-encode-app__selection-description' }, labels.selectionDescription) : null,
                            h('div', { className: 'mj-hour-encode-app__field' }, [
                                h('label', { htmlFor: selection.id + '-project' }, labels.selectionProjectLabel),
                                h('input', {
                                    id: selection.id + '-project',
                                    type: 'text',
                                    value: selection.formProject,
                                    placeholder: labels.projectPlaceholder || '',
                                    onInput: function(event) {
                                        props.onChange('formProject', event.target.value || '');
                                    }
                                }),
                                displayedProjectSuggestions.length > 0 ? h('div', { className: 'mj-hour-encode-app__selection-suggestions' }, displayedProjectSuggestions.map(function(project) {
                                    return h('button', {
                                        key: project,
                                        type: 'button',
                                        className: 'mj-hour-encode-app__chip mj-hour-encode-app__chip--inline',
                                        onClick: function() {
                                            props.onChange('formProject', project);
                                        }
                                    }, project);
                                })) : null
                            ]),
                            h('div', { className: 'mj-hour-encode-app__field' }, [
                                h('label', { htmlFor: selection.id + '-task' }, labels.selectionTaskLabel),
                                h('input', {
                                    id: selection.id + '-task',
                                    type: 'text',
                                    value: selection.formTask,
                                    placeholder: labels.newTask || '',
                                    onInput: function(event) {
                                        props.onChange('formTask', event.target.value || '');
                                    }
                                }),
                                displayedTaskSuggestions.length > 0 ? h('div', { className: 'mj-hour-encode-app__selection-suggestions' }, displayedTaskSuggestions.map(function(task) {
                                    return h('button', {
                                        key: task,
                                        type: 'button',
                                        className: 'mj-hour-encode-app__chip mj-hour-encode-app__chip--inline',
                                        onClick: function() {
                                            props.onChange('formTask', task);
                                        }
                                    }, task);
                                })) : null,
                                taskSuggestionsMessage ? h('p', { className: 'mj-hour-encode-app__helper-text' }, taskSuggestionsMessage) : null
                            ]),
                            h('div', { className: 'mj-hour-encode-app__field-row' }, [
                                h('div', { className: 'mj-hour-encode-app__field mj-hour-encode-app__field--time' }, [
                                    h('label', { htmlFor: selection.id + '-start' }, labels.selectionStartLabel),
                                    h('input', {
                                        id: selection.id + '-start',
                                        type: 'time',
                                        step: String(SLOT_STEP_MINUTES * 60),
                                        min: pad(HOURS_START) + ':00',
                                        max: pad(HOURS_END - 1) + ':59',
                                        value: selection.formStart,
                                        onInput: function(event) {
                                            props.onChange('formStart', event.target.value || '');
                                        }
                                    })
                                ]),
                                h('div', { className: 'mj-hour-encode-app__field mj-hour-encode-app__field--time' }, [
                                    h('label', { htmlFor: selection.id + '-end' }, labels.selectionEndLabel),
                                    h('input', {
                                        id: selection.id + '-end',
                                        type: 'time',
                                        step: String(SLOT_STEP_MINUTES * 60),
                                        min: pad(HOURS_START) + ':00',
                                        max: pad(HOURS_END - 1) + ':59',
                                        value: selection.formEnd,
                                        onInput: function(event) {
                                            props.onChange('formEnd', event.target.value || '');
                                        }
                                    })
                                ])
                            ]),
                            selection.error ? h('p', { className: 'mj-hour-encode-app__selection-error' }, selection.error) : null,
                            h('div', { className: 'mj-hour-encode-app__selection-actions' }, [
                                canDelete ? h('button', {
                                    type: 'button',
                                    className: 'mj-hour-encode-app__button mj-hour-encode-app__button--danger mj-hour-encode-app__selection-delete',
                                    onClick: function(event) {
                                        event.preventDefault();
                                        props.onDelete();
                                    }
                                }, labels.selectionDelete || 'Supprimer') : null,
                                h('button', {
                                    type: 'button',
                                    className: 'mj-hour-encode-app__button mj-hour-encode-app__button--secondary',
                                    onClick: function(event) {
                                        event.preventDefault();
                                        props.onCancel();
                                    }
                                }, labels.selectionCancel),
                                h('button', {
                                    type: 'submit',
                                    className: 'mj-hour-encode-app__button mj-hour-encode-app__button--primary'
                                }, primaryLabel)
                            ])
                        ]);
                    }

                    function CalendarView(props) {
                        var scrollRef = hooks.useRef(null);
                        var labels = props.labels || {};
                        var initialScrollApplied = hooks.useRef(false);
                        var dragStateRef = hooks.useRef(null);
                        var entryDragRef = hooks.useRef(null);
                        var dayRefsRef = hooks.useRef(Object.create(null));
                        var entryPreviewState = hooks.useState(null);
                        var entryPreview = entryPreviewState[0];
                        var setEntryPreview = entryPreviewState[1];
                        var lastEntryClickBlockedRef = hooks.useRef(false);
                        var scrollbarState = hooks.useState(0);
                        var scrollbarWidth = scrollbarState[0]; 
                        var setScrollbarWidth = scrollbarState[1]; 

                        var rangeStart = HOURS_START * 60;
                        var rangeEnd = HOURS_END * 60;
                        var minDuration = Math.max(SLOT_STEP_MINUTES, MIN_EVENT_HEIGHT);
                        var dayColumnCount = props.days && props.days.length > 0 ? props.days.length : 7;
                        var gridTemplateColumns = 'repeat(' + dayColumnCount + ', minmax(0, 1fr))';

                        var daysByIso = hooks.useMemo(function() {
                            var map = Object.create(null);
                            if (Array.isArray(props.days)) {
                                props.days.forEach(function(day) {
                                    if (day && day.iso) {
                                        map[day.iso] = day;
                                    }
                                });
                            }
                            return map;
                        }, [props.days]);

                        function getEntryKey(source, fallbackId) {
                            if (source && source.hourId) {
                                return 'hour:' + source.hourId;
                            }
                            if (source && source.id) {
                                return 'entry:' + source.id;
                            }
                            return fallbackId || '';
                        }

                        function snapshotDayRects() {
                            var rects = [];
                            var refs = dayRefsRef.current || Object.create(null);
                            Object.keys(refs).forEach(function(dayIso) {
                                var node = refs[dayIso];
                                if (!node || typeof node.getBoundingClientRect !== 'function') {
                                    return;
                                }
                                try {
                                    var rect = node.getBoundingClientRect();
                                    rects.push({
                                        iso: dayIso,
                                        node: node,
                                        rect: rect
                                    });
                                } catch (error) {
                                    // ignore measurement errors
                                }
                            });
                            rects.sort(function(a, b) {
                                return a.rect.left - b.rect.left;
                            });
                            return rects;
                        }

                        function resolvePointerDay(clientX, fallbackIso) {
                            var rects = snapshotDayRects();
                            if (rects.length === 0) {
                                return null;
                            }
                            var match = null;
                            var bestDistance = Infinity;
                            rects.forEach(function(entry) {
                                var rect = entry.rect;
                                if (clientX >= rect.left && clientX <= rect.right) {
                                    match = entry;
                                    bestDistance = 0;
                                    return;
                                }
                                var distance = clientX < rect.left ? rect.left - clientX : clientX - rect.right;
                                if (distance < bestDistance) {
                                    bestDistance = distance;
                                    match = entry;
                                }
                            });
                            if (!match && fallbackIso) {
                                for (var index = 0; index < rects.length; index++) {
                                    if (rects[index].iso === fallbackIso) {
                                        match = rects[index];
                                        break;
                                    }
                                }
                            }
                            return match;
                        }

                        function pointerMinutesForRect(rect, clientY) {
                            if (!rect) {
                                return null;
                            }
                            var offset = clientY - rect.top;
                            if (!Number.isFinite(offset)) {
                                offset = 0;
                            }
                            if (offset < 0) {
                                offset = 0;
                            }
                            if (offset > rect.height) {
                                offset = rect.height;
                            }
                            var minutes = rangeStart + Math.round(offset * MINUTES_PER_PIXEL);
                            if (minutes < rangeStart) {
                                minutes = rangeStart;
                            }
                            if (minutes > rangeEnd) {
                                minutes = rangeEnd;
                            }
                            return minutes;
                        }

                        function updateEntryPreviewState(drag, slot) {
                            if (!drag || !slot || !slot.position) {
                                setEntryPreview(function(previous) {
                                    return previous === null ? previous : null;
                                });
                                return;
                            }
                            setEntryPreview(function(previous) {
                                if (previous && previous.entryKey === drag.entryKey && previous.dayIso === slot.dayIso && previous.top === slot.position.top && previous.height === slot.position.height && previous.mode === drag.mode) {
                                    return previous;
                                }
                                return {
                                    entryKey: drag.entryKey,
                                    dayIso: slot.dayIso,
                                    top: slot.position.top,
                                    height: slot.position.height,
                                    mode: drag.mode,
                                    colors: drag.colors || null
                                };
                            });
                        }

                        function getEntryIntervals(day, excludeEntry) {
                            var intervals = [];
                            if (!day || !Array.isArray(day.events)) {
                                return intervals;
                            }
                            var currentSelection = props.selectedSlot;
                            day.events.forEach(function(eventItem) {
                                if (!eventItem || !eventItem.isEntry) {
                                    return;
                                }
                                var source = eventItem.source || {};
                                if (excludeEntry) {
                                    if (excludeEntry.hourId && source.hourId && excludeEntry.hourId === source.hourId) {
                                        return;
                                    }
                                    if (excludeEntry.entryId && source.id && excludeEntry.entryId === source.id) {
                                        return;
                                    }
                                }
                                if (currentSelection) {
                                    if (currentSelection.hourId && source.hourId && currentSelection.hourId === source.hourId) {
                                        return;
                                    }
                                    if (currentSelection.entryId && source.id && currentSelection.entryId === source.id) {
                                        return;
                                    }
                                }
                                var startDate = parseDateTime(source.start);
                                var endDate = parseDateTime(source.end);
                                if (!isValidDate(startDate) || !isValidDate(endDate)) {
                                    return;
                                }
                                var startMinutes = clamp(minutesSinceMidnight(startDate), rangeStart, rangeEnd);
                                var endMinutes = clamp(minutesSinceMidnight(endDate), rangeStart, rangeEnd);
                                if (endMinutes <= startMinutes) {
                                    return;
                                }
                                intervals.push({
                                    start: startMinutes,
                                    end: endMinutes
                                });
                            });
                            intervals.sort(function(a, b) {
                                return a.start - b.start;
                            });
                            return intervals;
                        }

                        function snapStartToNextFreeMinute(startMinutes, intervals) {
                            var adjusted = startMinutes;
                            for (var index = 0; index < intervals.length; index++) {
                                var interval = intervals[index];
                                if (adjusted >= interval.start && adjusted < interval.end) {
                                    adjusted = interval.end;
                                }
                            }
                            return adjusted;
                        }

                        function limitEndToNextInterval(startMinutes, desiredEndMinutes, intervals) {
                            var limited = desiredEndMinutes;
                            for (var index = 0; index < intervals.length; index++) {
                                var interval = intervals[index];
                                if (interval.start >= startMinutes && interval.start < limited) {
                                    limited = interval.start;
                                    break;
                                }
                            }
                            return limited;
                        }

                        function clampStartMinutes(candidate) {
                            var relative = candidate - rangeStart;
                            var snapped = Math.floor(relative / SLOT_STEP_MINUTES) * SLOT_STEP_MINUTES;
                            if (snapped < 0) {
                                snapped = 0;
                            }
                            var maxRelative = Math.max(0, DAY_RANGE_MINUTES - minDuration);
                            if (snapped > maxRelative) {
                                snapped = maxRelative;
                            }
                            return rangeStart + snapped;
                        }

                        function clampEndMinutes(startMinutes, candidate) {
                            var relative = candidate - rangeStart;
                            var snapped = Math.floor(relative / SLOT_STEP_MINUTES) * SLOT_STEP_MINUTES;
                            if (snapped < 0) {
                                snapped = 0;
                            }
                            var absolute = rangeStart + snapped;
                            if (absolute <= startMinutes) {
                                absolute = startMinutes + minDuration;
                            }
                            if (absolute > rangeEnd) {
                                absolute = rangeEnd;
                            }
                            if (absolute - startMinutes < minDuration) {
                                absolute = Math.min(rangeEnd, startMinutes + minDuration);
                            }
                            return absolute;
                        }

                        function pointerToMinutes(event) {
                            var target = event.currentTarget;
                            if (!target || typeof target.getBoundingClientRect !== 'function') {
                                return null;
                            }
                            var rect = target.getBoundingClientRect();
                            var offset = event.clientY - rect.top;
                            if (!Number.isFinite(offset)) {
                                offset = 0;
                            }
                            if (offset < 0) {
                                offset = 0;
                            }
                            var raw = Math.round(offset * MINUTES_PER_PIXEL);
                            raw = clamp(raw, 0, DAY_RANGE_MINUTES);
                            return rangeStart + raw;
                        }

                        function buildSlot(day, slotId, startMinutes, endMinutes, preserveForm, excludeEntry) {
                            var intervals = getEntryIntervals(day, excludeEntry);
                            var start = clampStartMinutes(startMinutes);
                            start = snapStartToNextFreeMinute(start, intervals);
                            if (start >= rangeEnd) {
                                return null;
                            }
                            var desiredEnd = clampEndMinutes(start, endMinutes);
                            var end = limitEndToNextInterval(start, desiredEnd, intervals);
                            if (end <= start) {
                                end = limitEndToNextInterval(start, start + SLOT_STEP_MINUTES, intervals);
                            }
                            end = clampEndMinutes(start, end);
                            if (end <= start) {
                                return null;
                            }
                            var duration = end - start;
                            var startDate = dateFromDayMinutes(day.date, start);
                            var endDate = dateFromDayMinutes(day.date, end);
                            return {
                                id: slotId,
                                dayIso: day.iso,
                                startIso: startDate.toISOString(),
                                endIso: endDate.toISOString(),
                                durationMinutes: duration,
                                preserveForm: Boolean(preserveForm),
                                position: {
                                    top: (start - rangeStart) / MINUTES_PER_PIXEL,
                                    height: duration / MINUTES_PER_PIXEL
                                }
                            };
                        }

                        function handlePointerDown(event, day) {
                            if (!props.onSlotSelect || !day || (event.button !== undefined && event.button !== 0) || event.isPrimary === false) {
                                return;
                            }
                            if (typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }
                            var absolute = pointerToMinutes(event);
                            if (absolute === null) {
                                return;
                            }
                            var startMinutes = clampStartMinutes(absolute);
                            var defaultEnd = clampEndMinutes(startMinutes, startMinutes + DEFAULT_SLOT_MINUTES);
                            var slotId = 'slot-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
                            var slot = buildSlot(day, slotId, startMinutes, defaultEnd, false, null);
                            if (!slot) {
                                dragStateRef.current = null;
                                return;
                            }
                            var adjustedStartMinutes = slot.position ? rangeStart + Math.round(slot.position.top * MINUTES_PER_PIXEL) : start;
                            dragStateRef.current = {
                                pointerId: event.pointerId,
                                dayIso: day.iso,
                                day: day,
                                slotId: slotId,
                                startMinutes: adjustedStartMinutes
                            };
                            if (event.currentTarget && typeof event.currentTarget.setPointerCapture === 'function') {
                                try {
                                    event.currentTarget.setPointerCapture(event.pointerId);
                                } catch (captureError) {
                                    // ignore capture errors
                                }
                            }
                            props.onSlotSelect(slot);
                        }

                        function extractTouch(event, pointerId) {
                            var list = null;
                            if (event && event.changedTouches && event.changedTouches.length > 0) {
                                list = event.changedTouches;
                            } else if (event && event.touches && event.touches.length > 0) {
                                list = event.touches;
                            }
                            if (!list) {
                                return null;
                            }
                            var identifier = pointerId;
                            for (var index = 0; index < list.length; index++) {
                                var touch = list[index];
                                if (identifier === undefined || touch.identifier === identifier) {
                                    return touch;
                                }
                            }
                            return null;
                        }

                        function createSyntheticPointer(event, touch) {
                            if (!touch) {
                                return null;
                            }
                            return {
                                currentTarget: event.currentTarget,
                                clientY: touch.clientY,
                                pointerId: touch.identifier !== undefined ? touch.identifier : 0,
                                pointerType: 'touch',
                                isPrimary: true,
                                button: 0,
                                preventDefault: function() {
                                    if (event && event.cancelable) {
                                        event.preventDefault();
                                    }
                                }
                            };
                        }

                        function handleTouchStart(event, day) {
                            if (!props.onSlotSelect || !day) {
                                return;
                            }
                            var touch = extractTouch(event);
                            var synthetic = createSyntheticPointer(event, touch);
                            if (!synthetic) {
                                return;
                            }
                            handlePointerDown(synthetic, day);
                        }

                        function handleTouchMove(event, day) {
                            var drag = dragStateRef.current;
                            if (!drag || !day) {
                                return;
                            }
                            var touch = extractTouch(event, drag.pointerId);
                            if (!touch) {
                                return;
                            }
                            var synthetic = createSyntheticPointer(event, touch);
                            if (!synthetic) {
                                return;
                            }
                            handlePointerMove(synthetic, day);
                        }

                        function handleTouchEnd(event) {
                            var drag = dragStateRef.current;
                            if (!drag) {
                                return;
                            }
                            var touch = extractTouch(event, drag.pointerId);
                            if (!touch) {
                                handlePointerRelease({ pointerId: drag.pointerId, preventDefault: function() {} });
                                return;
                            }
                            var synthetic = createSyntheticPointer(event, touch);
                            if (!synthetic) {
                                handlePointerRelease({ pointerId: drag.pointerId, preventDefault: function() {} });
                                return;
                            }
                            handlePointerRelease(synthetic);
                        }

                        function handlePointerMove(event, day) {
                            var drag = dragStateRef.current;
                            if (!drag || drag.pointerId !== event.pointerId || drag.dayIso !== day.iso) {
                                return;
                            }
                            if (typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }
                            var absolute = pointerToMinutes(event);
                            if (absolute === null) {
                                return;
                            }
                            var endMinutes = clampEndMinutes(drag.startMinutes, absolute);
                            var slot = buildSlot(day, drag.slotId, drag.startMinutes, endMinutes, true, null);
                            if (!slot) {
                                return;
                            }
                            props.onSlotSelect(slot);
                        }

                        function handlePointerRelease(event) {
                            var drag = dragStateRef.current;
                            if (!drag || drag.pointerId !== event.pointerId) {
                                return;
                            }
                            if (typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }
                            dragStateRef.current = null;
                            if (event.currentTarget && typeof event.currentTarget.releasePointerCapture === 'function') {
                                try {
                                    event.currentTarget.releasePointerCapture(event.pointerId);
                                } catch (releaseError) {
                                    // ignore release errors
                                }
                            }
                        }

                        function handleEntryPointerDown(event, day, eventItem, mode) {
                            if (typeof event.stopPropagation === 'function') {
                                event.stopPropagation();
                            }
                            if (!eventItem || !eventItem.isEntry) {
                                return;
                            }
                            if ((event.button !== undefined && event.button !== 0) || event.isPrimary === false) {
                                return;
                            }
                            if (typeof props.onEntryDrag !== 'function' || typeof props.onEntryDragEnd !== 'function') {
                                return;
                            }
                            var source = eventItem.source || {};
                            var startDate = parseDateTime(source.start);
                            var endDate = parseDateTime(source.end);
                            if (!isValidDate(startDate) || !isValidDate(endDate)) {
                                return;
                            }
                            if (typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }
                            var startMinutes = clamp(minutesSinceMidnight(startDate), rangeStart, rangeEnd);
                            var endMinutes = clamp(minutesSinceMidnight(endDate), rangeStart, rangeEnd);
                            if (endMinutes <= startMinutes) {
                                endMinutes = Math.min(rangeEnd, startMinutes + SLOT_STEP_MINUTES);
                            }
                            var duration = Math.max(endMinutes - startMinutes, SLOT_STEP_MINUTES);
                            var offsetMinutes = 0;
                            if (mode === 'move') {
                                var entryRect = event.currentTarget && typeof event.currentTarget.getBoundingClientRect === 'function' ? event.currentTarget.getBoundingClientRect() : null;
                                if (entryRect) {
                                    var offsetPixels = event.clientY - entryRect.top;
                                    if (!Number.isFinite(offsetPixels)) {
                                        offsetPixels = 0;
                                    }
                                    if (offsetPixels < 0) {
                                        offsetPixels = 0;
                                    }
                                    if (offsetPixels > entryRect.height) {
                                        offsetPixels = entryRect.height;
                                    }
                                    offsetMinutes = Math.round(offsetPixels * MINUTES_PER_PIXEL);
                                    if (offsetMinutes < 0) {
                                        offsetMinutes = 0;
                                    }
                                    if (offsetMinutes > duration) {
                                        offsetMinutes = duration;
                                    }
                                }
                            }

                            var entryKey = getEntryKey(source, eventItem.id);
                            var excludeEntry = {
                                hourId: source.hourId || null,
                                entryId: source.id || null
                            };

                            var drag = {
                                pointerId: event.pointerId,
                                pointerType: event.pointerType || 'pointer',
                                mode: mode,
                                entry: source,
                                entryKey: entryKey,
                                colors: eventItem.colors || null,
                                day: day,
                                dayIso: day ? day.iso : null,
                                activeDayIso: day ? day.iso : null,
                                startMinutes: startMinutes,
                                endMinutes: endMinutes,
                                duration: duration,
                                offsetMinutes: offsetMinutes,
                                excludeEntry: excludeEntry,
                                slotId: 'entry-slot-' + entryKey,
                                original: {
                                    dayIso: day ? day.iso : null,
                                    startIso: source.start || null,
                                    endIso: source.end || null
                                },
                                hasMoved: false,
                                lastSlot: null,
                                captureTarget: event.currentTarget || null
                            };

                            entryDragRef.current = drag;
                            lastEntryClickBlockedRef.current = false;
                            setEntryPreview(function(previous) {
                                return previous === null ? previous : null;
                            });

                            if (drag.captureTarget && typeof drag.captureTarget.setPointerCapture === 'function') {
                                try {
                                    drag.captureTarget.setPointerCapture(drag.pointerId);
                                } catch (captureError) {
                                    // ignore capture errors
                                }
                            }

                            if (typeof props.onEntryDragStart === 'function') {
                                props.onEntryDragStart(source, {
                                    dayIso: day ? day.iso : null,
                                    event: eventItem,
                                    trigger: event.pointerType || 'pointer'
                                });
                            }
                        }

                        function updateEntryDrag(event) {
                            var drag = entryDragRef.current;
                            if (!drag || drag.pointerId !== event.pointerId) {
                                return;
                            }
                            if (typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }
                            var pointerMeta = resolvePointerDay(event.clientX, drag.activeDayIso);
                            if (!pointerMeta) {
                                return;
                            }
                            var day = daysByIso[pointerMeta.iso] || drag.day;
                            if (!day) {
                                return;
                            }
                            var pointerMinutes = pointerMinutesForRect(pointerMeta.rect, event.clientY);
                            if (pointerMinutes === null) {
                                return;
                            }

                            var candidateStart = drag.startMinutes;
                            var candidateEnd = drag.endMinutes;

                            if (drag.mode === 'move') {
                                candidateStart = pointerMinutes - drag.offsetMinutes;
                                candidateEnd = candidateStart + drag.duration;
                            } else if (drag.mode === 'resize-start') {
                                candidateStart = pointerMinutes;
                                candidateEnd = drag.endMinutes;
                                if (candidateStart > drag.endMinutes - minDuration) {
                                    candidateStart = drag.endMinutes - minDuration;
                                }
                            } else if (drag.mode === 'resize-end') {
                                candidateStart = drag.startMinutes;
                                candidateEnd = pointerMinutes;
                            }

                            var slot = buildSlot(day, drag.slotId, candidateStart, candidateEnd, true, drag.excludeEntry);
                            if (!slot) {
                                return;
                            }

                            drag.day = day;
                            drag.dayIso = day.iso;
                            drag.activeDayIso = day.iso;
                            drag.lastSlot = slot;

                            var slotStartDate = parseDateTime(slot.startIso);
                            var slotEndDate = parseDateTime(slot.endIso);
                            if (isValidDate(slotStartDate)) {
                                drag.startMinutes = clamp(minutesSinceMidnight(slotStartDate), rangeStart, rangeEnd);
                            }
                            if (isValidDate(slotEndDate)) {
                                drag.endMinutes = clamp(minutesSinceMidnight(slotEndDate), rangeStart, rangeEnd);
                            } else {
                                drag.endMinutes = drag.startMinutes + slot.durationMinutes;
                            }
                            drag.duration = Math.max(drag.endMinutes - drag.startMinutes, SLOT_STEP_MINUTES);

                            var moved = drag.original.dayIso !== slot.dayIso || drag.original.startIso !== slot.startIso || drag.original.endIso !== slot.endIso;
                            drag.hasMoved = moved;
                            lastEntryClickBlockedRef.current = moved;

                            updateEntryPreviewState(drag, slot);

                            if (typeof props.onEntryDrag === 'function') {
                                props.onEntryDrag(slot, {
                                    entry: drag.entry,
                                    original: drag.original,
                                    mode: drag.mode,
                                    pointerType: drag.pointerType || event.pointerType || 'pointer'
                                });
                            }
                        }

                        function finalizeEntryDrag(event, canceled) {
                            var drag = entryDragRef.current;
                            if (!drag || drag.pointerId !== event.pointerId) {
                                return;
                            }
                            entryDragRef.current = null;
                            if (drag.captureTarget && typeof drag.captureTarget.releasePointerCapture === 'function') {
                                try {
                                    drag.captureTarget.releasePointerCapture(drag.pointerId);
                                } catch (releaseError) {
                                    // ignore release errors
                                }
                            }
                            if (typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }

                            if (canceled) {
                                setEntryPreview(null);
                                lastEntryClickBlockedRef.current = false;
                                if (typeof props.onEntryDragEnd === 'function') {
                                    props.onEntryDragEnd(null, {
                                        entry: drag.entry,
                                        original: drag.original,
                                        mode: drag.mode,
                                        pointerType: drag.pointerType || event.pointerType || 'pointer',
                                        canceled: true,
                                        clientX: event.clientX,
                                        clientY: event.clientY
                                    });
                                }
                                return;
                            }

                            var finalSlot = drag.lastSlot;
                            if (typeof props.onEntryDragEnd === 'function') {
                                props.onEntryDragEnd(drag.hasMoved ? finalSlot : null, {
                                    entry: drag.entry,
                                    original: drag.original,
                                    mode: drag.mode,
                                    pointerType: drag.pointerType || event.pointerType || 'pointer',
                                    canceled: false,
                                    clientX: event.clientX,
                                    clientY: event.clientY
                                });
                            }

                            setEntryPreview(null);
                            lastEntryClickBlockedRef.current = drag.hasMoved;
                        }

                        function handleEntryPointerMove(event) {
                            updateEntryDrag(event);
                        }

                        function handleEntryPointerUp(event) {
                            finalizeEntryDrag(event, false);
                        }

                        function handleEntryPointerCancel(event) {
                            finalizeEntryDrag(event, true);
                        }

                        hooks.useEffect(function() {
                            if (initialScrollApplied.current) {
                                return;
                            }
                            var node = scrollRef.current;
                            if (!node) {
                                return;
                            }
                            initialScrollApplied.current = true;
                            var viewport = node.clientHeight || SCROLL_VIEWPORT_HEIGHT;
                            var maxOffset = Math.max(0, DAY_CANVAS_HEIGHT - viewport);
                            var desired = Math.round(DEFAULT_VIEW_START_MINUTES / MINUTES_PER_PIXEL);
                            if (desired > maxOffset) {
                                desired = maxOffset;
                            }
                            if (desired < 0) {
                                desired = 0;
                            }
                            node.scrollTop = desired;
                        }, [props.days]);

                        hooks.useEffect(function() {
                            function updateScrollbarWidth() {
                                var node = scrollRef.current;
                                var diff = 0;
                                if (node) {
                                    var offset = node.offsetWidth - node.clientWidth;
                                    if (Number.isFinite(offset) && offset > 0) {
                                        diff = offset;
                                    }
                                }
                                setScrollbarWidth(function(previous) {
                                    if (previous === diff) {
                                        return previous;
                                    }
                                    return diff;
                                });
                            }

                            updateScrollbarWidth();
                            if (typeof window !== 'undefined') {
                                window.addEventListener('resize', updateScrollbarWidth);
                                return function() {
                                    window.removeEventListener('resize', updateScrollbarWidth);
                                };
                            }
                            return function() {};
                        }, [props.days, props.selectedSlot]);

                        var headerCells = props.days.map(function(day) {
                            var headerClass = 'mj-hour-encode-calendar__day-header';
                            if (day.isWeekend) {
                                headerClass += ' is-weekend';
                            }
                            if (day.isToday) {
                                headerClass += ' is-today';
                            }
                            return h('div', { key: day.iso, className: headerClass }, [
                                h('span', { className: 'mj-hour-encode-calendar__day-name' }, day.label.short),
                                h('span', { className: 'mj-hour-encode-calendar__day-date' }, day.label.date)
                            ]);
                        });

                        // Mapping des noms de jours vers les numéros de jour (0=dimanche, 1=lundi, etc.)
                        var dayNameToWeekday = {
                            sunday: 0,
                            monday: 1,
                            tuesday: 2,
                            wednesday: 3,
                            thursday: 4,
                            friday: 5,
                            saturday: 6
                        };

                        // Fonction pour calculer la position d'une plage horaire
                        function getWorkSlotPosition(slot) {
                            if (!slot || !slot.start || !slot.end) {
                                return null;
                            }
                            var startParts = slot.start.split(':');
                            var endParts = slot.end.split(':');
                            if (startParts.length < 2 || endParts.length < 2) {
                                return null;
                            }
                            var startMinutes = parseInt(startParts[0], 10) * 60 + parseInt(startParts[1], 10);
                            var endMinutes = parseInt(endParts[0], 10) * 60 + parseInt(endParts[1], 10);
                            if (endMinutes <= startMinutes) {
                                return null;
                            }
                            var breakMinutes = parseInt(slot.break_minutes || 0, 10);
                            var workMinutes = (endMinutes - startMinutes) - breakMinutes;
                            var top = (startMinutes - rangeStart) / MINUTES_PER_PIXEL;
                            var height = (endMinutes - startMinutes) / MINUTES_PER_PIXEL;
                            return { top: top, height: height, startMinutes: startMinutes, endMinutes: endMinutes, workMinutes: Math.max(0, workMinutes) };
                        }

                        // Fonction pour calculer les heures contractuelles d'un jour
                        function getDayContractualMinutes(weekday) {
                            if (!Array.isArray(props.workSchedule) || props.workSchedule.length === 0) {
                                return 0;
                            }
                            var totalMinutes = 0;
                            props.workSchedule.forEach(function(slot) {
                                var slotWeekday = dayNameToWeekday[slot.day];
                                if (slotWeekday !== weekday) {
                                    return;
                                }
                                var position = getWorkSlotPosition(slot);
                                if (position && position.workMinutes > 0) {
                                    totalMinutes += position.workMinutes;
                                }
                            });
                            return totalMinutes;
                        }

                        // Calculer le total des heures contractuelles de la semaine
                        var weekContractualMinutes = hooks.useMemo(function() {
                            if (!Array.isArray(props.workSchedule) || props.workSchedule.length === 0) {
                                return 0;
                            }
                            var total = 0;
                            props.workSchedule.forEach(function(slot) {
                                var position = getWorkSlotPosition(slot);
                                if (position && position.workMinutes > 0) {
                                    total += position.workMinutes;
                                }
                            });
                            return total;
                        }, [props.workSchedule]);

                        var dayColumns = props.days.map(function(day) {
                            var dayClass = 'mj-hour-encode-calendar__day';
                            if (day.isWeekend) {
                                dayClass += ' is-weekend';
                            }
                            if (day.isToday) {
                                dayClass += ' is-today';
                            }

                            var canvasChildren = [];

                            // Afficher les plages horaires contractuelles (work schedule)
                            if (Array.isArray(props.workSchedule) && props.workSchedule.length > 0) {
                                props.workSchedule.forEach(function(slot, slotIndex) {
                                    var slotWeekday = dayNameToWeekday[slot.day];
                                    if (slotWeekday !== day.weekday) {
                                        return;
                                    }
                                    var position = getWorkSlotPosition(slot);
                                    if (!position) {
                                        return;
                                    }
                                    canvasChildren.push(h('div', {
                                        key: 'work-slot-' + slotIndex,
                                        className: 'mj-hour-encode-calendar__work-slot',
                                        style: {
                                            top: position.top + 'px',
                                            height: position.height + 'px'
                                        },
                                        title: 'Plage contractuelle : ' + slot.start + ' - ' + slot.end
                                    }));
                                });
                            }

                            if (props.selectedSlot && props.selectedSlot.dayIso === day.iso) {
                                var selectionAttrs = {
                                    key: 'selection',
                                    className: 'mj-hour-encode-calendar__selection',
                                    style: {
                                        top: props.selectedSlot.position.top + 'px',
                                        height: props.selectedSlot.position.height + 'px'
                                    }
                                };
                                if (props.selectedSlot.startTime) {
                                    selectionAttrs['data-start-time'] = props.selectedSlot.startTime;
                                }
                                if (props.selectedSlot.endTime) {
                                    selectionAttrs['data-end-time'] = props.selectedSlot.endTime;
                                }
                                var selectionChildren = [];
                                if (props.selectedSlot.duration) {
                                    selectionChildren.push(h('div', {
                                        key: 'duration',
                                        className: 'mj-hour-encode-calendar__selection-duration'
                                    }, props.selectedSlot.duration));
                                }
                                canvasChildren.push(h('div', selectionAttrs, selectionChildren));
                            }

                            day.events.forEach(function(eventItem) {
                                var tooltipContent = null;
                                if (eventItem.tooltip) {
                                    var tooltip = eventItem.tooltip;
                                    var tooltipStyle = tooltip.accent ? {
                                        borderColor: tooltip.accent,
                                        background: '#ffffff',
                                        boxShadow: '0 18px 36px ' + hexToRgba(tooltip.accent, 0.18)
                                    } : {};
                                    var detailChildren = [];
                                    if (tooltip.time) {
                                        detailChildren.push(h('span', {
                                            key: 'time',
                                            className: 'mj-hour-encode-calendar__event-tooltip-time',
                                            style: tooltip.accent ? { color: tooltip.accent } : undefined
                                        }, tooltip.time));
                                    }
                                    if (tooltip.title) {
                                        detailChildren.push(h('span', {
                                            key: 'title',
                                            className: 'mj-hour-encode-calendar__event-tooltip-title'
                                        }, tooltip.title));
                                    }
                                    if (tooltip.location) {
                                        detailChildren.push(h('span', {
                                            key: 'location',
                                            className: 'mj-hour-encode-calendar__event-tooltip-location'
                                        }, tooltip.location));
                                    }
                                    tooltipContent = h('div', {
                                        className: 'mj-hour-encode-calendar__event-tooltip',
                                        style: tooltipStyle
                                    }, [
                                        tooltip.cover ? h('div', { className: 'mj-hour-encode-calendar__event-tooltip-cover' }, [
                                            h('img', {
                                                src: tooltip.cover,
                                                alt: tooltip.title || tooltip.location || tooltip.time || '',
                                                loading: 'lazy',
                                                width: 150,
                                                height: 150
                                            })
                                        ]) : null,
                                        h('div', { className: 'mj-hour-encode-calendar__event-tooltip-details' }, detailChildren)
                                    ]);
                                }

                                var isEntry = eventItem.kind === 'entry' || eventItem.isEntry;
                                var eventClass = 'mj-hour-encode-calendar__event';
                                if (isEntry) {
                                    eventClass += ' mj-hour-encode-calendar__event--entry';
                                }
                                var isCompactHeight = eventItem.position && eventItem.position.height < 34;
                                if (isCompactHeight) {
                                    eventClass += ' is-compact';
                                }

                                var entrySource = isEntry && eventItem.source && typeof eventItem.source === 'object' ? eventItem.source : null;

                                var eventChildren = [];
                                if (isEntry) {
                                    var durationMinutes = 0;
                                    if (Number.isFinite(eventItem.durationMinutes)) {
                                        durationMinutes = Math.max(0, Math.round(eventItem.durationMinutes));
                                    } else if (eventItem.position && Number.isFinite(eventItem.position.height)) {
                                        durationMinutes = Math.max(0, Math.round(eventItem.position.height * MINUTES_PER_PIXEL));
                                    } else if (entrySource && entrySource.start && entrySource.end) {
                                        var entryStart = parseDateTime(entrySource.start);
                                        var entryEnd = parseDateTime(entrySource.end);
                                        if (isValidDate(entryStart) && isValidDate(entryEnd) && entryEnd > entryStart) {
                                            durationMinutes = Math.round((entryEnd - entryStart) / 60000);
                                        }
                                    }
                                    if (durationMinutes > 0) {
                                        var entryDurationLabel = formatTotalMinutes(durationMinutes, labels);
                                        eventChildren.push(h('div', {
                                            key: eventItem.id + '-duration',
                                            className: 'mj-hour-encode-calendar__event-duration'
                                        }, entryDurationLabel));
                                    }
                                }
                                if (isEntry && !isCompactHeight) {
                                    var entryTitle = eventItem.title || (entrySource && entrySource.task) || '';
                                    var entryProject = entrySource && entrySource.project ? entrySource.project : '';
                                    if (entryTitle) {
                                        eventChildren.push(h('span', {
                                            key: eventItem.id + '-title',
                                            className: 'mj-hour-encode-calendar__event-label',
                                            title: entryTitle
                                        }, entryTitle));
                                    }
                                    if (entryProject) {
                                        eventChildren.push(h('span', {
                                            key: eventItem.id + '-project',
                                            className: 'mj-hour-encode-calendar__event-subtitle',
                                            title: entryProject
                                        }, entryProject));
                                    }
                                }

                                if (tooltipContent) {
                                    eventChildren.push(tooltipContent);
                                }

                                var eventColors = eventItem.colors && typeof eventItem.colors === 'object' ? eventItem.colors : {};
                                var borderColor = eventColors.border || '#2a55ff';
                                var backgroundColor = eventColors.background || hexToRgba(borderColor, 0.82);
                                var textColor = isEntry
                                    ? 'rgba(26, 45, 71, 0.92)'
                                    : borderColor;

                                var entryKey = isEntry ? getEntryKey(entrySource, eventItem.id) : null;
                                var previewActive = isEntry && entryPreview && entryPreview.entryKey === entryKey;
                                var previewInDay = previewActive && entryPreview.dayIso === day.iso;

                                if (isEntry) {
                                    eventChildren.unshift(h('span', {
                                        key: eventItem.id + '-handle-top',
                                        className: 'mj-hour-encode-calendar__event-handle mj-hour-encode-calendar__event-handle--top',
                                        role: 'presentation',
                                        'aria-hidden': 'true',
                                        onPointerDown: function(ev) {
                                            handleEntryPointerDown(ev, day, eventItem, 'resize-start');
                                        }
                                    }));
                                    eventChildren.push(h('span', {
                                        key: eventItem.id + '-handle-bottom',
                                        className: 'mj-hour-encode-calendar__event-handle mj-hour-encode-calendar__event-handle--bottom',
                                        role: 'presentation',
                                        'aria-hidden': 'true',
                                        onPointerDown: function(ev) {
                                            handleEntryPointerDown(ev, day, eventItem, 'resize-end');
                                        }
                                    }));
                                }

                                if (previewActive) {
                                    eventClass += ' is-dragging';
                                }

                                var eventStyle = {
                                    top: eventItem.position.top + 'px',
                                    height: eventItem.position.height + 'px',
                                    backgroundColor: backgroundColor,
                                    borderColor: borderColor,
                                    color: textColor
                                };
                                if (previewActive) {
                                    eventStyle.opacity = previewInDay ? 0.28 : 0.18;
                                }

                                var eventProps = {
                                    key: eventItem.id,
                                    className: eventClass,
                                    style: eventStyle,
                                    'aria-label': eventItem.tooltipLabel || eventItem.title || '',
                                    tabIndex: 0,
                                    role: 'button'
                                };

                                if (isEntry) {
                                    eventProps.onPointerDown = function(ev) {
                                        handleEntryPointerDown(ev, day, eventItem, 'move');
                                    };
                                    eventProps.onPointerMove = handleEntryPointerMove;
                                    eventProps.onPointerUp = handleEntryPointerUp;
                                    eventProps.onPointerCancel = handleEntryPointerCancel;
                                    eventProps.onLostPointerCapture = handleEntryPointerCancel;
                                } else {
                                    eventProps.onPointerDown = function(ev) {
                                        if (ev && typeof ev.stopPropagation === 'function') {
                                            ev.stopPropagation();
                                        }
                                    };
                                }

                                eventProps.onClick = function(ev) {
                                    if (ev && typeof ev.stopPropagation === 'function') {
                                        ev.stopPropagation();
                                    }
                                    if (!isEntry || typeof props.onEntrySelect !== 'function') {
                                        return;
                                    }
                                    if (entryDragRef.current || lastEntryClickBlockedRef.current) {
                                        if (ev && typeof ev.preventDefault === 'function') {
                                            ev.preventDefault();
                                        }
                                        lastEntryClickBlockedRef.current = false;
                                        return;
                                    }
                                    if (ev && typeof ev.preventDefault === 'function') {
                                        ev.preventDefault();
                                    }
                                    props.onEntrySelect(entrySource || eventItem.source, {
                                        dayIso: day.iso,
                                        event: eventItem,
                                        trigger: 'mouse'
                                    });
                                };

                                eventProps.onTouchEnd = function(ev) {
                                    if (!isEntry || typeof props.onEntrySelect !== 'function') {
                                        return;
                                    }
                                    if (entryDragRef.current || lastEntryClickBlockedRef.current) {
                                        if (ev && typeof ev.preventDefault === 'function') {
                                            ev.preventDefault();
                                        }
                                        if (ev && typeof ev.stopPropagation === 'function') {
                                            ev.stopPropagation();
                                        }
                                        lastEntryClickBlockedRef.current = false;
                                        return;
                                    }
                                    if (ev && typeof ev.preventDefault === 'function') {
                                        ev.preventDefault();
                                    }
                                    if (ev && typeof ev.stopPropagation === 'function') {
                                        ev.stopPropagation();
                                    }
                                    props.onEntrySelect(entrySource || eventItem.source, {
                                        dayIso: day.iso,
                                        event: eventItem,
                                        trigger: 'touch'
                                    });
                                };

                                eventProps.onKeyDown = function(ev) {
                                    if (!isEntry || typeof props.onEntrySelect !== 'function') {
                                        return;
                                    }
                                    if (ev.key === 'Enter' || ev.key === ' ') {
                                        ev.preventDefault();
                                        props.onEntrySelect(entrySource || eventItem.source, {
                                            dayIso: day.iso,
                                            event: eventItem,
                                            trigger: 'keyboard'
                                        });
                                    }
                                };

                                canvasChildren.push(h('div', eventProps, eventChildren));
                            });

                            if (entryPreview && entryPreview.dayIso === day.iso) {
                                var previewClass = 'mj-hour-encode-calendar__entry-preview';
                                if (entryPreview.mode === 'resize-start' || entryPreview.mode === 'resize-end') {
                                    previewClass += ' is-resizing';
                                } else if (entryPreview.mode === 'move') {
                                    previewClass += ' is-moving';
                                }
                                var previewStyle = {
                                    top: entryPreview.top + 'px',
                                    height: entryPreview.height + 'px'
                                };
                                if (entryPreview.colors) {
                                    if (entryPreview.colors.border) {
                                        previewStyle.borderColor = entryPreview.colors.border;
                                    }
                                    if (entryPreview.colors.background) {
                                        previewStyle.backgroundColor = entryPreview.colors.background;
                                    }
                                }
                                canvasChildren.push(h('div', {
                                    key: 'entry-preview-' + entryPreview.entryKey,
                                    className: previewClass,
                                    style: previewStyle
                                }));
                            }

                            var hourLabels = props.hourMarks.map(function(mark, index) {
                                return h('div', {
                                    key: 'hour-' + index,
                                    className: 'mj-hour-encode-calendar__day-hour-label',
                                    style: {
                                        top: (index * 60 / MINUTES_PER_PIXEL) + 'px'
                                    }
                                }, mark);
                            });

                            // Calculer les heures contractuelles du jour
                            var dayContractualMinutes = getDayContractualMinutes(day.weekday);
                            var dayContractualLabel = dayContractualMinutes > 0 ? formatTotalMinutes(dayContractualMinutes, labels) : null;

                            return h('div', { key: day.iso, className: dayClass }, [
                                h('div', {
                                    className: 'mj-hour-encode-calendar__day-body'
                                }, [
                                    h('div', {
                                        className: 'mj-hour-encode-calendar__day-canvas',
                                        style: { height: DAY_CANVAS_HEIGHT + 'px' },
                                        ref: function(node) {
                                            if (!dayRefsRef.current) {
                                                dayRefsRef.current = Object.create(null);
                                            }
                                            if (node) {
                                                dayRefsRef.current[day.iso] = node;
                                            } else if (dayRefsRef.current[day.iso]) {
                                                delete dayRefsRef.current[day.iso];
                                            }
                                        },
                                        onPointerDown: function(ev) {
                                            handlePointerDown(ev, day);
                                        },
                                        onPointerMove: function(ev) {
                                            handlePointerMove(ev, day);
                                        },
                                        onPointerUp: handlePointerRelease,
                                        onPointerCancel: handlePointerRelease,
                                        onLostPointerCapture: handlePointerRelease,
                                        onTouchStart: function(ev) {
                                            handleTouchStart(ev, day);
                                        },
                                        onTouchMove: function(ev) {
                                            handleTouchMove(ev, day);
                                        },
                                        onTouchEnd: handleTouchEnd,
                                        onTouchCancel: handleTouchEnd
                                    }, [hourLabels].concat(canvasChildren))
                                ]),
                                dayContractualLabel ? h('div', {
                                    className: 'mj-hour-encode-calendar__day-footer'
                                }, [
                                    h('span', { className: 'mj-hour-encode-calendar__day-contractual' }, dayContractualLabel)
                                ]) : null
                            ]);
                        });

                        var headerGridStyle = {
                            gridTemplateColumns: gridTemplateColumns
                        };
                        if (scrollbarWidth > 0) {
                            headerGridStyle.paddingRight = scrollbarWidth + 'px';
                        }

                        return h('div', { className: 'mj-hour-encode-app__card mj-hour-encode-app__card--calendar' }, [
                            h('div', { className: 'mj-hour-encode-calendar' }, [
                                h('div', { className: 'mj-hour-encode-calendar__header' }, [
                                    h('div', { className: 'mj-hour-encode-calendar__timeline-header' }),
                                    h('div', {
                                        className: 'mj-hour-encode-calendar__header-grid',
                                        style: headerGridStyle
                                    }, headerCells)
                                ]),
                                h('div', {
                                    className: 'mj-hour-encode-calendar__content',
                                    
                                    ref: function(node) {
                                        scrollRef.current = node;
                                    }
                                }, [
                                    h('div', { className: 'mj-hour-encode-calendar__timeline-column' }, [
                                        h('div', { className: 'mj-hour-encode-calendar__timeline-track', style: { height: DAY_CANVAS_HEIGHT + 'px' } }, props.hourMarks.map(function(mark) {
                                            return h('span', { key: mark }, mark);
                                        }))
                                    ]),
                                    h('div', { className: 'mj-hour-encode-calendar__grid', style: { gridTemplateColumns: gridTemplateColumns } }, dayColumns)
                                ]),
                                !props.loading && !props.hasEvents ? h('div', { className: 'mj-hour-encode-app__empty-text' }, props.emptyLabel) : null
                            ])
                        ]);
                    }

                    function MiniCalendar(props) {
                        var model = props.model;
                        if (!model) {
                            return null;
                        }
                        var labels = props.labels || {};
                        var locale = props.locale || 'fr';

                        function handleNavigate(offset) {
                            if (typeof props.onNavigate === 'function') {
                                props.onNavigate(offset);
                            }
                        }

                        function handleSelectWeek(weekIso) {
                            if (typeof props.onSelectWeek === 'function') {
                                props.onSelectWeek(weekIso);
                            }
                        }

                        function buildWeekLabel(weekIso) {
                            var weekDate = parseISODate(weekIso);
                            if (!weekDate) {
                                return '';
                            }
                            return buildPeriodLabel(labels.weekRange || 'Semaine du %s au %s', locale, weekDate);
                        }

                        var weekdaysRow = model.weekdayLabels.map(function(label, index) {
                            return h('span', {
                                key: 'weekday-' + index,
                                className: 'mj-hour-encode-mini-calendar__weekday'
                            }, label);
                        });
                        weekdaysRow.push(h('span', {
                            key: 'weekday-total',
                            className: 'mj-hour-encode-mini-calendar__weekday mj-hour-encode-mini-calendar__weekday--totals'
                        }, labels.calendarWeekHours || labels.totalWeek || 'Heures'));

                        var weeksRows = model.weeks.map(function(week) {
                            var weekClass = 'mj-hour-encode-mini-calendar__week' + (week.isActive ? ' is-active' : '');
                            var ariaLabel = buildWeekLabel(week.startIso);
                            var dayButtons = week.days.map(function(day) {
                                var dayClass = 'mj-hour-encode-mini-calendar__day';
                                if (!day.inMonth) {
                                    dayClass += ' is-outside';
                                }
                                if (day.isToday) {
                                    dayClass += ' is-today';
                                }
                                if (week.isActive) {
                                    dayClass += ' is-active-week';
                                }
                                return h('button', {
                                    key: day.iso,
                                    type: 'button',
                                    className: dayClass,
                                    'data-week-iso': week.startIso,
                                    'aria-pressed': week.isActive ? 'true' : 'false',
                                    'aria-label': ariaLabel,
                                    title: ariaLabel,
                                    onClick: function(event) {
                                        event.preventDefault();
                                        handleSelectWeek(week.startIso);
                                    }
                                }, day.label);
                            });
                            
                            // Construire l'affichage du total avec différentiel
                            var totalChildren = [week.totalLabel];
                            if (week.hasHours && week.differenceLabel && model.weekContractualMinutes > 0) {
                                var diffClass = 'mj-hour-encode-mini-calendar__week-diff';
                                if (week.differenceMinutes >= 0) {
                                    diffClass += ' is-positive';
                                } else {
                                    diffClass += ' is-negative';
                                }
                                totalChildren.push(h('span', {
                                    key: 'diff',
                                    className: diffClass
                                }, week.differenceLabel));
                            }
                            
                            dayButtons.push(h('span', {
                                key: 'total',
                                className: 'mj-hour-encode-mini-calendar__week-total' + (week.isActive ? ' is-active' : ''),
                                title: ariaLabel
                            }, totalChildren));
                            return h('div', { key: week.startIso, className: weekClass }, dayButtons);
                        });

                        return h('div', { className: 'mj-hour-encode-app__card mj-hour-encode-app__card--calendar' }, [
                            h('div', { className: 'mj-hour-encode-mini-calendar' }, [
                                h('div', { className: 'mj-hour-encode-mini-calendar__header' }, [
                                    h('button', {
                                        type: 'button',
                                        className: 'mj-hour-encode-mini-calendar__nav-btn',
                                        'aria-label': labels.calendarPrevious || 'Mois précédent',
                                        onClick: function(event) {
                                            event.preventDefault();
                                            handleNavigate(-1);
                                        }
                                    }, '‹'),
                                    h('span', { className: 'mj-hour-encode-mini-calendar__month' }, model.monthLabel),
                                    h('button', {
                                        type: 'button',
                                        className: 'mj-hour-encode-mini-calendar__nav-btn',
                                        'aria-label': labels.calendarNext || 'Mois suivant',
                                        onClick: function(event) {
                                            event.preventDefault();
                                            handleNavigate(1);
                                        }
                                    }, '›')
                                ]),
                                h('div', { className: 'mj-hour-encode-mini-calendar__weekdays' }, weekdaysRow),
                                h('div', { className: 'mj-hour-encode-mini-calendar__weeks' }, weeksRows)
                            ])
                        ]);
                    }

                    function SidePanel(props) {
                        var items = [];

                        if (props.calendarModel) {
                            items.push(h(MiniCalendar, {
                                key: 'calendar',
                                model: props.calendarModel,
                                labels: props.labels,
                                locale: props.locale,
                                onNavigate: props.onCalendarMonthNavigate,
                                onSelectWeek: props.onCalendarWeekSelect
                            }));
                        }

                        if (props.selection) {
                            items.push(h(SelectionForm, {
                                key: 'selection',
                                selection: props.selection,
                                labels: props.labels,
                                locale: props.locale,
                                durationLabel: props.durationLabel,
                                taskSuggestions: props.taskSuggestions,
                                taskSuggestionsMessage: props.taskSuggestionsMessage,
                                projectSuggestions: props.projectSuggestions,
                                allProjectOptions: props.allProjectOptions,
                                onChange: props.onSelectionChange,
                                onSubmit: props.onSelectionSubmit,
                                onCancel: props.onSelectionCancel,
                                onDelete: props.onSelectionDelete
                            }));
                        }

                        if (items.length === 0) {
                            return null;
                        }

                        return h('aside', { className: 'mj-hour-encode-app__sidebar' }, items);
                    }

                    function computeTopLabels(entriesList, field, fallbackList, limit) {
                        var max = typeof limit === 'number' && limit > 0 ? limit : 4;
                        var counts = Object.create(null);
                        if (Array.isArray(entriesList)) {
                            entriesList.forEach(function(item) {
                                if (!item || typeof item !== 'object') {
                                    return;
                                }
                                var value = item[field];
                                if (!isString(value)) {
                                    return;
                                }
                                var trimmed = value.trim();
                                if (!trimmed) {
                                    return;
                                }
                                counts[trimmed] = (counts[trimmed] || 0) + 1;
                            });
                        }

                        var sorted = Object.keys(counts).sort(function(a, b) {
                            if (counts[b] === counts[a]) {
                                return a.localeCompare(b);
                            }
                            return counts[b] - counts[a];
                        });

                        var result = [];
                        var seen = Object.create(null);

                        sorted.forEach(function(label) {
                            if (result.length >= max) {
                                return;
                            }
                            seen[label] = true;
                            result.push(label);
                        });

                        if (Array.isArray(fallbackList)) {
                            fallbackList.forEach(function(item) {
                                if (result.length >= max) {
                                    return;
                                }
                                if (!isString(item)) {
                                    return;
                                }
                                var trimmed = item.trim();
                                if (!trimmed || seen[trimmed]) {
                                    return;
                                }
                                seen[trimmed] = true;
                                result.push(trimmed);
                            });
                        }

                        return result;
                    }

                    function ResourceSection(props) {
                        var rawProjectSummaries = Array.isArray(props.projectSummaries) ? props.projectSummaries : [];
                        var projectCatalog = Array.isArray(props.projectCatalog) ? props.projectCatalog : [];

                        function normalizeProjectTasks(tasksList, labels) {
                            if (!Array.isArray(tasksList) || tasksList.length === 0) {
                                return [];
                            }
                            return tasksList.map(function(taskItem) {
                                if (!taskItem || typeof taskItem !== 'object') {
                                    return null;
                                }
                                var taskKey = isString(taskItem.key) && taskItem.key !== ''
                                    ? taskItem.key
                                    : (isString(taskItem.name) ? taskItem.name : '');
                                var taskName = isString(taskItem.name) && taskItem.name !== ''
                                    ? taskItem.name
                                    : taskKey;
                                var taskMinutes = Number(taskItem.totalMinutes || 0);
                                if (!Number.isFinite(taskMinutes) || taskMinutes < 0) {
                                    taskMinutes = 0;
                                }
                                return {
                                    key: taskKey || taskName,
                                    name: taskName,
                                    totalMinutes: taskMinutes,
                                    totalLabel: formatTotalMinutes(taskMinutes, labels)
                                };
                            }).filter(Boolean);
                        }

                        function areProjectSummariesEqual(first, second) {
                            if (first === second) {
                                return true;
                            }
                            if (!first || !second) {
                                return false;
                            }
                            if (first.key !== second.key || first.label !== second.label || first.value !== second.value) {
                                return false;
                            }
                            if (Number(first.totalMinutes || 0) !== Number(second.totalMinutes || 0)) {
                                return false;
                            }
                            if (Number(first.lifetimeMinutes || 0) !== Number(second.lifetimeMinutes || 0)) {
                                return false;
                            }
                            if (Number(first.weekMinutes || 0) !== Number(second.weekMinutes || 0)) {
                                return false;
                            }
                            if (Number(first.monthMinutes || 0) !== Number(second.monthMinutes || 0)) {
                                return false;
                            }
                            if (Number(first.yearMinutes || 0) !== Number(second.yearMinutes || 0)) {
                                return false;
                            }
                            if (first.hasWeekActivity !== second.hasWeekActivity) {
                                return false;
                            }
                            if (first.totalLabel !== second.totalLabel || first.lifetimeLabel !== second.lifetimeLabel) {
                                return false;
                            }
                            if (first.weekLabel !== second.weekLabel || first.monthLabel !== second.monthLabel || first.yearLabel !== second.yearLabel) {
                                return false;
                            }
                            var tasksA = Array.isArray(first.tasks) ? first.tasks : [];
                            var tasksB = Array.isArray(second.tasks) ? second.tasks : [];
                            if (tasksA.length !== tasksB.length) {
                                return false;
                            }
                            for (var taskIndex = 0; taskIndex < tasksA.length; taskIndex++) {
                                var taskA = tasksA[taskIndex];
                                var taskB = tasksB[taskIndex];
                                if (!taskA || !taskB) {
                                    return false;
                                }
                                if (taskA.key !== taskB.key || taskA.name !== taskB.name) {
                                    return false;
                                }
                                if (Number(taskA.totalMinutes || 0) !== Number(taskB.totalMinutes || 0)) {
                                    return false;
                                }
                                if (taskA.totalLabel !== taskB.totalLabel) {
                                    return false;
                                }
                            }
                            return true;
                        }

                        var projectsCacheRef = hooks.useRef(Object.create(null));
                        var projectsVersionState = hooks.useState(0);
                        var projectsVersion = projectsVersionState[0];
                        var setProjectsVersion = projectsVersionState[1];

                        hooks.useEffect(function() {
                            var cache = projectsCacheRef.current;
                            if (!cache || typeof cache !== 'object') {
                                cache = Object.create(null);
                                projectsCacheRef.current = cache;
                            }
                            var changed = false;

                            if (Array.isArray(rawProjectSummaries)) {
                                rawProjectSummaries.forEach(function(item) {
                                    if (!item || !item.key) {
                                        return;
                                    }
                                    var key = item.key;
                                    var normalizedValue = normalizeProjectValue(item.value || '');
                                    var labelValue = isString(item.label) && item.label !== ''
                                        ? item.label
                                        : (normalizedValue || (props.labels.projectWithoutLabel || 'Sans projet'));
                                    var totalMinutesValue = Number(item.totalMinutes || 0);
                                    if (!Number.isFinite(totalMinutesValue) || totalMinutesValue < 0) {
                                        totalMinutesValue = 0;
                                    }
                                    var weekMinutesValue = Number(item.weekMinutes || totalMinutesValue || 0);
                                    if (!Number.isFinite(weekMinutesValue) || weekMinutesValue < 0) {
                                        weekMinutesValue = totalMinutesValue;
                                    }
                                    var monthMinutesValue = Number(item.monthMinutes || weekMinutesValue || 0);
                                    if (!Number.isFinite(monthMinutesValue) || monthMinutesValue < 0) {
                                        monthMinutesValue = weekMinutesValue;
                                    }
                                    var yearMinutesValue = Number(item.yearMinutes || monthMinutesValue || 0);
                                    if (!Number.isFinite(yearMinutesValue) || yearMinutesValue < 0) {
                                        yearMinutesValue = monthMinutesValue;
                                    }
                                    var lifetimeMinutesValue = Number(item.lifetimeMinutes || yearMinutesValue || 0);
                                    if (!Number.isFinite(lifetimeMinutesValue) || lifetimeMinutesValue < 0) {
                                        lifetimeMinutesValue = Math.max(totalMinutesValue, yearMinutesValue, monthMinutesValue, weekMinutesValue, 0);
                                    }
                                    lifetimeMinutesValue = Math.max(lifetimeMinutesValue, totalMinutesValue);
                                    lifetimeMinutesValue = Math.max(lifetimeMinutesValue, yearMinutesValue);
                                    lifetimeMinutesValue = Math.max(lifetimeMinutesValue, monthMinutesValue);
                                    lifetimeMinutesValue = Math.max(lifetimeMinutesValue, weekMinutesValue);
                                    var weekLabelValue = isString(item.weekLabel) && item.weekLabel !== ''
                                        ? item.weekLabel
                                        : formatTotalMinutes(weekMinutesValue, props.labels);
                                    var monthLabelValue = isString(item.monthLabel) && item.monthLabel !== ''
                                        ? item.monthLabel
                                        : formatTotalMinutes(monthMinutesValue, props.labels);
                                    var yearLabelValue = isString(item.yearLabel) && item.yearLabel !== ''
                                        ? item.yearLabel
                                        : formatTotalMinutes(yearMinutesValue, props.labels);
                                    var tasksValue = normalizeProjectTasks(item.tasks, props.labels);

                                    var nextEntry = {
                                        key: key,
                                        label: labelValue,
                                        value: normalizedValue,
                                        totalMinutes: totalMinutesValue,
                                        totalLabel: formatTotalMinutes(totalMinutesValue, props.labels),
                                        lifetimeMinutes: lifetimeMinutesValue,
                                        lifetimeLabel: formatTotalMinutes(lifetimeMinutesValue, props.labels),
                                        tasks: tasksValue,
                                        weekMinutes: weekMinutesValue,
                                        weekLabel: weekLabelValue,
                                        monthMinutes: monthMinutesValue,
                                        monthLabel: monthLabelValue,
                                        yearMinutes: yearMinutesValue,
                                        yearLabel: yearLabelValue,
                                        hasWeekActivity: weekMinutesValue > 0
                                    };

                                    var existingEntry = cache[key];
                                    if (!existingEntry || !areProjectSummariesEqual(existingEntry, nextEntry)) {
                                        cache[key] = nextEntry;
                                        changed = true;
                                    }
                                });
                            }

                            if (Array.isArray(projectCatalog)) {
                                projectCatalog.forEach(function(name) {
                                    if (!isString(name)) {
                                        return;
                                    }
                                    var normalizedName = normalizeProjectValue(name);
                                    var key = ensureProjectKey(normalizedName);
                                    var labelValue = normalizedName || (props.labels.projectWithoutLabel || 'Sans projet');
                                    var existingEntry = cache[key];
                                    if (!existingEntry) {
                                        cache[key] = {
                                            key: key,
                                            label: labelValue,
                                            value: normalizedName,
                                            totalMinutes: 0,
                                            totalLabel: formatTotalMinutes(0, props.labels),
                                            lifetimeMinutes: 0,
                                            lifetimeLabel: formatTotalMinutes(0, props.labels),
                                            tasks: [],
                                            weekMinutes: 0,
                                            weekLabel: formatTotalMinutes(0, props.labels),
                                            monthMinutes: 0,
                                            monthLabel: formatTotalMinutes(0, props.labels),
                                            yearMinutes: 0,
                                            yearLabel: formatTotalMinutes(0, props.labels),
                                            hasWeekActivity: false
                                        };
                                        changed = true;
                                        return;
                                    }
                                    if (existingEntry.label !== labelValue || existingEntry.value !== normalizedName) {
                                        var updatedEntry = Object.assign({}, existingEntry, {
                                            label: labelValue,
                                            value: normalizedName
                                        });
                                        cache[key] = updatedEntry;
                                        changed = true;
                                    }
                                });
                            }

                            var cacheKeys = Object.keys(cache);
                            for (var index = 0; index < cacheKeys.length; index++) {
                                var cacheKey = cacheKeys[index];
                                var entry = cache[cacheKey];
                                if (!entry) {
                                    continue;
                                }

                                var totalMinutesValue = Number(entry.totalMinutes || 0);
                                if (!Number.isFinite(totalMinutesValue) || totalMinutesValue < 0) {
                                    totalMinutesValue = 0;
                                }
                                var weekMinutesValue = Number(entry.weekMinutes || totalMinutesValue);
                                if (!Number.isFinite(weekMinutesValue) || weekMinutesValue < 0) {
                                    weekMinutesValue = totalMinutesValue;
                                }
                                var monthMinutesValue = Number(entry.monthMinutes || weekMinutesValue);
                                if (!Number.isFinite(monthMinutesValue) || monthMinutesValue < 0) {
                                    monthMinutesValue = weekMinutesValue;
                                }
                                var yearMinutesValue = Number(entry.yearMinutes || monthMinutesValue);
                                if (!Number.isFinite(yearMinutesValue) || yearMinutesValue < 0) {
                                    yearMinutesValue = monthMinutesValue;
                                }
                                var lifetimeMinutesValue = Number(entry.lifetimeMinutes || yearMinutesValue);
                                if (!Number.isFinite(lifetimeMinutesValue) || lifetimeMinutesValue < 0) {
                                    lifetimeMinutesValue = Math.max(totalMinutesValue, yearMinutesValue, monthMinutesValue, weekMinutesValue, 0);
                                }
                                lifetimeMinutesValue = Math.max(lifetimeMinutesValue, totalMinutesValue, yearMinutesValue, monthMinutesValue, weekMinutesValue);

                                var formattedTotal = formatTotalMinutes(totalMinutesValue, props.labels);
                                var formattedLifetime = formatTotalMinutes(lifetimeMinutesValue, props.labels);
                                var formattedWeek = formatTotalMinutes(weekMinutesValue, props.labels);
                                var formattedMonth = formatTotalMinutes(monthMinutesValue, props.labels);
                                var formattedYear = formatTotalMinutes(yearMinutesValue, props.labels);

                                var normalizedEntry = entry;
                                var requiresNormalization = entry.totalMinutes !== totalMinutesValue
                                    || entry.lifetimeMinutes !== lifetimeMinutesValue
                                    || entry.weekMinutes !== weekMinutesValue
                                    || entry.monthMinutes !== monthMinutesValue
                                    || entry.yearMinutes !== yearMinutesValue
                                    || entry.totalLabel !== formattedTotal
                                    || entry.lifetimeLabel !== formattedLifetime
                                    || entry.weekLabel !== formattedWeek
                                    || entry.monthLabel !== formattedMonth
                                    || entry.yearLabel !== formattedYear
                                    || entry.hasWeekActivity !== (weekMinutesValue > 0);

                                if (requiresNormalization) {
                                    normalizedEntry = Object.assign({}, entry, {
                                        totalMinutes: totalMinutesValue,
                                        lifetimeMinutes: lifetimeMinutesValue,
                                        weekMinutes: weekMinutesValue,
                                        monthMinutes: monthMinutesValue,
                                        yearMinutes: yearMinutesValue,
                                        totalLabel: formattedTotal,
                                        lifetimeLabel: formattedLifetime,
                                        weekLabel: formattedWeek,
                                        monthLabel: formattedMonth,
                                        yearLabel: formattedYear,
                                        hasWeekActivity: weekMinutesValue > 0
                                    });
                                    cache[cacheKey] = normalizedEntry;
                                    changed = true;
                                }

                                if (Array.isArray(normalizedEntry.tasks) && normalizedEntry.tasks.length > 0) {
                                    var refreshedTasks = normalizeProjectTasks(normalizedEntry.tasks, props.labels);
                                    var existingTasks = normalizedEntry.tasks;
                                    var tasksChanged = existingTasks.length !== refreshedTasks.length;
                                    if (!tasksChanged) {
                                        for (var taskIndex = 0; taskIndex < existingTasks.length; taskIndex++) {
                                            var existingTask = existingTasks[taskIndex];
                                            var refreshedTask = refreshedTasks[taskIndex];
                                            if (!existingTask || !refreshedTask) {
                                                tasksChanged = true;
                                                break;
                                            }
                                            if (existingTask.key !== refreshedTask.key || existingTask.name !== refreshedTask.name) {
                                                tasksChanged = true;
                                                break;
                                            }
                                            if (Number(existingTask.totalMinutes || 0) !== Number(refreshedTask.totalMinutes || 0)) {
                                                tasksChanged = true;
                                                break;
                                            }
                                            if (existingTask.totalLabel !== refreshedTask.totalLabel) {
                                                tasksChanged = true;
                                                break;
                                            }
                                        }
                                    }
                                    if (tasksChanged) {
                                        cache[cacheKey] = Object.assign({}, normalizedEntry, {
                                            tasks: refreshedTasks
                                        });
                                        changed = true;
                                    }
                                }
                            }

                            if (changed) {
                                setProjectsVersion(function(previous) {
                                    return previous + 1;
                                });
                            }
                        }, [rawProjectSummaries, projectCatalog, props.labels]);

                        var projectSummaries = hooks.useMemo(function() {
                            var cache = projectsCacheRef.current;
                            if (!cache || typeof cache !== 'object') {
                                return [];
                            }
                            return Object.keys(cache).map(function(key) {
                                return cache[key];
                            }).filter(Boolean).sort(function(a, b) {
                                var weekDiff = Number(b.weekMinutes || 0) - Number(a.weekMinutes || 0);
                                if (weekDiff !== 0) {
                                    return weekDiff;
                                }
                                return String(a.label || '').localeCompare(String(b.label || ''));
                            });
                        }, [projectsVersion]);
                        var activeKey = props.activeProject || null;
                        var activeProject = null;
                        if (activeKey) {
                            for (var index = 0; index < projectSummaries.length; index++) {
                                if (projectSummaries[index].key === activeKey) {
                                    activeProject = projectSummaries[index];
                                    break;
                                }
                            }
                        }

                        var weekFilterIdRef = hooks.useRef('mj-hour-encode-week-filter-' + Math.floor(Math.random() * 1000000));
                        var weekFilterId = weekFilterIdRef.current;

                        var weekOnlyState = hooks.useState(false);
                        var showWeekOnly = weekOnlyState[0];
                        var setShowWeekOnly = weekOnlyState[1];

                        var projectRenameState = hooks.useState(function() {
                            return {
                                editing: false,
                                value: '',
                                originalValue: '',
                                error: ''
                            };
                        });
                        var projectRename = projectRenameState[0];
                        var setProjectRename = projectRenameState[1];

                        var taskRenameState = hooks.useState(function() {
                            return {
                                key: null,
                                value: '',
                                originalValue: '',
                                error: ''
                            };
                        });
                        var taskRename = taskRenameState[0];
                        var setTaskRename = taskRenameState[1];

                        var dropTargetState = hooks.useState(null);
                        var dropTargetKey = dropTargetState[0];
                        var setDropTargetKey = dropTargetState[1];

                        var draggingTaskState = hooks.useState(null);
                        var draggingTask = draggingTaskState[0];
                        var setDraggingTask = draggingTaskState[1];

                        var dragPositionState = hooks.useState(null);
                        var dragPosition = dragPositionState[0];
                        var setDragPosition = dragPositionState[1];

                        hooks.useEffect(function() {
                            if (projectSummaries.length === 0 && showWeekOnly) {
                                setShowWeekOnly(false);
                            }
                        }, [projectSummaries.length, showWeekOnly]);

                        var visibleProjectSummaries = hooks.useMemo(function() {
                            var list = projectSummaries.slice();
                            if (!showWeekOnly) {
                                return list;
                            }
                            var filtered = list.filter(function(item) {
                                if (!item) {
                                    return false;
                                }
                                if (item.hasWeekActivity === true) {
                                    return true;
                                }
                                var weekly = Number(item.weekMinutes || item.totalMinutes || 0);
                                return Number.isFinite(weekly) && weekly > 0;
                            });
                            if (activeKey) {
                                var exists = filtered.some(function(item) {
                                    return item && item.key === activeKey;
                                });
                                if (!exists) {
                                    for (var index = 0; index < list.length; index++) {
                                        var candidate = list[index];
                                        if (candidate && candidate.key === activeKey) {
                                            filtered.push(candidate);
                                            break;
                                        }
                                    }
                                }
                            }
                            return filtered;
                        }, [projectSummaries, showWeekOnly, activeKey]);

                        // Drag and drop pour déplacer les tâches entre projets
                        hooks.useEffect(function() {
                            if (!draggingTask) {
                                setDragPosition(null);
                                setDropTargetKey(null);
                                return;
                            }

                            function handlePointerMove(event) {
                                setDragPosition({ x: event.clientX, y: event.clientY });
                                
                                var elementUnder = document.elementFromPoint(event.clientX, event.clientY);
                                if (elementUnder) {
                                    var projectPill = elementUnder.closest('.mj-hour-encode-app__project-pill');
                                    if (projectPill) {
                                        var targetKey = projectPill.getAttribute('data-project-key');
                                        if (targetKey && activeProject && targetKey !== activeProject.key) {
                                            setDropTargetKey(targetKey);
                                        } else {
                                            setDropTargetKey(null);
                                        }
                                    } else {
                                        setDropTargetKey(null);
                                    }
                                }
                            }

                            function handlePointerUp(event) {
                                var elementUnder = document.elementFromPoint(event.clientX, event.clientY);
                                if (elementUnder && draggingTask) {
                                    var projectPill = elementUnder.closest('.mj-hour-encode-app__project-pill');
                                    if (projectPill) {
                                        var targetKey = projectPill.getAttribute('data-project-key');
                                        if (targetKey && activeProject && targetKey !== activeProject.key && typeof props.onTaskMoveToProject === 'function') {
                                            var targetProject = visibleProjectSummaries.find(function(p) {
                                                return p.key === targetKey;
                                            });
                                            if (targetProject) {
                                                props.onTaskMoveToProject(draggingTask, activeProject, targetProject);
                                            }
                                        }
                                    }
                                }
                                setDraggingTask(null);
                                setDragPosition(null);
                                setDropTargetKey(null);
                            }

                            document.addEventListener('pointermove', handlePointerMove);
                            document.addEventListener('pointerup', handlePointerUp);

                            return function() {
                                document.removeEventListener('pointermove', handlePointerMove);
                                document.removeEventListener('pointerup', handlePointerUp);
                            };
                        }, [draggingTask, activeProject, visibleProjectSummaries, props.onTaskMoveToProject]);

                        hooks.useEffect(function() {
                            if (activeProject) {
                                setProjectRename({
                                    editing: false,
                                    value: activeProject.value || '',
                                    originalValue: activeProject.value || '',
                                    error: ''
                                });
                            } else {
                                setProjectRename({
                                    editing: false,
                                    value: '',
                                    originalValue: '',
                                    error: ''
                                });
                            }
                            setTaskRename({
                                key: null,
                                value: '',
                                originalValue: '',
                                error: ''
                            });
                        }, [activeProject ? activeProject.key : null]);

                        var renameValidationMessage = props.labels.selectionErrorTask || 'Veuillez saisir un intitulé.';

                        function beginProjectRename() {
                            if (!activeProject) {
                                return;
                            }
                            setProjectRename({
                                editing: true,
                                value: activeProject.value || '',
                                originalValue: activeProject.value || '',
                                error: ''
                            });
                        }

                        function cancelProjectRename() {
                            setProjectRename(function(previous) {
                                return {
                                    editing: false,
                                    value: activeProject ? (activeProject.value || '') : '',
                                    originalValue: activeProject ? (activeProject.value || '') : '',
                                    error: ''
                                };
                            });
                        }

                        function submitProjectRename() {
                            if (!activeProject) {
                                setProjectRename({
                                    editing: false,
                                    value: '',
                                    originalValue: '',
                                    error: ''
                                });
                                return;
                            }
                            var nextValue = normalizeProjectValue(projectRename.value || '');
                            if (nextValue === '') {
                                setProjectRename(function(previous) {
                                    return Object.assign({}, previous, {
                                        error: renameValidationMessage
                                    });
                                });
                                return;
                            }
                            var previousValue = normalizeProjectValue(projectRename.originalValue || activeProject.value || '');
                            if (nextValue === previousValue) {
                                setProjectRename({
                                    editing: false,
                                    value: nextValue,
                                    originalValue: nextValue,
                                    error: ''
                                });
                                return;
                            }
                            if (typeof props.onProjectRename === 'function') {
                                props.onProjectRename({
                                    key: activeProject.key,
                                    projectValue: activeProject.value || '',
                                    previousValue: previousValue,
                                    newValue: nextValue
                                });
                            }
                            setProjectRename({
                                editing: false,
                                value: nextValue,
                                originalValue: nextValue,
                                error: ''
                            });
                        }

                        function beginTaskRename(task) {
                            if (!task) {
                                return;
                            }
                            setTaskRename({
                                key: task.key,
                                value: task.name,
                                originalValue: task.name,
                                error: ''
                            });
                        }

                        function cancelTaskRename() {
                            setTaskRename({
                                key: null,
                                value: '',
                                originalValue: '',
                                error: ''
                            });
                        }

                        function submitTaskRename() {
                            if (!taskRename.key || !activeProject) {
                                cancelTaskRename();
                                return;
                            }
                            var nextValue = normalizeProjectValue(taskRename.value || '');
                            if (nextValue === '') {
                                setTaskRename(function(previous) {
                                    return Object.assign({}, previous, {
                                        error: renameValidationMessage
                                    });
                                });
                                return;
                            }
                            if (nextValue === taskRename.key) {
                                cancelTaskRename();
                                return;
                            }
                            if (typeof props.onTaskRename === 'function') {
                                props.onTaskRename({
                                    projectKey: activeProject.key,
                                    projectValue: activeProject.value || '',
                                    taskKey: taskRename.key,
                                    currentValue: taskRename.value,
                                    previousValue: taskRename.originalValue || taskRename.key,
                                    newValue: nextValue
                                });
                            }
                            cancelTaskRename();
                        }

                        var statsDefinitions = hooks.useMemo(function() {
                            return [
                                { key: 'week', label: props.labels.statsWeek || props.labels.totalWeek || 'Semaine', valueKey: 'week' }
                            ];
                        }, [props.labels]);

                        var cards = [];
                        var hasAnyProjects = projectSummaries.length > 0;
                        var hasVisibleProjects = visibleProjectSummaries.some(function(item) {
                            return Boolean(item);
                        });
                        var emptyProjectsMessage = showWeekOnly && hasAnyProjects
                            ? (props.labels.noWeeklyProjects || props.labels.noProjects)
                            : props.labels.noProjects;

                        cards.push(h('div', { key: 'projects', className: 'mj-hour-encode-app__card' }, [
                            h('div', { className: 'mj-hour-encode-app__card-heading' }, [
                                h('h2', null, props.labels.pinnedProjects),
                                h('label', {
                                    className: 'mj-hour-encode-app__card-toggle' + (!hasAnyProjects ? ' is-disabled' : ''),
                                    htmlFor: weekFilterId,
                                    'aria-disabled': hasAnyProjects ? 'false' : 'true'
                                }, [
                                    h('input', {
                                        id: weekFilterId,
                                        type: 'checkbox',
                                        className: 'mj-hour-encode-app__card-toggle-input',
                                        checked: showWeekOnly,
                                        disabled: !hasAnyProjects,
                                        onChange: function(event) {
                                            var next = Boolean(event && event.target ? event.target.checked : false);
                                            setShowWeekOnly(next);
                                        }
                                    }),
                                    h('span', null, props.labels.weekProjectsOnly || 'Afficher uniquement les projets de la semaine')
                                ])
                            ]),
                            hasVisibleProjects
                                ? h('div', { className: 'mj-hour-encode-app__projects-list' }, visibleProjectSummaries.map(function(summary) {
                                    if (!summary) {
                                        return null;
                                    }
                                    var isActive = activeKey === summary.key;
                                    var isDragging = Boolean(props.draggingEntry) || Boolean(draggingTask);
                                    var isDropTarget = dropTargetKey === summary.key;
                                    var isCurrentProject = activeProject && activeProject.key === summary.key;
                                    var pillClass = 'mj-hour-encode-app__project-pill';
                                    if (isActive) {
                                        pillClass += ' is-active';
                                    }
                                    if (isDragging && !isCurrentProject) {
                                        pillClass += ' is-drop-zone';
                                    }
                                    if (isDropTarget && !isCurrentProject) {
                                        pillClass += ' is-drop-target';
                                    }
                                    return h('button', {
                                        key: summary.key,
                                        type: 'button',
                                        className: pillClass,
                                        'data-project-key': summary.key,
                                        onClick: function() {
                                            if (draggingTask) {
                                                return;
                                            }
                                            if (typeof props.onProjectSelect === 'function') {
                                                props.onProjectSelect(summary);
                                            }
                                        },
                                        onPointerEnter: function() {
                                            if ((props.draggingEntry || draggingTask) && !isCurrentProject) {
                                                setDropTargetKey(summary.key);
                                            }
                                        },
                                        onPointerLeave: function() {
                                            if (dropTargetKey === summary.key) {
                                                setDropTargetKey(null);
                                            }
                                        },
                                        onPointerUp: function(event) {
                                            if (draggingTask && !isCurrentProject && typeof props.onTaskMoveToProject === 'function') {
                                                event.preventDefault();
                                                event.stopPropagation();
                                                props.onTaskMoveToProject(draggingTask, activeProject, summary);
                                                setDraggingTask(null);
                                                setDropTargetKey(null);
                                            } else if (props.draggingEntry && typeof props.onEntryMoveToProject === 'function') {
                                                event.preventDefault();
                                                event.stopPropagation();
                                                props.onEntryMoveToProject(props.draggingEntry, summary);
                                                setDropTargetKey(null);
                                            }
                                        }
                                    }, [
                                        h('div', { className: 'mj-hour-encode-app__project-pill-content' }, [
                                            h('span', { className: 'mj-hour-encode-app__project-pill-name' }, summary.label),
                                            summary.lifetimeLabel ? h('span', { className: 'mj-hour-encode-app__project-pill-total' }, summary.lifetimeLabel) : null
                                        ])
                                    ]);
                                }).filter(Boolean))
                                : h('p', { className: 'mj-hour-encode-app__empty-text' }, emptyProjectsMessage),
                            hasVisibleProjects && !activeKey
                                ? h('p', { className: 'mj-hour-encode-app__helper-text' }, props.labels.selectProjectForTasks)
                                : null
                        ]));

                        if (activeProject) {
                            var tasksList = Array.isArray(activeProject.tasks) ? activeProject.tasks : [];
                            cards.push(h('div', { key: 'tasks', className: 'mj-hour-encode-app__card' }, [
                                h('h2', null, props.labels.suggestedTasks),
                                h('div', { className: 'mj-hour-encode-app__project-tasks-header' }, [
                                    h('div', { className: 'mj-hour-encode-app__project-tasks-header-main' }, [
                                        projectRename.editing
                                            ? h('div', { className: 'mj-hour-encode-app__inline-edit' }, [
                                                h('input', {
                                                    type: 'text',
                                                    className: 'mj-hour-encode-app__inline-input',
                                                    value: projectRename.value,
                                                    placeholder: props.labels.selectionProjectLabel || '',
                                                    onInput: function(event) {
                                                        var next = event.target.value || '';
                                                        setProjectRename(function(previous) {
                                                            return {
                                                                editing: true,
                                                                value: next,
                                                                originalValue: previous.originalValue || '',
                                                                error: ''
                                                            };
                                                        });
                                                    },
                                                    onKeyDown: function(event) {
                                                        if (event.key === 'Enter') {
                                                            event.preventDefault();
                                                            submitProjectRename();
                                                        } else if (event.key === 'Escape') {
                                                            event.preventDefault();
                                                            cancelProjectRename();
                                                        }
                                                    }
                                                }),
                                                h('div', { className: 'mj-hour-encode-app__inline-actions' }, [
                                                    h('button', {
                                                        type: 'button',
                                                        className: 'mj-hour-encode-app__inline-action is-confirm mj-hour-encode-app__inline-action--icon',
                                                        'aria-label': props.labels.selectionConfirm || 'Valider',
                                                        title: props.labels.selectionConfirm || 'Valider',
                                                        onClick: function(event) {
                                                            event.preventDefault();
                                                            submitProjectRename();
                                                        }
                                                    }, h('svg', { width: '16', height: '16', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2.5', strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                                        h('polyline', { points: '20 6 9 17 4 12' })
                                                    ])),
                                                    h('button', {
                                                        type: 'button',
                                                        className: 'mj-hour-encode-app__inline-action is-cancel mj-hour-encode-app__inline-action--icon',
                                                        'aria-label': props.labels.selectionCancel || 'Annuler',
                                                        title: props.labels.selectionCancel || 'Annuler',
                                                        onClick: function(event) {
                                                            event.preventDefault();
                                                            cancelProjectRename();
                                                        }
                                                    }, h('svg', { width: '16', height: '16', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2.5', strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                                        h('line', { x1: '18', y1: '6', x2: '6', y2: '18' }),
                                                        h('line', { x1: '6', y1: '6', x2: '18', y2: '18' })
                                                    ]))
                                                ]),
                                                projectRename.error ? h('p', { className: 'mj-hour-encode-app__helper-text is-error' }, projectRename.error) : null
                                            ])
                                            : h('div', { className: 'mj-hour-encode-app__project-title-row' }, [
                                                h('span', {
                                                    className: 'mj-hour-encode-app__project-tasks-header-name mj-hour-encode-app__editable-label',
                                                    role: 'button',
                                                    tabIndex: 0,
                                                    onClick: function(event) {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        beginProjectRename();
                                                    },
                                                    onKeyDown: function(event) {
                                                        if (event.key === 'Enter' || event.key === ' ') {
                                                            event.preventDefault();
                                                            event.stopPropagation();
                                                            beginProjectRename();
                                                        }
                                                    }
                                                }, activeProject.label)
                                            ])
                                    ]),
                                    h('div', { className: 'mj-hour-encode-app__project-tasks-header-totals' }, [
                                        h('span', { className: 'mj-hour-encode-app__project-week-total' }, (props.labels.totalWeek || 'Total semaine') + ' : ' + activeProject.weekLabel),
                                        h('span', { className: 'mj-hour-encode-app__project-month-total' }, (props.labels.totalMonth || 'Total mois') + ' : ' + activeProject.monthLabel),
                                        h('span', { className: 'mj-hour-encode-app__project-year-total' }, (props.labels.totalYear || 'Total année') + ' : ' + activeProject.yearLabel),
                                        h('span', { className: 'mj-hour-encode-app__project-lifetime-total' }, (props.labels.totalLifetime || 'Total cumulé') + ' : ' + activeProject.lifetimeLabel)
                                    ])
                                ]),
                                tasksList.length > 0
                                    ? h('div', { className: 'mj-hour-encode-app__project-tasks' }, tasksList.map(function(task) {
                                        var isEditingTask = taskRename.key === task.key;
                                        if (isEditingTask) {
                                            return h('div', { key: task.key, className: 'mj-hour-encode-app__project-task is-editing' }, [
                                                h('input', {
                                                    type: 'text',
                                                    className: 'mj-hour-encode-app__inline-input',
                                                    value: taskRename.value,
                                                    placeholder: props.labels.selectionTaskLabel || '',
                                                    onInput: function(event) {
                                                        var next = event.target.value || '';
                                                        setTaskRename(function(previous) {
                                                            return {
                                                                key: previous.key,
                                                                value: next,
                                                                originalValue: previous.originalValue || '',
                                                                error: ''
                                                            };
                                                        });
                                                    },
                                                    onKeyDown: function(event) {
                                                        if (event.key === 'Enter') {
                                                            event.preventDefault();
                                                            submitTaskRename();
                                                        } else if (event.key === 'Escape') {
                                                            event.preventDefault();
                                                            cancelTaskRename();
                                                        }
                                                    }
                                                }),
                                                h('div', { className: 'mj-hour-encode-app__inline-actions' }, [
                                                    h('button', {
                                                        type: 'button',
                                                        className: 'mj-hour-encode-app__inline-action is-confirm mj-hour-encode-app__inline-action--icon',
                                                        'aria-label': props.labels.selectionConfirm || 'Valider',
                                                        title: props.labels.selectionConfirm || 'Valider',
                                                        onClick: function(event) {
                                                            event.preventDefault();
                                                            submitTaskRename();
                                                        }
                                                    }, h('svg', { width: '16', height: '16', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2.5', strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                                        h('polyline', { points: '20 6 9 17 4 12' })
                                                    ])),
                                                    h('button', {
                                                        type: 'button',
                                                        className: 'mj-hour-encode-app__inline-action is-cancel mj-hour-encode-app__inline-action--icon',
                                                        'aria-label': props.labels.selectionCancel || 'Annuler',
                                                        title: props.labels.selectionCancel || 'Annuler',
                                                        onClick: function(event) {
                                                            event.preventDefault();
                                                            cancelTaskRename();
                                                        }
                                                    }, h('svg', { width: '16', height: '16', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2.5', strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                                        h('line', { x1: '18', y1: '6', x2: '6', y2: '18' }),
                                                        h('line', { x1: '6', y1: '6', x2: '18', y2: '18' })
                                                    ]))
                                                ]),
                                                taskRename.error ? h('p', { className: 'mj-hour-encode-app__helper-text is-error' }, taskRename.error) : null
                                            ]);
                                        }
                                        return h('div', {
                                            key: task.key,
                                            className: 'mj-hour-encode-app__project-task',
                                            role: 'button',
                                            tabIndex: 0,
                                            onClick: function() {
                                                if (typeof props.onTaskSelect === 'function') {
                                                    props.onTaskSelect({
                                                        task: task.name,
                                                        projectValue: activeProject.value,
                                                        projectKey: activeProject.key
                                                    });
                                                }
                                            },
                                            onKeyDown: function(event) {
                                                if (event.key === 'Enter' || event.key === ' ') {
                                                    event.preventDefault();
                                                    if (typeof props.onTaskSelect === 'function') {
                                                        props.onTaskSelect({
                                                            task: task.name,
                                                            projectValue: activeProject.value,
                                                            projectKey: activeProject.key
                                                        });
                                                    }
                                                }
                                            }
                                        }, [
                                            h('div', { className: 'mj-hour-encode-app__project-task-info' }, [
                                                h('span', {
                                                    className: 'mj-hour-encode-app__project-task-drag-icon' + (draggingTask && draggingTask.key === task.key ? ' is-dragging' : ''),
                                                    'aria-hidden': 'true',
                                                    title: props.labels.dragToMove || 'Glisser vers un projet',
                                                    onPointerDown: function(event) {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        setDraggingTask({
                                                            key: task.key,
                                                            name: task.name,
                                                            projectKey: activeProject.key,
                                                            projectValue: activeProject.value
                                                        });
                                                        setDragPosition({ x: event.clientX, y: event.clientY });
                                                    }
                                                }, [
                                                    h('svg', {
                                                        width: '16',
                                                        height: '16',
                                                        viewBox: '0 0 16 16',
                                                        fill: 'currentColor'
                                                    }, [
                                                        h('circle', { cx: '5', cy: '4', r: '1.5' }),
                                                        h('circle', { cx: '11', cy: '4', r: '1.5' }),
                                                        h('circle', { cx: '5', cy: '8', r: '1.5' }),
                                                        h('circle', { cx: '11', cy: '8', r: '1.5' }),
                                                        h('circle', { cx: '5', cy: '12', r: '1.5' }),
                                                        h('circle', { cx: '11', cy: '12', r: '1.5' })
                                                    ])
                                                ]),
                                                h('span', {
                                                    className: 'mj-hour-encode-app__project-task-name mj-hour-encode-app__editable-label',
                                                    role: 'button',
                                                    tabIndex: 0,
                                                    onClick: function(event) {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        beginTaskRename(task);
                                                    },
                                                    onKeyDown: function(event) {
                                                        if (event.key === 'Enter' || event.key === ' ') {
                                                            event.preventDefault();
                                                            event.stopPropagation();
                                                            beginTaskRename(task);
                                                        }
                                                    }
                                                }, task.name)
                                            ]),
                                            h('div', { className: 'mj-hour-encode-app__project-task-controls' }, [
                                                h('span', { className: 'mj-hour-encode-app__project-task-total' }, task.totalLabel)
                                            ])
                                        ]);
                                    }))
                                    : h('p', { className: 'mj-hour-encode-app__empty-text' }, props.labels.projectTasksEmpty)
                            ]));
                        }

                        if (props.miniCalendarModel) {
                            cards.push(h(MiniCalendar, {
                                key: 'mini-calendar',
                                model: props.miniCalendarModel,
                                labels: props.labels,
                                locale: props.locale,
                                onNavigate: props.onCalendarNavigate,
                                onSelectWeek: props.onCalendarWeekSelect
                            }));
                        }

                        var dragGhost = null;
                        if (draggingTask && dragPosition) {
                            dragGhost = h('div', {
                                className: 'mj-hour-encode-app__drag-ghost',
                                style: {
                                    position: 'fixed',
                                    left: (dragPosition.x + 12) + 'px',
                                    top: (dragPosition.y - 12) + 'px',
                                    pointerEvents: 'none',
                                    zIndex: 10000
                                }
                            }, [
                                h('div', { className: 'mj-hour-encode-app__drag-ghost-content' }, [
                                    h('span', { className: 'mj-hour-encode-app__drag-ghost-icon' }, [
                                        h('svg', { width: '14', height: '14', viewBox: '0 0 16 16', fill: 'currentColor' }, [
                                            h('circle', { cx: '5', cy: '4', r: '1.5' }),
                                            h('circle', { cx: '11', cy: '4', r: '1.5' }),
                                            h('circle', { cx: '5', cy: '8', r: '1.5' }),
                                            h('circle', { cx: '11', cy: '8', r: '1.5' }),
                                            h('circle', { cx: '5', cy: '12', r: '1.5' }),
                                            h('circle', { cx: '11', cy: '12', r: '1.5' })
                                        ])
                                    ]),
                                    h('span', { className: 'mj-hour-encode-app__drag-ghost-label' }, draggingTask.name)
                                ])
                            ]);
                        }

                        return h('div', { className: 'mj-hour-encode-app__resources' }, [cards, dragGhost]);
                    }

                    function HourEncodeApp(props) {
                        var config = props.config;
                        var weekAction = config && config.ajax ? (config.ajax.weekAction || config.ajax.action || '') : '';
                        var canRequest = !config.isPreview
                            && config.capabilities
                            && config.capabilities.canManage
                            && config.ajax
                            && config.ajax.url
                            && weekAction;

                        var initialWeek = ensureWeekStart(config.weekStart);

                        var weekState = hooks.useState(initialWeek);
                        var weekStart = weekState[0];
                        var setWeekStart = weekState[1];

                        var calendarMonthState = hooks.useState(function() {
                            var parsed = parseISODate(initialWeek);
                            if (!parsed) {
                                parsed = new Date();
                            }
                            return toISODate(startOfMonth(parsed));
                        });
                        var calendarMonth = calendarMonthState[0];
                        var setCalendarMonth = calendarMonthState[1];

                        var entriesState = hooks.useState(Array.isArray(config.entries) ? config.entries : []);
                        var entries = entriesState[0];
                        var setEntries = entriesState[1];

                        var eventsState = hooks.useState(Array.isArray(config.events) ? config.events : []);
                        var events = eventsState[0];
                        var setEvents = eventsState[1];

                        var knownTasksState = hooks.useState(uniqueStrings(config.commonTasks));
                        var knownTasks = knownTasksState[0];
                        var setKnownTasks = knownTasksState[1];

                        var projectsState = hooks.useState(uniqueStrings(config.projects));
                        var projects = projectsState[0];
                        var setProjects = projectsState[1];

                        var activeProjectState = hooks.useState(null);
                        var activeProjectKey = activeProjectState[0];
                        var setActiveProjectKey = activeProjectState[1];

                        var projectHistoryState = hooks.useState(function() {
                            return Object.create(null);
                        });
                        var projectHistory = projectHistoryState[0];
                        var setProjectHistory = projectHistoryState[1];

                        var projectTotalsState = hooks.useState(function() {
                            return normalizeProjectTotalsList(config.projectTotals);
                        });
                        var projectTotals = projectTotalsState[0];
                        var setProjectTotals = projectTotalsState[1];

                        var loadingState = hooks.useState(false);
                        var loading = loadingState[0];
                        var setLoading = loadingState[1];

                        var errorState = hooks.useState('');
                        var error = errorState[0];
                        var setError = errorState[1];

                        var selectionState = hooks.useState(null);
                        var selectedSlot = selectionState[0];
                        var setSelectedSlot = selectionState[1];

                        var mobileModeState = hooks.useState(function() {
                            if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
                                try {
                                    return window.matchMedia(MOBILE_BREAKPOINT_QUERY).matches;
                                } catch (mediaError) {
                                    return false;
                                }
                            }
                            return false;
                        });
                        var isMobileLayout = mobileModeState[0];
                        var setIsMobileLayout = mobileModeState[1];

                        var activeMobileDayState = hooks.useState(null);
                        var activeMobileDay = activeMobileDayState[0];
                        var setActiveMobileDay = activeMobileDayState[1];

                        var draggingEntryState = hooks.useState(null);
                        var draggingEntry = draggingEntryState[0];
                        var setDraggingEntry = draggingEntryState[1];

                        var fetchControllerRef = hooks.useRef(null);
                        var hasFetchedInitial = hooks.useRef(false);
                        var pendingDragSubmitRef = hooks.useRef(null);

                        var calendarModel = hooks.useMemo(function() {
                            return buildCalendarModel(config, weekStart, entries, events);
                        }, [config, weekStart, entries, events]);

                        var hourMarks = hooks.useMemo(function() {
                            return HOUR_MARKS;
                        }, []);

                        var generalTaskSuggestions = hooks.useMemo(function() {
                            return computeTopLabels(entries, 'task', knownTasks, 6);
                        }, [entries, knownTasks]);

                        var projectSelectionSuggestions = hooks.useMemo(function() {
                            return computeTopLabels(entries, 'project', projects, 4);
                        }, [entries, projects]);

                        var projectSummaryMap = hooks.useMemo(function() {
                            return computeProjectSummaries(entries);
                        }, [entries]);

                        hooks.useEffect(function() {
                            if (!projectSummaryMap) {
                                return;
                            }
                            var currentWeek = ensureWeekStart(weekStart);
                            setProjectHistory(function(previous) {
                                var base = previous || Object.create(null);
                                var next = base;
                                var changed = false;
                                Object.keys(projectSummaryMap).forEach(function(key) {
                                    var summary = projectSummaryMap[key];
                                    if (!summary) {
                                        return;
                                    }
                                    var normalizedProject = normalizeProjectValue(summary.project);
                                    var sourceTasks = summary.tasks || Object.create(null);
                                    var existing = base[key];
                                    var mergedTasks = Object.create(null);
                                    Object.keys(sourceTasks).forEach(function(taskName) {
                                        mergedTasks[taskName] = sourceTasks[taskName];
                                    });
                                    if (existing && existing.tasks) {
                                        Object.keys(existing.tasks).forEach(function(taskName) {
                                            var existingValue = existing.tasks[taskName];
                                            if (!Object.prototype.hasOwnProperty.call(mergedTasks, taskName) || mergedTasks[taskName] < existingValue) {
                                                mergedTasks[taskName] = existingValue;
                                            }
                                        });
                                    }
                                    var weeks = Object.create(null);
                                    if (existing && existing.weeks) {
                                        Object.keys(existing.weeks).forEach(function(iso) {
                                            weeks[iso] = existing.weeks[iso];
                                        });
                                    }
                                    if (currentWeek) {
                                        weeks[currentWeek] = summary.totalMinutes || 0;
                                    }
                                    var lifetimeMinutes = 0;
                                    Object.keys(weeks).forEach(function(iso) {
                                        lifetimeMinutes += Number(weeks[iso] || 0);
                                    });
                                    var previousLifetime = existing && Number.isFinite(existing.lifetimeMinutes) ? existing.lifetimeMinutes : 0;
                                    var shouldUpdate = !existing
                                        || existing.project !== normalizedProject
                                        || !areTaskMapsEqual(mergedTasks, existing.tasks)
                                        || !areNumberMapsEqual(weeks, existing.weeks)
                                        || previousLifetime !== lifetimeMinutes;
                                    if (shouldUpdate) {
                                        if (!changed) {
                                            next = Object.assign(Object.create(null), base);
                                            changed = true;
                                        }
                                        next[key] = {
                                            project: normalizedProject,
                                            tasks: mergedTasks,
                                            weeks: weeks,
                                            lifetimeMinutes: lifetimeMinutes
                                        };
                                    } else if (changed) {
                                        next[key] = existing;
                                    }
                                });
                                return changed ? next : base;
                            });
                        }, [projectSummaryMap, setProjectHistory, weekStart]);

                        var combinedProjectSummaries = hooks.useMemo(function() {
                            var result = Object.create(null);
                            var history = projectHistory || Object.create(null);
                            Object.keys(history).forEach(function(key) {
                                var stored = history[key] || {};
                                result[key] = {
                                    project: stored.project || '',
                                    totalMinutes: 0,
                                    lifetimeMinutes: Number.isFinite(stored.lifetimeMinutes) ? stored.lifetimeMinutes : 0,
                                    tasks: Object.assign(Object.create(null), stored.tasks || {}),
                                    weeks: Object.assign(Object.create(null), stored.weeks || {}),
                                    months: Object.assign(Object.create(null), stored.months || {}),
                                    years: Object.assign(Object.create(null), stored.years || {}),
                                    serverTotalMinutes: Number.isFinite(stored.serverTotalMinutes) ? stored.serverTotalMinutes : undefined,
                                    weeklyLifetime: Number.isFinite(stored.weeklyLifetime) ? stored.weeklyLifetime : undefined
                                };
                            });
                            var totalsMap = projectTotals || Object.create(null);
                            Object.keys(totalsMap).forEach(function(key) {
                                var totals = totalsMap[key];
                                if (!totals) {
                                    return;
                                }
                                if (!result[key]) {
                                    result[key] = {
                                        project: normalizeProjectValue(totals.project || ''),
                                        totalMinutes: 0,
                                        lifetimeMinutes: 0,
                                        tasks: Object.create(null),
                                        weeks: Object.create(null),
                                        months: Object.create(null),
                                        years: Object.create(null),
                                        serverTotalMinutes: undefined,
                                        weeklyLifetime: undefined
                                    };
                                }
                                var target = result[key];
                                if (totals.project) {
                                    target.project = normalizeProjectValue(totals.project);
                                }
                                if (!target.months) {
                                    target.months = Object.create(null);
                                }
                                if (!target.years) {
                                    target.years = Object.create(null);
                                }
                                if (!target.weeks) {
                                    target.weeks = Object.create(null);
                                }
                                var totalsMonths = totals.months || {};
                                Object.keys(totalsMonths).forEach(function(monthKey) {
                                    var monthValue = Number(totalsMonths[monthKey] || 0);
                                    if (!Number.isFinite(monthValue)) {
                                        return;
                                    }
                                    target.months[monthKey] = Math.max(0, Math.round(monthValue));
                                });
                                var totalsYears = totals.years || {};
                                Object.keys(totalsYears).forEach(function(yearKey) {
                                    var yearValue = Number(totalsYears[yearKey] || 0);
                                    if (!Number.isFinite(yearValue)) {
                                        return;
                                    }
                                    target.years[yearKey] = Math.max(0, Math.round(yearValue));
                                });
                                var totalsWeeks = totals.weeks || {};
                                Object.keys(totalsWeeks).forEach(function(weekKey) {
                                    var weekValue = Number(totalsWeeks[weekKey] || 0);
                                    if (!Number.isFinite(weekValue) || weekValue <= 0) {
                                        return;
                                    }
                                    var roundedWeek = Math.max(0, Math.round(weekValue));
                                    var currentWeekValue = Number(target.weeks[weekKey] || 0);
                                    if (!Number.isFinite(currentWeekValue) || currentWeekValue < roundedWeek) {
                                        target.weeks[weekKey] = roundedWeek;
                                    }
                                });
                                var aggregatedWeekTotal = 0;
                                Object.keys(target.weeks).forEach(function(weekIso) {
                                    var minutes = Number(target.weeks[weekIso] || 0);
                                    if (!Number.isFinite(minutes) || minutes < 0) {
                                        return;
                                    }
                                    aggregatedWeekTotal += minutes;
                                });
                                if (aggregatedWeekTotal > 0 || Number.isFinite(target.weeklyLifetime)) {
                                    target.weeklyLifetime = Math.max(0, Math.round(aggregatedWeekTotal));
                                    if (!Number.isFinite(target.lifetimeMinutes) || target.lifetimeMinutes < target.weeklyLifetime) {
                                        target.lifetimeMinutes = target.weeklyLifetime;
                                    }
                                }
                                if (!target.tasks) {
                                    target.tasks = Object.create(null);
                                }
                                var totalsTasks = totals.tasks || {};
                                Object.keys(totalsTasks).forEach(function(taskName) {
                                    var minutes = Number(totalsTasks[taskName] || 0);
                                    if (!Number.isFinite(minutes) || minutes <= 0) {
                                        return;
                                    }
                                    var normalizedTaskName = normalizeProjectValue(taskName);
                                    if (!normalizedTaskName) {
                                        return;
                                    }
                                    var existingMinutes = Number(target.tasks[normalizedTaskName] || 0);
                                    if (!Number.isFinite(existingMinutes) || existingMinutes < minutes) {
                                        target.tasks[normalizedTaskName] = Math.max(0, Math.round(minutes));
                                    }
                                });
                                var totalMinutesFromServer = Number(totals.totalMinutes || 0);
                                if (Number.isFinite(totalMinutesFromServer) && totalMinutesFromServer >= 0) {
                                    var roundedServerTotal = Math.round(totalMinutesFromServer);
                                    target.serverTotalMinutes = roundedServerTotal;
                                    if (!Number.isFinite(target.lifetimeMinutes) || target.lifetimeMinutes < roundedServerTotal) {
                                        target.lifetimeMinutes = roundedServerTotal;
                                    }
                                }
                            });
                            var current = projectSummaryMap || Object.create(null);
                            Object.keys(current).forEach(function(key) {
                                var summary = current[key];
                                if (!summary) {
                                    return;
                                }
                                if (!result[key]) {
                                    var initialMinutes = Number(summary.totalMinutes || 0);
                                    if (!Number.isFinite(initialMinutes) || initialMinutes < 0) {
                                        initialMinutes = 0;
                                    }
                                    result[key] = {
                                        project: normalizeProjectValue(summary.project),
                                        totalMinutes: initialMinutes,
                                        lifetimeMinutes: initialMinutes,
                                        tasks: Object.assign(Object.create(null), summary.tasks || {}),
                                        weeks: Object.create(null),
                                        months: Object.create(null),
                                        years: Object.create(null),
                                        serverTotalMinutes: initialMinutes,
                                        weeklyLifetime: initialMinutes
                                    };
                                    return;
                                }
                                var target = result[key];
                                if (summary.project) {
                                    target.project = normalizeProjectValue(summary.project);
                                }
                                var totalMinutes = Number(summary.totalMinutes || 0);
                                if (!Number.isFinite(totalMinutes) || totalMinutes < 0) {
                                    totalMinutes = 0;
                                }
                                target.totalMinutes = totalMinutes;
                                if (!target.tasks) {
                                    target.tasks = Object.create(null);
                                }
                                var summaryTasks = summary.tasks || Object.create(null);
                                Object.keys(summaryTasks).forEach(function(taskName) {
                                    var taskMinutes = Number(summaryTasks[taskName] || 0);
                                    if (!Number.isFinite(taskMinutes)) {
                                        return;
                                    }
                                    var normalizedTaskName = normalizeProjectValue(taskName);
                                    if (!normalizedTaskName) {
                                        return;
                                    }
                                    var existingTaskMinutes = Number(target.tasks[normalizedTaskName] || 0);
                                    if (!Number.isFinite(existingTaskMinutes) || existingTaskMinutes < taskMinutes) {
                                        target.tasks[normalizedTaskName] = Math.max(0, Math.round(taskMinutes));
                                    }
                                });
                                if (!target.weeks) {
                                    target.weeks = Object.create(null);
                                }
                                if (summary.totalMinutes !== undefined && summary.totalMinutes !== null) {
                                    var weekIso = ensureWeekStart(weekStart);
                                    if (weekIso) {
                                        target.weeks[weekIso] = totalMinutes;
                                        var lifetimeFromWeeks = 0;
                                        Object.keys(target.weeks).forEach(function(iso) {
                                            lifetimeFromWeeks += Number(target.weeks[iso] || 0);
                                        });
                                        target.weeklyLifetime = lifetimeFromWeeks;
                                        var serverTotal = Number(target.serverTotalMinutes || 0);
                                        if (!Number.isFinite(serverTotal) || serverTotal < 0) {
                                            serverTotal = 0;
                                        }
                                        var combinedLifetime = Math.max(serverTotal, lifetimeFromWeeks);
                                        target.lifetimeMinutes = combinedLifetime;
                                        target.serverTotalMinutes = combinedLifetime;
                                    }
                                }
                                if (target.serverTotalMinutes !== undefined && target.lifetimeMinutes !== undefined) {
                                    var serverTotalValue = Number(target.serverTotalMinutes || 0);
                                    if (!Number.isFinite(serverTotalValue) || serverTotalValue < 0) {
                                        serverTotalValue = 0;
                                    }
                                    if (target.lifetimeMinutes < serverTotalValue) {
                                        target.lifetimeMinutes = serverTotalValue;
                                    }
                                }
                            });
                            return result;
                        }, [projectHistory, projectSummaryMap, projectTotals, weekStart]);

                        var weekTotalsMap = hooks.useMemo(function() {
                            var totals = Object.create(null);
                            var summaryMap = combinedProjectSummaries || Object.create(null);
                            Object.keys(summaryMap).forEach(function(key) {
                                var summary = summaryMap[key];
                                if (!summary || !summary.weeks) {
                                    return;
                                }
                                Object.keys(summary.weeks).forEach(function(weekIso) {
                                    var minutes = Number(summary.weeks[weekIso] || 0);
                                    if (!Number.isFinite(minutes) || minutes <= 0) {
                                        return;
                                    }
                                    var rounded = Math.max(0, Math.round(minutes));
                                    totals[weekIso] = (totals[weekIso] || 0) + rounded;
                                });
                            });
                            var activeWeekIso = ensureWeekStart(weekStart);
                            if (activeWeekIso) {
                                var activeMinutes = Number(calendarModel.totalMinutes || 0);
                                if (!Number.isFinite(activeMinutes) || activeMinutes < 0) {
                                    activeMinutes = 0;
                                }
                                if (activeMinutes > 0 || !Object.prototype.hasOwnProperty.call(totals, activeWeekIso)) {
                                    totals[activeWeekIso] = Math.max(0, Math.round(activeMinutes));
                                }
                            }
                            return totals;
                        }, [combinedProjectSummaries, calendarModel.totalMinutes, weekStart]);

                        var projectSummaryDetails = hooks.useMemo(function() {
                            var summaryMap = combinedProjectSummaries;
                            var seen = Object.create(null);
                            var ordered = [];
                            var currentWeekIso = ensureWeekStart(weekStart);

                            var baseProjects = Array.isArray(projects) ? projects : [];
                            baseProjects.forEach(function(projectName) {
                                var key = ensureProjectKey(projectName);
                                if (seen[key]) {
                                    return;
                                }
                                var summary = summaryMap[key] || null;
                                ordered.push(buildProjectSummaryItem(key, projectName, summary, config.labels, currentWeekIso));
                                seen[key] = true;
                            });

                            Object.keys(summaryMap).forEach(function(key) {
                                if (seen[key]) {
                                    return;
                                }
                                var summary = summaryMap[key];
                                var projectValue = summary ? summary.project : '';
                                ordered.push(buildProjectSummaryItem(key, projectValue, summary, config.labels, currentWeekIso));
                                seen[key] = true;
                            });

                            return ordered;
                        }, [combinedProjectSummaries, projects, config.labels, weekStart]);

                        // Calculer le total des heures contractuelles de la semaine
                        var weekContractualMinutes = hooks.useMemo(function() {
                            if (!Array.isArray(config.workSchedule) || config.workSchedule.length === 0) {
                                return 0;
                            }
                            var total = 0;
                            config.workSchedule.forEach(function(slot) {
                                if (!slot || !slot.start || !slot.end) {
                                    return;
                                }
                                var startParts = slot.start.split(':');
                                var endParts = slot.end.split(':');
                                if (startParts.length < 2 || endParts.length < 2) {
                                    return;
                                }
                                var startMinutes = parseInt(startParts[0], 10) * 60 + parseInt(startParts[1], 10);
                                var endMinutes = parseInt(endParts[0], 10) * 60 + parseInt(endParts[1], 10);
                                if (endMinutes <= startMinutes) {
                                    return;
                                }
                                var breakMinutes = parseInt(slot.break_minutes || 0, 10);
                                var workMinutes = (endMinutes - startMinutes) - breakMinutes;
                                total += Math.max(0, workMinutes);
                            });
                            return total;
                        }, [config.workSchedule]);

                        var globalStatsDefinitions = hooks.useMemo(function() {
                            return [
                                { key: 'week', label: config.labels.statsWeek || config.labels.totalWeek || 'Semaine', valueKey: 'week' },
                                { key: 'month', label: config.labels.statsMonth || config.labels.totalMonth || 'Mois', valueKey: 'month' },
                                { key: 'year', label: config.labels.statsYear || config.labels.totalYear || 'Année', valueKey: 'year' },
                                { key: 'lifetime', label: config.labels.statsTotal || config.labels.totalLifetime || 'Total', valueKey: 'lifetime' }
                            ];
                        }, [config.labels]);

                        var globalAggregateTotals = hooks.useMemo(function() {
                            var totals = {
                                weekMinutes: Math.max(0, Number(calendarModel.totalMinutes || 0)),
                                monthMinutes: 0,
                                yearMinutes: 0,
                                lifetimeMinutes: 0
                            };

                            projectSummaryDetails.forEach(function(summary) {
                                if (!summary) {
                                    return;
                                }
                                var monthValue = Number(summary.monthMinutes || 0);
                                var yearValue = Number(summary.yearMinutes || 0);
                                var lifetimeValue = Number(summary.lifetimeMinutes || 0);

                                totals.monthMinutes += Number.isFinite(monthValue) && monthValue > 0 ? monthValue : 0;
                                totals.yearMinutes += Number.isFinite(yearValue) && yearValue > 0 ? yearValue : 0;
                                totals.lifetimeMinutes += Number.isFinite(lifetimeValue) && lifetimeValue > 0 ? lifetimeValue : 0;
                            });

                            if (totals.monthMinutes <= 0) {
                                totals.monthMinutes = totals.weekMinutes;
                            }
                            if (totals.yearMinutes <= 0) {
                                totals.yearMinutes = Math.max(totals.monthMinutes, totals.weekMinutes);
                            }
                            if (totals.lifetimeMinutes <= 0) {
                                totals.lifetimeMinutes = Math.max(totals.yearMinutes, totals.monthMinutes, totals.weekMinutes);
                            }

                            // Calculer le différentiel de la semaine (heures travaillées - heures contractuelles)
                            var weekDifferenceMinutes = totals.weekMinutes - weekContractualMinutes;
                            var weekDifferenceLabel = '';
                            if (weekContractualMinutes > 0) {
                                var absDiff = Math.abs(weekDifferenceMinutes);
                                var diffLabel = formatTotalMinutes(absDiff, config.labels);
                                if (weekDifferenceMinutes >= 0) {
                                    weekDifferenceLabel = '+' + diffLabel;
                                } else {
                                    weekDifferenceLabel = '-' + diffLabel;
                                }
                            }

                            return {
                                weekMinutes: totals.weekMinutes,
                                monthMinutes: totals.monthMinutes,
                                yearMinutes: totals.yearMinutes,
                                lifetimeMinutes: totals.lifetimeMinutes,
                                weekLabel: formatTotalMinutes(totals.weekMinutes, config.labels),
                                monthLabel: formatTotalMinutes(totals.monthMinutes, config.labels),
                                yearLabel: formatTotalMinutes(totals.yearMinutes, config.labels),
                                lifetimeLabel: formatTotalMinutes(totals.lifetimeMinutes, config.labels),
                                weekContractualMinutes: weekContractualMinutes,
                                weekContractualLabel: weekContractualMinutes > 0 ? formatTotalMinutes(weekContractualMinutes, config.labels) : '',
                                weekDifferenceMinutes: weekDifferenceMinutes,
                                weekDifferenceLabel: weekDifferenceLabel
                            };
                        }, [projectSummaryDetails, calendarModel.totalMinutes, config.labels, weekContractualMinutes]);

                        hooks.useEffect(function() {
                            setProjects(function(previous) {
                                var baseList = Array.isArray(previous) ? previous.slice() : [];
                                var summaryMap = combinedProjectSummaries || Object.create(null);
                                Object.keys(summaryMap).forEach(function(key) {
                                    var summary = summaryMap[key];
                                    if (!summary) {
                                        return;
                                    }
                                    var projectValue = normalizeProjectValue(summary.project || '');
                                    if (!projectValue) {
                                        return;
                                    }
                                    baseList.push(projectValue);
                                });
                                var uniqueList = uniqueStrings(baseList);
                                if (!Array.isArray(previous) || previous.length !== uniqueList.length) {
                                    return uniqueList;
                                }
                                for (var index = 0; index < uniqueList.length; index++) {
                                    if (previous[index] !== uniqueList[index]) {
                                        return uniqueList;
                                    }
                                }
                                return previous;
                            });
                        }, [combinedProjectSummaries, setProjects]);

                        hooks.useEffect(function() {
                            return function() {
                                if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                    clearTimeout(pendingDragSubmitRef.current.timeoutId);
                                }
                                pendingDragSubmitRef.current = null;
                            };
                        }, []);

                        var projectTasksMap = hooks.useMemo(function() {
                            var map = Object.create(null);
                            var summaryMap = combinedProjectSummaries;
                            Object.keys(summaryMap).forEach(function(key) {
                                var summary = summaryMap[key];
                                if (!summary) {
                                    map[key] = [];
                                    return;
                                }
                                var totals = summary.tasks || Object.create(null);
                                var names = Object.keys(totals).sort(function(a, b) {
                                    if (totals[b] === totals[a]) {
                                        return a.localeCompare(b);
                                    }
                                    return totals[b] - totals[a];
                                });
                                map[key] = names;
                            });
                            return map;
                        }, [combinedProjectSummaries]);

                        var selectionTaskContext = hooks.useMemo(function() {
                            if (!selectedSlot) {
                                return {
                                    suggestions: generalTaskSuggestions,
                                    message: config.labels.selectProjectForTasks || ''
                                };
                            }
                            var projectValue = normalizeProjectValue(selectedSlot.formProject || '');
                            if (!projectValue) {
                                return {
                                    suggestions: generalTaskSuggestions,
                                    message: config.labels.selectProjectForTasks || ''
                                };
                            }
                            var projectKey = ensureProjectKey(projectValue);
                            var names = projectTasksMap[projectKey] || [];
                            if (names.length > 0) {
                                return {
                                    suggestions: names,
                                    message: ''
                                };
                            }
                            return {
                                suggestions: [],
                                message: config.labels.projectTasksEmpty || ''
                            };
                        }, [selectedSlot, generalTaskSuggestions, projectTasksMap, config.labels]);

                        var visibleDays = hooks.useMemo(function() {
                            var daysList = calendarModel.days || [];
                            if (!isMobileLayout) {
                                return daysList;
                            }
                            if (!Array.isArray(daysList) || daysList.length === 0) {
                                return [];
                            }
                            var targetIso = activeMobileDay;
                            if (targetIso) {
                                for (var index = 0; index < daysList.length; index++) {
                                    if (daysList[index].iso === targetIso) {
                                        return [daysList[index]];
                                    }
                                }
                            }
                            return [daysList[0]];
                        }, [calendarModel.days, isMobileLayout, activeMobileDay]);

                        var calendarHasEvents = hooks.useMemo(function() {
                            if (!isMobileLayout) {
                                return calendarModel.hasEvents;
                            }
                            return visibleDays.some(function(day) {
                                return day.events && day.events.length > 0;
                            });
                        }, [isMobileLayout, calendarModel.hasEvents, visibleDays]);

                        var selectionDurationLabel = hooks.useMemo(function() {
                            if (!selectedSlot) {
                                return '';
                            }
                            return formatDurationFromValues(selectedSlot.dayIso, selectedSlot.formStart, selectedSlot.formEnd, config.labels);
                        }, [selectedSlot, config.labels]);

                        var selectionHighlight = hooks.useMemo(function() {
                            if (!selectedSlot) {
                                return null;
                            }
                            var start = createDateFromDayAndTime(selectedSlot.dayIso, selectedSlot.formStart) || parseDateTime(selectedSlot.baseStartIso);
                            var end = createDateFromDayAndTime(selectedSlot.dayIso, selectedSlot.formEnd) || parseDateTime(selectedSlot.baseEndIso);
                            end = normalizeMidnightEndDate(selectedSlot.dayIso, start, end, selectedSlot.formEnd);
                            if (!isValidDate(start) || !isValidDate(end)) {
                                return null;
                            }
                            var rangeStart = HOURS_START * 60;
                            var rangeEnd = HOURS_END * 60;
                            var startMinutes = clamp(minutesSinceMidnight(start), rangeStart, rangeEnd);
                            var endMinutes = clamp(minutesSinceMidnight(end), rangeStart, rangeEnd);
                            if (endMinutes <= startMinutes) {
                                if (isMidnightTimeValue(selectedSlot.formEnd) && startMinutes > rangeStart && startMinutes < rangeEnd) {
                                    endMinutes = rangeEnd;
                                } else {
                                    endMinutes = Math.min(rangeEnd, startMinutes + SLOT_STEP_MINUTES);
                                }
                            }
                            var duration = Math.max(endMinutes - startMinutes, MIN_EVENT_HEIGHT);
                            var durationLabel = formatTotalMinutes(duration, config.labels);
                            return {
                                dayIso: selectedSlot.dayIso,
                                position: {
                                    top: (startMinutes - rangeStart) / MINUTES_PER_PIXEL,
                                    height: duration / MINUTES_PER_PIXEL
                                },
                                startTime: formatTimeValue(selectedSlot.formStart, config.locale),
                                endTime: formatTimeValue(selectedSlot.formEnd, config.locale),
                                duration: durationLabel
                            };
                        }, [selectedSlot, config.locale]);

                        var miniCalendarModel = hooks.useMemo(function() {
                            return buildMiniCalendarModel(config.locale, calendarMonth, weekStart, weekTotalsMap, config.labels, weekContractualMinutes);
                        }, [config.locale, calendarMonth, weekStart, weekTotalsMap, config.labels, weekContractualMinutes]);

                        hooks.useEffect(function() {
                            var weekDate = parseISODate(weekStart);
                            if (!weekDate) {
                                return;
                            }
                            var desiredMonthIso = toISODate(startOfMonth(weekDate));
                            setCalendarMonth(function(current) {
                                return current === desiredMonthIso ? current : desiredMonthIso;
                            });
                        }, [weekStart]);

                        hooks.useEffect(function() {
                            if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
                                return;
                            }
                            var mediaQueryList;
                            try {
                                mediaQueryList = window.matchMedia(MOBILE_BREAKPOINT_QUERY);
                            } catch (queryError) {
                                return;
                            }
                            function handleChange(event) {
                                setIsMobileLayout(function(previous) {
                                    return previous === event.matches ? previous : event.matches;
                                });
                            }
                            setIsMobileLayout(function(previous) {
                                return previous === mediaQueryList.matches ? previous : mediaQueryList.matches;
                            });
                            if (typeof mediaQueryList.addEventListener === 'function') {
                                mediaQueryList.addEventListener('change', handleChange);
                                return function() {
                                    mediaQueryList.removeEventListener('change', handleChange);
                                };
                            }
                            if (typeof mediaQueryList.addListener === 'function') {
                                mediaQueryList.addListener(handleChange);
                                return function() {
                                    mediaQueryList.removeListener(handleChange);
                                };
                            }
                            return function() {};
                        }, [setIsMobileLayout]);

                        hooks.useEffect(function() {
                            if (!activeProjectKey) {
                                return;
                            }
                            var exists = projectSummaryDetails.some(function(item) {
                                return item.key === activeProjectKey;
                            });
                            if (!exists) {
                                setActiveProjectKey(null);
                            }
                        }, [activeProjectKey, projectSummaryDetails, setActiveProjectKey]);

                        hooks.useEffect(function() {
                            if (!selectedSlot) {
                                return;
                            }
                            var normalizedProject = normalizeProjectValue(selectedSlot.formProject || '');
                            if (!normalizedProject) {
                                return;
                            }
                            var key = ensureProjectKey(normalizedProject);
                            if (activeProjectKey === key) {
                                return;
                            }
                            var exists = projectSummaryDetails.some(function(item) {
                                return item.key === key;
                            });
                            if (exists) {
                                setActiveProjectKey(key);
                            }
                        }, [selectedSlot, activeProjectKey, projectSummaryDetails, setActiveProjectKey]);

                        hooks.useEffect(function() {
                            if (!isMobileLayout) {
                                if (activeMobileDay !== null) {
                                    setActiveMobileDay(null);
                                }
                                return;
                            }
                            var daysList = Array.isArray(calendarModel.days) ? calendarModel.days : [];
                            if (daysList.length === 0) {
                                setActiveMobileDay(null);
                                return;
                            }
                            setActiveMobileDay(function(previous) {
                                if (previous && daysList.some(function(day) { return day.iso === previous; })) {
                                    return previous;
                                }
                                var todayIso = toISODate(startOfDay(new Date()));
                                if (daysList.some(function(day) { return day.iso === todayIso; })) {
                                    return todayIso;
                                }
                                return daysList[0].iso;
                            });
                        }, [isMobileLayout, calendarModel.days, activeMobileDay, setActiveMobileDay]);

                        hooks.useEffect(function() {
                            if (!isMobileLayout || !selectedSlot || !selectedSlot.dayIso) {
                                return;
                            }
                            setActiveMobileDay(function(previous) {
                                return previous === selectedSlot.dayIso ? previous : selectedSlot.dayIso;
                            });
                        }, [isMobileLayout, selectedSlot ? selectedSlot.dayIso : null, setActiveMobileDay]);

                        function applyPayload(payload) {
                            if (!payload || typeof payload !== 'object') {
                                return;
                            }
                            if (Array.isArray(payload.entries)) {
                                setEntries(payload.entries);
                                setKnownTasks(function(previous) {
                                    var list = Array.isArray(previous) ? previous.slice() : [];
                                    payload.entries.forEach(function(entry) {
                                        if (!entry || !isString(entry.task)) {
                                            return;
                                        }
                                        var normalized = normalizeProjectValue(entry.task);
                                        if (normalized) {
                                            list.push(normalized);
                                        }
                                    });
                                    var unique = uniqueStrings(list);
                                    if (Array.isArray(previous) && previous.length === unique.length) {
                                        var unchanged = true;
                                        for (var index = 0; index < unique.length; index++) {
                                            if (previous[index] !== unique[index]) {
                                                unchanged = false;
                                                break;
                                            }
                                        }
                                        if (unchanged) {
                                            return previous;
                                        }
                                    }
                                    return unique;
                                });
                                setProjects(function(previous) {
                                    var list = Array.isArray(previous) ? previous.slice() : [];
                                    payload.entries.forEach(function(entry) {
                                        if (!entry || !isString(entry.project)) {
                                            return;
                                        }
                                        var normalizedProject = normalizeProjectValue(entry.project);
                                        if (normalizedProject) {
                                            list.push(normalizedProject);
                                        }
                                    });
                                    var unique = uniqueStrings(list);
                                    if (Array.isArray(previous) && previous.length === unique.length) {
                                        var unchanged = true;
                                        for (var index = 0; index < unique.length; index++) {
                                            if (previous[index] !== unique[index]) {
                                                unchanged = false;
                                                break;
                                            }
                                        }
                                        if (unchanged) {
                                            return previous;
                                        }
                                    }
                                    return unique;
                                });
                            }
                            if (Array.isArray(payload.events)) {
                                setEvents(payload.events);
                            }
                            if (payload.week && typeof payload.week === 'object' && typeof payload.week.start === 'string') {
                                setWeekStart(ensureWeekStart(payload.week.start));
                            } else if (typeof payload.weekStart === 'string') {
                                setWeekStart(ensureWeekStart(payload.weekStart));
                            }
                            var supplementalProjectLists = [];
                            if (Array.isArray(payload.projects)) {
                                supplementalProjectLists.push(payload.projects);
                            }
                            if (Array.isArray(payload.projectCatalog)) {
                                supplementalProjectLists.push(payload.projectCatalog);
                            }
                            if (Array.isArray(payload.projectTotals)) {
                                supplementalProjectLists.push(payload.projectTotals.map(function(total) {
                                    if (total && isString(total.project)) {
                                        return total.project;
                                    }
                                    if (total && isString(total.label)) {
                                        return total.label;
                                    }
                                    return '';
                                }));
                            }
                            if (supplementalProjectLists.length > 0) {
                                setProjects(function(previous) {
                                    var list = Array.isArray(previous) ? previous.slice() : [];
                                    supplementalProjectLists.forEach(function(collection) {
                                        collection.forEach(function(project) {
                                            if (!isString(project)) {
                                                return;
                                            }
                                            var normalizedProject = normalizeProjectValue(project);
                                            if (!normalizedProject) {
                                                return;
                                            }
                                            list.push(normalizedProject);
                                        });
                                    });
                                    var unique = uniqueStrings(list);
                                    if (Array.isArray(previous) && previous.length === unique.length) {
                                        var unchanged = true;
                                        for (var index = 0; index < unique.length; index++) {
                                            if (previous[index] !== unique[index]) {
                                                unchanged = false;
                                                break;
                                            }
                                        }
                                        if (unchanged) {
                                            return previous;
                                        }
                                    }
                                    return unique;
                                });
                            }
                            if (Array.isArray(payload.projectTotals)) {
                                var normalizedTotals = normalizeProjectTotalsList(payload.projectTotals);
                                setProjectTotals(function(previous) {
                                    if (areProjectTotalsMapsEqual(previous, normalizedTotals)) {
                                        return previous;
                                    }
                                    return normalizedTotals;
                                });
                            }
                        }

                        var requestWeek = hooks.useCallback(function(targetWeek) {
                            if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                clearTimeout(pendingDragSubmitRef.current.timeoutId);
                            }
                            pendingDragSubmitRef.current = null;
                            var normalizedWeek = ensureWeekStart(targetWeek);
                            setWeekStart(normalizedWeek);
                            setSelectedSlot(null);

                            if (!canRequest) {
                                return Promise.resolve();
                            }

                            if (fetchControllerRef.current && typeof fetchControllerRef.current.abort === 'function') {
                                fetchControllerRef.current.abort();
                            }

                            var controller = typeof AbortController === 'function' ? new AbortController() : null;
                            fetchControllerRef.current = controller;

                            setLoading(true);
                            setError('');

                            var action = config.ajax.weekAction || config.ajax.action || '';
                            if (!action) {
                                setLoading(false);
                                return Promise.reject(new Error('Missing AJAX action.'));
                            }

                            var params = new URLSearchParams();
                            params.append('action', action);
                            params.append('nonce', config.ajax.nonce || '');
                            params.append('week', normalizedWeek);

                            return fetch(config.ajax.url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                credentials: 'same-origin',
                                body: params.toString(),
                                signal: controller ? controller.signal : undefined
                            })
                                .then(function(response) {
                                    if (!response.ok) {
                                        throw new Error('Request failed with status ' + response.status);
                                    }
                                    return response.json();
                                })
                                .then(function(payload) {
                                    if (!payload || payload.success !== true) {
                                        throw new Error('Unexpected payload structure.');
                                    }
                                    applyPayload(payload.data || {});
                                })
                                .catch(function(errorMessage) {
                                    if (errorMessage && errorMessage.name === 'AbortError') {
                                        return;
                                    }
                                    console.error('MJ Hour Encode - fetch error', errorMessage);
                                    setError(config.labels.fetchError || 'Une erreur est survenue.');
                                })
                                .finally(function() {
                                    setLoading(false);
                                    if (fetchControllerRef.current === controller) {
                                        fetchControllerRef.current = null;
                                    }
                                });
                        }, [canRequest, config.ajax]);

                        hooks.useEffect(function() {
                            if (!canRequest || hasFetchedInitial.current) {
                                return;
                            }
                            hasFetchedInitial.current = true;
                            requestWeek(weekStart);
                        }, [canRequest, requestWeek, weekStart]);

                        hooks.useEffect(function() {
                            return function() {
                                if (fetchControllerRef.current && typeof fetchControllerRef.current.abort === 'function') {
                                    fetchControllerRef.current.abort();
                                }
                            };
                        }, []);

                        function handleNavigate(offset) {
                            requestWeek(shiftWeek(weekStart, offset));
                        }

                        function handleToday() {
                            var todayWeek = toISODate(startOfWeek(new Date()));
                            requestWeek(todayWeek);
                            if (isMobileLayout) {
                                var todayIso = toISODate(startOfDay(new Date()));
                                setActiveMobileDay(function(previous) {
                                    return previous === todayIso ? previous : todayIso;
                                });
                            }
                        }

                        function handleCalendarMonthNavigate(offset) {
                            setCalendarMonth(function(previous) {
                                var base = parseISODate(previous);
                                if (!base) {
                                    base = new Date();
                                }
                                var shifted = addMonths(base, offset);
                                return toISODate(startOfMonth(shifted));
                            });
                        }

                        function handleCalendarWeekSelect(weekIso) {
                            if (!weekIso) {
                                return;
                            }
                            var normalized = ensureWeekStart(weekIso);
                            if (normalized === weekStart) {
                                return;
                            }
                            requestWeek(normalized);
                        }

                        function handleMobileDaySelect(dayIso) {
                            if (!isMobileLayout || !dayIso) {
                                return;
                            }
                            setActiveMobileDay(function(previous) {
                                return previous === dayIso ? previous : dayIso;
                            });
                        }

                        function handleProjectSelect(summary) {
                            if (!summary) {
                                setActiveProjectKey(null);
                                setSelectedSlot(function(previous) {
                                    if (!previous) {
                                        return previous;
                                    }
                                    var next = Object.assign({}, previous);
                                    next.formProject = '';
                                    next.error = '';
                                    return next;
                                });
                                return;
                            }

                            var nextKey = activeProjectKey === summary.key ? null : summary.key;
                            setActiveProjectKey(nextKey);

                            var projectValue = nextKey ? normalizeProjectValue(summary.value) : '';
                            setSelectedSlot(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var next = Object.assign({}, previous);
                                next.formProject = projectValue;
                                next.error = '';
                                return next;
                            });
                        }

                        function handleChipSelect(value) {
                            if (!value) {
                                return;
                            }
                            var taskValue = '';
                            var projectValue = null;
                            var projectKey = null;

                            if (typeof value === 'string') {
                                taskValue = value;
                            } else if (value && typeof value === 'object') {
                                if (isString(value.task)) {
                                    taskValue = value.task;
                                }
                                if (isString(value.projectValue) || value.projectValue === '') {
                                    projectValue = value.projectValue;
                                }
                                if (isString(value.projectKey)) {
                                    projectKey = value.projectKey;
                                }
                            }

                            if (projectKey !== null) {
                                setActiveProjectKey(projectKey);
                            }

                            setSelectedSlot(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var next = Object.assign({}, previous);
                                if (taskValue) {
                                    next.formTask = taskValue;
                                }
                                if (projectValue !== null) {
                                    next.formProject = normalizeProjectValue(projectValue);
                                }
                                next.error = '';
                                return next;
                            });
                        }

                        function handleSlotSelect(slot) {
                            if (!slot) {
                                return;
                            }
                            var duration = Number.isFinite(slot.durationMinutes) ? slot.durationMinutes : 0;
                            var shouldPreserve = Boolean(slot.preserveForm);
                            setSelectedSlot(function(previous) {
                                var keepPrevious = shouldPreserve && previous && previous.id === slot.id;
                                return {
                                    id: slot.id,
                                    dayIso: slot.dayIso,
                                    baseStartIso: slot.startIso,
                                    baseEndIso: slot.endIso,
                                    durationMinutes: duration,
                                    formTask: keepPrevious ? previous.formTask : '',
                                    formProject: keepPrevious ? previous.formProject : '',
                                    formStart: isoToTimeValue(slot.startIso),
                                    formEnd: isoToTimeValue(slot.endIso),
                                    hourId: null,
                                    entryId: null,
                                    isEditing: false,
                                    color: keepPrevious && previous.color ? previous.color : config.accentColor,
                                    source: null,
                                    error: ''
                                };
                            });
                            if (isMobileLayout && slot.dayIso) {
                                setActiveMobileDay(function(previous) {
                                    return previous === slot.dayIso ? previous : slot.dayIso;
                                });
                            }
                            if (!shouldPreserve) {
                                try {
                                    window.dispatchEvent(new CustomEvent('mj-member-hour-encode:slot-selected', {
                                        detail: {
                                            weekStart: weekStart,
                                            slot: slot
                                        }
                                    }));
                                } catch (errorMessage) {
                                    console.info('MJ Hour Encode - slot selected', slot);
                                }
                            }
                        }

                        function handleEntrySelect(entry, context) {
                            if (!entry || typeof entry !== 'object') {
                                return;
                            }

                            var startDate = parseDateTime(entry.start);
                            var endDate = parseDateTime(entry.end);
                            if (!isValidDate(startDate) || !isValidDate(endDate)) {
                                return;
                            }

                            var duration = Number.isFinite(entry.durationMinutes)
                                ? Math.max(0, entry.durationMinutes)
                                : Math.max(0, Math.round((endDate.getTime() - startDate.getTime()) / 60000));

                            var contextDayIso = context && context.dayIso ? context.dayIso : toISODate(startOfDay(startDate));

                            var resolvedEntryId = entry.id || (entry.hourId ? 'hour-' + entry.hourId : 'entry-' + Date.now());

                            setSelectedSlot(function(previous) {
                                var hasMatchingIdentifier = false;
                                if (previous && previous.isEditing) {
                                    if (previous.hourId && entry.hourId && previous.hourId === entry.hourId) {
                                        hasMatchingIdentifier = true;
                                    } else if (previous.entryId && entry.id && previous.entryId === entry.id) {
                                        hasMatchingIdentifier = true;
                                    }
                                }

                                var formStartValue = isoToTimeValue(entry.start);
                                var formEndValue = isoToTimeValue(entry.end);

                                var nextId = hasMatchingIdentifier && previous.entryId ? previous.entryId : resolvedEntryId;

                                return {
                                    id: nextId,
                                    dayIso: contextDayIso,
                                    baseStartIso: entry.start,
                                    baseEndIso: entry.end,
                                    durationMinutes: duration,
                                    formTask: hasMatchingIdentifier ? previous.formTask : (entry.task || ''),
                                    formProject: hasMatchingIdentifier ? previous.formProject : (entry.project || ''),
                                    formStart: hasMatchingIdentifier ? previous.formStart : formStartValue,
                                    formEnd: hasMatchingIdentifier ? previous.formEnd : formEndValue,
                                    hourId: entry.hourId || null,
                                    entryId: nextId,
                                    isEditing: true,
                                    color: entry.color || (previous && previous.color) || config.accentColor,
                                    source: entry,
                                    error: ''
                                };
                            });

                            if (isMobileLayout && contextDayIso) {
                                setActiveMobileDay(function(previous) {
                                    return previous === contextDayIso ? previous : contextDayIso;
                                });
                            }

                            try {
                                window.dispatchEvent(new CustomEvent('mj-member-hour-encode:entry-select', {
                                    detail: {
                                        weekStart: weekStart,
                                        entry: entry
                                    }
                                }));
                            } catch (errorMessage) {
                                console.info('MJ Hour Encode - entry selected', entry);
                            }
                        }

                        function handleEntryDragStart(entry, context) {
                            if (!entry || typeof entry !== 'object') {
                                return;
                            }
                            if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                clearTimeout(pendingDragSubmitRef.current.timeoutId);
                            }
                            pendingDragSubmitRef.current = null;

                            setDraggingEntry(entry);

                            var alreadyEditing = false;
                            if (selectedSlot && selectedSlot.isEditing) {
                                if (entry.hourId && selectedSlot.hourId && String(selectedSlot.hourId) === String(entry.hourId)) {
                                    alreadyEditing = true;
                                } else if (entry.id && selectedSlot.entryId && String(selectedSlot.entryId) === String(entry.id)) {
                                    alreadyEditing = true;
                                }
                            }

                            if (!alreadyEditing) {
                                handleEntrySelect(entry, Object.assign({}, context, { trigger: 'drag-start' }));
                            }
                        }

                        function scheduleDragSubmission(slot, meta) {
                            if (!slot || !meta || !meta.entry) {
                                return;
                            }
                            var entry = meta.entry;
                            var entryKey = entry.hourId ? 'hour:' + entry.hourId : (entry.id ? 'entry:' + entry.id : null);
                            if (!entryKey) {
                                return;
                            }
                            var original = meta.original || {};
                            if (original.dayIso === slot.dayIso && original.startIso === slot.startIso && original.endIso === slot.endIso) {
                                return;
                            }
                            if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                clearTimeout(pendingDragSubmitRef.current.timeoutId);
                            }
                            var timeoutId = typeof window !== 'undefined' ? window.setTimeout(function() {
                                if (!pendingDragSubmitRef.current || pendingDragSubmitRef.current.entryKey !== entryKey) {
                                    return;
                                }
                                pendingDragSubmitRef.current = null;
                                handleSelectionSubmit();
                            }, 40) : null;
                            pendingDragSubmitRef.current = {
                                entryKey: entryKey,
                                timeoutId: timeoutId
                            };
                        }

                        function updateSelectionForDrag(slot, meta, shouldCommit) {
                            if (!slot || !meta || !meta.entry) {
                                return;
                            }

                            var entry = meta.entry;
                            var identifiers = {
                                hourId: entry.hourId ? String(entry.hourId) : null,
                                entryId: entry.id ? String(entry.id) : null
                            };

                            setSelectedSlot(function(previous) {
                                if (!previous || !previous.isEditing) {
                                    return previous;
                                }
                                var matchesHour = identifiers.hourId && previous.hourId && String(previous.hourId) === identifiers.hourId;
                                var matchesEntry = identifiers.entryId && previous.entryId && String(previous.entryId) === identifiers.entryId;
                                if (!matchesHour && !matchesEntry) {
                                    return previous;
                                }
                                var next = Object.assign({}, previous);
                                next.dayIso = slot.dayIso;
                                next.formStart = isoToTimeValue(slot.startIso);
                                next.formEnd = isoToTimeValue(slot.endIso);
                                next.baseStartIso = slot.startIso;
                                next.baseEndIso = slot.endIso;
                                next.durationMinutes = slot.durationMinutes;
                                next.error = '';
                                next.preserveForm = true;
                                return next;
                            });

                            if (shouldCommit) {
                                scheduleDragSubmission(slot, meta);
                            }
                        }

                        function handleEntryDragUpdate(slot, meta) {
                            updateSelectionForDrag(slot, meta, false);
                        }

                        function handleEntryDragEnd(slot, meta) {
                            setDraggingEntry(null);

                            if (meta && meta.canceled) {
                                return;
                            }

                            if (meta && meta.entry && typeof meta.clientX === 'number' && typeof meta.clientY === 'number') {
                                var elementUnderPointer = document.elementFromPoint(meta.clientX, meta.clientY);
                                console.log('MJ Hour Encode - drag end at', meta.clientX, meta.clientY, 'element:', elementUnderPointer);
                                if (elementUnderPointer) {
                                    var projectPill = elementUnderPointer.closest('.mj-hour-encode-app__project-pill');
                                    console.log('MJ Hour Encode - project pill found:', projectPill);
                                    if (projectPill) {
                                        var projectKey = projectPill.getAttribute('data-project-key');
                                        console.log('MJ Hour Encode - project key:', projectKey);
                                        if (projectKey) {
                                            var targetProject = projectSummaryDetails.find(function(item) {
                                                return item.key === projectKey;
                                            });
                                            console.log('MJ Hour Encode - target project:', targetProject);
                                            if (targetProject) {
                                                handleEntryMoveToProject(meta.entry, targetProject);
                                                return;
                                            }
                                        }
                                    }
                                }
                            }

                            if (slot) {
                                updateSelectionForDrag(slot, meta, true);
                            }
                        }

                        function handleSelectionChange(field, value) {
                            if (field === 'formProject') {
                                var normalizedProject = normalizeProjectValue(value || '');
                                var projectKey = normalizedProject ? ensureProjectKey(normalizedProject) : null;
                                if (projectKey) {
                                    var exists = projectSummaryDetails.some(function(item) {
                                        return item.key === projectKey;
                                    });
                                    setActiveProjectKey(exists ? projectKey : null);
                                } else {
                                    setActiveProjectKey(null);
                                }
                            }
                            setSelectedSlot(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var next = Object.assign({}, previous);
                                next[field] = value;
                                next.error = '';
                                return next;
                            });
                        }

                        function handleProjectRename(detail) {
                            if (!detail || !detail.key) {
                                return;
                            }
                            var targetKey = detail.key;
                            var nextValue = normalizeProjectValue(detail.newValue || '');
                            if (nextValue === '') {
                                return;
                            }
                            var previousValue = normalizeProjectValue((detail.previousValue || detail.projectValue || ''));
                            var nextKey = ensureProjectKey(nextValue);

                            setEntries(function(previous) {
                                if (!Array.isArray(previous) || previous.length === 0) {
                                    return previous;
                                }
                                var changed = false;
                                var updated = previous.map(function(item) {
                                    if (!item || typeof item !== 'object') {
                                        return item;
                                    }
                                    var itemKey = ensureProjectKey(item.project || '');
                                    if (itemKey !== targetKey) {
                                        return item;
                                    }
                                    changed = true;
                                    return Object.assign({}, item, {
                                        project: nextValue
                                    });
                                });
                                return changed ? updated : previous;
                            });

                            setProjects(function(previous) {
                                var list = Array.isArray(previous) ? previous.slice() : [];
                                var filtered = list.filter(function(name) {
                                    return ensureProjectKey(name) !== targetKey;
                                });
                                if (nextValue && !filtered.some(function(name) {
                                    return normalizeProjectValue(name) === nextValue;
                                })) {
                                    filtered.push(nextValue);
                                }
                                return uniqueStrings(filtered);
                            });

                            setProjectHistory(function(previous) {
                                var source = previous || Object.create(null);
                                var oldEntry = source[targetKey] || null;
                                var nextStore = Object.assign(Object.create(null), source);
                                var mergedTasks = Object.create(null);
                                if (targetKey !== nextKey && nextStore[nextKey] && nextStore[nextKey].tasks) {
                                    Object.keys(nextStore[nextKey].tasks).forEach(function(taskName) {
                                        mergedTasks[taskName] = nextStore[nextKey].tasks[taskName];
                                    });
                                }
                                if (oldEntry && oldEntry.tasks) {
                                    Object.keys(oldEntry.tasks).forEach(function(taskName) {
                                        var minutes = oldEntry.tasks[taskName];
                                        if (!Object.prototype.hasOwnProperty.call(mergedTasks, taskName) || mergedTasks[taskName] < minutes) {
                                            mergedTasks[taskName] = minutes;
                                        }
                                    });
                                }
                                var weeks = Object.create(null);
                                if (targetKey !== nextKey && nextStore[nextKey] && nextStore[nextKey].weeks) {
                                    Object.keys(nextStore[nextKey].weeks).forEach(function(iso) {
                                        weeks[iso] = nextStore[nextKey].weeks[iso];
                                    });
                                }
                                if (oldEntry && oldEntry.weeks) {
                                    Object.keys(oldEntry.weeks).forEach(function(iso) {
                                        weeks[iso] = oldEntry.weeks[iso];
                                    });
                                }
                                var lifetimeMinutes = 0;
                                Object.keys(weeks).forEach(function(iso) {
                                    lifetimeMinutes += Number(weeks[iso] || 0);
                                });
                                if (targetKey !== nextKey) {
                                    delete nextStore[targetKey];
                                }
                                nextStore[nextKey] = {
                                    project: nextValue,
                                    tasks: mergedTasks,
                                    weeks: weeks,
                                    lifetimeMinutes: lifetimeMinutes
                                };
                                return nextStore;
                            });

                            setActiveProjectKey(nextKey);

                            setSelectedSlot(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var slotKey = ensureProjectKey(previous.formProject || '');
                                if (slotKey !== targetKey) {
                                    return previous;
                                }
                                return Object.assign({}, previous, {
                                    formProject: nextValue,
                                    error: ''
                                });
                            });

                            if (canRequest && config.ajax && config.ajax.url && config.ajax.renameProjectAction) {
                                var renameNonce = config.ajax.renameNonce || config.ajax.nonce || '';
                                if (renameNonce !== '') {
                                    var params = new URLSearchParams();
                                    params.append('action', config.ajax.renameProjectAction);
                                    params.append('nonce', renameNonce);
                                    params.append('project_key', targetKey);
                                    params.append('old_label', previousValue);
                                    params.append('new_label', nextValue);

                                    setLoading(true);

                                    fetch(config.ajax.url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                        },
                                        credentials: 'same-origin',
                                        body: params.toString()
                                    })
                                        .then(function(response) {
                                            if (!response.ok) {
                                                throw new Error('Request failed with status ' + response.status);
                                            }
                                            return response.json();
                                        })
                                        .then(function(payload) {
                                            if (!payload || payload.success !== true) {
                                                var message = payload && payload.data && payload.data.message ? payload.data.message : '';
                                                throw new Error(message || 'Rename project failed');
                                            }
                                            return requestWeek(weekStart);
                                        })
                                        .catch(function(errorMessage) {
                                            var message = '';
                                            if (errorMessage) {
                                                if (typeof errorMessage === 'string') {
                                                    message = errorMessage;
                                                } else if (errorMessage && errorMessage.message) {
                                                    message = errorMessage.message;
                                                }
                                            }
                                            if (!message) {
                                                message = config.labels.fetchError || 'Une erreur est survenue.';
                                            }
                                            console.error('MJ Hour Encode - project rename error', errorMessage);
                                            setError(message);
                                            return requestWeek(weekStart);
                                        })
                                        .finally(function() {
                                            setLoading(false);
                                        });
                                }
                            }
                        }

                        function handleTaskRename(detail) {
                            if (!detail || !detail.projectKey || !detail.taskKey) {
                                return;
                            }
                            var projectKey = detail.projectKey;
                            var taskKey = normalizeProjectValue(detail.taskKey || '');
                            var previousTaskValue = normalizeProjectValue(detail.previousValue || detail.taskKey || '');
                            var normalizedTaskKey = taskKey !== '' ? taskKey : previousTaskValue;
                            var nextValue = normalizeProjectValue(detail.newValue || '');
                            if (nextValue === '' || nextValue === previousTaskValue) {
                                return;
                            }

                            setEntries(function(previous) {
                                if (!Array.isArray(previous) || previous.length === 0) {
                                    return previous;
                                }
                                var changed = false;
                                var updated = previous.map(function(item) {
                                    if (!item || typeof item !== 'object') {
                                        return item;
                                    }
                                    var itemProjectKey = ensureProjectKey(item.project || '');
                                    if (itemProjectKey !== projectKey) {
                                        return item;
                                    }
                                    var itemTask = normalizeProjectValue(item.task || '');
                                    if (itemTask !== previousTaskValue) {
                                        return item;
                                    }
                                    changed = true;
                                    return Object.assign({}, item, {
                                        task: nextValue
                                    });
                                });
                                return changed ? updated : previous;
                            });

                            setKnownTasks(function(previous) {
                                var list = Array.isArray(previous) ? previous.slice() : [];
                                var filtered = list.filter(function(name) {
                                    return normalizeProjectValue(name) !== previousTaskValue;
                                });
                                if (nextValue && !filtered.some(function(name) {
                                    return normalizeProjectValue(name) === nextValue;
                                })) {
                                    filtered.push(nextValue);
                                }
                                return uniqueStrings(filtered);
                            });

                            setProjectHistory(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var projectEntry = previous[projectKey];
                                if (!projectEntry) {
                                    return previous;
                                }
                                var next = Object.assign(Object.create(null), previous);
                                var tasks = Object.assign(Object.create(null), projectEntry.tasks || {});
                                if (Object.prototype.hasOwnProperty.call(tasks, normalizedTaskKey)) {
                                    var preservedMinutes = tasks[normalizedTaskKey] || 0;
                                    delete tasks[normalizedTaskKey];
                                    tasks[nextValue] = (tasks[nextValue] || 0) + preservedMinutes;
                                }
                                next[projectKey] = Object.assign({}, projectEntry, {
                                    tasks: tasks
                                });
                                return next;
                            });

                            setSelectedSlot(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var slotProjectKey = ensureProjectKey(previous.formProject || '');
                                if (slotProjectKey !== projectKey) {
                                    return previous;
                                }
                                var slotTask = normalizeProjectValue(previous.formTask || '');
                                if (slotTask !== previousTaskValue) {
                                    return previous;
                                }
                                return Object.assign({}, previous, {
                                    formTask: nextValue,
                                    error: ''
                                });
                            });

                            if (canRequest && config.ajax && config.ajax.url && config.ajax.renameTaskAction) {
                                var renameNonce = config.ajax.renameNonce || config.ajax.nonce || '';
                                if (renameNonce !== '') {
                                    var params = new URLSearchParams();
                                    params.append('action', config.ajax.renameTaskAction);
                                    params.append('nonce', renameNonce);
                                    params.append('old_label', previousTaskValue);
                                    params.append('new_label', nextValue);

                                    setLoading(true);

                                    fetch(config.ajax.url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                        },
                                        credentials: 'same-origin',
                                        body: params.toString()
                                    })
                                        .then(function(response) {
                                            if (!response.ok) {
                                                throw new Error('Request failed with status ' + response.status);
                                            }
                                            return response.json();
                                        })
                                        .then(function(payload) {
                                            if (!payload || payload.success !== true) {
                                                var message = payload && payload.data && payload.data.message ? payload.data.message : '';
                                                throw new Error(message || 'Rename task failed');
                                            }
                                            return requestWeek(weekStart);
                                        })
                                        .catch(function(errorMessage) {
                                            var message = '';
                                            if (errorMessage) {
                                                if (typeof errorMessage === 'string') {
                                                    message = errorMessage;
                                                } else if (errorMessage && errorMessage.message) {
                                                    message = errorMessage.message;
                                                }
                                            }
                                            if (!message) {
                                                message = config.labels.fetchError || 'Une erreur est survenue.';
                                            }
                                            console.error('MJ Hour Encode - task rename error', errorMessage);
                                            setError(message);
                                            return requestWeek(weekStart);
                                        })
                                        .finally(function() {
                                            setLoading(false);
                                        });
                                }
                            }
                        }

                        function handleTaskMoveToProject(task, sourceProject, targetProject) {
                            if (!task || !sourceProject || !targetProject) {
                                return;
                            }
                            var taskName = normalizeProjectValue(task.name || task.key || '');
                            var sourceProjectValue = normalizeProjectValue(sourceProject.value || sourceProject.label || '');
                            var targetProjectValue = normalizeProjectValue(targetProject.value || targetProject.label || '');
                            
                            if (!taskName || targetProjectValue === sourceProjectValue) {
                                return;
                            }

                            // Mise à jour optimiste de l'UI
                            setEntries(function(previous) {
                                if (!Array.isArray(previous) || previous.length === 0) {
                                    return previous;
                                }
                                var changed = false;
                                var updated = previous.map(function(item) {
                                    if (!item || typeof item !== 'object') {
                                        return item;
                                    }
                                    var itemProject = normalizeProjectValue(item.project || '');
                                    var itemTask = normalizeProjectValue(item.task || '');
                                    if (itemProject !== sourceProjectValue || itemTask !== taskName) {
                                        return item;
                                    }
                                    changed = true;
                                    return Object.assign({}, item, {
                                        project: targetProjectValue
                                    });
                                });
                                return changed ? updated : previous;
                            });

                            setActiveProjectKey(ensureProjectKey(targetProjectValue));

                            // Appel AJAX pour déplacer la tâche vers le nouveau projet
                            if (canRequest && config.ajax && config.ajax.url) {
                                setLoading(true);
                                
                                var params = new URLSearchParams();
                                params.append('action', 'mj_member_hour_encode_move_task_to_project');
                                params.append('nonce', config.ajax.nonce || '');
                                params.append('task_label', taskName);
                                params.append('source_project', sourceProjectValue);
                                params.append('target_project', targetProjectValue);

                                fetch(config.ajax.url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                    },
                                    credentials: 'same-origin',
                                    body: params.toString()
                                })
                                .then(function(response) {
                                    return response.json();
                                })
                                .then(function(payload) {
                                    if (!payload || payload.success !== true) {
                                        var message = payload && payload.data && payload.data.message ? payload.data.message : '';
                                        throw new Error(message || 'Move task failed');
                                    }
                                    console.info('MJ Hour Encode - task moved:', payload.data);
                                    return requestWeek(weekStart);
                                })
                                .catch(function(errorMessage) {
                                    var message = '';
                                    if (errorMessage) {
                                        if (typeof errorMessage === 'string') {
                                            message = errorMessage;
                                        } else if (errorMessage && errorMessage.message) {
                                            message = errorMessage.message;
                                        }
                                    }
                                    if (!message) {
                                        message = config.labels.fetchError || 'Une erreur est survenue.';
                                    }
                                    console.error('MJ Hour Encode - task move error', errorMessage);
                                    setError(message);
                                    return requestWeek(weekStart);
                                })
                                .finally(function() {
                                    setLoading(false);
                                });
                            }

                            try {
                                window.dispatchEvent(new CustomEvent('mj-member-hour-encode:task-move-to-project', {
                                    detail: {
                                        weekStart: weekStart,
                                        task: taskName,
                                        sourceProject: sourceProjectValue,
                                        targetProject: targetProjectValue
                                    }
                                }));
                            } catch (e) {
                                console.info('MJ Hour Encode - task moved to project', taskName, targetProjectValue);
                            }
                        }

                        function handleEntryMoveToProject(entry, targetProject) {
                            if (!entry || typeof entry !== 'object') {
                                setDraggingEntry(null);
                                return;
                            }
                            var targetProjectValue = normalizeProjectValue(targetProject.value || targetProject.label || '');
                            var currentProjectValue = normalizeProjectValue(entry.project || '');
                            if (targetProjectValue === currentProjectValue) {
                                setDraggingEntry(null);
                                return;
                            }

                            var entryId = entry.hourId || entry.id || null;
                            if (!entryId) {
                                setDraggingEntry(null);
                                return;
                            }

                            setEntries(function(previous) {
                                if (!Array.isArray(previous) || previous.length === 0) {
                                    return previous;
                                }
                                var changed = false;
                                var updated = previous.map(function(item) {
                                    if (!item || typeof item !== 'object') {
                                        return item;
                                    }
                                    var matches = false;
                                    if (entry.hourId && item.hourId && String(item.hourId) === String(entry.hourId)) {
                                        matches = true;
                                    } else if (entry.id && item.id && String(item.id) === String(entry.id)) {
                                        matches = true;
                                    }
                                    if (!matches) {
                                        return item;
                                    }
                                    changed = true;
                                    return Object.assign({}, item, {
                                        project: targetProjectValue
                                    });
                                });
                                return changed ? updated : previous;
                            });

                            setSelectedSlot(function(previous) {
                                if (!previous) {
                                    return previous;
                                }
                                var slotMatches = false;
                                if (entry.hourId && previous.hourId && String(previous.hourId) === String(entry.hourId)) {
                                    slotMatches = true;
                                } else if (entry.id && previous.entryId && String(previous.entryId) === String(entry.id)) {
                                    slotMatches = true;
                                }
                                if (!slotMatches) {
                                    return previous;
                                }
                                return Object.assign({}, previous, {
                                    formProject: targetProjectValue
                                });
                            });

                            setActiveProjectKey(ensureProjectKey(targetProjectValue));
                            setDraggingEntry(null);

                            if (canRequest && config.ajax && config.ajax.url && config.ajax.updateAction) {
                                var nonce = config.ajax.nonce || '';
                                var startIso = entry.start || '';
                                var endIso = entry.end || '';
                                var startDate = parseDateTime(startIso);
                                var endDate = parseDateTime(endIso);
                                var dayIso = isValidDate(startDate) ? toISODate(startOfDay(startDate)) : '';
                                var startTime = isValidDate(startDate) ? (pad(startDate.getHours()) + ':' + pad(startDate.getMinutes())) : '';
                                var endTime = isValidDate(endDate) ? (pad(endDate.getHours()) + ':' + pad(endDate.getMinutes())) : '';
                                var taskLabel = normalizeProjectValue(entry.task || '');

                                var params = new URLSearchParams();
                                params.append('action', config.ajax.updateAction);
                                params.append('nonce', nonce);
                                params.append('entry_id', String(entry.hourId || entry.id));
                                params.append('day', dayIso);
                                params.append('start', startTime);
                                params.append('end', endTime);
                                params.append('task', taskLabel);
                                params.append('project', targetProjectValue);
                                params.append('week', weekStart);

                                setLoading(true);

                                fetch(config.ajax.url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                    },
                                    credentials: 'same-origin',
                                    body: params.toString()
                                })
                                    .then(function(response) {
                                        if (!response.ok) {
                                            throw new Error('Request failed with status ' + response.status);
                                        }
                                        return response.json();
                                    })
                                    .then(function(payload) {
                                        if (!payload || payload.success !== true) {
                                            var message = payload && payload.data && payload.data.message ? payload.data.message : '';
                                            throw new Error(message || 'Move entry to project failed');
                                        }
                                        return requestWeek(weekStart);
                                    })
                                    .catch(function(errorMessage) {
                                        var message = '';
                                        if (errorMessage) {
                                            if (typeof errorMessage === 'string') {
                                                message = errorMessage;
                                            } else if (errorMessage && errorMessage.message) {
                                                message = errorMessage.message;
                                            }
                                        }
                                        if (!message) {
                                            message = config.labels.fetchError || 'Une erreur est survenue.';
                                        }
                                        console.error('MJ Hour Encode - move entry to project error', errorMessage);
                                        setError(message);
                                        return requestWeek(weekStart);
                                    })
                                    .finally(function() {
                                        setLoading(false);
                                    });
                            }

                            try {
                                window.dispatchEvent(new CustomEvent('mj-member-hour-encode:entry-move-to-project', {
                                    detail: {
                                        weekStart: weekStart,
                                        entry: entry,
                                        previousProject: currentProjectValue,
                                        newProject: targetProjectValue
                                    }
                                }));
                            } catch (eventError) {
                                console.info('MJ Hour Encode - entry moved to project', entry, targetProjectValue);
                            }
                        }

                        function handleSelectionCancel() {
                            if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                clearTimeout(pendingDragSubmitRef.current.timeoutId);
                            }
                            pendingDragSubmitRef.current = null;
                            setSelectedSlot(null);
                            try {
                                window.dispatchEvent(new CustomEvent('mj-member-hour-encode:slot-cancel', {
                                    detail: { weekStart: weekStart }
                                }));
                            } catch (errorMessage) {
                                console.info('MJ Hour Encode - slot cancelled');
                            }
                        }

                        function handleSelectionSubmit() {
                            if (!selectedSlot) {
                                return;
                            }
                            if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                clearTimeout(pendingDragSubmitRef.current.timeoutId);
                            }
                            pendingDragSubmitRef.current = null;
                            var startDate = createDateFromDayAndTime(selectedSlot.dayIso, selectedSlot.formStart);
                            var endDate = createDateFromDayAndTime(selectedSlot.dayIso, selectedSlot.formEnd);
                            endDate = normalizeMidnightEndDate(selectedSlot.dayIso, startDate, endDate, selectedSlot.formEnd);
                            var taskLabel = selectedSlot.formTask ? selectedSlot.formTask.trim() : '';
                            var projectLabel = selectedSlot.formProject ? selectedSlot.formProject.trim() : '';

                            if (!taskLabel) {
                                setSelectedSlot(function(previous) {
                                    if (!previous) {
                                        return previous;
                                    }
                                    return Object.assign({}, previous, {
                                        error: config.labels.selectionErrorTask || 'Veuillez saisir un intitulé.'
                                    });
                                });
                                return;
                            }

                            if (!isValidDate(startDate) || !isValidDate(endDate) || endDate <= startDate) {
                                setSelectedSlot(function(previous) {
                                    if (!previous) {
                                        return previous;
                                    }
                                    return Object.assign({}, previous, {
                                        error: config.labels.selectionErrorRange || 'Plage horaire invalide.'
                                    });
                                });
                                return;
                            }

                            var hasOverlap = entries.some(function(item) {
                                if (!item || typeof item !== 'object') {
                                    return false;
                                }
                                if (selectedSlot.hourId && item.hourId && item.hourId === selectedSlot.hourId) {
                                    return false;
                                }
                                if (selectedSlot.entryId && item.id && item.id === selectedSlot.entryId) {
                                    return false;
                                }
                                var itemStart = parseDateTime(item.start);
                                var itemEnd = parseDateTime(item.end);
                                if (!isValidDate(itemStart) || !isValidDate(itemEnd)) {
                                    return false;
                                }
                                if (toISODate(startOfDay(itemStart)) !== selectedSlot.dayIso) {
                                    return false;
                                }
                                return itemStart < endDate && itemEnd > startDate;
                            });

                            if (hasOverlap) {
                                setSelectedSlot(function(previous) {
                                    if (!previous) {
                                        return previous;
                                    }
                                    return Object.assign({}, previous, {
                                        error: config.labels.selectionErrorOverlap || 'Une plage est déjà encodée sur ces horaires.'
                                    });
                                });
                                return;
                            }

                            var durationMinutes = Math.max(0, Math.round((endDate.getTime() - startDate.getTime()) / 60000));
                            var normalizedStartValue = isString(selectedSlot.formStart) ? (selectedSlot.formStart.length === 5 ? selectedSlot.formStart + ':00' : selectedSlot.formStart) : '';
                            var normalizedEndValue = isString(selectedSlot.formEnd) ? (selectedSlot.formEnd.length === 5 ? selectedSlot.formEnd + ':00' : selectedSlot.formEnd) : '';
                            var hasIdentifier = Boolean(selectedSlot.hourId || selectedSlot.entryId);
                            var isEditing = Boolean(selectedSlot.isEditing && hasIdentifier);
                            var action = isEditing ? config.ajax.updateAction : config.ajax.createAction;
                            var entryColor = selectedSlot.color || config.accentColor;
                            var entryId = selectedSlot.entryId || (isEditing && selectedSlot.hourId ? 'hour-' + selectedSlot.hourId : 'temp-' + Date.now());

                            var entry = {
                                id: entryId,
                                hourId: selectedSlot.hourId || null,
                                task: taskLabel,
                                project: projectLabel,
                                start: startDate.toISOString(),
                                end: endDate.toISOString(),
                                color: entryColor,
                                durationMinutes: durationMinutes,
                                startTime: normalizedStartValue,
                                endTime: normalizedEndValue
                            };

                            if (!canRequest || !config.ajax || !config.ajax.url || !action) {
                                setEntries(function(existing) {
                                    var next;
                                    if (isEditing) {
                                        var replaced = false;
                                        next = existing.map(function(item) {
                                            if ((entry.hourId && item.hourId === entry.hourId) || (item.id && entry.id && item.id === entry.id)) {
                                                replaced = true;
                                                return Object.assign({}, item, entry);
                                            }
                                            return item;
                                        });
                                        if (!replaced) {
                                            next = next.concat([entry]);
                                        }
                                    } else {
                                        next = existing.concat([entry]);
                                    }
                                    setProjects(uniqueStrings(next.map(function(item) {
                                        return item.project || '';
                                    })));
                                    return next;
                                });
                                setSelectedSlot(null);

                                var fallbackEventName = isEditing ? 'update-entry' : 'create-entry';
                                try {
                                    window.dispatchEvent(new CustomEvent('mj-member-hour-encode:' + fallbackEventName, {
                                        detail: {
                                            weekStart: weekStart,
                                            entry: entry
                                        }
                                    }));
                                } catch (errorMessage) {
                                    console.info('MJ Hour Encode - entry ' + (isEditing ? 'updated' : 'created'), entry);
                                }
                                return;
                            }

                            setLoading(true);
                            setError('');

                            var params = new URLSearchParams();
                            params.append('action', action);
                            params.append('nonce', config.ajax.nonce || '');
                            params.append('day', selectedSlot.dayIso);
                            params.append('start', selectedSlot.formStart);
                            params.append('end', selectedSlot.formEnd);
                            params.append('task', taskLabel);
                            params.append('project', projectLabel);
                            params.append('week', weekStart);
                            if (isEditing && selectedSlot.hourId) {
                                params.append('entry_id', String(selectedSlot.hourId));
                            }

                            fetch(config.ajax.url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                credentials: 'same-origin',
                                body: params.toString()
                            })
                                .then(function(response) {
                                    return response.json()
                                        .catch(function() {
                                            if (!response.ok) {
                                                var fallbackError = new Error('Request failed with status ' + response.status);
                                                fallbackError.status = response.status;
                                                throw fallbackError;
                                            }
                                            throw new Error('Unexpected server response.');
                                        })
                                        .then(function(payload) {
                                            if (!response.ok) {
                                                var requestError = new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Request failed with status ' + response.status);
                                                requestError.payload = payload;
                                                requestError.status = response.status;
                                                throw requestError;
                                            }
                                            return payload;
                                        });
                                })
                                .then(function(payload) {
                                    if (!payload || payload.success !== true) {
                                        var errorPayload = payload && payload.data && payload.data.message ? payload.data.message : 'Unexpected payload structure.';
                                        var error = new Error(errorPayload);
                                        error.payload = payload;
                                        throw error;
                                    }

                                    var data = payload.data || {};
                                    applyPayload(data);
                                    setSelectedSlot(null);

                                    var emittedEntry = data.entry && typeof data.entry === 'object' ? data.entry : Object.assign({}, entry);
                                    var emittedWeek = weekStart;
                                    if (data.week && typeof data.week === 'object' && typeof data.week.start === 'string') {
                                        emittedWeek = ensureWeekStart(data.week.start);
                                    }

                                    var eventName = isEditing ? 'update-entry' : 'create-entry';

                                    try {
                                        window.dispatchEvent(new CustomEvent('mj-member-hour-encode:' + eventName, {
                                            detail: {
                                                weekStart: emittedWeek,
                                                entry: emittedEntry
                                            }
                                        }));
                                    } catch (errorMessage) {
                                        console.info('MJ Hour Encode - entry ' + (isEditing ? 'updated' : 'created'), emittedEntry);
                                    }
                                })
                                .catch(function(errorMessage) {
                                    console.error('MJ Hour Encode - save error', errorMessage);
                                    var message = '';
                                    if (errorMessage) {
                                        if (errorMessage.payload && errorMessage.payload.data && errorMessage.payload.data.message) {
                                            message = String(errorMessage.payload.data.message);
                                        } else if (errorMessage.message) {
                                            message = String(errorMessage.message);
                                        }
                                    }
                                    setSelectedSlot(function(previous) {
                                        if (!previous) {
                                            return previous;
                                        }
                                        return Object.assign({}, previous, {
                                            error: message || (config.labels.fetchError || 'Une erreur est survenue.')
                                        });
                                    });
                                })
                                .finally(function() {
                                    setLoading(false);
                                });
                        }

                        function handleSelectionDelete() {
                            if (!selectedSlot) {
                                return;
                            }

                            if (pendingDragSubmitRef.current && pendingDragSubmitRef.current.timeoutId) {
                                clearTimeout(pendingDragSubmitRef.current.timeoutId);
                            }
                            pendingDragSubmitRef.current = null;

                            var slotSnapshot = selectedSlot;
                            var hourId = slotSnapshot.hourId;
                            var entryIdentifier = slotSnapshot.entryId || '';
                            var entrySource = slotSnapshot.source || null;

                            if (!hourId && !entryIdentifier) {
                                setSelectedSlot(function(previous) {
                                    if (!previous) {
                                        return previous;
                                    }
                                    return Object.assign({}, previous, {
                                        error: config.labels.fetchError || 'Impossible de supprimer cette plage.'
                                    });
                                });
                                return;
                            }

                            var confirmationMessage = config.labels.selectionDeleteConfirm || 'Voulez-vous vraiment supprimer cette plage ?';
                            if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
                                if (!window.confirm(confirmationMessage)) {
                                    return;
                                }
                            }

                            if (!canRequest || !config.ajax || !config.ajax.url || !config.ajax.deleteAction || !hourId) {
                                setEntries(function(existing) {
                                    var next = existing.filter(function(item) {
                                        if (hourId && item.hourId) {
                                            return item.hourId !== hourId;
                                        }
                                        if (entryIdentifier && item.id) {
                                            return item.id !== entryIdentifier;
                                        }
                                        return item !== entrySource;
                                    });
                                    setProjects(uniqueStrings(next.map(function(item) {
                                        return item.project || '';
                                    })));
                                    return next;
                                });
                                setSelectedSlot(null);

                                try {
                                    window.dispatchEvent(new CustomEvent('mj-member-hour-encode:delete-entry', {
                                        detail: {
                                            weekStart: weekStart,
                                            entryId: hourId || entryIdentifier,
                                            entry: entrySource
                                        }
                                    }));
                                } catch (errorMessage) {
                                    console.info('MJ Hour Encode - entry deleted', hourId || entryIdentifier);
                                }
                                return;
                            }

                            setLoading(true);
                            setError('');

                            var params = new URLSearchParams();
                            params.append('action', config.ajax.deleteAction);
                            params.append('nonce', config.ajax.nonce || '');
                            params.append('entry_id', String(hourId));
                            params.append('week', weekStart);

                            fetch(config.ajax.url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                credentials: 'same-origin',
                                body: params.toString()
                            })
                                .then(function(response) {
                                    return response.json()
                                        .catch(function() {
                                            if (!response.ok) {
                                                var fallbackError = new Error('Request failed with status ' + response.status);
                                                fallbackError.status = response.status;
                                                throw fallbackError;
                                            }
                                            throw new Error('Unexpected server response.');
                                        })
                                        .then(function(payload) {
                                            if (!response.ok) {
                                                var requestError = new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Request failed with status ' + response.status);
                                                requestError.payload = payload;
                                                requestError.status = response.status;
                                                throw requestError;
                                            }
                                            return payload;
                                        });
                                })
                                .then(function(payload) {
                                    if (!payload || payload.success !== true) {
                                        var errorPayload = payload && payload.data && payload.data.message ? payload.data.message : 'Unexpected payload structure.';
                                        var error = new Error(errorPayload);
                                        error.payload = payload;
                                        throw error;
                                    }

                                    var data = payload.data || {};
                                    applyPayload(data);
                                    setSelectedSlot(null);

                                    var deletedId = data.deleted || hourId || entryIdentifier;
                                    var emittedWeek = weekStart;
                                    if (data.week && typeof data.week === 'object' && typeof data.week.start === 'string') {
                                        emittedWeek = ensureWeekStart(data.week.start);
                                    }

                                    try {
                                        window.dispatchEvent(new CustomEvent('mj-member-hour-encode:delete-entry', {
                                            detail: {
                                                weekStart: emittedWeek,
                                                entryId: deletedId,
                                                entry: entrySource
                                            }
                                        }));
                                    } catch (errorMessage) {
                                        console.info('MJ Hour Encode - entry deleted', deletedId);
                                    }
                                })
                                .catch(function(errorMessage) {
                                    console.error('MJ Hour Encode - delete error', errorMessage);
                                    var message = '';
                                    if (errorMessage) {
                                        if (errorMessage.payload && errorMessage.payload.data && errorMessage.payload.data.message) {
                                            message = String(errorMessage.payload.data.message);
                                        } else if (errorMessage.message) {
                                            message = String(errorMessage.message);
                                        }
                                    }
                                    setSelectedSlot(function(previous) {
                                        if (!previous) {
                                            return previous;
                                        }
                                        return Object.assign({}, previous, {
                                            error: message || (config.labels.fetchError || 'Une erreur est survenue.')
                                        });
                                    });
                                })
                                .finally(function() {
                                    setLoading(false);
                                });
                        }

                        var activeVisibleDayIso = visibleDays.length > 0 ? visibleDays[0].iso : null;
                        var allDaysForPicker = Array.isArray(calendarModel.days) ? calendarModel.days : [];
                        
                        // Construire les items de statistiques avec le différentiel de la semaine
                        var aggregateItems = globalStatsDefinitions.map(function(definition) {
                            var aggregateLabel = '';
                            if (definition.valueKey === 'week') {
                                aggregateLabel = globalAggregateTotals.weekLabel;
                            } else if (definition.valueKey === 'month') {
                                aggregateLabel = globalAggregateTotals.monthLabel;
                            } else if (definition.valueKey === 'year') {
                                aggregateLabel = globalAggregateTotals.yearLabel;
                            } else {
                                aggregateLabel = globalAggregateTotals.lifetimeLabel;
                            }
                            return h('div', { key: 'global-' + definition.key, className: 'mj-hour-encode-app__global-aggregate-item' }, [
                                h('span', { className: 'mj-hour-encode-app__global-aggregate-label' }, definition.label),
                                h('span', { className: 'mj-hour-encode-app__global-aggregate-value' }, aggregateLabel)
                            ]);
                        });

                        // Ajouter le différentiel de la semaine si des heures contractuelles sont définies
                        if (globalAggregateTotals.weekContractualMinutes > 0 && globalAggregateTotals.weekDifferenceLabel) {
                            var diffClass = 'mj-hour-encode-app__global-aggregate-item mj-hour-encode-app__global-aggregate-item--difference';
                            if (globalAggregateTotals.weekDifferenceMinutes >= 0) {
                                diffClass += ' is-positive';
                            } else {
                                diffClass += ' is-negative';
                            }
                            aggregateItems.push(h('div', { key: 'global-week-diff', className: diffClass }, [
                                h('span', { className: 'mj-hour-encode-app__global-aggregate-label' }, config.labels.weekDifference || 'Différence semaine'),
                                h('span', { className: 'mj-hour-encode-app__global-aggregate-value' }, globalAggregateTotals.weekDifferenceLabel)
                            ]));
                        }

                        // Ajouter le solde cumulé d'heures (total à récupérer)
                        if (config.cumulativeBalance && typeof config.cumulativeBalance.balanceMinutes === 'number') {
                            var balanceMinutes = config.cumulativeBalance.balanceMinutes;
                            var absBalance = Math.abs(balanceMinutes);
                            var balanceLabel = formatTotalMinutes(absBalance, config.labels);
                            if (balanceMinutes >= 0) {
                                balanceLabel = '+' + balanceLabel;
                            } else {
                                balanceLabel = '-' + balanceLabel;
                            }
                            var balanceClass = 'mj-hour-encode-app__global-aggregate-item mj-hour-encode-app__global-aggregate-item--balance';
                            if (balanceMinutes >= 0) {
                                balanceClass += ' is-positive';
                            } else {
                                balanceClass += ' is-negative';
                            }
                            aggregateItems.push(h('div', { key: 'global-cumulative-balance', className: balanceClass }, [
                                h('span', { className: 'mj-hour-encode-app__global-aggregate-label' }, config.labels.cumulativeDifference || 'Solde cumulé'),
                                h('span', { className: 'mj-hour-encode-app__global-aggregate-value' }, balanceLabel)
                            ]));
                        }

                        var aggregateSummary = h('div', { className: 'mj-hour-encode-app__global-aggregates' }, aggregateItems);

                        return h('div', { className: 'mj-hour-encode-app' + (loading ? ' is-loading' : '') + (isMobileLayout ? ' is-mobile-layout' : '') }, [
                            error ? h('div', { className: 'mj-hour-encode-app__error' }, error) : null,
                            h('div', { className: 'mj-hour-encode-app__header' }, [
                                h('div', { className: 'mj-hour-encode-app__title-group' }, [
                                    h('h1', { className: 'mj-hour-encode-app__title' }, config.labels.title),
                                    config.labels.subtitle ? h('p', { className: 'mj-hour-encode-app__subtitle' }, config.labels.subtitle) : null
                                ])
                            ]),
                            h('div', { className: 'mj-hour-encode-app__controls' }, [
                                h('div', { className: 'mj-hour-encode-app__controls-left' }, [
                                    h('button', {
                                        type: 'button',
                                        className: 'mj-hour-encode-app__nav-btn',
                                        'aria-label': config.labels.previousWeek,
                                        onClick: function(event) {
                                            event.preventDefault();
                                            handleNavigate(-1);
                                        }
                                    }, '<'),
                                    h('button', {
                                        type: 'button',
                                        className: 'mj-hour-encode-app__nav-btn',
                                        'aria-label': config.labels.nextWeek,
                                        onClick: function(event) {
                                            event.preventDefault();
                                            handleNavigate(1);
                                        }
                                    }, '>'),
                                    h('button', {
                                        type: 'button',
                                        className: 'mj-hour-encode-app__today-btn',
                                        onClick: function(event) {
                                            event.preventDefault();
                                            handleToday();
                                        }
                                    }, config.labels.today)
                                    ]),
                                    h('div', { className: 'mj-hour-encode-app__week-label' }, calendarModel.periodLabel)
                            ]),
                            isMobileLayout && allDaysForPicker.length > 0 ? h('div', { className: 'mj-hour-encode-app__mobile-day-picker' }, allDaysForPicker.map(function(day) {
                                var isActive = activeVisibleDayIso === day.iso;
                                return h('button', {
                                    key: day.iso,
                                    type: 'button',
                                    className: 'mj-hour-encode-app__mobile-day-picker-button' + (isActive ? ' is-active' : ''),
                                    onClick: function(event) {
                                        event.preventDefault();
                                        handleMobileDaySelect(day.iso);
                                    }
                                }, [
                                    h('span', { className: 'mj-hour-encode-app__mobile-day-picker-weekday' }, day.label.short),
                                    h('span', { className: 'mj-hour-encode-app__mobile-day-picker-date' }, day.label.date),
                                    isActive && selectedSlot && selectedSlot.entry && selectedSlot.entry.dayIso === day.iso ? h('span', { className: 'mj-hour-encode-app__mobile-day-picker-edit-badge' }, config.labels.selectionEditTitle || 'Modifier') : null
                                ]);
                            })) : null,
                            h('div', { className: 'mj-hour-encode-app__layout' }, [
                                h('div', { className: 'mj-hour-encode-app__calendar-section' }, [
                                    h(CalendarView, {
                                        days: visibleDays,
                                        hourMarks: hourMarks,
                                        loading: loading,
                                        hasEvents: calendarHasEvents,
                                        emptyLabel: config.labels.noEvents,
                                        labels: config.labels,
                                        workSchedule: config.workSchedule,
                                        onSlotSelect: handleSlotSelect,
                                        onEntrySelect: handleEntrySelect,
                                        onEntryDragStart: handleEntryDragStart,
                                        onEntryDrag: handleEntryDragUpdate,
                                        onEntryDragEnd: handleEntryDragEnd,
                                        selectedSlot: selectionHighlight
                                    }),
                                    aggregateSummary
                                ]),
                                h(SidePanel, {
                                    labels: config.labels,
                                    locale: config.locale,
                                    selection: selectedSlot,
                                    durationLabel: selectionDurationLabel,
                                    taskSuggestions: selectionTaskContext.suggestions,
                                    taskSuggestionsMessage: selectionTaskContext.message,
                                    projectSuggestions: projectSelectionSuggestions,
                                    allProjectOptions: projects,
                                    calendarModel: null,
                                    onCalendarMonthNavigate: handleCalendarMonthNavigate,
                                    onCalendarWeekSelect: handleCalendarWeekSelect,
                                    onSelectionChange: handleSelectionChange,
                                    onSelectionSubmit: handleSelectionSubmit,
                                    onSelectionCancel: handleSelectionCancel,
                                    onSelectionDelete: handleSelectionDelete
                                })
                            ]),
                            h(ResourceSection, {
                                miniCalendarModel: miniCalendarModel,
                                locale: config.locale,
                                projectSummaries: projectSummaryDetails,
                                projectCatalog: projects,
                                activeProject: activeProjectKey,
                                labels: config.labels,
                                draggingEntry: draggingEntry,
                                onTaskSelect: handleChipSelect,
                                onProjectSelect: handleProjectSelect,
                                onProjectRename: handleProjectRename,
                                onTaskRename: handleTaskRename,
                                onEntryMoveToProject: handleEntryMoveToProject,
                                onTaskMoveToProject: handleTaskMoveToProject,
                                onCalendarNavigate: handleCalendarMonthNavigate,
                                onCalendarWeekSelect: handleCalendarWeekSelect
                            }),
                            loading ? h('div', { className: 'mj-hour-encode-app__loader-text' }, config.labels.loading) : null
                        ]);
                    }

                    return HourEncodeApp;
                }

                function init(root) {
                    if (!root || instances.has(root)) {
                        return;
                    }

                    var config = parseConfig(root);

                    ensureRuntime()
                        .then(function(runtime) {
                            var App = createHourEncodeApp(runtime);
                            root.innerHTML = '';
                            var vnode = runtime.preact.h(App, { config: config });
                            runtime.preact.render(vnode, root);
                            instances.set(root, {
                                runtime: runtime,
                                unmount: function() {
                                    runtime.preact.render(null, root);
                                }
                            });
                        })
                        .catch(function(error) {
                            console.error('MJ Hour Encode – unable to initialise', error);
                            showError(root, 'Impossible de charger le module d’encodage.');
                        });
                }

                function destroy(root) {
                    if (!root) {
                        return;
                    }
                    var instance = instances.get(root);
                    if (instance && instance.unmount) {
                        instance.unmount();
                    }
                    instances.delete(root);
                }

                function initAll(context) {
                    if (typeof document === 'undefined') {
                        return;
                    }
                    var scope = context || document;
                    toArray(scope.querySelectorAll('.mj-hour-encode')).forEach(init);
                }

                domReady(function() {
                    initAll(document);
                });

                if (window.jQuery && window.jQuery(window).on) {
                    window.jQuery(window).on('elementor/frontend/init', function() {
                        if (window.elementorFrontend && window.elementorFrontend.hooks) {
                            window.elementorFrontend.hooks.addAction('frontend/element_ready/widget', function(scope) {
                                var element = scope && scope[0] ? scope[0] : scope;
                                initAll(element || document);
                            });
                        }
                    });
                }

                document.addEventListener('mj-member-hour-encode:init', function(event) {
                    if (event && event.detail && event.detail.context) {
                        initAll(event.detail.context);
                    } else {
                        initAll(document);
                    }
                });

                document.addEventListener('mj-member-hour-encode:destroy', function(event) {
                    if (!event || !event.detail || !event.detail.context) {
                        return;
                    }
                    var nodes = event.detail.context.querySelectorAll('.mj-hour-encode');
                    toArray(nodes).forEach(destroy);
                });
            })();
