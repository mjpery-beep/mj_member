/**
 * MjCreateEventModal – shared create-event stepper modal.
 *
 * Usage:
 *   var modal = MjCreateEventModal.init(rootElement, config);
 *   modal.open('2026-03-10', triggerButton);   // open for a given day
 *   modal.close();
 *
 * config keys:
 *   ajaxUrl, createNonce, createUrl,
 *   createTypes        – { key: label }
 *   createTypeColors   – { key: color }
 *   createLocations    – { id: label }
 *   createAnimateurs   – { id: displayName }
 *
 * The rootElement must contain the HTML produced by
 * CreateEventModalRenderer::render().
 */
(function () {
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var toArray = typeof Utils.toArray === 'function'
        ? Utils.toArray
        : function (collection) {
            if (!collection) return [];
            if (Array.isArray(collection)) return collection.slice();
            try { return Array.prototype.slice.call(collection); }
            catch (e) {
                var arr = [];
                for (var i = 0; i < collection.length; i++) arr.push(collection[i]);
                return arr;
            }
        };

    /**
     * Initialise the create-event modal inside `root`.
     *
     * @param {HTMLElement} root  Container that holds the .ccm markup.
     * @param {Object}      config
     * @returns {{ open: Function, close: Function }}
     */
    function init(root, config) {
        if (!root || !config) return null;

        // ---- DOM refs ----
        // Try scoped search first, then fall back to document-level lookup.
        var modal = root.querySelector
            ? root.querySelector('[data-ccm-modal]')
            : null;
        if (!modal) {
            modal = document.querySelector('[data-ccm-modal]');
        }
        if (!modal) {
            console.warn('[CCM] Modal element [data-ccm-modal] not found');
            return null;
        }

        // All CCM elements live *inside* the modal – scope every query to it.
        var closeButtons    = toArray(modal.querySelectorAll('[data-ccm-close]'));
        var panels          = toArray(modal.querySelectorAll('[data-ccm-panel]'));
        var feedback        = modal.querySelector('[data-ccm-feedback]');
        var titleInput      = modal.querySelector('[data-ccm-title]');
        var typeHidden      = modal.querySelector('[data-ccm-type]');
        var typeGrid        = modal.querySelector('[data-ccm-type-grid]');
        var dateInput       = modal.querySelector('[data-ccm-date]');
        var dateDisplay     = modal.querySelector('[data-ccm-date-display]');
        var startInput      = modal.querySelector('[data-ccm-start]');
        var endInput        = modal.querySelector('[data-ccm-end]');
        var summary         = modal.querySelector('[data-ccm-summary]');
        var prevButton      = modal.querySelector('[data-ccm-prev]');
        var nextButton      = modal.querySelector('[data-ccm-next]');
        var submitButton    = modal.querySelector('[data-ccm-submit]');
        var onlyButton      = modal.querySelector('[data-ccm-only]');
        var emojiMount      = modal.querySelector('[data-ccm-emoji-mount]');
        var freeParticipation = modal.querySelector('[data-ccm-free-participation]');
        var showAllMembers  = modal.querySelector('[data-ccm-show-all-members]');
        var capacity        = modal.querySelector('[data-ccm-capacity]');
        var price           = modal.querySelector('[data-ccm-price]');
        var ageMin          = modal.querySelector('[data-ccm-age-min]');
        var ageMax          = modal.querySelector('[data-ccm-age-max]');
        var locationSelect  = modal.querySelector('[data-ccm-location]');
        var teamGrid        = modal.querySelector('[data-ccm-team-grid]');
        var statusSelect    = modal.querySelector('[data-ccm-status]');
        var occurrenceChoice = modal.querySelector('[data-ccm-occurrence-choice]');
        var requireValidation = modal.querySelector('[data-ccm-require-validation]');
        var coverZone       = modal.querySelector('[data-ccm-cover-zone]');
        var coverInput      = modal.querySelector('[data-ccm-cover-input]');
        var coverPlaceholder = modal.querySelector('[data-ccm-cover-placeholder]');
        var coverPreview    = modal.querySelector('[data-ccm-cover-preview]');
        var coverImg        = modal.querySelector('[data-ccm-cover-img]');
        var coverRemove     = modal.querySelector('[data-ccm-cover-remove]');
        var stepperDots     = toArray(modal.querySelectorAll('[data-ccm-step-dot]'));

        var descriptionInput = modal.querySelector('[data-ccm-description]');
        var coverFile       = null;
        var stepValidated   = [false, false, false, false, false];

        // ---- Date picker sync ----
        if (dateInput && dateInput.type === 'date') {
            dateInput.addEventListener('change', function () {
                selectedDay = String(dateInput.value || '').trim();
                if (dateDisplay) {
                    if (selectedDay) {
                        dateDisplay.textContent = formatDate(selectedDay);
                        dateDisplay.hidden = false;
                    } else {
                        dateDisplay.textContent = '';
                        dateDisplay.hidden = true;
                    }
                }
            });
        }
        var currentStep     = 1;
        var selectedDay     = '';
        var activeTrigger   = null;
        var isSubmitting    = false;
        var selectedEmoji   = '';
        var totalSteps      = 5;
        var emojiRendered   = false;

        // ---- Emoji picker ----
        function mountEmoji() {
            if (emojiRendered || !emojiMount) return;
            var EmojiPicker = window.MjRegMgrEmojiPicker && window.MjRegMgrEmojiPicker.EmojiPickerField;
            var preact = window.preact;
            if (!EmojiPicker || !preact || !preact.render || !preact.h) return;
            var h = preact.h;
            function handleChange(val) { selectedEmoji = String(val || ''); }
            function Wrapper() {
                var hooks = window.preactHooks || {};
                var useState = hooks.useState;
                if (!useState) return h('span', null, '');
                var s = useState(selectedEmoji);
                return h(EmojiPicker, {
                    value: s[0],
                    onChange: function (v) { s[1](v); handleChange(v); },
                    fallbackPlaceholder: '\xF0\x9F\x8E\xB2'
                });
            }
            preact.render(h(Wrapper, null), emojiMount);
            emojiRendered = true;
        }

        // ---- Cover upload ----
        function setCover(file) {
            if (!file || !file.type.startsWith('image/')) { coverFile = null; return; }
            if (file.size > 5 * 1024 * 1024) {
                setFeedback('L\u2019image est trop volumineuse (max 5 Mo).', 'error');
                return;
            }
            coverFile = file;
            var reader = new FileReader();
            reader.onload = function (e) {
                if (coverImg) coverImg.src = e.target.result;
                if (coverPlaceholder) coverPlaceholder.hidden = true;
                if (coverPreview) coverPreview.hidden = false;
            };
            reader.readAsDataURL(file);
        }

        function clearCover() {
            coverFile = null;
            if (coverInput) coverInput.value = '';
            if (coverImg) coverImg.src = '';
            if (coverPlaceholder) coverPlaceholder.hidden = false;
            if (coverPreview) coverPreview.hidden = true;
        }

        if (coverZone) {
            coverZone.addEventListener('click', function (e) {
                if (e.target.closest('[data-ccm-cover-remove]')) return;
                if (coverInput) coverInput.click();
            });
            coverZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                coverZone.classList.add('is-dragover');
            });
            coverZone.addEventListener('dragleave', function () {
                coverZone.classList.remove('is-dragover');
            });
            coverZone.addEventListener('drop', function (e) {
                e.preventDefault();
                coverZone.classList.remove('is-dragover');
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) setCover(files[0]);
            });
        }
        if (coverInput) {
            coverInput.addEventListener('change', function () {
                if (coverInput.files && coverInput.files.length) setCover(coverInput.files[0]);
            });
        }
        if (coverRemove) {
            coverRemove.addEventListener('click', function (e) {
                e.stopPropagation();
                clearCover();
            });
        }

        // ---- Type grid ----
        function populateTypeGrid() {
            if (!typeGrid) return;
            var types = config.createTypes || {};
            var keys = Object.keys(types);
            typeGrid.innerHTML = '';
            if (!keys.length) {
                typeGrid.innerHTML = '<span class="ccm__type-empty">Aucun type disponible</span>';
                return;
            }
            var colors = config.createTypeColors || {};
            keys.forEach(function (typeKey, index) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'ccm__type-chip' + (index === 0 ? ' is-selected' : '');
                chip.setAttribute('data-type-value', typeKey);
                chip.textContent = String(types[typeKey] || typeKey);
                var c = colors[typeKey] || '';
                if (c) chip.style.setProperty('--chip-color', c);
                if (index === 0 && typeHidden) typeHidden.value = typeKey;
                chip.addEventListener('click', function () {
                    toArray(typeGrid.querySelectorAll('.ccm__type-chip')).forEach(function (ch) { ch.classList.remove('is-selected'); });
                    chip.classList.add('is-selected');
                    if (typeHidden) typeHidden.value = typeKey;
                });
                typeGrid.appendChild(chip);
            });
        }

        // ---- Location select ----
        function populateLocations() {
            if (!locationSelect) return;
            var locs = config.createLocations || {};
            var keys = Object.keys(locs);
            while (locationSelect.options.length > 1) locationSelect.remove(1);
            keys.forEach(function (id) {
                var opt = document.createElement('option');
                opt.value = id;
                opt.textContent = String(locs[id] || id);
                locationSelect.appendChild(opt);
            });
        }

        // ---- Team grid ----
        function populateTeam() {
            if (!teamGrid) return;
            var anims = config.createAnimateurs || {};
            var keys = Object.keys(anims);
            teamGrid.innerHTML = '';
            if (!keys.length) {
                teamGrid.innerHTML = '<span class="ccm__team-empty">Aucun animateur disponible</span>';
                return;
            }
            keys.forEach(function (id) {
                var label = document.createElement('label');
                label.className = 'ccm__team-check';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = id;
                cb.setAttribute('data-ccm-animateur', '');
                var span = document.createElement('span');
                span.className = 'ccm__team-name';
                span.textContent = String(anims[id] || id);
                label.appendChild(cb);
                label.appendChild(span);
                teamGrid.appendChild(label);
            });
        }

        // ---- Helpers ----
        function formatDate(str) {
            if (!str) return '';
            try {
                var p = str.split('-');
                var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
                var days = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                var months = ['janvier','f\u00e9vrier','mars','avril','mai','juin','juillet','ao\u00fbt','septembre','octobre','novembre','d\u00e9cembre'];
                return days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            } catch (e) { return str; }
        }

        function buildDateTime(day, time) {
            if (!day || !time) return '';
            return String(day) + ' ' + String(time);
        }

        function setFeedback(msg, type) {
            if (!feedback) return;
            if (!msg) {
                feedback.textContent = '';
                feedback.hidden = true;
                feedback.classList.remove('is-error', 'is-success');
                return;
            }
            feedback.textContent = msg;
            feedback.hidden = false;
            feedback.classList.toggle('is-error', type === 'error');
            feedback.classList.toggle('is-success', type === 'success');
        }

        function validate(step) {
            if (step === 1) {
                var t = titleInput ? String(titleInput.value || '').trim() : '';
                var ty = typeHidden ? String(typeHidden.value || '').trim() : '';
                if (!t) { setFeedback('Le titre est requis.', 'error'); if (titleInput) titleInput.focus(); return false; }
                if (!ty) { setFeedback('S\u00e9lectionnez un type.', 'error'); return false; }
            }
            // Step 2 (Description) – no mandatory validation
            if (step === 3) {
                var sv = startInput ? String(startInput.value || '').trim() : '';
                var ev = endInput ? String(endInput.value || '').trim() : '';
                var needDate = config.dateRequired !== false;
                if (needDate && (!selectedDay || !sv || !ev)) { setFeedback('Date et horaires obligatoires.', 'error'); return false; }
                if (!needDate && selectedDay && (!sv || !ev)) { setFeedback('Si vous renseignez une date, les horaires sont aussi requis.', 'error'); return false; }
                if (selectedDay && sv && ev && ev <= sv) { setFeedback("L'heure de fin doit \u00eatre apr\u00e8s l'heure de d\u00e9but.", 'error'); if (endInput) endInput.focus(); return false; }
            }
            setFeedback('', '');
            return true;
        }

        function updateSummary() {
            if (!summary) return;
            var t = titleInput ? String(titleInput.value || '').trim() : '';
            var typeLabel = '';
            var sel = typeGrid ? typeGrid.querySelector('.is-selected') : null;
            if (sel) typeLabel = sel.textContent || '';
            var sv = startInput ? String(startInput.value || '').trim() : '';
            var ev = endInput ? String(endInput.value || '').trim() : '';
            var emoji = selectedEmoji || '';
            var dl = formatDate(selectedDay);
            var esc = Utils.escapeHtml || function (s) { return String(s); };
            summary.innerHTML = '<div class="ccm__summary-emoji">' + (emoji || '\uD83D\uDCC5') + '</div>'
                + '<div class="ccm__summary-info">'
                + '<strong>' + esc(t) + '</strong>'
                + '<span>' + esc(typeLabel) + '</span>'
                + '<span>' + esc(dl) + ' &middot; ' + esc(sv) + ' \u2192 ' + esc(ev) + '</span>'
                + '</div>';
        }

        function syncStep() {
            panels.forEach(function (panel) {
                var ps = parseInt(panel.getAttribute('data-ccm-panel') || '0', 10);
                var active = ps === currentStep;
                panel.classList.toggle('is-active', active);
                if (active) panel.removeAttribute('hidden');
                else panel.setAttribute('hidden', 'hidden');
            });
            stepperDots.forEach(function (dot) {
                var ds = parseInt(dot.getAttribute('data-ccm-step-dot') || '0', 10);
                dot.classList.toggle('is-active', ds === currentStep);
                dot.classList.toggle('is-done', ds < currentStep);
            });
            if (prevButton) { prevButton.hidden = currentStep <= 1; prevButton.disabled = isSubmitting; }
            if (nextButton) { nextButton.hidden = currentStep >= totalSteps; nextButton.disabled = isSubmitting; }
            if (submitButton) { submitButton.hidden = currentStep < totalSteps || config.showEditButton === false; submitButton.disabled = isSubmitting; }
            if (onlyButton) { onlyButton.hidden = currentStep < totalSteps; onlyButton.disabled = isSubmitting; }
            if (currentStep === 4) updateSummary();
        }

        // ---- Open / Close ----
        function open(dayValue, trigger) {
            if (!modal) return;
            selectedDay = String(dayValue || '').trim();
            activeTrigger = trigger || null;
            currentStep = 1;
            isSubmitting = false;
            selectedEmoji = '';
            setFeedback('', '');

            if (dateInput) dateInput.value = selectedDay;
            if (dateDisplay) {
                if (selectedDay) {
                    dateDisplay.textContent = formatDate(selectedDay);
                    dateDisplay.hidden = false;
                } else {
                    dateDisplay.textContent = '';
                    dateDisplay.hidden = true;
                }
            }
            if (startInput) startInput.value = '14:00';
            if (endInput) endInput.value = '17:00';
            if (titleInput) titleInput.value = '';
            if (descriptionInput) descriptionInput.value = '';
            clearCover();
            if (occurrenceChoice) occurrenceChoice.checked = false;
            if (requireValidation) requireValidation.checked = false;
            if (freeParticipation) freeParticipation.checked = false;
            if (showAllMembers) showAllMembers.checked = false;
            if (capacity) capacity.value = '0';
            if (price) price.value = '0';
            if (ageMin) ageMin.value = '12';
            if (ageMax) ageMax.value = '26';
            if (statusSelect) statusSelect.value = 'brouillon';
            if (locationSelect) locationSelect.value = '0';
            if (teamGrid) {
                toArray(teamGrid.querySelectorAll('input[type="checkbox"]')).forEach(function (cb) { cb.checked = false; });
            }
            stepValidated = [false, false, false, false, false];

            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ccm-open');
            syncStep();
            mountEmoji();
            if (titleInput) titleInput.focus();
        }

        function close() {
            if (!modal) return;
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ccm-open');
            setFeedback('', '');
            isSubmitting = false;
            syncStep();
            if (activeTrigger && typeof activeTrigger.focus === 'function') activeTrigger.focus();
        }

        // ---- Form data ----
        function buildFormData() {
            var tv = titleInput ? String(titleInput.value || '').trim() : '';
            var tyv = typeHidden ? String(typeHidden.value || '').trim() : '';
            var sv = startInput ? String(startInput.value || '').trim() : '';
            var ev = endInput ? String(endInput.value || '').trim() : '';

            var fd = new FormData();
            fd.append('action', 'mj_events_manager_create');
            fd.append('nonce', String(config.createNonce));
            fd.append('title', tv);
            fd.append('type', tyv);
            fd.append('status', statusSelect ? String(statusSelect.value || 'brouillon') : 'brouillon');
            if (selectedDay && sv) fd.append('start_date', buildDateTime(selectedDay, sv));
            if (selectedDay && ev) fd.append('end_date', buildDateTime(selectedDay, ev));
            if (selectedEmoji) fd.append('emoji', selectedEmoji);
            if (coverFile) fd.append('cover_image', coverFile);
            var desc = descriptionInput ? String(descriptionInput.value || '').trim() : '';
            if (desc) fd.append('description', desc);
            if (occurrenceChoice && occurrenceChoice.checked) fd.append('occurrence_choice', '1');
            if (requireValidation && requireValidation.checked) fd.append('require_validation', '1');

            var cap = capacity ? parseInt(capacity.value, 10) || 0 : 0;
            var pr = price ? parseFloat(price.value) || 0 : 0;
            if (cap > 0) fd.append('capacity_total', String(cap));
            if (pr > 0) fd.append('price', String(pr));
            if (showAllMembers && showAllMembers.checked) fd.append('attendance_show_all_members', '1');

            var locId = locationSelect ? parseInt(locationSelect.value, 10) || 0 : 0;
            if (locId > 0) fd.append('location_id', String(locId));

            var checked = teamGrid ? toArray(teamGrid.querySelectorAll('input[data-ccm-animateur]:checked')) : [];
            checked.forEach(function (cb) { fd.append('animateur_ids[]', cb.value); });
            return fd;
        }

        // ---- Submit ----
        function submit(mode) {
            if (isSubmitting) return;
            if (!validate(1) || !validate(3)) return;
            if (!config.ajaxUrl || !config.createNonce) {
                setFeedback('Configuration incompl\u00e8te.', 'error');
                return;
            }

            isSubmitting = true;
            syncStep();
            setFeedback('Cr\u00e9ation en cours\u2026', 'success');

            var fd = buildFormData();

            fetch(config.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (!result || !result.success) {
                        var msg = result && result.data && result.data.message ? result.data.message : 'Impossible de cr\u00e9er l\'\u00e9v\u00e9nement.';
                        throw new Error(msg);
                    }
                    var evt = result.data && result.data.event ? result.data.event : null;
                    var id = evt && evt.id ? parseInt(evt.id, 10) : 0;

                    if (mode === 'edit' && id > 0) {
                        var base = config.createUrl || '/mon-compte/gestionnaire/';
                        var sep = base.indexOf('?') === -1 ? '?' : '&';
                        window.location.href = base + sep + 'event=' + String(id);
                        return;
                    }

                    var title = titleInput ? String(titleInput.value || '').trim() : 'L\'\u00e9v\u00e9nement';
                    setFeedback('\u2705 \u00ab ' + title + ' \u00bb cr\u00e9\u00e9 avec succ\u00e8s !', 'success');

                    // Notify listeners
                    if (typeof config.onCreated === 'function') {
                        config.onCreated(result.data);
                    }

                    setTimeout(function () {
                        close();
                        if (typeof config.onAfterCreate === 'function') {
                            config.onAfterCreate(result.data);
                        } else {
                            window.location.reload();
                        }
                    }, 1500);
                })
                .catch(function (error) {
                    isSubmitting = false;
                    syncStep();
                    setFeedback(error && error.message ? error.message : 'Erreur r\u00e9seau.', 'error');
                });
        }

        // ---- Populate dynamic content ----
        populateTypeGrid();
        populateLocations();
        populateTeam();

        // ---- Event listeners ----
        if (prevButton) {
            prevButton.addEventListener('click', function () {
                if (currentStep > 1 && !isSubmitting) {
                    currentStep -= 1;
                    setFeedback('', '');
                    syncStep();
                }
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', function () {
                if (isSubmitting) return;
                if (!validate(currentStep)) return;
                stepValidated[currentStep - 1] = true;
                if (currentStep < totalSteps) { currentStep += 1; syncStep(); }
            });
        }
        if (submitButton) {
            submitButton.addEventListener('click', function () { submit('edit'); });
        }
        if (onlyButton) {
            onlyButton.addEventListener('click', function () { submit('create'); });
        }
        closeButtons.forEach(function (btn) {
            btn.addEventListener('click', function () { close(); });
        });
        stepperDots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                var target = parseInt(dot.getAttribute('data-ccm-step-dot') || '0', 10);
                if (isSubmitting || target === currentStep) return;
                if (target < currentStep || stepValidated[target - 1]) {
                    currentStep = target;
                    setFeedback('', '');
                    syncStep();
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hidden) close();
        });

        return { open: open, close: close };
    }

    // Expose globally
    window.MjCreateEventModal = { init: init };
})();
