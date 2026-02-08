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

        $('.mj-testimonials__load-more-btn').each(function() {
            const $btn = $(this);
            // Prevent double initialization
            if ($btn.data('mj-testimonials-init')) {
                return;
            }
            $btn.data('mj-testimonials-init', true);
            new TestimonialsLoadMore($btn);
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
            this.$addVideoBtn = $form.find('.mj-testimonials__add-video');
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
            this.isRecording = false;
            this.isSubmitting = false;

            this.bindEvents();
        }

        bindEvents() {
            this.$form.on('submit', (e) => this.handleSubmit(e));
            this.$addPhotoBtn.on('click', () => this.$photoInput.trigger('click'));
            this.$photoInput.on('change', (e) => this.handlePhotoSelect(e));
            this.$photosGrid.on('click', '.mj-testimonials__photo-remove', (e) => this.removePhoto(e));
            
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

            const maxPhotos = config.maxPhotos || 5;
            const remaining = maxPhotos - this.photos.length;
            
            if (remaining <= 0) {
                this.showStatus(i18n.maxPhotosReached || `Maximum ${maxPhotos} photos`, 'error');
                return;
            }

            const filesToUpload = Array.from(files).slice(0, remaining);
            filesToUpload.forEach(file => this.uploadPhoto(file));
            
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
                    this.showStatus(response.data?.message || i18n.submitError, 'error');
                }
            } catch (err) {
                placeholder.remove();
                this.showStatus(i18n.submitError, 'error');
                console.error('Photo upload error:', err);
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

            const formData = new FormData();
            formData.append('action', 'mj_front_testimonial_upload');
            formData.append('_wpnonce', config.nonce);
            formData.append('type', 'video');
            formData.append('file', this.videoBlob, 'testimonial-video.webm');

            const response = await $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });

            if (response.success && response.data && response.data.id) {
                return response.data.id;
            }
            
            throw new Error(response.data?.message || 'Video upload failed');
        }

        async handleSubmit(e) {
            e.preventDefault();
            
            if (this.isSubmitting) return;

            const content = this.$textarea.val().trim();
            
            if (!content && this.photos.length === 0 && !this.videoBlob) {
                this.showStatus('Veuillez ajouter du texte, des photos ou une vidéo.', 'error');
                return;
            }

            this.isSubmitting = true;
            this.$submitBtn.addClass('is-loading').prop('disabled', true);
            this.showStatus(i18n.uploading || 'Envoi en cours...', '');

            try {
                // Upload video if exists
                if (this.videoBlob && !this.videoId) {
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
                this.showStatus(i18n.submitError, 'error');
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
     * TestimonialsLoadMore class
     */
    class TestimonialsLoadMore {
        constructor($btn) {
            this.$btn = $btn;
            this.$container = $btn.closest('.mj-testimonials');
            this.$feed = this.$container.find('.mj-testimonials__feed');
            this.page = parseInt($btn.data('page'), 10) || 1;
            this.totalPages = parseInt($btn.data('total-pages'), 10) || 1;
            this.perPage = config.perPage || 6;
            this.isLoading = false;

            this.bindEvents();
        }

        bindEvents() {
            this.$btn.on('click', () => this.loadMore());
        }

        async loadMore() {
            if (this.isLoading || this.page >= this.totalPages) return;

            this.isLoading = true;
            this.$btn.addClass('is-loading');

            try {
                const response = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mj_front_testimonial_list',
                        nonce: config.nonce,
                        page: this.page + 1,
                        per_page: this.perPage
                    }
                });

                if (response.success && response.data && response.data.testimonials) {
                    this.page++;
                    this.renderTestimonials(response.data.testimonials);
                    
                    if (this.page >= this.totalPages) {
                        this.$btn.hide();
                    }
                }
            } catch (err) {
                console.error('Load more error:', err);
            } finally {
                this.isLoading = false;
                this.$btn.removeClass('is-loading');
            }
        }

        renderTestimonials(testimonials) {
            testimonials.forEach(t => {
                const card = this.createCard(t);
                this.$list.append(card);
            });
        }

        createCard(t) {
            let photosHtml = '';
            if (t.photos && t.photos.length > 0) {
                const visiblePhotos = t.photos.slice(0, 3);
                const moreCount = t.photos.length - 3;
                
                photosHtml = `
                    <div class="mj-testimonial-card__photos">
                        ${visiblePhotos.map(p => `
                            <a href="${this.escapeHtml(p.full)}" class="mj-testimonial-card__photo" target="_blank">
                                <img src="${this.escapeHtml(p.thumb)}" alt="" loading="lazy">
                            </a>
                        `).join('')}
                        ${moreCount > 0 ? `<span class="mj-testimonial-card__photos-more">+${moreCount}</span>` : ''}
                    </div>
                `;
            }

            let videoHtml = '';
            if (t.video && t.video.url) {
                videoHtml = `
                    <div class="mj-testimonial-card__video">
                        <video controls playsinline poster="${this.escapeHtml(t.video.poster || '')}">
                            <source src="${this.escapeHtml(t.video.url)}" type="video/mp4">
                        </video>
                    </div>
                `;
            }

            const contentHtml = t.content ? `
                <div class="mj-testimonial-card__content">
                    <blockquote>${this.formatContent(t.content)}</blockquote>
                </div>
            ` : '';

            return `
                <article class="mj-testimonial-card" data-id="${t.id}">
                    ${photosHtml}
                    ${videoHtml}
                    ${contentHtml}
                    <footer class="mj-testimonial-card__footer">
                        <div class="mj-testimonial-card__author">
                            <span class="mj-testimonial-card__author-name">${this.escapeHtml(t.author || '')}</span>
                        </div>
                        <time class="mj-testimonial-card__date">${this.escapeHtml(t.date_ago || '')}</time>
                    </footer>
                </article>
            `;
        }

        formatContent(content) {
            return content.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
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
