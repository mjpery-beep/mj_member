(function(){
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
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
        : function (collection) {
            if (!collection) {
                return [];
            }

            if (Array.isArray(collection)) {
                return collection.slice();
            }

            try {
                return Array.prototype.slice.call(collection);
            } catch (error) {
                var fallback = [];
                for (var idx = 0; idx < collection.length; idx += 1) {
                    fallback.push(collection[idx]);
                }
                return fallback;
            }
        };

    function initCalendar(root, config) {
        if (!root) {
            return;
        }

        // ---- Create modal ----
        var createModal = root.querySelector('[data-calendar-create-modal]');
        var createCloseButtons = toArray(root.querySelectorAll('[data-calendar-create-close]'));
        var createPanels = toArray(root.querySelectorAll('[data-calendar-create-panel]'));
        var createFeedback = root.querySelector('[data-calendar-create-feedback]');
        var createTitleInput = root.querySelector('[data-calendar-create-title]');
        var createTypeHidden = root.querySelector('[data-calendar-create-type]');
        var createTypeGrid = root.querySelector('[data-calendar-create-type-grid]');
        var createDateInput = root.querySelector('[data-calendar-create-date]');
        var createDateDisplay = root.querySelector('[data-calendar-create-date-display]');
        var createStartInput = root.querySelector('[data-calendar-create-start]');
        var createEndInput = root.querySelector('[data-calendar-create-end]');
        var createSummary = root.querySelector('[data-calendar-create-summary]');
        var createPrevButton = root.querySelector('[data-calendar-create-prev]');
        var createNextButton = root.querySelector('[data-calendar-create-next]');
        var createSubmitButton = root.querySelector('[data-calendar-create-submit]');
        var createOnlyButton = root.querySelector('[data-calendar-create-only]');
        var createEmojiMount = root.querySelector('[data-calendar-create-emoji-mount]');
        var createFreeParticipation = root.querySelector('[data-calendar-create-free-participation]');
        var createShowAllMembers = root.querySelector('[data-calendar-create-show-all-members]');
        var createCapacity = root.querySelector('[data-calendar-create-capacity]');
        var createPrice = root.querySelector('[data-calendar-create-price]');
        var createAgeMin = root.querySelector('[data-calendar-create-age-min]');
        var createAgeMax = root.querySelector('[data-calendar-create-age-max]');
        var createLocationSelect = root.querySelector('[data-calendar-create-location]');
        var createTeamGrid = root.querySelector('[data-calendar-create-team-grid]');
        var createStatusSelect = root.querySelector('[data-calendar-create-status]');
        var createOccurrenceChoice = root.querySelector('[data-calendar-create-occurrence-choice]');
        var createRequireValidation = root.querySelector('[data-calendar-create-require-validation]');
        var stepperDots = toArray(root.querySelectorAll('[data-step-dot]'));
        var stepValidated = [false, false, false, false];
        var createCurrentStep = 1;
        var createSelectedDay = '';
        var createActiveTrigger = null;
        var createIsSubmitting = false;
        var createSelectedEmoji = '';
        var totalSteps = 4;

        // -- Emoji picker (Preact) --
        var emojiPickerRendered = false;
        function mountEmojiPicker() {
            if (emojiPickerRendered || !createEmojiMount) return;
            var EmojiPicker = window.MjRegMgrEmojiPicker && window.MjRegMgrEmojiPicker.EmojiPickerField;
            var preact = window.preact;
            if (!EmojiPicker || !preact || !preact.render || !preact.h) return;
            var h = preact.h;
            function handleEmojiChange(val) {
                createSelectedEmoji = String(val || '');
            }
            function EmojiWrapper() {
                var hooks = window.preactHooks || {};
                var useState = hooks.useState;
                if (!useState) return h('span', null, '');
                var state = useState(createSelectedEmoji);
                var value = state[0];
                var setValue = state[1];
                return h(EmojiPicker, {
                    value: value,
                    onChange: function(v) { setValue(v); handleEmojiChange(v); },
                    fallbackPlaceholder: "\xF0\x9F\x8E\xB2"
                });
            }
            preact.render(h(EmojiWrapper, null), createEmojiMount);
            emojiPickerRendered = true;
        }

        // -- Type grid (chip buttons) --
        function populateTypeGrid() {
            if (!createTypeGrid) return;
            var types = config && config.createTypes ? config.createTypes : {};
            var keys = Object.keys(types);
            createTypeGrid.innerHTML = '';
            if (!keys.length) {
                createTypeGrid.innerHTML = '<span class="ccm__type-empty">Aucun type disponible</span>';
                return;
            }
            var typeColors = config && config.createTypeColors ? config.createTypeColors : {};
            keys.forEach(function(typeKey, index) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'ccm__type-chip' + (index === 0 ? ' is-selected' : '');
                chip.setAttribute('data-type-value', typeKey);
                chip.textContent = String(types[typeKey] || typeKey);
                var chipColor = typeColors[typeKey] || '';
                if (chipColor) {
                    chip.style.setProperty('--chip-color', chipColor);
                }
                if (index === 0 && createTypeHidden) {
                    createTypeHidden.value = typeKey;
                }
                chip.addEventListener('click', function() {
                    toArray(createTypeGrid.querySelectorAll('.ccm__type-chip')).forEach(function(c) {
                        c.classList.remove('is-selected');
                    });
                    chip.classList.add('is-selected');
                    if (createTypeHidden) createTypeHidden.value = typeKey;
                });
                createTypeGrid.appendChild(chip);
            });
        }

        // -- Location select --
        function populateLocationSelect() {
            if (!createLocationSelect) return;
            var locations = config && config.createLocations ? config.createLocations : {};
            var keys = Object.keys(locations);
            // Keep the first "no location" option, clear the rest
            while (createLocationSelect.options.length > 1) {
                createLocationSelect.remove(1);
            }
            keys.forEach(function(locId) {
                var option = document.createElement('option');
                option.value = locId;
                option.textContent = String(locations[locId] || locId);
                createLocationSelect.appendChild(option);
            });
        }

        // -- Team grid (animateur checkboxes) --
        function populateTeamGrid() {
            if (!createTeamGrid) return;
            var animateurs = config && config.createAnimateurs ? config.createAnimateurs : {};
            var keys = Object.keys(animateurs);
            createTeamGrid.innerHTML = '';
            if (!keys.length) {
                createTeamGrid.innerHTML = '<span class="ccm__team-empty">Aucun animateur disponible</span>';
                return;
            }
            keys.forEach(function(animId) {
                var label = document.createElement('label');
                label.className = 'ccm__team-check';
                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = animId;
                checkbox.setAttribute('data-calendar-create-animateur', '');
                var span = document.createElement('span');
                span.className = 'ccm__team-name';
                span.textContent = String(animateurs[animId] || animId);
                label.appendChild(checkbox);
                label.appendChild(span);
                createTeamGrid.appendChild(label);
            });
        }

        // -- Date display --
        function formatDateDisplay(dateStr) {
            if (!dateStr) return '';
            try {
                var parts = dateStr.split('-');
                var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                var dayNames = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                var monthNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
                return dayNames[d.getDay()] + ' ' + d.getDate() + ' ' + monthNames[d.getMonth()] + ' ' + d.getFullYear();
            } catch(e) { return dateStr; }
        }

        function buildDateTime(day, timeValue) {
            if (!day || !timeValue) return '';
            return String(day) + ' ' + String(timeValue);
        }

        function setCreateFeedback(message, type) {
            if (!createFeedback) return;
            if (!message) {
                createFeedback.textContent = '';
                createFeedback.hidden = true;
                createFeedback.classList.remove('is-error', 'is-success');
                return;
            }
            createFeedback.textContent = message;
            createFeedback.hidden = false;
            createFeedback.classList.toggle('is-error', type === 'error');
            createFeedback.classList.toggle('is-success', type === 'success');
        }

        function validateCreateStep(step) {
            if (step === 1) {
                var titleValue = createTitleInput ? String(createTitleInput.value || '').trim() : '';
                var typeValue = createTypeHidden ? String(createTypeHidden.value || '').trim() : '';
                if (!titleValue) {
                    setCreateFeedback('Le titre est requis.', 'error');
                    if (createTitleInput) createTitleInput.focus();
                    return false;
                }
                if (!typeValue) {
                    setCreateFeedback('Sélectionnez un type.', 'error');
                    return false;
                }
            }
            if (step === 2) {
                var startValue = createStartInput ? String(createStartInput.value || '').trim() : '';
                var endValue = createEndInput ? String(createEndInput.value || '').trim() : '';
                if (!createSelectedDay || !startValue || !endValue) {
                    setCreateFeedback('Date et horaires obligatoires.', 'error');
                    return false;
                }
                if (endValue <= startValue) {
                    setCreateFeedback("L'heure de fin doit être après l'heure de début.", 'error');
                    if (createEndInput) createEndInput.focus();
                    return false;
                }
            }
            setCreateFeedback('', '');
            return true;
        }

        function updateCreateSummary() {
            if (!createSummary) return;
            var titleValue = createTitleInput ? String(createTitleInput.value || '').trim() : '';
            var typeLabel = '';
            var selectedChip = createTypeGrid ? createTypeGrid.querySelector('.is-selected') : null;
            if (selectedChip) typeLabel = selectedChip.textContent || '';
            var startValue = createStartInput ? String(createStartInput.value || '').trim() : '';
            var endValue = createEndInput ? String(createEndInput.value || '').trim() : '';
            var emoji = createSelectedEmoji || '';
            var dateLabel = formatDateDisplay(createSelectedDay);
            var esc = Utils.escapeHtml || function(s) { return String(s); };
            createSummary.innerHTML = '<div class="ccm__summary-emoji">' + (emoji || '\uD83D\uDCC5') + '</div>'
                + '<div class="ccm__summary-info">'
                + '<strong>' + esc(titleValue) + '</strong>'
                + '<span>' + esc(typeLabel) + '</span>'
                + '<span>' + esc(dateLabel) + ' &middot; ' + esc(startValue) + ' → ' + esc(endValue) + '</span>'
                + '</div>';
        }

        function syncCreateStep() {
            createPanels.forEach(function(panel) {
                var panelStep = parseInt(panel.getAttribute('data-calendar-create-panel') || '0', 10);
                var isActive = panelStep === createCurrentStep;
                panel.classList.toggle('is-active', isActive);
                if (isActive) panel.removeAttribute('hidden');
                else panel.setAttribute('hidden', 'hidden');
            });

            // Update stepper items
            stepperDots.forEach(function(dot) {
                var dotStep = parseInt(dot.getAttribute('data-step-dot') || '0', 10);
                dot.classList.toggle('is-active', dotStep === createCurrentStep);
                dot.classList.toggle('is-done', dotStep < createCurrentStep);
            });

            if (createPrevButton) {
                createPrevButton.hidden = createCurrentStep <= 1;
                createPrevButton.disabled = createIsSubmitting;
            }
            if (createNextButton) {
                createNextButton.hidden = (createCurrentStep >= totalSteps);
                createNextButton.disabled = createIsSubmitting;
            }
            if (createSubmitButton) {
                createSubmitButton.hidden = (createCurrentStep < totalSteps);
                createSubmitButton.disabled = createIsSubmitting;
            }
            if (createOnlyButton) {
                createOnlyButton.hidden = (createCurrentStep < totalSteps);
                createOnlyButton.disabled = createIsSubmitting;
            }
            if (createCurrentStep === 3) updateCreateSummary();
        }

        function openCreateModal(dayValue, triggerButton) {
            if (!createModal) return;
            createSelectedDay = String(dayValue || '').trim();
            createActiveTrigger = triggerButton || null;
            createCurrentStep = 1;
            createIsSubmitting = false;
            createSelectedEmoji = '';
            setCreateFeedback('', '');

            if (createDateInput) createDateInput.value = createSelectedDay;
            if (createDateDisplay) createDateDisplay.textContent = formatDateDisplay(createSelectedDay);
            if (createStartInput) createStartInput.value = '14:00';
            if (createEndInput) createEndInput.value = '17:00';
            if (createTitleInput) createTitleInput.value = '';

            // Reset step 2 toggles
            if (createOccurrenceChoice) createOccurrenceChoice.checked = false;
            if (createRequireValidation) createRequireValidation.checked = false;

            // Reset step 3 fields
            if (createFreeParticipation) createFreeParticipation.checked = false;
            if (createShowAllMembers) createShowAllMembers.checked = false;
            if (createCapacity) createCapacity.value = '0';
            if (createPrice) createPrice.value = '0';
            if (createAgeMin) createAgeMin.value = '12';
            if (createAgeMax) createAgeMax.value = '26';

            // Reset step 4 fields
            if (createStatusSelect) createStatusSelect.value = 'brouillon';
            if (createLocationSelect) createLocationSelect.value = '0';
            if (createTeamGrid) {
                toArray(createTeamGrid.querySelectorAll('input[type="checkbox"]')).forEach(function(cb) {
                    cb.checked = false;
                });
            }

            stepValidated = [false, false, false, false];

            createModal.hidden = false;
            createModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ccm-open');
            syncCreateStep();
            mountEmojiPicker();
            if (createTitleInput) createTitleInput.focus();
        }

        function closeCreateModal() {
            if (!createModal) return;
            createModal.hidden = true;
            createModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ccm-open');
            setCreateFeedback('', '');
            createIsSubmitting = false;
            syncCreateStep();
            if (createActiveTrigger && typeof createActiveTrigger.focus === 'function') {
                createActiveTrigger.focus();
            }
        }

        function buildCreateFormData() {
            var titleValue = createTitleInput ? String(createTitleInput.value || '').trim() : '';
            var typeValue = createTypeHidden ? String(createTypeHidden.value || '').trim() : '';
            var startValue = createStartInput ? String(createStartInput.value || '').trim() : '';
            var endValue = createEndInput ? String(createEndInput.value || '').trim() : '';

            var formData = new FormData();
            formData.append('action', 'mj_events_manager_create');
            formData.append('nonce', String(config.createNonce));
            formData.append('title', titleValue);
            formData.append('type', typeValue);
            formData.append('status', createStatusSelect ? String(createStatusSelect.value || 'brouillon') : 'brouillon');
            formData.append('start_date', buildDateTime(createSelectedDay, startValue));
            formData.append('end_date', buildDateTime(createSelectedDay, endValue));
            if (createSelectedEmoji) formData.append('emoji', createSelectedEmoji);
            if (createOccurrenceChoice && createOccurrenceChoice.checked) formData.append('occurrence_choice', '1');
            if (createRequireValidation && createRequireValidation.checked) formData.append('require_validation', '1');

            var capacity = createCapacity ? parseInt(createCapacity.value, 10) || 0 : 0;
            var price = createPrice ? parseFloat(createPrice.value) || 0 : 0;
            if (capacity > 0) formData.append('capacity_total', String(capacity));
            if (price > 0) formData.append('price', String(price));
            if (createShowAllMembers && createShowAllMembers.checked) {
                formData.append('attendance_show_all_members', '1');
            }

            // Step 4 – Location & Team
            var locationId = createLocationSelect ? parseInt(createLocationSelect.value, 10) || 0 : 0;
            if (locationId > 0) {
                formData.append('location_id', String(locationId));
            }
            var checkedAnimateurs = createTeamGrid ? toArray(createTeamGrid.querySelectorAll('input[data-calendar-create-animateur]:checked')) : [];
            checkedAnimateurs.forEach(function(cb) {
                formData.append('animateur_ids[]', cb.value);
            });
            return formData;
        }

        function submitCreateEvent(mode) {
            if (createIsSubmitting) return;
            if (!validateCreateStep(1) || !validateCreateStep(2)) return;
            if (!config || !config.ajaxUrl || !config.createNonce) {
                setCreateFeedback('Configuration incomplète.', 'error');
                return;
            }

            createIsSubmitting = true;
            syncCreateStep();
            setCreateFeedback('Création en cours…', 'success');

            var formData = buildCreateFormData();

            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (!result || !result.success) {
                    var msg = result && result.data && result.data.message ? result.data.message : 'Impossible de créer l\'événement.';
                    throw new Error(msg);
                }
                var createdEvent = result.data && result.data.event ? result.data.event : null;
                var createdId = createdEvent && createdEvent.id ? parseInt(createdEvent.id, 10) : 0;

                if (mode === 'edit' && createdId > 0) {
                    var baseUrl = config.createUrl || '/mon-compte/gestionnaire/';
                    var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = baseUrl + separator + 'event=' + String(createdId);
                    return;
                }

                // "create only" mode – show confirmation then reload calendar
                var title = createTitleInput ? String(createTitleInput.value || '').trim() : 'L\'événement';
                setCreateFeedback('\u2705 « ' + title + ' » créé avec succès !', 'success');
                setTimeout(function() {
                    closeCreateModal();
                    window.location.reload();
                }, 1500);
            })
            .catch(function(error) {
                createIsSubmitting = false;
                syncCreateStep();
                setCreateFeedback(error && error.message ? error.message : 'Erreur réseau.', 'error');
            });
        }

        populateTypeGrid();
        populateLocationSelect();
        populateTeamGrid();

        if (createPrevButton) {
            createPrevButton.addEventListener('click', function() {
                if (createCurrentStep > 1 && !createIsSubmitting) {
                    createCurrentStep -= 1;
                    setCreateFeedback('', '');
                    syncCreateStep();
                }
            });
        }
        if (createNextButton) {
            createNextButton.addEventListener('click', function() {
                if (createIsSubmitting) return;
                if (!validateCreateStep(createCurrentStep)) return;
                stepValidated[createCurrentStep - 1] = true;
                if (createCurrentStep < totalSteps) {
                    createCurrentStep += 1;
                    syncCreateStep();
                }
            });
        }
        if (createSubmitButton) {
            createSubmitButton.addEventListener('click', function() { submitCreateEvent('edit'); });
        }
        if (createOnlyButton) {
            createOnlyButton.addEventListener('click', function() { submitCreateEvent('create'); });
        }
        if (createCloseButtons.length) {
            createCloseButtons.forEach(function(closeButton) {
                closeButton.addEventListener('click', function() { closeCreateModal(); });
            });
        }
        // Allow clicking stepper dots to navigate to visited/validated steps
        stepperDots.forEach(function(dot) {
            dot.addEventListener('click', function() {
                var targetStep = parseInt(dot.getAttribute('data-step-dot') || '0', 10);
                if (createIsSubmitting || targetStep === createCurrentStep) return;
                // Allow backward or forward to already-validated steps
                if (targetStep < createCurrentStep || stepValidated[targetStep - 1]) {
                    createCurrentStep = targetStep;
                    setCreateFeedback('', '');
                    syncCreateStep();
                }
            });
        });
        root.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && createModal && !createModal.hidden) closeCreateModal();
        });
