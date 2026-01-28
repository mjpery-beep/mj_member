(function (window) {
    'use strict';

    if (!window) {
        return;
    }

    var utils = window.MjMemberUtils || {};

    function fallbackInt(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? null : parsed;
    }

    function fallbackToArray(collection) {
        if (!collection) {
            return [];
        }

        if (Array.isArray(collection)) {
            return collection.slice();
        }

        try {
            return Array.prototype.slice.call(collection);
        } catch (error) {
            var copy = [];
            for (var i = 0; i < collection.length; i += 1) {
                copy.push(collection[i]);
            }
            return copy;
        }
    }

    function fallbackDomReady(callback) {
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
    }

    var toInt = typeof utils.toInt === 'function' ? utils.toInt : fallbackInt;
    var toArray = typeof utils.toArray === 'function' ? utils.toArray : fallbackToArray;
    var domReady = typeof utils.domReady === 'function' ? utils.domReady : fallbackDomReady;

    function interpretFlag(value) {
        if (value === true || value === 1) {
            return true;
        }
        if (value === false || value === 0 || value === null) {
            return false;
        }
        if (typeof value === 'string') {
            var normalized = value.trim().toLowerCase();
            if (normalized === '' || normalized === '0' || normalized === 'false' || normalized === 'no') {
                return false;
            }
            if (normalized === '1' || normalized === 'true' || normalized === 'yes') {
                return true;
            }
        }
        return Boolean(value);
    }

    domReady(function () {
        var settings = window.mjMemberAccountData;
        if (!settings || !settings.ajaxUrl) {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('[mj-member][member-account] Données non disponibles, arrêt du script.', settings);
            }
            return;
        }

        var root = document.querySelector('[data-mj-member-account]');
        if (!root) {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('[mj-member][member-account] Conteneur principal introuvable, arrêt du script.');
            }
            return;
        }
        var section = root.querySelector('[data-mj-member-children-section]');
        if (!section) {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('[mj-member][member-account] Section enfants introuvable, arrêt du script.');
            }
            return;
        }

        var modal = root.querySelector('[data-mj-member-child-modal]');
        if (!modal) {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('[mj-member][member-account] Modale introuvable, arrêt du script.');
            }
            return;
        }

        var isPreview = interpretFlag(settings.isPreview);

        var childrenContainer = section.querySelector('[data-mj-member-children]');
        var emptyStateNode = section.querySelector('[data-mj-member-children-empty]');
        var feedbackNode = section.querySelector('[data-mj-member-child-feedback]');
        var form = modal.querySelector('[data-mj-member-child-form]');
        var errorsNode = modal.querySelector('[data-mj-member-child-errors]');
        var submitButton = modal.querySelector('[data-mj-member-child-submit]');
        var cancelButton = modal.querySelector('[data-mj-member-child-cancel]');
        var titleNode = modal.querySelector('[data-mj-member-child-title]');
        var closeButtons = toArray(modal.querySelectorAll('[data-mj-member-child-close]'));
        var photoInput = modal.querySelector('[data-mj-child-photo-input]');
        var photoPreviewNode = modal.querySelector('[data-mj-child-photo-preview]');
        var photoImageNode = modal.querySelector('[data-mj-child-photo-image]');
        var photoPlaceholderNode = modal.querySelector('[data-mj-child-photo-placeholder]');
        var photoFilenameNode = modal.querySelector('[data-mj-child-photo-filename]');
        var photoRemoveField = modal.querySelector('[data-mj-child-field="photo_remove"]');
        var photoExistingField = form ? form.querySelector('[data-mj-child-field="photo_id_existing"]') : null;
        var photoFilenameDefault = photoFilenameNode && photoFilenameNode.getAttribute('data-default') ? photoFilenameNode.getAttribute('data-default') : '';
        var photoState = {
            initialId: 0,
            initialUrl: '',
            initialAlt: '',
            objectUrl: null,
        };

        if (!form || !submitButton || !titleNode) {
            return;
        }

        var formMode = 'create';
        var currentChildId = null;
        var submitLabels = {
            create: settings.i18n && settings.i18n.submitCreate ? String(settings.i18n.submitCreate) : 'Enregistrer',
            edit: settings.i18n && settings.i18n.submitEdit ? String(settings.i18n.submitEdit) : 'Enregistrer',
        };
        var defaultTitleCreate = settings.i18n && settings.i18n.formTitleCreate ? String(settings.i18n.formTitleCreate) : '';
        var defaultTitleEdit = settings.i18n && settings.i18n.formTitleEdit ? String(settings.i18n.formTitleEdit) : '';
        var defaultChildName = settings.i18n && settings.i18n.defaultChildName ? String(settings.i18n.defaultChildName) : '';

        var children = [];
        var childIndex = Object.create(null);

        function normalizeChild(input) {
            if (!input || typeof input !== 'object') {
                return null;
            }

            var id = toInt(input.id);
            if (id === null) {
                return null;
            }

            var profile = input.profile && typeof input.profile === 'object' ? input.profile : {};
            var first = profile.first_name ? String(profile.first_name) : '';
            var last = profile.last_name ? String(profile.last_name) : '';
            var fullname = input.full_name ? String(input.full_name) : (first + ' ' + last).trim();
            if (fullname === '') {
                fullname = defaultChildName;
            }

            var photoSource = input.photo && typeof input.photo === 'object' ? input.photo : null;
            var resolvedPhotoId = null;
            if (photoSource && photoSource.id !== undefined && photoSource.id !== null) {
                resolvedPhotoId = toInt(photoSource.id);
            }
            if (resolvedPhotoId === null && profile.photo_id !== undefined) {
                resolvedPhotoId = toInt(profile.photo_id);
            }
            if (resolvedPhotoId === null) {
                resolvedPhotoId = 0;
            }
            var resolvedPhotoUrl = '';
            if (photoSource && photoSource.url) {
                resolvedPhotoUrl = String(photoSource.url);
            } else if (profile.photo_url) {
                resolvedPhotoUrl = String(profile.photo_url);
            }

            return {
                id: id,
                full_name: fullname,
                status: input.status ? String(input.status) : 'unknown',
                status_label: input.status_label ? String(input.status_label) : '',
                description: input.description ? String(input.description) : '',
                last_payment_display: input.last_payment_display ? String(input.last_payment_display) : '',
                expires_display: input.expires_display ? String(input.expires_display) : '',
                requires_payment: input.requires_payment ? 1 : 0,
                photo: {
                    id: resolvedPhotoId,
                    url: resolvedPhotoUrl,
                },
                profile: {
                    first_name: first,
                    last_name: last,
                    email: profile.email ? String(profile.email) : '',
                    phone: profile.phone ? String(profile.phone) : '',
                    birth_date: profile.birth_date ? String(profile.birth_date) : '',
                    notes: profile.notes ? String(profile.notes) : '',
                    is_autonomous: profile.is_autonomous ? 1 : 0,
                    photo_usage_consent: profile.photo_usage_consent ? 1 : 0,
                    photo_id: resolvedPhotoId,
                    photo_url: resolvedPhotoUrl,
                },
            };
        }

        function findChildIndex(id) {
            for (var i = 0; i < children.length; i += 1) {
                if (children[i].id === id) {
                    return i;
                }
            }
            return -1;
        }

        function upsertChild(input) {
            var normalized = normalizeChild(input);
            if (!normalized) {
                return null;
            }

            var index = findChildIndex(normalized.id);
            if (index === -1) {
                children.push(normalized);
            } else {
                children[index] = normalized;
            }
            childIndex[normalized.id] = normalized;
            return normalized;
        }

        function ensureChildrenContainer() {
            if (childrenContainer && childrenContainer.parentNode) {
                return childrenContainer;
            }

            var container = document.createElement('div');
            container.className = 'mj-account-children';
            container.setAttribute('data-mj-member-children', '');
            if (emptyStateNode && emptyStateNode.parentNode) {
                emptyStateNode.parentNode.insertBefore(container, emptyStateNode);
            } else {
                section.appendChild(container);
            }
            childrenContainer = container;
            return container;
        }

        function sanitizeStatus(status) {
            if (!status) {
                return 'unknown';
            }
            return String(status).replace(/[^a-z0-9_-]/gi, '').toLowerCase() || 'unknown';
        }

        function createAnchor(href, text) {
            var anchor = document.createElement('a');
            anchor.className = 'mj-account-link';
            anchor.href = href;
            anchor.textContent = text;
            return anchor;
        }

        function renderChildCard(child) {
            var statusClass = sanitizeStatus(child.status);
            var article = document.createElement('article');
            article.className = 'mj-account-child-card mj-account-child-card--' + statusClass;
            article.setAttribute('data-mj-member-child', '');
            article.setAttribute('data-mj-child-id', String(child.id));
            article.setAttribute('data-mj-child-status', statusClass);

            var layout = document.createElement('div');
            layout.className = 'mj-account-child-card__layout';
            article.appendChild(layout);

            var media = document.createElement('div');
            media.className = 'mj-account-child-card__media';
            var hasPhoto = child.photo && child.photo.url;
            if (hasPhoto) {
                var img = document.createElement('img');
                img.src = String(child.photo.url);
                img.alt = child.full_name ? String(child.full_name) : '';
                media.appendChild(img);
            } else {
                var placeholder = document.createElement('span');
                placeholder.className = 'mj-account-child-card__placeholder';
                placeholder.setAttribute('aria-hidden', 'true');
                placeholder.textContent = '👤';
                media.appendChild(placeholder);
            }
            layout.appendChild(media);

            var content = document.createElement('div');
            content.className = 'mj-account-child-card__content';
            layout.appendChild(content);

            var header = document.createElement('div');
            header.className = 'mj-account-child-card__header';
            content.appendChild(header);

            var heading = document.createElement('div');
            heading.className = 'mj-account-child-card__heading';
            header.appendChild(heading);

            var title = document.createElement('h4');
            title.className = 'mj-account-child-card__title';
            title.textContent = child.full_name;
            heading.appendChild(title);

            if (child.status_label) {
                var chip = document.createElement('span');
                chip.className = 'mj-account-chip mj-account-chip--' + statusClass;
                chip.textContent = child.status_label;
                heading.appendChild(chip);
            }

            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'mj-button mj-button--ghost mj-account-child-card__edit';
            editButton.setAttribute('data-mj-member-child-edit', '');
            editButton.setAttribute('data-child-id', String(child.id));
            editButton.textContent = settings.i18n && settings.i18n.editChild ? String(settings.i18n.editChild) : 'Modifier';
            header.appendChild(editButton);

            if (child.description) {
                var summary = document.createElement('p');
                summary.className = 'mj-account-child-card__summary';
                summary.textContent = child.description;
                content.appendChild(summary);
            }

            var metaList = document.createElement('ul');
            metaList.className = 'mj-account-child-card__meta';

            if (child.profile.birth_date) {
                var birthItem = document.createElement('li');
                var birthLabel = settings.i18n && settings.i18n.birthLabel ? String(settings.i18n.birthLabel) : 'Né(e) le';
                birthItem.textContent = birthLabel + ' ' + child.profile.birth_date;
                metaList.appendChild(birthItem);
            }

            if (child.profile.email) {
                var emailItem = document.createElement('li');
                emailItem.appendChild(createAnchor('mailto:' + child.profile.email, child.profile.email));
                metaList.appendChild(emailItem);
            }

            if (child.profile.phone) {
                var phoneItem = document.createElement('li');
                phoneItem.appendChild(createAnchor('tel:' + child.profile.phone, child.profile.phone));
                metaList.appendChild(phoneItem);
            }

            if (child.expires_display) {
                var expiresItem = document.createElement('li');
                var expiresLabel = settings.i18n && settings.i18n.expiresLabel ? String(settings.i18n.expiresLabel) : 'Expire le';
                expiresItem.textContent = expiresLabel + ' ' + child.expires_display;
                metaList.appendChild(expiresItem);
            }

            if (child.profile.is_autonomous) {
                var autonomousItem = document.createElement('li');
                autonomousItem.textContent = settings.i18n && settings.i18n.autonomousLabel ? String(settings.i18n.autonomousLabel) : '';
                if (autonomousItem.textContent) {
                    metaList.appendChild(autonomousItem);
                }
            }

            if (child.profile.photo_usage_consent) {
                var consentItem = document.createElement('li');
                consentItem.textContent = settings.i18n && settings.i18n.photoConsentLabel ? String(settings.i18n.photoConsentLabel) : '';
                if (consentItem.textContent) {
                    metaList.appendChild(consentItem);
                }
            }

            if (metaList.children.length > 0) {
                content.appendChild(metaList);
            }

            if (child.profile.notes) {
                var notes = document.createElement('p');
                notes.className = 'mj-account-child-card__notes';
                var notesLabel = settings.i18n && settings.i18n.notesLabel ? String(settings.i18n.notesLabel) : 'Notes';
                var strong = document.createElement('strong');
                strong.textContent = notesLabel + ' :';
                notes.appendChild(strong);
                notes.appendChild(document.createTextNode(' ' + child.profile.notes));
                content.appendChild(notes);
            }

            return article;
        }

        function updateEmptyState() {
            var hasChildren = children.length > 0;
            if (emptyStateNode) {
                emptyStateNode.hidden = hasChildren;
            }
            if (childrenContainer) {
                childrenContainer.hidden = !hasChildren;
            }
        }

        function showFeedback(message, type) {
            if (!feedbackNode) {
                return;
            }

            if (!message) {
                feedbackNode.textContent = '';
                feedbackNode.hidden = true;
                feedbackNode.classList.remove('is-success', 'is-error');
                return;
            }

            feedbackNode.textContent = String(message);
            feedbackNode.hidden = false;
            feedbackNode.classList.remove('is-success', 'is-error');

            if (type === 'error') {
                feedbackNode.classList.add('is-error');
            } else {
                feedbackNode.classList.add('is-success');
            }
        }

        function clearErrors() {
            if (!errorsNode) {
                return;
            }
            errorsNode.innerHTML = '';
            errorsNode.hidden = true;
        }

        function displayErrors(messages) {
            if (!errorsNode) {
                return;
            }

            clearErrors();
            var displayMessages = messages;
            if (isPreview) {
                displayMessages = [settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Cette action est désactivée en mode prévisualisation.'];
            }

            if (!displayMessages || displayMessages.length === 0) {
                return;
            }

            var intro = document.createElement('p');
            intro.className = 'mj-modal__error-intro';
            intro.textContent = settings.i18n && settings.i18n.errorListIntro ? String(settings.i18n.errorListIntro) : '';

            var list = document.createElement('ul');
            list.className = 'mj-modal__error-list';

            for (var i = 0; i < displayMessages.length; i += 1) {
                if (!displayMessages[i]) {
                    continue;
                }
                var item = document.createElement('li');
                item.textContent = String(displayMessages[i]);
                list.appendChild(item);
            }

            errorsNode.appendChild(intro);
            errorsNode.appendChild(list);
            errorsNode.hidden = false;
        }

        function setLoading(isLoading) {
            if (!submitButton) {
                return;
            }

            if (isLoading) {
                submitButton.disabled = true;
                submitButton.dataset.loading = '1';
                submitButton.textContent = settings.i18n && settings.i18n.saving ? String(settings.i18n.saving) : 'Enregistrement…';
                if (cancelButton) {
                    cancelButton.disabled = true;
                }
                closeButtons.forEach(function (button) {
                    if (button.tagName === 'BUTTON') {
                        button.disabled = true;
                    }
                });
            } else {
                submitButton.disabled = false;
                submitButton.textContent = submitLabels[formMode];
                delete submitButton.dataset.loading;
                if (cancelButton) {
                    cancelButton.disabled = false;
                }
                closeButtons.forEach(function (button) {
                    if (button.tagName === 'BUTTON') {
                        button.disabled = false;
                    }
                });
            }
        }

        function releasePhotoObjectUrl() {
            if (photoState.objectUrl && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
                URL.revokeObjectURL(photoState.objectUrl);
            }
            photoState.objectUrl = null;
        }

        function updatePhotoFilename(text) {
            if (!photoFilenameNode) {
                return;
            }
            if (text && String(text).trim() !== '') {
                photoFilenameNode.textContent = String(text);
            } else {
                photoFilenameNode.textContent = photoFilenameDefault;
            }
        }

        function setPhotoPreview(url, alt) {
            var hasUrl = !!(url && String(url).trim() !== '');
            if (photoImageNode) {
                if (hasUrl) {
                    photoImageNode.src = String(url);
                    photoImageNode.alt = alt ? String(alt) : '';
                    photoImageNode.hidden = false;
                } else {
                    photoImageNode.removeAttribute('src');
                    photoImageNode.alt = '';
                    photoImageNode.hidden = true;
                }
            }
            if (photoPlaceholderNode) {
                photoPlaceholderNode.hidden = hasUrl;
            }
        }

        function resetPhotoControls() {
            releasePhotoObjectUrl();
            if (photoInput) {
                try {
                    photoInput.value = '';
                } catch (error) {
                    // Ignore reset errors on older browsers.
                }
            }
            if (photoRemoveField) {
                photoRemoveField.checked = false;
            }
            if (photoExistingField) {
                photoExistingField.value = '';
            }
            photoState.initialId = 0;
            photoState.initialUrl = '';
            photoState.initialAlt = '';
            setPhotoPreview('', '');
            updatePhotoFilename('');
        }

        function applyInitialPhoto() {
            if (photoState.initialUrl) {
                setPhotoPreview(photoState.initialUrl, photoState.initialAlt);
            } else {
                setPhotoPreview('', '');
            }
            updatePhotoFilename('');
        }

        function handlePhotoInputChange() {
            if (!photoInput) {
                return;
            }

            var file = (photoInput.files && photoInput.files[0]) ? photoInput.files[0] : null;
            if (!file) {
                releasePhotoObjectUrl();
                if (photoRemoveField && photoRemoveField.checked) {
                    setPhotoPreview('', '');
                    updatePhotoFilename('');
                    return;
                }
                applyInitialPhoto();
                return;
            }

            releasePhotoObjectUrl();
            if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
                photoState.objectUrl = URL.createObjectURL(file);
                setPhotoPreview(photoState.objectUrl, file.name);
            } else {
                setPhotoPreview('', '');
            }
            updatePhotoFilename(file.name);
            if (photoRemoveField) {
                photoRemoveField.checked = false;
            }
        }

        function handlePhotoRemoveChange() {
            if (!photoRemoveField) {
                return;
            }
            if (photoRemoveField.checked) {
                releasePhotoObjectUrl();
                if (photoInput) {
                    try {
                        photoInput.value = '';
                    } catch (error) {
                        // Ignore reset errors on older browsers.
                    }
                }
                setPhotoPreview('', '');
                updatePhotoFilename('');
            } else if (photoInput && photoInput.files && photoInput.files[0]) {
                handlePhotoInputChange();
            } else {
                applyInitialPhoto();
            }
        }

        function setFormValues(child) {
            var fieldMap = {
                child_id: form.querySelector('[data-mj-child-field="child_id"]'),
                first_name: form.querySelector('[data-mj-child-field="first_name"]'),
                last_name: form.querySelector('[data-mj-child-field="last_name"]'),
                email: form.querySelector('[data-mj-child-field="email"]'),
                phone: form.querySelector('[data-mj-child-field="phone"]'),
                birth_date: form.querySelector('[data-mj-child-field="birth_date"]'),
                notes: form.querySelector('[data-mj-child-field="notes"]'),
                is_autonomous: form.querySelector('[data-mj-child-field="is_autonomous"]'),
                photo_usage_consent: form.querySelector('[data-mj-child-field="photo_usage_consent"]'),
            };

            resetPhotoControls();

            if (!child) {
                currentChildId = null;
                if (fieldMap.child_id) {
                    fieldMap.child_id.value = '';
                }
                if (fieldMap.first_name) {
                    fieldMap.first_name.value = '';
                }
                if (fieldMap.last_name) {
                    fieldMap.last_name.value = '';
                }
                if (fieldMap.email) {
                    fieldMap.email.value = '';
                }
                if (fieldMap.phone) {
                    fieldMap.phone.value = '';
                }
                if (fieldMap.birth_date) {
                    fieldMap.birth_date.value = '';
                }
                if (fieldMap.notes) {
                    fieldMap.notes.value = '';
                }
                if (fieldMap.is_autonomous) {
                    fieldMap.is_autonomous.checked = false;
                }
                if (fieldMap.photo_usage_consent) {
                    fieldMap.photo_usage_consent.checked = false;
                }
                return;
            }

            currentChildId = child.id;
            if (fieldMap.child_id) {
                fieldMap.child_id.value = String(child.id);
            }
            if (fieldMap.first_name) {
                fieldMap.first_name.value = child.profile.first_name || '';
            }
            if (fieldMap.last_name) {
                fieldMap.last_name.value = child.profile.last_name || '';
            }
            if (fieldMap.email) {
                fieldMap.email.value = child.profile.email || '';
            }
            if (fieldMap.phone) {
                fieldMap.phone.value = child.profile.phone || '';
            }
            if (fieldMap.birth_date) {
                fieldMap.birth_date.value = child.profile.birth_date || '';
            }
            if (fieldMap.notes) {
                fieldMap.notes.value = child.profile.notes || '';
            }
            if (fieldMap.is_autonomous) {
                fieldMap.is_autonomous.checked = !!child.profile.is_autonomous;
            }
            if (fieldMap.photo_usage_consent) {
                fieldMap.photo_usage_consent.checked = !!child.profile.photo_usage_consent;
            }

             var photoInfo = (child.photo && typeof child.photo === 'object') ? child.photo : null;
             var photoId = null;
             if (photoInfo && photoInfo.id !== undefined && photoInfo.id !== null) {
                 photoId = toInt(photoInfo.id);
             }
             if (photoId === null && child.profile && child.profile.photo_id !== undefined) {
                 photoId = toInt(child.profile.photo_id);
             }
             var photoUrl = '';
             if (photoInfo && photoInfo.url) {
                 photoUrl = String(photoInfo.url);
             } else if (child.profile && child.profile.photo_url) {
                 photoUrl = String(child.profile.photo_url);
             }

             photoState.initialId = photoId !== null ? photoId : 0;
             photoState.initialUrl = photoUrl;
             photoState.initialAlt = child.full_name || defaultChildName;

             if (photoExistingField) {
                 photoExistingField.value = photoState.initialId > 0 ? String(photoState.initialId) : '';
             }

             applyInitialPhoto();
        }

        function focusFirstField() {
            var first = form.querySelector('[data-mj-child-field="first_name"]');
            if (first && typeof first.focus === 'function' && !first.disabled) {
                first.focus();
            }
        }

        function setFormMode(mode, child) {
            formMode = mode === 'edit' ? 'edit' : 'create';
            submitButton.textContent = submitLabels[formMode];
            titleNode.textContent = formMode === 'edit' ? defaultTitleEdit : defaultTitleCreate;
            setFormValues(child || null);
            clearErrors();
        }

        function handleKeyDown(event) {
            if (event.key === 'Escape' && !modal.hidden) {
                event.preventDefault();
                closeModal();
            }
        }

        function openModal(mode, childId) {
            var child = null;
            if (mode === 'edit') {
                if (childId === null) {
                    return;
                }
                child = childIndex[childId];
                if (!child) {
                    showFeedback(settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue.', 'error');
                    return;
                }
            }

            form.reset();
            setFormMode(mode, child);
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-visible');
            document.body.classList.add('mj-modal-open');
            document.addEventListener('keydown', handleKeyDown);
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(function () {
                    focusFirstField();
                });
            } else {
                focusFirstField();
            }
        }

        function closeModal() {
            if (modal.hidden) {
                return;
            }
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-visible');
            document.body.classList.remove('mj-modal-open');
            document.removeEventListener('keydown', handleKeyDown);
            form.reset();
            resetPhotoControls();
            clearErrors();
        }

        function collectFormValues() {
            var values = {
                child_id: currentChildId !== null ? currentChildId : toInt(form.querySelector('[data-mj-child-field="child_id"]').value),
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                birth_date: '',
                notes: '',
                is_autonomous: 0,
                photo_usage_consent: 0,
                photo_remove: 0,
                photo_id_existing: null,
                has_new_photo: false,
            };

            var first = form.querySelector('[data-mj-child-field="first_name"]');
            if (first) {
                values.first_name = first.value ? first.value.trim() : '';
            }

            var last = form.querySelector('[data-mj-child-field="last_name"]');
            if (last) {
                values.last_name = last.value ? last.value.trim() : '';
            }

            var email = form.querySelector('[data-mj-child-field="email"]');
            if (email) {
                values.email = email.value ? email.value.trim() : '';
            }

            var phone = form.querySelector('[data-mj-child-field="phone"]');
            if (phone) {
                values.phone = phone.value ? phone.value.trim() : '';
            }

            var birth = form.querySelector('[data-mj-child-field="birth_date"]');
            if (birth) {
                values.birth_date = birth.value ? birth.value.trim() : '';
            }

            var notes = form.querySelector('[data-mj-child-field="notes"]');
            if (notes) {
                values.notes = notes.value ? notes.value.trim() : '';
            }

            var autonomous = form.querySelector('[data-mj-child-field="is_autonomous"]');
            if (autonomous) {
                values.is_autonomous = autonomous.checked ? 1 : 0;
            }

            var photoConsent = form.querySelector('[data-mj-child-field="photo_usage_consent"]');
            if (photoConsent) {
                values.photo_usage_consent = photoConsent.checked ? 1 : 0;
            }

            if (photoRemoveField) {
                values.photo_remove = photoRemoveField.checked ? 1 : 0;
            }

            if (photoExistingField) {
                var existingId = toInt(photoExistingField.value);
                if (existingId !== null) {
                    values.photo_id_existing = existingId;
                }
            }

            if (photoInput && photoInput.files && photoInput.files.length > 0) {
                values.has_new_photo = true;
            }

            return values;
        }

        function validateValues(values) {
            var messages = [];
            if (!values.first_name) {
                messages.push(settings.i18n && settings.i18n.errorFirstName ? settings.i18n.errorFirstName : 'Merci de renseigner le prénom du jeune.');
            }
            if (!values.last_name) {
                messages.push(settings.i18n && settings.i18n.errorLastName ? settings.i18n.errorLastName : 'Merci de renseigner le nom du jeune.');
            }
            if (formMode === 'edit' && (values.child_id === null || values.child_id === undefined)) {
                messages.push(settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue. Merci de réessayer.');
            }
            return messages;
        }

        function buildPayload(values) {
            var actionKey = formMode === 'edit' ? 'update' : 'create';
            var supportFormData = typeof FormData === 'function';
            var actionName = settings.actions && settings.actions[actionKey] ? settings.actions[actionKey] : '';
            var nonceValue = settings.nonces && settings.nonces[actionKey] ? settings.nonces[actionKey] : '';

            if (!supportFormData && values.has_new_photo) {
                displayErrors([settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue. Merci de réessayer.']);
                return null;
            }

            if (supportFormData) {
                var data = new FormData();
                data.append('action', actionName);
                data.append('nonce', nonceValue);
                data.append('first_name', values.first_name);
                data.append('last_name', values.last_name);
                data.append('email', values.email);
                data.append('phone', values.phone);
                data.append('birth_date', values.birth_date);
                data.append('notes', values.notes);
                data.append('is_autonomous', String(values.is_autonomous));
                data.append('photo_usage_consent', String(values.photo_usage_consent));
                data.append('photo_remove', values.photo_remove ? '1' : '0');

                if (formMode === 'edit') {
                    data.append('child_id', String(values.child_id));
                }

                if (values.photo_id_existing !== null && values.photo_id_existing !== undefined) {
                    data.append('photo_id_existing', String(values.photo_id_existing));
                }

                if (photoInput && photoInput.files && photoInput.files[0]) {
                    data.append('child_photo', photoInput.files[0]);
                }

                return data;
            }

            var payload = {
                action: actionName,
                nonce: nonceValue,
                first_name: values.first_name,
                last_name: values.last_name,
                email: values.email,
                phone: values.phone,
                birth_date: values.birth_date,
                notes: values.notes,
                is_autonomous: values.is_autonomous,
                photo_usage_consent: values.photo_usage_consent,
                photo_remove: values.photo_remove ? 1 : 0,
            };

            if (formMode === 'edit') {
                payload.child_id = values.child_id;
            }

            if (values.photo_id_existing !== null && values.photo_id_existing !== undefined) {
                payload.photo_id_existing = values.photo_id_existing;
            }

            return payload;
        }

        function sendPayload(data) {
            var isFormData = typeof FormData === 'function' && data instanceof FormData;
            var bodyData = null;
            var headers = null;

            if (!isFormData) {
                var hasSearchParams = typeof URLSearchParams === 'function';
                if (hasSearchParams) {
                    var params = new URLSearchParams();
                    Object.keys(data).forEach(function (key) {
                        var value = data[key];
                        if (value === undefined || value === null) {
                            return;
                        }
                        params.append(key, value);
                    });
                    bodyData = params.toString();
                } else {
                    var pairs = [];
                    Object.keys(data).forEach(function (key) {
                        var value = data[key];
                        if (value === undefined || value === null) {
                            return;
                        }
                        pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
                    });
                    bodyData = pairs.join('&');
                }
                headers = {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                };
            }

            if (typeof window.fetch !== 'function') {
                return new Promise(function (resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', settings.ajaxUrl, true);
                    if (!isFormData) {
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                    }
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                var parsed = JSON.parse(xhr.responseText);
                                resolve(parsed);
                            } catch (parseError) {
                                reject(parseError);
                            }
                        } else {
                            var error = new Error('Request failed');
                            error.status = xhr.status;
                            error.body = xhr.responseText;
                            reject(error);
                        }
                    };
                    xhr.onerror = function () {
                        reject(new Error('Network error'));
                    };
                    xhr.send(isFormData ? data : bodyData);
                });
            }

            var fetchOptions = {
                method: 'POST',
                credentials: 'same-origin',
                body: isFormData ? data : bodyData,
            };

            if (headers) {
                fetchOptions.headers = headers;
            }

            return fetch(settings.ajaxUrl, fetchOptions).then(function (response) {
                if (!response.ok) {
                    var error = new Error('Request failed');
                    error.status = response.status;
                    return response.text().then(function (text) {
                        error.body = text;
                        throw error;
                    });
                }

                return response.json().catch(function () {
                    var parseError = new Error('Invalid JSON response');
                    throw parseError;
                });
            });
        }

        function upsertCard(child) {
            var normalized = upsertChild(child);
            if (!normalized) {
                return;
            }

            var container = ensureChildrenContainer();
            container.hidden = false;
            var newCard = renderChildCard(normalized);
            var existing = container.querySelector('[data-mj-child-id="' + String(normalized.id) + '"]');
            if (existing && existing.parentNode) {
                existing.parentNode.replaceChild(newCard, existing);
            } else {
                container.appendChild(newCard);
            }

            updateEmptyState();
        }

        function handleSubmit(event) {
            event.preventDefault();
            if (submitButton.disabled) {
                return;
            }

            clearErrors();
            var values = collectFormValues();
            var validationErrors = validateValues(values);
            if (validationErrors.length > 0) {
                displayErrors(validationErrors);
                return;
            }

            var payload = buildPayload(values);
            if (!payload) {
                return;
            }

            var missingCredentials = false;
            if (typeof FormData === 'function' && payload instanceof FormData) {
                missingCredentials = !payload.get('action') || !payload.get('nonce');
            } else {
                missingCredentials = !payload.action || !payload.nonce;
            }

            if (missingCredentials) {
                displayErrors([settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue. Merci de réessayer.']);
                return;
            }

            setLoading(true);
            sendPayload(payload)
                .then(function (response) {
                    if (!response || response.success !== true) {
                        var errorMessage = settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue. Merci de réessayer.';
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        displayErrors([errorMessage]);
                        return;
                    }

                    var child = response.data && response.data.child ? response.data.child : null;
                    if (!child) {
                        displayErrors([settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue. Merci de réessayer.']);
                        return;
                    }

                    upsertCard(child);

                    var successMessage = response.data && response.data.message ? response.data.message : (formMode === 'edit'
                        ? (settings.i18n && settings.i18n.successUpdate ? settings.i18n.successUpdate : 'Les informations du jeune ont été mises à jour.')
                        : (settings.i18n && settings.i18n.successCreate ? settings.i18n.successCreate : 'Le jeune a été ajouté.'));

                    closeModal();
                    showFeedback(successMessage, 'success');
                })
                .catch(function (error) {
                    var message = settings.i18n && settings.i18n.errorGeneric ? settings.i18n.errorGeneric : 'Une erreur est survenue. Merci de réessayer.';
                    if (error && error.body) {
                        try {
                            var parsedBody = JSON.parse(error.body);
                            if (parsedBody && parsedBody.data && parsedBody.data.message) {
                                message = parsedBody.data.message;
                            } else if (typeof parsedBody === 'string' && parsedBody.trim() !== '') {
                                message = parsedBody.trim();
                            }
                        } catch (parseError) {
                            var trimmed = String(error.body).trim();
                            if (trimmed.length > 0) {
                                if (trimmed.length > 160) {
                                    trimmed = trimmed.slice(0, 157) + '…';
                                }
                                message = message + ' (' + trimmed + ')';
                            }
                        }
                    }
                    displayErrors([message]);
                })
                .then(function () {
                    setLoading(false);
                    updateEmptyState();
                });
        }

        function hydrateInitialChildren() {
            if (!Array.isArray(settings.children)) {
                updateEmptyState();
                return;
            }

            for (var i = 0; i < settings.children.length; i += 1) {
                upsertChild(settings.children[i]);
            }
            updateEmptyState();
        }

        hydrateInitialChildren();

        root.addEventListener('click', function (event) {
            var addTrigger = event.target.closest('[data-mj-member-child-add]');
            if (addTrigger && root.contains(addTrigger)) {
                event.preventDefault();
                if (isPreview) {
                    displayErrors(['Cette action est désactivée en mode prévisualisation.']);
                } else {
                    openModal('create', null);
                }
                return;
            }

            var editTrigger = event.target.closest('[data-mj-member-child-edit]');
            if (editTrigger && root.contains(editTrigger)) {
                event.preventDefault();
                var childId = toInt(editTrigger.getAttribute('data-child-id'));
                if (childId !== null) {
                    if (isPreview) {
                        displayErrors(['Cette action est désactivée en mode prévisualisation.']);
                    } else {
                        openModal('edit', childId);
                    }
                }
                return;
            }
        });

        if (cancelButton) {
            cancelButton.addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });
        }

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });
        });

        if (photoInput) {
            photoInput.addEventListener('change', handlePhotoInputChange);
        }

        if (photoRemoveField) {
            photoRemoveField.addEventListener('change', handlePhotoRemoveChange);
        }

        form.addEventListener('submit', handleSubmit);
    });

})(typeof window !== 'undefined' ? window : this);
