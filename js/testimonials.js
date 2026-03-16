/**
 * Testimonials Widget JavaScript
 *
 * @package MjMember
 */

(function($) {
    'use strict';

    // Wait for data to be available or use defaults
    const config = typeof mjTestimonialsData !== 'undefined' ? mjTestimonialsData : {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: '',
        isLoggedIn: false,
        memberId: 0,
        reactionTypes: {},
        i18n: {}
    };
    const i18n = config.i18n || {};

    /**
     * Initialize testimonials widget
     */
    function initTestimonials() {
        $('.mj-testimonials__form').each(function() {
            const $form = $(this);
            // Prevent double initialization
            if ($form.data('mj-testimonials-init')) {
                return;
            }
            $form.data('mj-testimonials-init', true);
            new TestimonialsForm($form);
        });

        $('.mj-testimonials__infinite-scroll-sentinel').each(function() {
            const $sentinel = $(this);
            // Prevent double initialization
            if ($sentinel.data('mj-testimonials-init')) {
                return;
            }
            $sentinel.data('mj-testimonials-init', true);
            new TestimonialsInfiniteScroll($sentinel);
        });

        // Initialize carousel if present
        initTestimonialsCarousel();
    }

    /**
     * Initialize carousel navigation for carousel-3 template
     */
    function initTestimonialsCarousel() {
        $('.mj-testimonials--template-carousel-3').each(function() {
            const $container = $(this);
            if ($container.data('carousel-init')) return;
            $container.data('carousel-init', true);

            const $viewport = $container.find('.mj-testimonials__carousel-viewport');
            const $prevBtn = $container.find('.mj-testimonials__carousel-btn--prev');
            const $nextBtn = $container.find('.mj-testimonials__carousel-btn--next');

            if (!$viewport.length) return;

            function getCardWidth() {
                const $card = $viewport.find('.mj-carousel-card').first();
                if (!$card.length) return 300;
                return $card.outerWidth(true);
            }

            function updateButtons() {
                const el = $viewport[0];
                $prevBtn.prop('disabled', el.scrollLeft <= 5);
                $nextBtn.prop('disabled', el.scrollLeft + el.clientWidth >= el.scrollWidth - 5);
            }

            $prevBtn.on('click', function() {
                $viewport[0].scrollBy({ left: -getCardWidth(), behavior: 'smooth' });
            });

            $nextBtn.on('click', function() {
                $viewport[0].scrollBy({ left: getCardWidth(), behavior: 'smooth' });
            });

            $viewport.on('scroll', updateButtons);
            $(window).on('resize', updateButtons);
            setTimeout(updateButtons, 100);
        });
    }

    /**
     * TestimonialsForm class
     */
    class TestimonialsForm {
        constructor($form) {
            this.$form = $form;
            this.$textarea = $form.find('.mj-testimonials__textarea');
            this.$photosGrid = $form.find('.mj-testimonials__photos-grid');
            this.$photoInput = $form.find('.mj-testimonials__photo-input');
            this.$addPhotoBtn = $form.find('.mj-testimonials__add-photo');
            this.$capturePhotoBtn = $form.find('.mj-testimonials__capture-photo');
            this.$addVideoBtn = $form.find('.mj-testimonials__add-video');
            this.$cameraPreview = $form.find('.mj-testimonials__camera-preview');
            this.$cameraElement = $form.find('.mj-testimonials__camera-element');
            this.$videoPreview = $form.find('.mj-testimonials__video-preview');
            this.$videoResult = $form.find('.mj-testimonials__video-result');
            this.$videoElement = $form.find('.mj-testimonials__video-element');
            this.$videoPlayback = $form.find('.mj-testimonials__video-playback');
            this.$submitBtn = $form.find('.mj-testimonials__submit');
            this.$status = $form.find('.mj-testimonials__form-status');
            this.$photoIdsInput = $form.find('input[name="photo_ids"]');
            this.$videoIdInput = $form.find('input[name="video_id"]');

            this.photos = [];
            this.videoBlob = null;
            this.videoId = null;
            this.mediaRecorder = null;
            this.recordedChunks = [];
            this.stream = null;
            this.cameraStream = null;
            this.isRecording = false;
            this.isSubmitting = false;
            
            // Link preview
            this.linkPreview = null;
            this.linkPreviewFetching = false;
            this.linkPreviewDebounce = null;
            this.$linkPreviewContainer = null;

            // Event @mention autocomplete
            this.mentionActive = false;
            this.mentionQuery = '';
            this.mentionStart = -1;
            this.mentionResults = [];
            this.mentionSelectedIndex = 0;
            this.mentionDebounce = null;
            this.$mentionDropdown = null;

            this.bindEvents();
            this.initLinkPreviewContainer();
            this.initMentionDropdown();
        }

        initLinkPreviewContainer() {
            // Create link preview container after photos grid
            this.$linkPreviewContainer = $('<div class="mj-testimonials__link-preview" style="display:none;"></div>');
            this.$photosGrid.after(this.$linkPreviewContainer);
        }

        initMentionDropdown() {
            this.$mentionDropdown = $('<div class="mj-mention-dropdown" style="display:none;"></div>');
            this.$textarea.parent().css('position', 'relative').append(this.$mentionDropdown);
        }

        bindEvents() {
            this.$form.on('submit', (e) => this.handleSubmit(e));
            this.$addPhotoBtn.on('click', () => this.$photoInput.trigger('click'));
            this.$photoInput.on('change', (e) => this.handlePhotoSelect(e));
            this.$photosGrid.on('click', '.mj-testimonials__photo-remove', (e) => this.removePhoto(e));
            
            // Link preview detection on textarea input
            this.$textarea.on('input', () => this.detectUrl());

            // Event @mention autocomplete on textarea
            this.$textarea.on('input', () => this.handleMentionInput());
            this.$textarea.on('keydown', (e) => this.handleMentionKeydown(e));
            this.$textarea.on('blur', () => { setTimeout(() => this.closeMentionDropdown(), 200); });
            
            // Photo capture events
            this.$capturePhotoBtn.on('click', () => this.startPhotoCapture());
            this.$form.find('.mj-testimonials__camera-capture').on('click', () => this.capturePhoto());
            this.$form.find('.mj-testimonials__camera-cancel').on('click', () => this.cancelPhotoCapture());
            
            // Video events
            this.$addVideoBtn.on('click', () => this.startVideoCapture());
            this.$form.find('.mj-testimonials__video-record').on('click', () => this.toggleRecording());
            this.$form.find('.mj-testimonials__video-stop').on('click', () => this.stopRecording());
            this.$form.find('.mj-testimonials__video-cancel').on('click', () => this.cancelVideo());
            this.$form.find('.mj-testimonials__video-retake').on('click', () => this.retakeVideo());
            this.$form.find('.mj-testimonials__video-remove').on('click', () => this.removeVideo());
        }

        handlePhotoSelect(e) {
            const files = e.target.files;
            if (!files || files.length === 0) return;

            const allFiles = Array.from(files);
            const videoFiles = allFiles.filter(f => f.type.startsWith('video/'));
            const photoFiles = allFiles.filter(f => !f.type.startsWith('video/'));

            // Handle video file(s) — only one video allowed
            if (videoFiles.length > 0) {
                if (this.videoBlob || this.videoId) {
                    this.showStatus(i18n.videoAlreadyAdded || 'Une vidéo est déjà ajoutée. Supprimez-la d\'abord.', 'error');
                } else {
                    this.uploadVideoFile(videoFiles[0]);
                }
            }

            // Handle photo files
            if (photoFiles.length > 0) {
                const maxPhotos = config.maxPhotos || 5;
                const remaining = maxPhotos - this.photos.length;

                if (remaining <= 0) {
                    this.showStatus(i18n.maxPhotosReached || `Maximum ${maxPhotos} photos`, 'error');
                } else {
                    photoFiles.slice(0, remaining).forEach(file => this.uploadPhoto(file));
                }
            }

            // Reset input to allow selecting same file again
            this.$photoInput.val('');
        }

        async uploadPhoto(file) {
            const placeholder = this.createPhotoPlaceholder();
            this.$photosGrid.append(placeholder);

            const formData = new FormData();
            formData.append('action', 'mj_front_testimonial_upload');
            formData.append('_wpnonce', config.nonce);
            formData.append('type', 'photo');
            formData.append('file', file);

            try {
                const response = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                });

                if (response.success && response.data && response.data.id) {
                    this.photos.push({
                        id: response.data.id,
                        url: response.data.url
                    });
                    this.updatePhotoIdsInput();
                    this.renderPhoto(placeholder, response.data);
                } else {
                    placeholder.remove();
                    const errMsg = (typeof response.data === 'string') ? response.data : (response.data?.message || i18n.submitError);
                    this.showStatus(errMsg, 'error');
                }
            } catch (err) {
                placeholder.remove();
                // Extract server error message from jQuery jqXHR if available
                let errMsg = i18n.submitError;
                if (err && err.responseJSON && err.responseJSON.data) {
                    errMsg = (typeof err.responseJSON.data === 'string') ? err.responseJSON.data : (err.responseJSON.data.message || errMsg);
                }
                this.showStatus(errMsg, 'error');
                console.error('Photo upload error:', err);
            }
        }

        /**
         * Upload a video file (from file input, not camera capture).
         */
        async uploadVideoFile(file) {
            const maxVideoSize = config.maxVideoSize || (100 * 1024 * 1024);
            if (file.size > maxVideoSize) {
                const sizeMb = (maxVideoSize / (1024 * 1024)).toFixed(0);
                const msg = (i18n.videoTooLarge || 'La vidéo est trop volumineuse. Taille maximale : %s.')
                    .replace('%s', sizeMb + '\u00a0Mo');
                this.showStatus(msg, 'error');
                return;
            }

            this.showStatus(i18n.videoUploading || 'Upload de la vidéo en cours...', '');
            this.$addVideoBtn.hide();

            const formData = new FormData();
            formData.append('action', 'mj_front_testimonial_upload');
            formData.append('_wpnonce', config.nonce);
            formData.append('type', 'video');
            formData.append('file', file);

            const self = this;

            try {
                const response = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();

                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            const pct = Math.round((e.loaded / e.total) * 100);
                            self.showStatus((i18n.videoUploading || 'Upload de la vidéo en cours...') + ' ' + pct + '%', '');
                        }
                    });

                    xhr.addEventListener('load', function () {
                        let resp;
                        try {
                            resp = JSON.parse(xhr.responseText);
                        } catch (_) {
                            const sizeMb = (maxVideoSize / (1024 * 1024)).toFixed(0);
                            reject(new Error((i18n.videoTooLarge || 'La vidéo est trop volumineuse. Taille maximale : %s.').replace('%s', sizeMb + '\u00a0Mo')));
                            return;
                        }
                        if (resp && resp.success && resp.data && resp.data.id) {
                            resolve(resp.data);
                        } else {
                            const errMsg = (resp && resp.data && (typeof resp.data === 'string' ? resp.data : resp.data.message))
                                || (i18n.videoUploadError || 'Échec de l\'upload vidéo.');
                            reject(new Error(errMsg));
                        }
                    });

                    xhr.addEventListener('error', function () {
                        reject(new Error(i18n.videoUploadError || 'Échec de l\'upload vidéo. Vérifiez votre connexion.'));
                    });

                    xhr.addEventListener('abort', function () {
                        reject(new Error(i18n.videoUploadError || 'Upload vidéo annulé.'));
                    });

                    xhr.open('POST', config.ajaxUrl, true);
                    xhr.send(formData);
                });

                // Success: show video in result area
                this.videoId = response.id;
                this.$videoIdInput.val(response.id);
                this.$videoPlayback[0].src = response.url;
                this.$videoResult.show();
                this.showStatus('', '');
            } catch (err) {
                this.$addVideoBtn.show();
                this.showStatus(err.message || i18n.videoUploadError || 'Échec de l\'upload vidéo.', 'error');
                console.error('Video file upload error:', err);
            }
        }

        createPhotoPlaceholder() {
            return $(`
                <div class="mj-testimonials__photo-item is-uploading">
                    <div class="mj-testimonials__photo-loader"></div>
                </div>
            `);
        }

        renderPhoto($placeholder, data) {
            $placeholder.removeClass('is-uploading').html(`
                <img src="${this.escapeHtml(data.url)}" alt="">
                <button type="button" class="mj-testimonials__photo-remove" data-id="${data.id}">&times;</button>
            `);
        }

        removePhoto(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const photoId = $btn.data('id');
            
            this.photos = this.photos.filter(p => p.id !== photoId);
            this.updatePhotoIdsInput();
            $btn.closest('.mj-testimonials__photo-item').remove();
        }

        updatePhotoIdsInput() {
            const ids = this.photos.map(p => p.id);
            this.$photoIdsInput.val(JSON.stringify(ids));
        }

        // ===== LINK PREVIEW METHODS =====

        // ===== EVENT @MENTION AUTOCOMPLETE =====

        handleMentionInput() {
            const textarea = this.$textarea[0];
            const cursorPos = textarea.selectionStart;
            const content = textarea.value;

            // Find the @ symbol before the cursor
            const textBeforeCursor = content.substring(0, cursorPos);
            const atIndex = textBeforeCursor.lastIndexOf('@');

            if (atIndex === -1) {
                this.closeMentionDropdown();
                return;
            }

            // Check that @ is at start or preceded by a space/newline
            if (atIndex > 0 && !/[\s]/.test(content.charAt(atIndex - 1))) {
                this.closeMentionDropdown();
                return;
            }

            // Extract the query after @
            const query = textBeforeCursor.substring(atIndex + 1);

            // Validate query: only slug-valid chars
            if (query.length > 50) {
                this.closeMentionDropdown();
                return;
            }

            this.mentionActive = true;
            this.mentionStart = atIndex;
            this.mentionQuery = query;

            // Debounce the AJAX search
            clearTimeout(this.mentionDebounce);
            if (query.length >= 1) {
                this.mentionDebounce = setTimeout(() => this.searchEvents(query), 250);
            } else {
                // Just typed @, show hint
                this.mentionResults = [];
                this.renderMentionDropdown();
            }
        }

        handleMentionKeydown(e) {
            if (!this.mentionActive || !this.$mentionDropdown || !this.$mentionDropdown.is(':visible')) return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.mentionSelectedIndex = Math.min(this.mentionSelectedIndex + 1, this.mentionResults.length - 1);
                    this.highlightMentionItem();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.mentionSelectedIndex = Math.max(this.mentionSelectedIndex - 1, 0);
                    this.highlightMentionItem();
                    break;
                case 'Enter':
                case 'Tab':
                    if (this.mentionResults.length > 0) {
                        e.preventDefault();
                        this.selectMentionItem(this.mentionResults[this.mentionSelectedIndex]);
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.closeMentionDropdown();
                    break;
            }
        }

        async searchEvents(query) {
            try {
                const response = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mj_front_testimonial_search_events',
                        _wpnonce: config.nonce,
                        search: query
                    },
                    dataType: 'json'
                });

                if (response.success && response.data && response.data.events) {
                    this.mentionResults = response.data.events;
                    this.mentionSelectedIndex = 0;
                    this.renderMentionDropdown();
                }
            } catch (err) {
                console.error('Event search error:', err);
            }
        }

        renderMentionDropdown() {
            if (!this.mentionActive || !this.$mentionDropdown) return;

            if (this.mentionResults.length === 0 && this.mentionQuery.length >= 1) {
                this.$mentionDropdown.html(
                    '<div class="mj-mention-dropdown__empty">Aucun \u00e9v\u00e9nement trouv\u00e9</div>'
                ).show();
                return;
            }

            if (this.mentionResults.length === 0) {
                this.$mentionDropdown.html(
                    '<div class="mj-mention-dropdown__hint">Tapez le nom d\'un \u00e9v\u00e9nement...</div>'
                ).show();
                return;
            }

            let html = '';
            this.mentionResults.forEach((event, index) => {
                const emoji = event.emoji ? this.escapeHtml(event.emoji) + ' ' : '';
                const title = this.escapeHtml(event.title);
                const slug = this.escapeHtml(event.slug);
                const type = event.type ? '<span class="mj-mention-dropdown__type">' + this.escapeHtml(event.type) + '</span>' : '';
                const date = event.date_debut ? '<span class="mj-mention-dropdown__date">' + this.formatShortDate(event.date_debut) + '</span>' : '';
                const isSelected = index === this.mentionSelectedIndex ? ' is-selected' : '';

                html += '<div class="mj-mention-dropdown__item' + isSelected + '" data-index="' + index + '" data-slug="' + slug + '">' +
                    '<div class="mj-mention-dropdown__item-main">' +
                        '<span class="mj-mention-dropdown__item-title">' + emoji + title + '</span>' +
                        type +
                    '</div>' +
                    '<div class="mj-mention-dropdown__item-meta">' +
                        '<span class="mj-mention-dropdown__item-slug">@' + slug + '</span>' +
                        date +
                    '</div>' +
                '</div>';
            });

            // Keyboard hint footer
            html += '<div class="mj-mention-dropdown__footer">' +
                '<span class="mj-mention-dropdown__footer-key"><kbd>\u2191</kbd><kbd>\u2193</kbd> naviguer</span>' +
                '<span class="mj-mention-dropdown__footer-key"><kbd>\u23CE</kbd> s\u00e9lectionner</span>' +
                '<span class="mj-mention-dropdown__footer-key"><kbd>Esc</kbd> fermer</span>' +
            '</div>';

            this.$mentionDropdown.html(html).show();

            // Bind click events
            this.$mentionDropdown.find('.mj-mention-dropdown__item').on('mousedown', (e) => {
                e.preventDefault();
                const index = parseInt($(e.currentTarget).data('index'), 10);
                if (this.mentionResults[index]) {
                    this.selectMentionItem(this.mentionResults[index]);
                }
            });

            // Sync hover with keyboard selection
            this.$mentionDropdown.find('.mj-mention-dropdown__item').on('mouseenter', (e) => {
                const index = parseInt($(e.currentTarget).data('index'), 10);
                this.mentionSelectedIndex = index;
                this.highlightMentionItem();
            });
        }

        highlightMentionItem() {
            const $items = this.$mentionDropdown.find('.mj-mention-dropdown__item');
            $items.removeClass('is-selected');
            const $selected = $items.eq(this.mentionSelectedIndex).addClass('is-selected');

            // Scroll selected item into view within the dropdown
            if ($selected.length) {
                const container = this.$mentionDropdown[0];
                const el = $selected[0];
                const elTop = el.offsetTop;
                const elBottom = elTop + el.offsetHeight;
                if (elTop < container.scrollTop) {
                    container.scrollTop = elTop;
                } else if (elBottom > container.scrollTop + container.clientHeight) {
                    container.scrollTop = elBottom - container.clientHeight;
                }
            }
        }

        selectMentionItem(event) {
            const textarea = this.$textarea[0];
            const content = textarea.value;
            const cursorPos = textarea.selectionStart;

            // Replace @query with @slug
            const before = content.substring(0, this.mentionStart);
            const after = content.substring(cursorPos);
            const replacement = '@' + event.slug + ' ';

            textarea.value = before + replacement + after;
            const newCursorPos = this.mentionStart + replacement.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
            textarea.focus();

            this.closeMentionDropdown();
        }

        closeMentionDropdown() {
            this.mentionActive = false;
            this.mentionQuery = '';
            this.mentionStart = -1;
            this.mentionResults = [];
            this.mentionSelectedIndex = 0;
            if (this.$mentionDropdown) {
                this.$mentionDropdown.hide().empty();
            }
        }

        formatShortDate(dateStr) {
            if (!dateStr) return '';
            try {
                const d = new Date(dateStr);
                return d.toLocaleDateString('fr-BE', { day: 'numeric', month: 'short', year: 'numeric' });
            } catch (e) {
                return dateStr;
            }
        }

        // ===== END EVENT @MENTION AUTOCOMPLETE =====

        detectUrl() {
            // Debounce URL detection
            clearTimeout(this.linkPreviewDebounce);
            this.linkPreviewDebounce = setTimeout(() => this.checkForUrl(), 800);
        }

        checkForUrl() {
            // Don't fetch if already have a preview
            if (this.linkPreview) return;
            if (this.linkPreviewFetching) return;

            const content = this.$textarea.val();
            // Match URLs
            const urlRegex = /https?:\/\/[^\s<>"{}|\\^`\[\]]+/gi;
            const matches = content.match(urlRegex);

            if (matches && matches.length > 0) {
                this.fetchLinkPreview(matches[0]);
            }
        }

        async fetchLinkPreview(url) {
            if (this.linkPreviewFetching) return;
            this.linkPreviewFetching = true;

            // Show loading state
            this.$linkPreviewContainer.html(`
                <div class="mj-testimonials__link-preview-loading">
                    <div class="mj-testimonials__photo-loader"></div>
                    <span>Chargement de l'aperçu...</span>
                </div>
            `).show();

            try {
                const response = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mj_front_testimonial_link_preview',
                        _wpnonce: config.nonce,
                        url: url
                    },
                    dataType: 'json'
                });

                console.log('Link preview response:', response);
                
                if (response.success && response.data) {
                    this.linkPreview = response.data;
                    console.log('Link preview data:', this.linkPreview);
                    this.renderLinkPreview();
                } else {
                    this.$linkPreviewContainer.hide();
                }
            } catch (err) {
                console.error('Link preview error:', err);
                this.$linkPreviewContainer.hide();
            } finally {
                this.linkPreviewFetching = false;
            }
        }

        renderLinkPreview() {
            if (!this.linkPreview) {
                this.$linkPreviewContainer.hide();
                return;
            }

            console.log('renderLinkPreview - linkPreview:', this.linkPreview);
            console.log('renderLinkPreview - is_youtube:', this.linkPreview.is_youtube);
            console.log('renderLinkPreview - youtube_id:', this.linkPreview.youtube_id);

            // Check if it's a YouTube video
            if (this.linkPreview.is_youtube && this.linkPreview.youtube_id) {
                console.log('Rendering YouTube embed');
                this.renderYouTubeEmbed();
                return;
            }

            console.log('Rendering regular link preview');
            const { url, title, description, image, site_name } = this.linkPreview;
            const imageHtml = image ? `<img src="${image}" alt="" class="mj-testimonials__link-preview-image">` : '';
            
            this.$linkPreviewContainer.html(`
                <div class="mj-testimonials__link-preview-card">
                    ${imageHtml}
                    <div class="mj-testimonials__link-preview-content">
                        <div class="mj-testimonials__link-preview-site">${site_name || ''}</div>
                        <div class="mj-testimonials__link-preview-title">${title || url}</div>
                        ${description ? `<div class="mj-testimonials__link-preview-desc">${description}</div>` : ''}
                    </div>
                    <button type="button" class="mj-testimonials__link-preview-remove">&times;</button>
                </div>
            `).show();

            // Bind remove event
            this.$linkPreviewContainer.find('.mj-testimonials__link-preview-remove').on('click', () => this.removeLinkPreview());
        }

        renderYouTubeEmbed() {
            if (!this.linkPreview.youtube_id) {
                this.$linkPreviewContainer.hide();
                return;
            }

            const youtubeId = this.linkPreview.youtube_id;
            const embedUrl = `https://www.youtube.com/embed/${youtubeId}?rel=0`;

            this.$linkPreviewContainer.html(`
                <div class="mj-testimonials__youtube-embed-container">
                    <iframe 
                        class="mj-testimonials__youtube-embed" 
                        src="${embedUrl}" 
                        title="YouTube video" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                    <button type="button" class="mj-testimonials__youtube-embed-remove">&times;</button>
                </div>
            `).show();

            // Bind remove event
            this.$linkPreviewContainer.find('.mj-testimonials__youtube-embed-remove').on('click', () => this.removeLinkPreview());
        }

        removeLinkPreview() {
            this.linkPreview = null;
            this.$linkPreviewContainer.hide().empty();
        }

        // ===== PHOTO CAPTURE METHODS =====
        
        async startPhotoCapture() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showStatus('Votre navigateur ne supporte pas la capture photo.', 'error');
                return;
            }

            try {
                this.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: false
                });

                this.$cameraElement[0].srcObject = this.cameraStream;
                await this.$cameraElement[0].play();
                this.$cameraPreview.show();
            } catch (err) {
                console.error('Camera access error:', err);
                this.showStatus('Impossible d\'accéder à la caméra.', 'error');
            }
        }

        async capturePhoto() {
            const video = this.$cameraElement[0];
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            
            // Convert to blob and upload
            canvas.toBlob(async (blob) => {
                if (!blob) {
                    this.showStatus('Erreur lors de la capture.', 'error');
                    return;
                }
                
                const maxPhotos = config.maxPhotos || 5;
                if (this.photos.length >= maxPhotos) {
                    this.showStatus(i18n.maxPhotosReached || `Maximum ${maxPhotos} photos`, 'error');
                    this.cancelPhotoCapture();
                    return;
                }
                
                // Create placeholder
                const placeholder = $(`
                    <div class="mj-testimonials__photo-item is-uploading">
                        <div class="mj-testimonials__photo-loader"></div>
                    </div>
                `);
                this.$photosGrid.append(placeholder);
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'mj_front_testimonial_upload');
                    formData.append('_wpnonce', config.nonce);
                    formData.append('type', 'photo');
                    formData.append('file', blob, 'capture-' + Date.now() + '.jpg');

                    const response = await $.ajax({
                        url: config.ajaxUrl,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json'
                    });

                    if (response.success && response.data && response.data.id) {
                        this.photos.push({
                            id: response.data.id,
                            url: response.data.url
                        });
                        this.updatePhotoIdsInput();
                        this.renderPhoto(placeholder, response.data);
                    } else {
                        placeholder.remove();
                        this.showStatus(response.data?.message || i18n.submitError, 'error');
                    }
                } catch (err) {
                    placeholder.remove();
                    this.showStatus(i18n.submitError, 'error');
                    console.error('Photo capture upload error:', err);
                }
                
                this.cancelPhotoCapture();
            }, 'image/jpeg', 0.9);
        }

        cancelPhotoCapture() {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(track => track.stop());
                this.cameraStream = null;
            }
            this.$cameraPreview.hide();
        }

        // ===== VIDEO CAPTURE METHODS =====

        async startVideoCapture() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showStatus('Votre navigateur ne supporte pas la capture vidéo.', 'error');
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: true
                });

                this.$videoElement[0].srcObject = this.stream;
                this.$videoElement[0].play();
                this.$videoPreview.show();
                this.$addVideoBtn.hide();
            } catch (err) {
                console.error('Camera access error:', err);
                this.showStatus('Impossible d\'accéder à la caméra.', 'error');
            }
        }

        toggleRecording() {
            if (this.isRecording) {
                this.stopRecording();
            } else {
                this.startRecording();
            }
        }

        startRecording() {
            if (!this.stream) return;

            this.recordedChunks = [];
            const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus') 
                ? 'video/webm;codecs=vp9,opus' 
                : 'video/webm';

            this.mediaRecorder = new MediaRecorder(this.stream, { mimeType });
            
            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    this.recordedChunks.push(e.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                this.videoBlob = new Blob(this.recordedChunks, { type: mimeType });
                this.showVideoResult();
            };

            this.mediaRecorder.start(1000); // Record in 1-second chunks
            this.isRecording = true;
            this.$form.find('.mj-testimonials__video-record').addClass('is-recording').find('span').last().text(i18n.videoStop || 'Arrêter');
            this.$form.find('.mj-testimonials__video-stop').show();
        }

        stopRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this.mediaRecorder.stop();
                this.isRecording = false;
                this.$form.find('.mj-testimonials__video-record').removeClass('is-recording').find('span').last().text('Enregistrer');
                this.$form.find('.mj-testimonials__video-stop').hide();
            }
        }

        showVideoResult() {
            this.stopStream();
            this.$videoPreview.hide();
            
            const url = URL.createObjectURL(this.videoBlob);
            this.$videoPlayback[0].src = url;
            this.$videoResult.show();
        }

        cancelVideo() {
            this.stopStream();
            this.$videoPreview.hide();
            this.$addVideoBtn.show();
            this.recordedChunks = [];
        }

        retakeVideo() {
            this.$videoResult.hide();
            this.videoBlob = null;
            this.videoId = null;
            this.$videoIdInput.val('');
            this.startVideoCapture();
        }

        removeVideo() {
            this.$videoResult.hide();
            this.$addVideoBtn.show();
            this.videoBlob = null;
            this.videoId = null;
            this.$videoIdInput.val('');
        }

        stopStream() {
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
        }

        async uploadVideo() {
            if (!this.videoBlob) return null;

            // Client-side size validation before attempting upload
            const maxVideoSize = config.maxVideoSize || (100 * 1024 * 1024);
            if (this.videoBlob.size > maxVideoSize) {
                const sizeMb = (maxVideoSize / (1024 * 1024)).toFixed(0);
                const msg = (i18n.videoTooLarge || 'La vidéo est trop volumineuse. Taille maximale : %s.')
                    .replace('%s', sizeMb + '\u00a0Mo');
                throw new Error(msg);
            }

            const formData = new FormData();
            formData.append('action', 'mj_front_testimonial_upload');
            formData.append('_wpnonce', config.nonce);
            formData.append('type', 'video');
            formData.append('file', this.videoBlob, 'testimonial-video.webm');

            const self = this;
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                // Progress tracking
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 100);
                        const uploadingLabel = i18n.videoUploading || 'Upload de la vidéo en cours...';
                        self.showStatus(uploadingLabel + ' ' + pct + '%', '');
                    }
                });

                xhr.addEventListener('load', function () {
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (parseErr) {
                        // Empty or non-JSON response = server-level rejection (post_max_size, 413…)
                        const sizeMb = (maxVideoSize / (1024 * 1024)).toFixed(0);
                        const tooBigMsg = (i18n.videoTooLarge || 'La vidéo est trop volumineuse. Taille maximale : %s.')
                            .replace('%s', sizeMb + '\u00a0Mo');
                        reject(new Error(tooBigMsg));
                        return;
                    }

                    if (response && response.success && response.data && response.data.id) {
                        resolve(response.data.id);
                    } else {
                        const errMsg = (response && response.data && (response.data.message || response.data))
                            || (i18n.videoUploadError || 'Échec de l\'upload vidéo.');
                        reject(new Error(typeof errMsg === 'string' ? errMsg : JSON.stringify(errMsg)));
                    }
                });

                xhr.addEventListener('error', function () {
                    reject(new Error(i18n.videoUploadError || 'Échec de l\'upload vidéo. Vérifiez votre connexion.'));
                });

                xhr.addEventListener('abort', function () {
                    reject(new Error(i18n.videoUploadError || 'Upload vidéo annulé.'));
                });

                xhr.open('POST', config.ajaxUrl, true);
                xhr.send(formData);
            });
        }

        async handleSubmit(e) {
            e.preventDefault();
            
            if (this.isSubmitting) return;

            const content = this.$textarea.val().trim();
            
            if (!content && this.photos.length === 0 && !this.videoBlob && !this.videoId) {
                this.showStatus('Veuillez ajouter du texte, des photos ou une vidéo.', 'error');
                return;
            }

            this.isSubmitting = true;
            this.$submitBtn.addClass('is-loading').prop('disabled', true);
            this.showStatus(i18n.uploading || 'Envoi en cours...', '');

            try {
                // Upload video if exists
                if (this.videoBlob && !this.videoId) {
                    this.showStatus(i18n.videoUploading || 'Upload de la vidéo en cours…', '');
                    this.videoId = await this.uploadVideo();
                }

                // Submit testimonial
                const formData = new FormData();
                formData.append('action', 'mj_front_testimonial_submit');
                formData.append('_wpnonce', config.nonce);
                formData.append('content', content);
                formData.append('photo_ids', JSON.stringify(this.photos.map(p => p.id)));
                
                if (this.videoId) {
                    formData.append('video_id', this.videoId);
                }
                
                if (this.linkPreview) {
                    formData.append('link_preview', JSON.stringify(this.linkPreview));
                }

                // Pass event_slug when form is on an event page
                const eventSlug = this.$form.attr('data-event-slug') || config.eventSlug || '';
                if (eventSlug) {
                    formData.append('event_slug', eventSlug);
                }

                const response = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                });

                if (response.success) {
                    this.showStatus(i18n.submitSuccess || 'Témoignage envoyé !', 'success');
                    this.resetForm();
                } else {
                    this.showStatus(response.data?.message || i18n.submitError, 'error');
                }
            } catch (err) {
                console.error('Submit error:', err);
                let errMsg = err.message || i18n.submitError;
                // Extract server error from jQuery jqXHR if available
                if (err && err.responseJSON && err.responseJSON.data) {
                    const d = err.responseJSON.data;
                    errMsg = (typeof d === 'string') ? d : (d.message || errMsg);
                }
                this.showStatus(errMsg, 'error');
            } finally {
                this.isSubmitting = false;
                this.$submitBtn.removeClass('is-loading').prop('disabled', false);
            }
        }

        resetForm() {
            this.$textarea.val('');
            this.photos = [];
            this.$photosGrid.empty();
            this.$photoIdsInput.val('[]');
            this.removeVideo();
            this.removeLinkPreview();
        }

        showStatus(message, type) {
            this.$status
                .removeClass('is-success is-error')
                .addClass(type ? `is-${type}` : '')
                .text(message);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * TestimonialsInfiniteScroll class
     * Uses IntersectionObserver to trigger loading when the sentinel enters the viewport.
     */
    class TestimonialsInfiniteScroll {
        constructor($sentinel) {
            this.$sentinel = $sentinel;
            this.$container = $sentinel.closest('.mj-testimonials');
            this.$feed = this.$container.find('.mj-testimonials__feed');
            this.$spinner = $sentinel.find('.mj-testimonials__infinite-scroll-spinner');
            this.page = parseInt($sentinel.data('page'), 10) || 1;
            this.totalPages = parseInt($sentinel.data('total-pages'), 10) || 1;
            this.perPage = config.perPage || 6;
            this.isLoading = false;

            if (this.page >= this.totalPages) {
                this.$sentinel.hide();
                return;
            }

            this.initObserver();
        }

        initObserver() {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMore();
                    }
                });
            }, { rootMargin: '200px' });

            this.observer.observe(this.$sentinel[0]);
        }

        async loadMore() {
            if (this.isLoading || this.page >= this.totalPages) return;

            this.isLoading = true;
            this.$spinner.addClass('is-active');

            try {
                const response = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mj_front_testimonial_list',
                        nonce: config.nonce,
                        page: this.page + 1,
                        per_page: this.perPage,
                        featured_only: config.featuredOnly ? '1' : ''
                    }
                });

                if (response.success && response.data && response.data.testimonials) {
                    this.page++;
                    this.renderTestimonials(response.data.testimonials);

                    if (this.page >= this.totalPages) {
                        this.observer.disconnect();
                        this.$sentinel.hide();
                    }
                }
            } catch (err) {
                console.error('Infinite scroll error:', err);
            } finally {
                this.isLoading = false;
                this.$spinner.removeClass('is-active');
            }
        }

        renderTestimonials(testimonials) {
            testimonials.forEach(t => {
                const card = this.createCard(t);
                this.$feed.append(card);
            });
        }

        createCard(t) {
            // Avatar
            const avatarInner = t.memberAvatarUrl
                ? `<img src="${this.escapeHtml(t.memberAvatarUrl)}" alt="${this.escapeHtml(t.memberName)}" class="mj-feed-post__avatar-img">`
                : `<span class="mj-feed-post__avatar-initial">${this.escapeHtml(t.memberInitial || '?')}</span>`;

            // Date
            const dateHtml = t.createdAgo
                ? `<span class="mj-feed-post__date">Il y a ${this.escapeHtml(t.createdAgo)} · 🌍</span>`
                : '';

            // Content
            const contentHtml = t.content
                ? `<div class="mj-feed-post__content">${this.formatContent(t.content)}</div>`
                : '';

            // Photos
            let photosHtml = '';
            if (t.photos && t.photos.length > 0) {
                const visible = t.photos.slice(0, 5);
                const moreCount = t.photos.length - 5;
                photosHtml = `<div class="mj-feed-post__media mj-feed-post__media--photos-${Math.min(t.photos.length, 5)}">`;
                visible.forEach((p, i) => {
                    const moreTag = (i === 4 && moreCount > 0) ? `<span class="mj-feed-post__photo-more">+${moreCount}</span>` : '';
                    photosHtml += `<a href="${this.escapeHtml(p.full)}" class="mj-feed-post__photo" data-lightbox="post-${t.id}"><img src="${this.escapeHtml(p.url)}" alt="" loading="lazy">${moreTag}</a>`;
                });
                photosHtml += '</div>';
            }

            // Video
            let videoHtml = '';
            if (t.video && t.video.url) {
                videoHtml = `<div class="mj-feed-post__media mj-feed-post__media--video"><video controls playsinline poster="${this.escapeHtml(t.video.poster || '')}"><source src="${this.escapeHtml(t.video.url)}" type="video/mp4"></video></div>`;
            }

            // Link preview / YouTube
            let linkHtml = '';
            if (t.linkPreview && t.linkPreview.url) {
                const lp = t.linkPreview;
                if (lp.is_youtube && lp.youtube_id) {
                    linkHtml = `<div class="mj-feed-post__youtube-embed-container"><iframe class="mj-feed-post__youtube-embed" src="https://www.youtube.com/embed/${this.escapeHtml(lp.youtube_id)}?rel=0" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>`;
                } else {
                    const imgTag = lp.image ? `<img src="${this.escapeHtml(lp.image)}" alt="" class="mj-feed-post__link-preview-image" loading="lazy">` : '';
                    const siteTag = lp.site_name ? `<div class="mj-feed-post__link-preview-site">${this.escapeHtml(lp.site_name)}</div>` : '';
                    const descTag = lp.description ? `<div class="mj-feed-post__link-preview-desc">${this.escapeHtml(lp.description)}</div>` : '';
                    linkHtml = `<a href="${this.escapeHtml(lp.url)}" class="mj-feed-post__link-preview" target="_blank" rel="noopener noreferrer">${imgTag}<div class="mj-feed-post__link-preview-content">${siteTag}<div class="mj-feed-post__link-preview-title">${this.escapeHtml(lp.title || lp.url)}</div>${descTag}</div></a>`;
                }
            }

            // Like button SVG
            const likeSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>';
            const commentSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
            const shareSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>';

            // Reaction picker
            let pickerHtml = '';
            if (config.reactionTypes) {
                pickerHtml = '<div class="mj-feed-post__reaction-picker">';
                for (const [type, data] of Object.entries(config.reactionTypes)) {
                    pickerHtml += `<button type="button" class="mj-feed-post__reaction-option" data-reaction="${this.escapeHtml(type)}" title="${this.escapeHtml(data.label)}"><span class="mj-feed-post__reaction-option-emoji">${this.escapeHtml(data.emoji)}</span></button>`;
                }
                pickerHtml += '</div>';
            }

            // Comment form (only if logged in)
            let commentFormHtml = '';
            if (config.isLoggedIn) {
                const myInitial = config.memberInitial || 'M';
                commentFormHtml = `
                    <form class="mj-feed-post__comment-form">
                        <div class="mj-feed-comment__avatar"><span class="mj-feed-comment__avatar-initial">${this.escapeHtml(myInitial)}</span></div>
                        <div class="mj-feed-post__comment-input-wrap">
                            <input type="text" class="mj-feed-post__comment-input" placeholder="${this.escapeHtml(i18n.writeComment || 'Écrire un commentaire...')}">
                            <button type="submit" class="mj-feed-post__comment-submit"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg></button>
                        </div>
                    </form>`;
            }

            return `
                <article class="mj-feed-post-wrapper" data-post-id="${t.id}" data-post-status="approved">
                    <div class="mj-feed-post" data-id="${t.id}">
                        <div class="mj-feed-post__header">
                            <div class="mj-feed-post__avatar">${avatarInner}</div>
                            <div class="mj-feed-post__meta">
                                <span class="mj-feed-post__author">${this.escapeHtml(t.memberName)}</span>
                                ${dateHtml}
                            </div>
                        </div>
                        ${contentHtml}
                        ${photosHtml}
                        ${videoHtml}
                        ${linkHtml}
                        <div class="mj-feed-post__reactions-bar">
                            <div class="mj-feed-post__reactions-summary"></div>
                        </div>
                        <div class="mj-feed-post__actions">
                            <div class="mj-feed-post__action mj-feed-post__action--like" data-action="react" data-current-reaction="">
                                <span class="mj-feed-post__action-icon">${likeSvg}</span>
                                <span class="mj-feed-post__action-label">${this.escapeHtml(i18n.like || "J'aime")}</span>
                                ${pickerHtml}
                            </div>
                            <button type="button" class="mj-feed-post__action mj-feed-post__action--comment" data-action="toggle-comments">
                                <span class="mj-feed-post__action-icon">${commentSvg}</span>
                                <span class="mj-feed-post__action-label">${this.escapeHtml(i18n.comment || 'Commenter')}</span>
                            </button>
                            <div class="mj-feed-post__action mj-feed-post__action--share" data-action="toggle-share">
                                <span class="mj-feed-post__action-icon">${shareSvg}</span>
                                <span class="mj-feed-post__action-label">${this.escapeHtml(i18n.share || 'Partager')}</span>
                            </div>
                        </div>
                        <div class="mj-feed-post__comments" style="display: none;">
                            <div class="mj-feed-post__comments-list"></div>
                            ${commentFormHtml}
                        </div>
                    </div>
                </article>`;
        }

        formatContent(content) {
            // Content already contains HTML from server-side linkify, wrap in <p> tags similar to wpautop
            const escaped = content.replace(/\n\n+/g, '</p><p>').replace(/\n/g, '<br>');
            return '<p>' + escaped + '</p>';
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // =====================================================
    // FEED INTERACTIONS - Simple event delegation approach
    // =====================================================
    
    /**
     * Get the wrapper element from any element inside a post
     */
    function getWrapper(el) {
        return $(el).closest('.mj-feed-post-wrapper');
    }
    
    /**
     * Get testimonial ID from wrapper
     */
    function getTestimonialId($wrapper) {
        return parseInt($wrapper.find('.mj-feed-post').data('id'), 10);
    }
    
    /**
     * Get comments section from wrapper (sibling of post)
     */
    function getCommentsSection($wrapper) {
        return $wrapper.children('.mj-feed-post__comments');
    }
    
    /**
     * Toggle comments visibility
     */
    function toggleComments($wrapper) {
        console.log('[MJ] toggleComments - wrapper:', $wrapper.length, 'class:', $wrapper.attr('class'));
        console.log('[MJ] Wrapper HTML preview:', $wrapper.html().substring(0, 500));
        console.log('[MJ] Wrapper children classes:', $wrapper.children().map(function() { return this.className; }).get());
        
        // Try multiple selectors
        let $comments = $wrapper.children('.mj-feed-post__comments');
        console.log('[MJ] children selector:', $comments.length);
        
        if ($comments.length === 0) {
            $comments = $wrapper.find('.mj-feed-post__comments');
            console.log('[MJ] find selector:', $comments.length);
        }
        
        if ($comments.length === 0) {
            // Maybe comments are siblings of wrapper?
            $comments = $wrapper.siblings('.mj-feed-post__comments');
            console.log('[MJ] siblings selector:', $comments.length);
        }
        
        if ($comments.length === 0) {
            // Try finding from post-id data
            const postId = $wrapper.find('.mj-feed-post').data('id') || $wrapper.data('post-id');
            console.log('[MJ] Looking for comments by post ID:', postId);
            $comments = $('.mj-feed-post__comments').filter(function() {
                return $(this).closest('.mj-feed-post-wrapper').data('post-id') == postId ||
                       $(this).closest('.mj-feed-post-wrapper').find('.mj-feed-post').data('id') == postId;
            });
            console.log('[MJ] filter by postId:', $comments.length);
        }
        
        if ($comments.length === 0) {
            console.error('[MJ] Comments section not found! Dumping all .mj-feed-post__comments:', $('.mj-feed-post__comments').length);
            return;
        }
        
        $comments.slideToggle(200, function() {
            if ($(this).is(':visible')) {
                $(this).find('.mj-feed-post__comment-input').focus();
            }
        });
    }
    
    /**
     * Update reactions UI
     */
    function updateReactionsUI($wrapper, data) {
        const summary = data.summary;
        const memberReaction = data.memberReaction;
        const types = data.reactionTypes || config.reactionTypes;
        const $post = $wrapper.find('.mj-feed-post');
        const $likeBtn = $post.find('.mj-feed-post__action--like');
        const $reactionsBar = $post.find('.mj-feed-post__reactions-bar');

        // Update like button state
        $likeBtn.data('current-reaction', memberReaction || '');
        
        if (memberReaction && types[memberReaction]) {
            $likeBtn.addClass('is-active');
            $likeBtn.find('.mj-feed-post__action-icon').html(
                '<span class="mj-feed-post__reaction-active">' + types[memberReaction].emoji + '</span>'
            );
            $likeBtn.find('.mj-feed-post__action-label').text(types[memberReaction].label);
        } else {
            $likeBtn.removeClass('is-active');
            $likeBtn.find('.mj-feed-post__action-icon').html(
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>'
            );
            $likeBtn.find('.mj-feed-post__action-label').text(i18n.like || "J'aime");
        }

        // Update reactions summary
        const $summary = $reactionsBar.find('.mj-feed-post__reactions-summary');
        
        if (summary.total > 0) {
            let emojisHtml = '';
            summary.top_emojis.forEach(function(emoji) {
                emojisHtml += '<span class="mj-feed-post__reaction-emoji">' + escapeHtml(emoji) + '</span>';
            });
            
            let countText = '';
            if (summary.names && summary.names.length > 0) {
                const remaining = summary.total - summary.names.length;
                if (remaining > 0) {
                    countText = summary.names.join(', ') + ' ' + (i18n.andOthers || 'et %d autres').replace('%d', remaining);
                } else {
                    countText = summary.names.join(', ');
                }
            } else {
                countText = String(summary.total);
            }

            $summary.html(
                '<span class="mj-feed-post__reactions-emojis">' + emojisHtml + '</span>' +
                '<span class="mj-feed-post__reactions-count">' + escapeHtml(countText) + '</span>'
            );
        } else {
            $summary.empty();
        }
    }
    
    /**
     * Append a comment to the list
     */
    function appendComment($wrapper, comment) {
        const $list = getCommentsSection($wrapper).find('.mj-feed-post__comments-list');
        const initial = comment.member_name ? comment.member_name.charAt(0).toUpperCase() : '?';
        const deleteBtn = comment.isOwner 
            ? '<button type="button" class="mj-feed-comment__delete" data-action="delete-comment">' + (i18n.deleteComment || 'Supprimer') + '</button>' 
            : '';
        
        const html = 
            '<div class="mj-feed-comment" data-comment-id="' + comment.id + '">' +
                '<div class="mj-feed-comment__avatar">' +
                    '<span class="mj-feed-comment__avatar-initial">' + escapeHtml(initial) + '</span>' +
                '</div>' +
                '<div class="mj-feed-comment__body">' +
                    '<div class="mj-feed-comment__bubble">' +
                        '<span class="mj-feed-comment__author">' + escapeHtml(comment.member_name) + '</span>' +
                        '<span class="mj-feed-comment__text">' + comment.content + '</span>' +
                    '</div>' +
                    '<div class="mj-feed-comment__meta">' +
                        '<span class="mj-feed-comment__time">' + escapeHtml(comment.created_ago) + '</span>' +
                        deleteBtn +
                    '</div>' +
                '</div>' +
            '</div>';
        
        $list.append(html);
    }
    
    /**
     * Update comment count display
     */
    function updateCommentCount($wrapper, count) {
        const $reactionsBar = $wrapper.find('.mj-feed-post__reactions-bar');
        let $countBtn = $reactionsBar.find('.mj-feed-post__comments-count');
        
        if (count > 0) {
            const text = count === 1 ? '1 commentaire' : count + ' commentaires';
            if ($countBtn.length) {
                $countBtn.text(text);
            } else {
                $reactionsBar.append('<button type="button" class="mj-feed-post__comments-count" data-action="toggle-comments">' + text + '</button>');
            }
        } else {
            $countBtn.remove();
        }
    }
    
    /**
     * Simple HTML escape
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =====================================================
    // EVENT HANDLERS - Using document-level delegation
    // =====================================================
    
    /**
     * Initialize all feed event listeners
     */
    function initFeedEvents() {
        // Remove any previous handlers to avoid duplicates
        $(document).off('.mjFeed');
        
        // Comment button click
        $(document).on('click.mjFeed', '.mj-feed-post__action--comment', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[MJ] Comment button clicked');
            const $wrapper = getWrapper(this);
            toggleComments($wrapper);
        });
        
        // Comment count click
        $(document).on('click.mjFeed', '.mj-feed-post__comments-count', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[MJ] Comment count clicked');
            const $wrapper = getWrapper(this);
            toggleComments($wrapper);
        });
        
        // Reaction option click
        $(document).on('click.mjFeed', '.mj-feed-post__reaction-option', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!config.isLoggedIn) {
                alert(i18n.loginRequired || 'Connectez-vous pour réagir.');
                return;
            }
            
            const $wrapper = getWrapper(this);
            const testimonialId = getTestimonialId($wrapper);
            const reactionType = $(this).data('reaction');
            
            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_react',
                _wpnonce: config.nonce,
                testimonial_id: testimonialId,
                reaction_type: reactionType
            }).done(function(response) {
                if (response.success) {
                    updateReactionsUI($wrapper, response.data);
                }
            });
        });
        
        // Like button click (quick like/unlike)
        $(document).on('click.mjFeed', '.mj-feed-post__action--like', function(e) {
            // Don't trigger if clicking on picker
            if ($(e.target).closest('.mj-feed-post__reaction-picker').length) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            if (!config.isLoggedIn) {
                alert(i18n.loginRequired || 'Connectez-vous pour réagir.');
                return;
            }
            
            const $btn = $(this);
            const $wrapper = getWrapper(this);
            const testimonialId = getTestimonialId($wrapper);
            const currentReaction = $btn.data('current-reaction');
            
            const actionName = currentReaction ? 'mj_front_testimonial_unreact' : 'mj_front_testimonial_react';
            const postData = {
                action: actionName,
                _wpnonce: config.nonce,
                testimonial_id: testimonialId
            };
            if (!currentReaction) {
                postData.reaction_type = 'like';
            }
            
            $.post(config.ajaxUrl, postData).done(function(response) {
                if (response.success) {
                    updateReactionsUI($wrapper, response.data);
                }
            });
        });
        
        // Comment form submit
        $(document).on('submit.mjFeed', '.mj-feed-post__comment-form', function(e) {
            e.preventDefault();
            
            if (!config.isLoggedIn) {
                alert(i18n.loginRequired || 'Connectez-vous pour commenter.');
                return;
            }
            
            const $form = $(this);
            const $input = $form.find('.mj-feed-post__comment-input');
            const content = $input.val().trim();
            if (!content) return;
            
            const $wrapper = getWrapper(this);
            const testimonialId = getTestimonialId($wrapper);
            const $submitBtn = $form.find('.mj-feed-post__comment-submit');
            
            $submitBtn.prop('disabled', true);
            
            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_comment_add',
                _wpnonce: config.nonce,
                testimonial_id: testimonialId,
                content: content
            }).done(function(response) {
                if (response.success && response.data.comment) {
                    $input.val('');
                    appendComment($wrapper, response.data.comment);
                    updateCommentCount($wrapper, response.data.commentCount);
                }
            }).always(function() {
                $submitBtn.prop('disabled', false);
            });
        });
        
        // Delete comment
        $(document).on('click.mjFeed', '[data-action="delete-comment"]', function(e) {
            e.preventDefault();
            
            const $comment = $(this).closest('.mj-feed-comment');
            const commentId = $comment.data('comment-id');
            const $wrapper = getWrapper(this);
            
            if (!confirm('Supprimer ce commentaire ?')) return;
            
            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_comment_delete',
                _wpnonce: config.nonce,
                comment_id: commentId
            }).done(function(response) {
                if (response.success) {
                    $comment.slideUp(200, function() { $(this).remove(); });
                    updateCommentCount($wrapper, response.data.commentCount);
                }
            });
        });
        
        // Approve testimonial (animators only)
        $(document).on('click.mjFeed', '[data-action="approve-testimonial"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const testimonialId = $btn.data('testimonial-id');
            const $wrapper = $btn.closest('.mj-feed-post-wrapper');
            
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true);
            const originalText = $btn.html();
            $btn.html('<span>⏳ Validation...</span>');
            
            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_approve',
                _wpnonce: config.nonce,
                id: testimonialId
            }).done(function(response) {
                if (response.success) {
                    $wrapper.removeClass('mj-feed-post-wrapper--pending');
                    $wrapper.find('.mj-feed-post').removeClass('mj-feed-post--pending');
                    $wrapper.find('.mj-feed-post__approval-panel').fadeOut(200, function() { $(this).remove(); });
                    $wrapper.find('.mj-feed-post__pending-badge').fadeOut(200, function() { $(this).remove(); });
                    
                    // Show success message
                    const $message = $('<div class="mj-testimonial-success" style="padding:10px;margin:10px 0;background:#d4edda;color:#155724;border-radius:4px;border:1px solid #c3e6cb;">✓ ' + response.data.message + '</div>');
                    $wrapper.find('.mj-feed-post__header').after($message);
                    setTimeout(function() { $message.fadeOut(200, function() { $(this).remove(); }); }, 3000);
                }
            }).always(function() {
                $btn.prop('disabled', false);
                $btn.html(originalText);
            });
        });
        
        // Reject testimonial (animators only)
        $(document).on('click.mjFeed', '[data-action="reject-testimonial"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const testimonialId = $btn.data('testimonial-id');
            const $wrapper = $btn.closest('.mj-feed-post-wrapper');
            
            // Prompt for rejection reason
            const reason = prompt('Motif du refus (optionnel):', '');
            if (reason === null) return; // User cancelled
            
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true);
            const originalText = $btn.html();
            $btn.html('<span>⏳ Traitement...</span>');
            
            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_reject',
                _wpnonce: config.nonce,
                id: testimonialId,
                reason: reason
            }).done(function(response) {
                if (response.success) {
                    $wrapper.fadeOut(200, function() { $(this).remove(); });
                    
                    // Show success message
                    const $message = $('<div class="mj-testimonial-success" style="padding:10px;margin:10px 0;background:#f8d7da;color:#721c24;border-radius:4px;border:1px solid #f5c6cb;">✓ ' + response.data.message + '</div>');
                    $wrapper.after($message);
                    setTimeout(function() { $message.fadeOut(200, function() { $(this).remove(); }); }, 3000);
                }
            }).always(function() {
                $btn.prop('disabled', false);
                $btn.html(originalText);
            });
        });
        
        // Load more comments
        $(document).on('click.mjFeed', '[data-action="load-more-comments"]', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            if ($btn.prop('disabled')) return;
            
            const $wrapper = getWrapper(this);
            const testimonialId = getTestimonialId($wrapper);
            const currentPage = parseInt($btn.data('page'), 10) || 1;
            
            $btn.prop('disabled', true);
            
            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_comments_list',
                testimonial_id: testimonialId,
                page: currentPage + 1,
                per_page: 10
            }).done(function(response) {
                if (response.success && response.data.comments) {
                    $btn.data('page', currentPage + 1);
                    response.data.comments.forEach(function(comment) {
                        appendComment($wrapper, comment);
                    });
                    
                    if (currentPage + 1 >= response.data.totalPages) {
                        $btn.remove();
                    }
                }
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });
        
        // --- Owner menu: toggle ---
        $(document).on('click.mjFeed', '[data-action="toggle-owner-menu"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $dropdown = $(this).siblings('.mj-feed-post__owner-dropdown');
            // Close any other open dropdown first
            $('.mj-feed-post__owner-dropdown').not($dropdown).hide();
            $dropdown.toggle();
        });

        // Close owner dropdown on outside click
        $(document).on('click.mjFeed', function(e) {
            if (!$(e.target).closest('.mj-feed-post__owner-menu').length) {
                $('.mj-feed-post__owner-dropdown').hide();
            }
        });

        // --- Owner: Delete testimonial ---
        $(document).on('click.mjFeed', '[data-action="delete-testimonial"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $wrapper = getWrapper(this);
            const testimonialId = getTestimonialId($wrapper);

            // Close dropdown
            $(this).closest('.mj-feed-post__owner-dropdown').hide();

            if (!confirm('Êtes-vous sûr de vouloir supprimer ce témoignage ? Cette action est irréversible.')) return;

            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_delete',
                _wpnonce: config.nonce,
                testimonial_id: testimonialId
            }).done(function(response) {
                if (response.success) {
                    $wrapper.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert(response.data || 'Erreur lors de la suppression.');
                }
            }).fail(function() {
                alert('Erreur réseau lors de la suppression.');
            });
        });

        // --- Owner: Edit testimonial (inline with media support) ---
        $(document).on('click.mjFeed', '[data-action="edit-testimonial"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $wrapper = getWrapper(this);
            const $post = $wrapper.find('.mj-feed-post');
            let $content = $wrapper.find('.mj-feed-post__content');

            // Close dropdown
            $(this).closest('.mj-feed-post__owner-dropdown').hide();

            // If already editing, do nothing
            if ($wrapper.find('.mj-feed-post__edit-form').length) return;

            // If content div doesn't exist (media-only post), create it before media
            if (!$content.length) {
                const $insertBefore = $post.find('.mj-feed-post__media').first();
                const $newContent = $('<div class="mj-feed-post__content" data-raw-content=""></div>');
                if ($insertBefore.length) {
                    $insertBefore.before($newContent);
                } else {
                    $post.append($newContent);
                }
                $content = $newContent;
            }

            // Get raw content from data attribute
            const rawContent = $content.attr('data-raw-content') || $content.text().trim();

            // Store original HTML for cancel
            const originalContentHtml = $content.html();

            // Get current media from data attributes
            let editPhotos = [];
            let editVideoId = 0;
            let editVideoUrl = '';

            try {
                const photosData = $post.attr('data-photos');
                if (photosData) editPhotos = JSON.parse(photosData);
            } catch(e) {}

            try {
                const videoData = $post.attr('data-video');
                if (videoData) {
                    const v = JSON.parse(videoData);
                    editVideoId = v.id || 0;
                    editVideoUrl = v.url || '';
                }
            } catch(e) {}

            // Store original media sections
            const $origPhotosMedia = $wrapper.find('.mj-feed-post__media--photos-1, .mj-feed-post__media--photos-2, .mj-feed-post__media--photos-3, .mj-feed-post__media--photos-4, .mj-feed-post__media--photos-5');
            const $origVideoMedia = $wrapper.find('.mj-feed-post__media--video');
            const origPhotosHtml = $origPhotosMedia.length ? $origPhotosMedia[0].outerHTML : '';
            const origVideoHtml = $origVideoMedia.length ? $origVideoMedia[0].outerHTML : '';

            // Hide original media
            $origPhotosMedia.hide();
            $origVideoMedia.hide();

            // --- Build the edit media grid ---
            function buildMediaPreview() {
                let html = '<div class="mj-feed-post__edit-media">';

                // Photos grid
                html += '<div class="mj-feed-post__edit-media-grid">';
                editPhotos.forEach(function(p) {
                    html += '<div class="mj-feed-post__edit-media-item" data-photo-id="' + p.id + '">';
                    html += '<img src="' + escapeHtml(p.url) + '" alt="">';
                    html += '<button type="button" class="mj-feed-post__edit-media-remove" data-action="edit-remove-photo" data-photo-id="' + p.id + '" title="Supprimer">&times;</button>';
                    html += '</div>';
                });
                html += '</div>';

                // Video preview
                if (editVideoId && editVideoUrl) {
                    html += '<div class="mj-feed-post__edit-video-preview" data-video-id="' + editVideoId + '">';
                    html += '<video src="' + escapeHtml(editVideoUrl) + '" controls playsinline></video>';
                    html += '<button type="button" class="mj-feed-post__edit-media-remove" data-action="edit-remove-video" title="Supprimer">&times;</button>';
                    html += '</div>';
                }

                // Add media button (show if under limits)
                const canAddPhoto = editPhotos.length < (config.maxPhotos || 5);
                const canAddVideo = config.allowVideo && !editVideoId;
                if (canAddPhoto || canAddVideo) {
                    html += '<div class="mj-feed-post__edit-add-wrap">';
                    let acceptTypes = [];
                    if (canAddPhoto) acceptTypes.push('image/*');
                    if (canAddVideo) acceptTypes.push('video/*');
                    html += '<label class="mj-feed-post__edit-add-media">';
                    html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
                    html += ' <span>Ajouter</span>';
                    html += '<input type="file" class="mj-feed-post__edit-file-input" accept="' + acceptTypes.join(',') + '" multiple style="display:none">';
                    html += '</label>';
                    html += '</div>';
                }

                html += '</div>';
                return html;
            }

            // Build edit form
            const $editForm = $('<div class="mj-feed-post__edit-form">' +
                '<textarea class="mj-feed-post__edit-textarea">' + $('<span>').text(rawContent).html() + '</textarea>' +
                buildMediaPreview() +
                '<div class="mj-feed-post__edit-status" style="display:none;"></div>' +
                '<div class="mj-feed-post__edit-actions">' +
                    '<button type="button" class="mj-btn mj-btn--small mj-feed-post__edit-save" data-action="save-edit">' +
                        'Enregistrer' +
                    '</button>' +
                    '<button type="button" class="mj-btn mj-btn--small mj-btn--ghost mj-feed-post__edit-cancel" data-action="cancel-edit">' +
                        'Annuler' +
                    '</button>' +
                '</div>' +
            '</div>');

            $content.html($editForm);
            $content.find('.mj-feed-post__edit-textarea').focus();

            // --- Helper to refresh the media preview section ---
            function refreshMediaPreview() {
                $content.find('.mj-feed-post__edit-media').replaceWith(buildMediaPreview());
                bindMediaEvents();
            }

            function showEditStatus(msg, type) {
                const $status = $content.find('.mj-feed-post__edit-status');
                if (!msg) { $status.hide().text(''); return; }
                $status.text(msg)
                    .removeClass('mj-feed-post__edit-status--error mj-feed-post__edit-status--success')
                    .addClass(type ? 'mj-feed-post__edit-status--' + type : '')
                    .show();
            }

            // --- Bind media events ---
            function bindMediaEvents() {
                // Remove photo
                $content.find('[data-action="edit-remove-photo"]').off('click.editMedia').on('click.editMedia', function(ev) {
                    ev.preventDefault();
                    const photoId = parseInt($(this).data('photo-id'));
                    editPhotos = editPhotos.filter(function(p) { return p.id !== photoId; });
                    refreshMediaPreview();
                });

                // Remove video
                $content.find('[data-action="edit-remove-video"]').off('click.editMedia').on('click.editMedia', function(ev) {
                    ev.preventDefault();
                    editVideoId = 0;
                    editVideoUrl = '';
                    refreshMediaPreview();
                });

                // File input change → upload
                $content.find('.mj-feed-post__edit-file-input').off('change.editMedia').on('change.editMedia', function() {
                    const files = Array.from(this.files || []);
                    if (!files.length) return;

                    files.forEach(function(file) {
                        if (file.type.startsWith('video/')) {
                            editUploadVideo(file);
                        } else if (file.type.startsWith('image/')) {
                            editUploadPhoto(file);
                        }
                    });

                    // Reset input
                    $(this).val('');
                });
            }

            // --- Upload a photo in edit mode ---
            function editUploadPhoto(file) {
                if (editPhotos.length >= (config.maxPhotos || 5)) {
                    showEditStatus(i18n.maxPhotosReached || 'Maximum de photos atteint.', 'error');
                    return;
                }

                showEditStatus(i18n.uploading || 'Envoi en cours...', '');

                const formData = new FormData();
                formData.append('action', 'mj_front_testimonial_upload');
                formData.append('_wpnonce', config.nonce);
                formData.append('type', 'photo');
                formData.append('file', file);

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                }).done(function(response) {
                    if (response.success && response.data && response.data.id) {
                        editPhotos.push({ id: response.data.id, url: response.data.url || response.data.thumb });
                        showEditStatus('', '');
                        refreshMediaPreview();
                    } else {
                        const errMsg = (typeof response.data === 'string') ? response.data : (response.data?.message || i18n.submitError);
                        showEditStatus(errMsg, 'error');
                    }
                }).fail(function(xhr) {
                    let errMsg = i18n.submitError;
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                        errMsg = (typeof xhr.responseJSON.data === 'string') ? xhr.responseJSON.data : (xhr.responseJSON.data.message || errMsg);
                    }
                    showEditStatus(errMsg, 'error');
                });
            }

            // --- Upload a video in edit mode ---
            function editUploadVideo(file) {
                const maxVideoSize = config.maxVideoSize || (100 * 1024 * 1024);
                if (file.size > maxVideoSize) {
                    const sizeMb = (maxVideoSize / (1024 * 1024)).toFixed(0);
                    const msg = (i18n.videoTooLarge || 'La vidéo est trop volumineuse. Taille maximale : %s.')
                        .replace('%s', sizeMb + '\u00a0Mo');
                    showEditStatus(msg, 'error');
                    return;
                }

                if (editVideoId) {
                    showEditStatus('Une vidéo est déjà attachée. Supprimez-la d\'abord.', 'error');
                    return;
                }

                showEditStatus(i18n.videoUploading || 'Upload de la vidéo en cours...', '');

                const formData = new FormData();
                formData.append('action', 'mj_front_testimonial_upload');
                formData.append('_wpnonce', config.nonce);
                formData.append('type', 'video');
                formData.append('file', file);

                const xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(ev) {
                    if (ev.lengthComputable) {
                        const pct = Math.round((ev.loaded / ev.total) * 100);
                        showEditStatus((i18n.videoUploading || 'Upload de la vidéo en cours...') + ' ' + pct + '%', '');
                    }
                });

                xhr.addEventListener('load', function() {
                    let resp;
                    try { resp = JSON.parse(xhr.responseText); } catch(_) {
                        showEditStatus(i18n.videoUploadError || 'Échec de l\'upload vidéo.', 'error');
                        return;
                    }
                    if (resp && resp.success && resp.data && resp.data.id) {
                        editVideoId = resp.data.id;
                        editVideoUrl = resp.data.url;
                        showEditStatus('', '');
                        refreshMediaPreview();
                    } else {
                        const errMsg = (resp && resp.data && (typeof resp.data === 'string' ? resp.data : resp.data.message))
                            || (i18n.videoUploadError || 'Échec de l\'upload vidéo.');
                        showEditStatus(errMsg, 'error');
                    }
                });

                xhr.addEventListener('error', function() {
                    showEditStatus(i18n.videoUploadError || 'Échec de l\'upload vidéo.', 'error');
                });

                xhr.open('POST', config.ajaxUrl, true);
                xhr.send(formData);
            }

            // Initial bind
            bindMediaEvents();

            // Cancel
            $content.find('[data-action="cancel-edit"]').on('click', function() {
                $content.html(originalContentHtml);
                $origPhotosMedia.show();
                $origVideoMedia.show();
            });

            // Save
            $content.find('[data-action="save-edit"]').on('click', function() {
                const newContent = $content.find('.mj-feed-post__edit-textarea').val().trim();
                const hasMedia = editPhotos.length > 0 || editVideoId > 0;

                if (!newContent && !hasMedia) {
                    showEditStatus('Le témoignage doit contenir au moins du texte, une photo ou une vidéo.', 'error');
                    return;
                }

                const testimonialId = getTestimonialId($wrapper);
                const $saveBtn = $(this);
                $saveBtn.prop('disabled', true).text('Enregistrement...');
                showEditStatus('', '');

                const postData = {
                    action: 'mj_front_testimonial_edit',
                    _wpnonce: config.nonce,
                    testimonial_id: testimonialId,
                    content: newContent,
                    photo_ids: JSON.stringify(editPhotos.map(function(p) { return p.id; })),
                    video_id: editVideoId
                };

                $.post(config.ajaxUrl, postData).done(function(response) {
                    if (response.success) {
                        // Update displayed content with linkified HTML
                        $content.html(response.data.contentHtml || '');
                        // Update the raw content attribute
                        $content.attr('data-raw-content', response.data.content || '');

                        // Remove old media sections and insert new ones
                        $origPhotosMedia.remove();
                        $origVideoMedia.remove();

                        // Insert new media after content
                        if (response.data.photosHtml) {
                            $content.after(response.data.photosHtml);
                        }
                        if (response.data.videoHtml) {
                            const $afterContent = $content.next('.mj-feed-post__media');
                            if ($afterContent.length) {
                                $afterContent.after(response.data.videoHtml);
                            } else {
                                $content.after(response.data.videoHtml);
                            }
                        }

                        // Update data attributes for future edits
                        $post.attr('data-photos', JSON.stringify(response.data.photos || []));
                        $post.attr('data-video', response.data.video ? JSON.stringify(response.data.video) : '');
                    } else {
                        showEditStatus(response.data || 'Erreur lors de la modification.', 'error');
                        $saveBtn.prop('disabled', false).text('Enregistrer');
                    }
                }).fail(function() {
                    showEditStatus('Erreur réseau lors de la modification.', 'error');
                    $saveBtn.prop('disabled', false).text('Enregistrer');
                });
            });
        });

        // --- Animator: Toggle featured ---
        $(document).on('click.mjFeed', '[data-action="toggle-featured"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const $wrapper = getWrapper(this);
            const $post = $wrapper.find('.mj-feed-post');
            const testimonialId = getTestimonialId($wrapper);

            // Close dropdown
            $btn.closest('.mj-feed-post__owner-dropdown').hide();

            $btn.prop('disabled', true);

            $.post(config.ajaxUrl, {
                action: 'mj_front_testimonial_toggle_featured',
                _wpnonce: config.nonce,
                testimonial_id: testimonialId
            }).done(function(response) {
                if (response.success) {
                    const featured = response.data.featured;
                    // Update data attribute
                    $post.attr('data-featured', featured ? '1' : '0');

                    // Toggle featured class
                    $post.toggleClass('mj-feed-post--featured', featured);

                    // Update the button label & icon fill
                    $btn.find('span').text(response.data.label);
                    $btn.find('svg').attr('fill', featured ? 'currentColor' : 'none');

                    // Toggle the star badge next to the menu
                    const $menu = $wrapper.find('.mj-feed-post__owner-menu');
                    $wrapper.find('.mj-feed-post__featured-badge').remove();
                    if (featured) {
                        $menu.before('<span class="mj-feed-post__featured-badge" title="Mis en avant">\u2b50</span>');
                    }
                } else {
                    alert(response.data || 'Erreur.');
                }
            }).fail(function() {
                alert('Erreur réseau.');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });

        // Share button - toggle picker
        $(document).on('click.mjFeed', '.mj-feed-post__action--share', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $picker = $(this).find('.mj-feed-post__share-picker');
            // Close all other open pickers
            $('.mj-feed-post__share-picker').not($picker).removeClass('is-visible');
            $picker.toggleClass('is-visible');
        });

        // Close share picker on outside click
        $(document).on('click.mjFeed', function(e) {
            if (!$(e.target).closest('.mj-feed-post__action--share').length) {
                $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
            }
        });

        // Share option click
        $(document).on('click.mjFeed', '.mj-feed-post__share-option', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const platform = $(this).data('share');
            const $wrapper = getWrapper(this);
            const relativeUrl = $wrapper.data('post-url') || window.location.href;
            // Ensure absolute URL with domain
            const postUrl = relativeUrl.startsWith('http') ? relativeUrl : window.location.origin + (relativeUrl.startsWith('/') ? '' : window.location.pathname) + relativeUrl;
            const $post = $wrapper.find('.mj-feed-post');
            const rawContent = $post.find('.mj-feed-post__content').data('raw-content') || '';
            const author = $post.find('.mj-feed-post__author').text().trim();
            const shareText = author
                ? 'Découvrez le témoignage de ' + author + ' sur la MJ Pery : ' + rawContent.substring(0, 100) + (rawContent.length > 100 ? '...' : '')
                : rawContent.substring(0, 140) + (rawContent.length > 140 ? '...' : '');
            const encodedUrl = encodeURIComponent(postUrl);
            const encodedText = encodeURIComponent(shareText);

            let shareUrl = '';
            switch (platform) {
                case 'whatsapp':
                    shareUrl = 'https://api.whatsapp.com/send?text=' + encodedText + '%20' + encodedUrl;
                    break;
                case 'facebook':
                    shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl + '&quote=' + encodedText;
                    break;
                case 'instagram':
                    // Instagram n'a pas d'API de partage web, on copie le lien
                    copyToClipboard(postUrl, 'Lien copié ! Collez-le dans votre story ou publication Instagram.');
                    $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
                    return;
                case 'tiktok':
                    // TikTok n'a pas d'API de partage web, on copie le lien
                    copyToClipboard(postUrl, 'Lien copié ! Collez-le dans votre vidéo TikTok.');
                    $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
                    return;
                case 'copy':
                    copyToClipboard(postUrl, 'Lien copié dans le presse-papier !');
                    $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
                    return;
            }

            if (shareUrl) {
                window.open(shareUrl, '_blank', 'noopener,noreferrer,width=600,height=400');
                $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
            }
        });

        /**
         * Copy text to clipboard and show feedback
         */
        function copyToClipboard(text, message) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showShareToast(message);
                }).catch(function() {
                    fallbackCopy(text, message);
                });
            } else {
                fallbackCopy(text, message);
            }
        }

        function fallbackCopy(text, message) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showShareToast(message);
            } catch (err) {
                showShareToast('Impossible de copier le lien.');
            }
            document.body.removeChild(textarea);
        }

        function showShareToast(message) {
            // Remove existing toast
            $('.mj-share-toast').remove();
            const $toast = $('<div class="mj-share-toast">' + $('<span>').text(message).html() + '</div>');
            $('body').append($toast);
            // Trigger animation
            requestAnimationFrame(function() {
                $toast.addClass('is-visible');
            });
            setTimeout(function() {
                $toast.removeClass('is-visible');
                setTimeout(function() { $toast.remove(); }, 300);
            }, 2500);
        }

        // Click on post to navigate to single view (only in list mode)
        $(document).on('click', '.mj-feed-post-wrapper:not(.mj-feed-post-wrapper--single)', function(e) {
            // Don't navigate if clicking on interactive elements
            const $target = $(e.target);
            const isInteractive = $target.closest('button, a, input, textarea, video, .mj-feed-post__actions, .mj-feed-post__reactions-bar, .mj-feed-post__comments, .mj-feed-post__reaction-picker, .mj-feed-post__share-picker, .mj-feed-post__photo, .mj-feed-post__owner-menu, .mj-feed-post__edit-form').length > 0;
            
            if (isInteractive) {
                return;
            }
            
            const postUrl = $(this).data('post-url');
            if (postUrl) {
                window.location.href = postUrl;
            }
        });
        
        console.log('[MJ] Feed events initialized');
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('[MJ-TESTIMONIALS] Document ready');
        initTestimonials();
        initFeedEvents();
    });

    // Re-init for Elementor preview
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-testimonials.default', function() {
                initTestimonials();
                initFeedEvents();
            });
        }
    });

})(jQuery);