// ---- Delete occurrence handler ----
        if (config && config.ajaxUrl && config.deleteNonce) {
            root.addEventListener('click', function(e) {
                var addBtn = e.target.closest('[data-calendar-create-day]');
                if (addBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    openCreateModal(addBtn.getAttribute('data-calendar-create-day') || '', addBtn);
                    return;
                }

                var btn = e.target.closest('.mj-member-events-calendar__event-delete');
                if (!btn) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();

                var eventId = parseInt(btn.getAttribute('data-delete-event'), 10);
                var startTs = parseInt(btn.getAttribute('data-delete-ts'), 10);
                if (!eventId || !startTs) {
                    return;
                }

                if (!confirm('Supprimer cette occurrence ? Cette action est irréversible.')) {
                    return;
                }

                btn.classList.add('is-loading');
                btn.disabled = true;

                var formData = new FormData();
                formData.append('action', 'mj_calendar_delete_occurrence');
                formData.append('nonce', config.deleteNonce);
                formData.append('event_id', String(eventId));
                formData.append('start_ts', String(startTs));

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        // Remove the event item from DOM
                        var li = btn.closest('.mj-member-events-calendar__event') || btn.closest('li');
                        if (li) {
                            li.style.transition = 'opacity 0.3s ease';
                            li.style.opacity = '0';
                            setTimeout(function() {
                                li.remove();
                                updateDayStates();
                            }, 300);
                        }
                        // Also remove the mobile counterpart if exists
                        var mobileItem = root.querySelector('.mj-member-events-calendar__event-delete[data-delete-event="' + eventId + '"][data-delete-ts="' + startTs + '"]');
                        if (mobileItem && mobileItem !== btn) {
                            var mobileLi = mobileItem.closest('li');
                            if (mobileLi) {
                                mobileLi.style.transition = 'opacity 0.3s ease';
                                mobileLi.style.opacity = '0';
                                setTimeout(function() { mobileLi.remove(); }, 300);
                            }
                        }
                    } else {
                        var msg = result.data && result.data.message ? result.data.message : 'Erreur lors de la suppression.';
                        alert(msg);
                        btn.classList.remove('is-loading');
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Erreur réseau. Veuillez réessayer.');
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                });
            });
        }

        var months = toArray(root.querySelectorAll('[data-calendar-month]'));
        if (!months.length) {
            return;
        }

        var monthIndexMap = {};
        months.forEach(function(month, idx) {
            var key = month.getAttribute('data-calendar-month');
            if (key) {
                monthIndexMap[key] = idx;
            }
        });

        var filterInputs = toArray(root.querySelectorAll('[data-calendar-filter]'));
        var typeItems = toArray(root.querySelectorAll('[data-calendar-type-item]'));
        var dayNodes = toArray(root.querySelectorAll('[data-calendar-day]'));
        var countSingular = root.getAttribute('data-calendar-count-singular') || '%d';
        var countPlural = root.getAttribute('data-calendar-count-plural') || '%d';
        var countEmpty = root.getAttribute('data-calendar-count-empty') || '';

        var prev = root.querySelector('[data-calendar-nav="prev"]');
        var next = root.querySelector('[data-calendar-nav="next"]');
        var label = root.querySelector('[data-calendar-active-label]');
        var todayBtn = root.querySelector('[data-calendar-action="today"]');
        var todayMonthKey = root.getAttribute('data-calendar-today') || '';
        if (config && config.todayMonth) {
            todayMonthKey = config.todayMonth;
        }
        var activeIndex = 0;
        var weekOnly = root.getAttribute('data-calendar-week-only') === '1';
        var weekDaysAttr = root.getAttribute('data-calendar-week-days') || '';
        var weekDays = weekOnly && weekDaysAttr ? weekDaysAttr.split(',').map(function(item) {
            return item.trim();
        }).filter(function(value) {
            return value.length > 0;
        }) : [];

        if (weekOnly && weekDays.length) {
            var weekDaySet = {};
            weekDays.forEach(function(dayKey) {
                weekDaySet[dayKey] = true;
            });

            var visibleMonths = [];
            months.forEach(function(month) {
                var keepMonth = false;
                var weeks = toArray(month.querySelectorAll('.mj-member-events-calendar__week'));
                weeks.forEach(function(week) {
                    var containsTargetDay = false;
                    var dayCells = toArray(week.querySelectorAll('[data-calendar-day]'));
                    dayCells.forEach(function(dayCell) {
                        var dayKey = dayCell.getAttribute('data-calendar-day');
                        if (dayKey && Object.prototype.hasOwnProperty.call(weekDaySet, dayKey)) {
                            containsTargetDay = true;
                        }
                    });

                    if (!containsTargetDay) {
                        week.style.display = 'none';
                    } else {
                        keepMonth = true;
                    }
                });

                if (keepMonth) {
                    visibleMonths.push(month);
                } else {
                    month.style.display = 'none';
                    month.classList.remove('is-active');
                }
            });

            if (visibleMonths.length) {
                months = visibleMonths;
                monthIndexMap = {};
                months.forEach(function(month, idx) {
                    var key = month.getAttribute('data-calendar-month');
                    if (key) {
                        monthIndexMap[key] = idx;
                    }
                });
            }

            activeIndex = 0;
            root.setAttribute('data-calendar-preferred', '0');
            if (prev) {
                prev.disabled = true;
                prev.style.display = 'none';
            }
            if (next) {
                next.disabled = true;
                next.style.display = 'none';
            }
            if (todayBtn) {
                todayBtn.disabled = true;
                todayBtn.style.display = 'none';
            }
        }

        var todayIndex = -1;
        if (todayMonthKey && Object.prototype.hasOwnProperty.call(monthIndexMap, todayMonthKey)) {
            todayIndex = monthIndexMap[todayMonthKey];
        }

        activeIndex = 0;
        var preferred = parseInt(root.getAttribute('data-calendar-preferred'), 10);
        if (!isNaN(preferred) && preferred >= 0 && preferred < months.length) {
            activeIndex = preferred;
        } else if (config && typeof config.preferredIndex === 'number' && config.preferredIndex >= 0 && config.preferredIndex < months.length) {
            activeIndex = config.preferredIndex;
        }

        function updateDayStates() {
            if (!dayNodes.length) {
                return;
            }
            dayNodes.forEach(function(dayNode) {
                var items = dayNode.querySelectorAll('[data-calendar-type-item]');
                var visibleCount = 0;
                toArray(items).forEach(function(item) {
                    if (!item.classList.contains('is-filtered-out')) {
                        visibleCount += 1;
                    }
                });
                if (visibleCount === 0) {
                    dayNode.classList.add('is-filtered-empty');
                } else {
                    dayNode.classList.remove('is-filtered-empty');
                }
                var countNode = dayNode.querySelector('[data-calendar-day-count]');
                if (countNode) {
                    var label;
                    if (visibleCount === 0) {
                        label = countEmpty || '';
                    } else if (visibleCount === 1) {
                        label = countSingular.replace('%d', '1');
                    } else {
                        label = countPlural.replace('%d', String(visibleCount));
                    }
                    countNode.textContent = label;
                }
            });
        }

        function applyFilters() {
            if (!filterInputs.length) {
                updateDayStates();
                return;
            }

            var activeMap = {};
            var hasChecked = false;
            filterInputs.forEach(function(input) {
                if (input.checked) {
                    activeMap[input.value] = true;
                    hasChecked = true;
                }
            });

            typeItems.forEach(function(item) {
                var typeKey = item.getAttribute('data-calendar-type') || '';
                var isKnown = item.getAttribute('data-calendar-type-known') === '1';
                if (!hasChecked || !isKnown || Object.prototype.hasOwnProperty.call(activeMap, typeKey)) {
                    item.classList.remove('is-filtered-out');
                } else {
                    item.classList.add('is-filtered-out');
                }
            });

            updateDayStates();
        }

        function sync() {
            months.forEach(function(month, idx) {
                if (idx === activeIndex) {
                    month.classList.add('is-active');
                } else {
                    month.classList.remove('is-active');
                }
            });
            if (label && months[activeIndex]) {
                label.textContent = months[activeIndex].getAttribute('data-calendar-label') || '';
            }
            if (prev) {
                prev.disabled = activeIndex === 0;
            }
            if (next) {
                next.disabled = activeIndex === months.length - 1;
            }
            if (todayBtn) {
                todayBtn.disabled = todayIndex === -1 || activeIndex === todayIndex;
            }
            applyFilters();
        }

        if (prev) {
            prev.addEventListener('click', function() {
                if (activeIndex > 0) {
                    activeIndex -= 1;
                    sync();
                }
            });
        }

        if (next) {
            next.addEventListener('click', function() {
                if (activeIndex < months.length - 1) {
                    activeIndex += 1;
                    sync();
                }
            });
        }

        if (todayBtn) {
            todayBtn.addEventListener('click', function() {
                if (todayIndex >= 0 && activeIndex !== todayIndex) {
                    activeIndex = todayIndex;
                    sync();
                }
            });
        }

        if (filterInputs.length) {
            filterInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    applyFilters();
                });
            });
        }

        sync();

        if (!filterInputs.length) {
            updateDayStates();
        }
    }

    function drainQueue() {
        if (!window.mjMemberEventsCalendarQueue) {
            return;
        }
        while (window.mjMemberEventsCalendarQueue.length) {
            var item = window.mjMemberEventsCalendarQueue.shift();
            if (!item || !item.id) {
                continue;
            }
            var root = document.getElementById(item.id);
            if (root && item.config && typeof item.config.preferredIndex === 'number') {
                root.setAttribute('data-calendar-preferred', String(item.config.preferredIndex));
            }
            if (root && item.config && item.config.todayMonth) {
                root.setAttribute('data-calendar-today', String(item.config.todayMonth));
            }
            initCalendar(root, item.config || {});
        }
    }

    domReady(drainQueue);
})();
