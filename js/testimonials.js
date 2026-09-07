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
    const allowFileUpload = config.allowFileUpload !== false;

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

    function initTestimonialEditor($textarea) {
        const richTextEditor = new TestimonialRichTextEditor($textarea);
        const mentionAutocomplete = new MentionAutocomplete(richTextEditor.$editor);

        return { richTextEditor, mentionAutocomplete };
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
     * Lightweight rich-text surface shared by the create and edit forms.
     * The original textarea stays the source of truth for AJAX submissions.
     */
    class TestimonialRichTextEditor {
        constructor($textarea) {
            this.$textarea = $textarea;
            this.$editor = null;
            this.$toolbar = null;
            this.$mentionPreview = null;
            this.previewRequest = null;
            this.resolvedPreviewTokens = new Set();
            this._init();
        }

        _init() {
            if (!this.$textarea.length || this.$textarea.data('mj-rich-text-init')) return;

            this.$textarea.data('mj-rich-text-init', true).addClass('mj-testimonials__textarea--source');
            const placeholder = this.$textarea.attr('placeholder') || '';
            this.$toolbar = $(
                '<div class="mj-testimonials__format-toolbar" role="toolbar" aria-label="Mise en forme du témoignage">' +
                    '<button type="button" data-command="bold" title="Gras"><strong>G</strong></button>' +
                    '<button type="button" data-command="italic" title="Italique"><em>I</em></button>' +
                    '<button type="button" data-command="insertUnorderedList" title="Liste à puces">•</button>' +
                    '<button type="button" data-mention="member" title="Mentionner un membre">@</button>' +
                    '<button type="button" data-mention="event" title="Mentionner un événement">#</button>' +
                '</div>'
            );
            this.$editor = $('<div class="mj-testimonials__rich-editor" contenteditable="true" role="textbox" aria-multiline="true"></div>')
                .attr('data-placeholder', placeholder)
                .html(this.$textarea.val() || '');
            this.$mentionPreview = $('<div class="mj-testimonials__mention-preview" aria-live="polite"></div>');
            const $container = $('<div class="mj-testimonials__rich-editor-wrap"></div>');

            this.$textarea.after($container);
            $container.append(this.$toolbar, this.$editor, this.$mentionPreview, this.$textarea);
            this.$textarea.attr('aria-hidden', 'true').attr('tabindex', '-1');

            this.$toolbar.on('mousedown', 'button', (event) => event.preventDefault());
            this.$toolbar.on('click', 'button', (event) => {
                const $button = $(event.currentTarget);
                const mentionType = $button.data('mention');
                if (mentionType) {
                    this.$editor.trigger('mj:start-mention', [mentionType]);
                    return;
                }
                const command = $button.data('command');
                this.$editor.trigger('focus');
                document.execCommand(command, false, null);
                this.sync();
            });
            this.$editor.on('input blur', () => this.sync());
            this.$editor.on('mj:mention-selected', (event, mention) => this.addMentionPreview(mention));
            this.syncMentionPreview();
        }

        sync() {
            if (!this.$editor) return;
            this.$textarea.val(this.$editor.html()).trigger('input');
            const content = this.$editor.text();
            this.$mentionPreview.children('[data-member-token]').each(function() {
                const memberToken = $(this).data('member-token');
                if (content.indexOf('@{' + memberToken + '}') === -1) {
                    $(this).remove();
                }
            });
            this.syncMentionPreview();
        }

        syncMentionPreview() {
            if (!this.$editor || this.previewRequest) return;
            const tokens = [...new Set((this.$editor.text().match(/@\{([a-z0-9][a-z0-9\-]*)\}/gi) || [])
                .map(token => token.slice(2, -1)))];
            const knownTokens = this.$mentionPreview.children('[data-member-token]').map(function() {
                return String($(this).data('member-token'));
            }).get();
            const missingTokens = tokens.filter(token => !knownTokens.includes(token) && !this.resolvedPreviewTokens.has(token));
            if (!missingTokens.length) return;

            this.previewRequest = $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mj_front_testimonial_search_members',
                    _wpnonce: config.nonce,
                    ids: JSON.stringify(missingTokens.filter(token => /^\d+$/.test(token))),
                    slugs: JSON.stringify(missingTokens.filter(token => !/^\d+$/.test(token)))
                },
                dataType: 'json'
            }).done((response) => {
                if (response.success && response.data && Array.isArray(response.data.members)) {
                    response.data.members.forEach(member => this.addMentionPreview({ type: 'member', item: member }));
                }
            }).always(() => {
                missingTokens.forEach(token => this.resolvedPreviewTokens.add(token));
                this.previewRequest = null;
                this.syncMentionPreview();
            });
        }

        addMentionPreview(mention) {
            if (!mention || mention.type !== 'member' || !mention.item || !mention.item.id) return;
            const token = mention.item.slug || String(mention.item.id);
            if (this.$mentionPreview.children('[data-member-token="' + token + '"]').length) return;

            const $chip = $('<span class="mj-testimonials__mention-chip"></span>').attr('data-member-token', token);
            if (mention.item.avatarUrl) {
                $('<img alt="" class="mj-testimonials__mention-avatar">').attr('src', mention.item.avatarUrl).appendTo($chip);
            } else {
                $('<span class="mj-testimonials__mention-initial"></span>').text(mention.item.initial || '?').appendTo($chip);
            }
            $('<span class="mj-testimonials__mention-name"></span>').text(mention.item.name || '').appendTo($chip);
            this.$mentionPreview.append($chip);
        }

        clear() {
            if (this.$editor) this.$editor.empty();
            this.$textarea.val('');
        }

        destroy() {
            if (!this.$editor) return;
            this.$textarea.removeData('mj-rich-text-init').removeClass('mj-testimonials__textarea--source')
                .removeAttr('aria-hidden tabindex');
            this.$editor.closest('.mj-testimonials__rich-editor-wrap').before(this.$textarea).remove();
            this.$editor = null;
        }
    }

    /**
     * Standalone mention autocomplete (# events, @ members).
     * Can be attached to a textarea or a contenteditable rich-text surface.
     */
    class MentionAutocomplete {
        constructor($textarea) {
            this.$textarea = $textarea;
            this.active = false;
            this.type = null;
            this.query = '';
            this.triggerStart = -1;
            this.results = [];
            this.selectedIndex = 0;
            this.debounce = null;
            this.$dropdown = null;
            this._init();
        }

        _init() {
            // Wrap the textarea in a positioned container so the dropdown anchors to it
            this.$wrap = $('<div class="mj-mention-wrap"></div>');
            this.$textarea.after(this.$wrap);
            this.$wrap.append(this.$textarea);

            this.$dropdown = $('<div class="mj-mention-dropdown" style="display:none;"></div>');
            this.$wrap.append(this.$dropdown);

            this.$textarea.on('input.mjMention', () => this._handleInput());
            this.$textarea.on('keydown.mjMention', (e) => this._handleKeydown(e));
            this.$textarea.on('blur.mjMention', () => { setTimeout(() => this._close(), 200); });
            this.$textarea.on('mj:start-mention.mjMention', (event, type) => this.start(type));
        }

        start(type) {
            const textarea = this.$textarea[0];
            const marker = type === 'event' ? '#' : '@';
            const content = textarea.isContentEditable ? textarea.textContent : textarea.value;
            const cursorPos = textarea.isContentEditable ? this._getCaretOffset(textarea) : textarea.selectionStart;
            const prefix = cursorPos > 0 && !/\s/.test(content.charAt(cursorPos - 1)) ? ' ' : '';

            if (textarea.isContentEditable) {
                this._replaceRichTextRange(textarea, cursorPos, cursorPos, prefix + marker);
                this.$textarea.trigger('input');
            } else {
                textarea.value = content.slice(0, cursorPos) + prefix + marker + content.slice(cursorPos);
                const nextPosition = cursorPos + prefix.length + marker.length;
                textarea.setSelectionRange(nextPosition, nextPosition);
            }

            this.active = true;
            this.type = type;
            this.triggerStart = cursorPos + prefix.length;
            this.query = '';
            this.results = [];
            if (type === 'event') this._searchEvents('');
            else this._searchMembers('');
        }

        destroy() {
            this.$textarea.off('.mjMention');
            clearTimeout(this.debounce);
            if (this.$wrap) {
                this.$wrap.before(this.$textarea);
                this.$wrap.remove();
                this.$wrap = null;
            }
            this.$dropdown = null;
        }

        _handleInput() {
            const textarea = this.$textarea[0];
            const isRichText = textarea.isContentEditable;
            const cursorPos = isRichText ? this._getCaretOffset(textarea) : textarea.selectionStart;
            const content = isRichText ? textarea.textContent : textarea.value;
            const textBeforeCursor = content.substring(0, cursorPos);

            const hashIndex = textBeforeCursor.lastIndexOf('#');
            const atIndex = textBeforeCursor.lastIndexOf('@');

            let triggerChar = null;
            let triggerIndex = -1;
            if (hashIndex !== -1 && (atIndex === -1 || hashIndex > atIndex)) {
                triggerChar = '#'; triggerIndex = hashIndex;
            } else if (atIndex !== -1) {
                triggerChar = '@'; triggerIndex = atIndex;
            }

            if (triggerIndex === -1) { this._close(); return; }
            if (triggerIndex > 0 && !/[\s]/.test(content.charAt(triggerIndex - 1))) { this._close(); return; }

            const query = textBeforeCursor.substring(triggerIndex + 1);
            if (query.length > 50) { this._close(); return; }
            if (triggerChar === '#' && !/^[a-z0-9\-]*$/i.test(query)) { this._close(); return; }
            if (triggerChar === '@' && !/^[a-zA-ZÀ-ÿ0-9\- ]*$/.test(query)) { this._close(); return; }

            this.active = true;
            this.type = triggerChar === '#' ? 'event' : 'member';
            this.triggerStart = triggerIndex;
            this.query = query;

            clearTimeout(this.debounce);
            if (query.length >= 1) {
                this.debounce = setTimeout(() => {
                    if (this.type === 'event') { this._searchEvents(query); }
                    else { this._searchMembers(query); }
                }, 250);
            } else {
                this.results = [];
                this._renderDropdown();
            }
        }

        _handleKeydown(e) {
            if (!this.active || !this.$dropdown || !this.$dropdown.is(':visible')) return;
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                    this._highlight(); break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                    this._highlight(); break;
                case 'Enter': case 'Tab':
                    if (this.results.length > 0) { e.preventDefault(); this._select(this.results[this.selectedIndex]); }
                    break;
                case 'Escape':
                    e.preventDefault(); this._close(); break;
            }
        }

        async _searchEvents(query) {
            try {
                const response = await $.ajax({
                    url: config.ajaxUrl, method: 'POST',
                    data: { action: 'mj_front_testimonial_search_events', _wpnonce: config.nonce, search: query },
                    dataType: 'json'
                });
                if (response.success && response.data && response.data.events) {
                    this.results = response.data.events;
                    this.selectedIndex = 0;
                    this._renderDropdown();
                }
            } catch (err) { console.error('Event search error:', err); }
        }

        async _searchMembers(query) {
            try {
                const response = await $.ajax({
                    url: config.ajaxUrl, method: 'POST',
                    data: { action: 'mj_front_testimonial_search_members', _wpnonce: config.nonce, search: query },
                    dataType: 'json'
                });
                if (response.success && response.data && response.data.members) {
                    this.results = response.data.members;
                    this.selectedIndex = 0;
                    this._renderDropdown();
                }
            } catch (err) { console.error('Member search error:', err); }
        }

        _renderDropdown() {
            if (!this.active || !this.$dropdown) return;
            const isEvent = this.type === 'event';
            const emptyMsg = isEvent ? 'Aucun événement trouvé' : 'Aucun membre trouvé';
            const hintMsg = isEvent ? 'Tapez le nom d\'un événement...' : 'Tapez le nom d\'un membre...';

            if (this.results.length === 0 && this.query.length >= 1) {
                this.$dropdown.html('<div class="mj-mention-dropdown__empty">' + emptyMsg + '</div>').show();
                return;
            }
            if (this.results.length === 0) {
                this.$dropdown.html('<div class="mj-mention-dropdown__hint">' + hintMsg + '</div>').show();
                return;
            }

            let html = '';
            this.results.forEach((item, index) => {
                const isSelected = index === this.selectedIndex ? ' is-selected' : '';
                if (isEvent) {
                    const emoji = item.emoji ? this._escapeHtml(item.emoji) + ' ' : '';
                    const type = item.type ? '<span class="mj-mention-dropdown__type">' + this._escapeHtml(item.type) + '</span>' : '';
                    const date = item.date_debut ? '<span class="mj-mention-dropdown__date">' + this._formatShortDate(item.date_debut) + '</span>' : '';
                    html += '<div class="mj-mention-dropdown__item' + isSelected + '" data-index="' + index + '">' +
                        '<div class="mj-mention-dropdown__item-main"><span class="mj-mention-dropdown__item-title">' + emoji + this._escapeHtml(item.title) + '</span>' + type + '</div>' +
                        '<div class="mj-mention-dropdown__item-meta"><span class="mj-mention-dropdown__item-slug">#' + this._escapeHtml(item.slug) + '</span>' + date + '</div>' +
                    '</div>';
                } else {
                    const avatarHtml = item.avatarUrl
                        ? '<img src="' + this._escapeHtml(item.avatarUrl) + '" alt="" class="mj-mention-dropdown__member-avatar">'
                        : '<span class="mj-mention-dropdown__member-initial">' + this._escapeHtml(item.initial || '?') + '</span>';
                    html += '<div class="mj-mention-dropdown__item mj-mention-dropdown__item--member' + isSelected + '" data-index="' + index + '">' +
                        '<div class="mj-mention-dropdown__member-avatar-wrap">' + avatarHtml + '</div>' +
                        '<span class="mj-mention-dropdown__item-title">' + this._escapeHtml(item.name) + '</span>' +
                    '</div>';
                }
            });

            html += '<div class="mj-mention-dropdown__footer">' +
                '<span class="mj-mention-dropdown__footer-key"><kbd>↑</kbd><kbd>↓</kbd> naviguer</span>' +
                '<span class="mj-mention-dropdown__footer-key"><kbd>↵</kbd> sélectionner</span>' +
                '<span class="mj-mention-dropdown__footer-key"><kbd>Esc</kbd> fermer</span>' +
            '</div>';

            this.$dropdown.html(html).show();

            this.$dropdown.find('.mj-mention-dropdown__item').on('mousedown', (e) => {
                e.preventDefault();
                const index = parseInt($(e.currentTarget).data('index'), 10);
                if (this.results[index]) { this._select(this.results[index]); }
            }).on('mouseenter', (e) => {
                this.selectedIndex = parseInt($(e.currentTarget).data('index'), 10);
                this._highlight();
            });
        }

        _highlight() {
            const $items = this.$dropdown.find('.mj-mention-dropdown__item');
            $items.removeClass('is-selected');
            const $sel = $items.eq(this.selectedIndex).addClass('is-selected');
            if ($sel.length) {
                const c = this.$dropdown[0], el = $sel[0];
                const elTop = el.offsetTop, elBottom = elTop + el.offsetHeight;
                if (elTop < c.scrollTop) { c.scrollTop = elTop; }
                else if (elBottom > c.scrollTop + c.clientHeight) { c.scrollTop = elBottom - c.clientHeight; }
            }
        }

        _select(item) {
            const textarea = this.$textarea[0];
            const mentionType = this.type;
            const replacement = mentionType === 'event' ? '#' + item.slug + ' ' : '@{' + item.slug + '} ';

            if (textarea.isContentEditable) {
                const cursorPos = this._getCaretOffset(textarea);
                this._replaceRichTextRange(textarea, this.triggerStart, cursorPos, replacement);
                this.$textarea.trigger('input');
            } else {
                const content = textarea.value;
                const cursorPos = textarea.selectionStart;
                const before = content.substring(0, this.triggerStart);
                const after = content.substring(cursorPos);
                textarea.value = before + replacement + after;
                const newPos = this.triggerStart + replacement.length;
                textarea.setSelectionRange(newPos, newPos);
            }
            textarea.focus();
            this.$textarea.trigger('mj:mention-selected', [{ type: mentionType, item }]);
            this._close();
        }

        _getCaretOffset(element) {
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount || !element.contains(selection.anchorNode)) {
                return element.textContent.length;
            }
            const range = selection.getRangeAt(0);
            if (!element.contains(range.endContainer)) return element.textContent.length;
            const before = range.cloneRange();
            before.selectNodeContents(element);
            before.setEnd(range.endContainer, range.endOffset);
            return before.toString().length;
        }

        _replaceRichTextRange(element, start, end, replacement) {
            const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
            const nodes = [];
            let node;
            while ((node = walker.nextNode())) nodes.push(node);

            const pointAt = (offset) => {
                let remaining = offset;
                for (const textNode of nodes) {
                    if (remaining <= textNode.nodeValue.length) return { node: textNode, offset: remaining };
                    remaining -= textNode.nodeValue.length;
                }
                return { node: element, offset: element.childNodes.length };
            };
            const from = pointAt(start);
            const to = pointAt(end);
            const range = document.createRange();
            range.setStart(from.node, from.offset);
            range.setEnd(to.node, to.offset);
            range.deleteContents();
            const inserted = document.createTextNode(replacement);
            range.insertNode(inserted);
            range.setStartAfter(inserted);
            range.collapse(true);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }

        _close() {
            this.active = false;
            this.type = null;
            this.query = '';
            this.triggerStart = -1;
            this.results = [];
            this.selectedIndex = 0;
            if (this.$dropdown) { this.$dropdown.hide().empty(); }
        }

        _escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }

        _formatShortDate(dateStr) {
            if (!dateStr) return '';
            try { return new Date(dateStr).toLocaleDateString('fr-BE', { day: 'numeric', month: 'short', year: 'numeric' }); }
            catch (e) { return dateStr; }
        }
    }

    /**
     * TestimonialsForm class
     */
    class TestimonialsForm {
        constructor($form) {
            this.$form = $form;
            this.$textarea = $form.find('.mj-testimonials__textarea');
            this.editorInstance = initTestimonialEditor(this.$textarea);
            this.richTextEditor = this.editorInstance.richTextEditor;
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
            this.isPhotoBoothMode = String($form.data('photo-booth')) === '1';
            this.photoBoothAllowVideo = String($form.data('allow-video')) === '1';
            this.captureMode = 'photo';
            this.$cameraModeButtons = $form.find('.mj-testimonials__camera-mode-btn');
            this.$countdownOverlays = $form.find('.mj-testimonials__capture-countdown');
            this.$countdownValues = $form.find('.mj-testimonials__capture-countdown-value');

            this.photos = [];
            this.videos = []; // array of {id, url} uploaded videos
            this.videoBlob = null; // current recording blob (not yet uploaded)
            this.mediaRecorder = null;
            this.recordedChunks = [];
            this.stream = null;
            this.cameraStream = null;
            this.isRecording = false;
            this.isSubmitting = false;
            this.isCountingDown = false;
            this.captureCountdownInterval = null;
            
            // Link preview
            this.linkPreview = null;
            this.linkPreviewFetching = false;
            this.linkPreviewDebounce = null;
            this.$linkPreviewContainer = null;

            // Mention autocomplete (# for events, @ for members)
            this.mentionActive = false;
            this.mentionType = null; // 'event' | 'member'
            this.mentionQuery = '';
            this.mentionStart = -1;
            this.mentionResults = [];
            this.mentionSelectedIndex = 0;
            this.mentionDebounce = null;
            this.$mentionDropdown = null;

            this.bindEvents();
            this.initLinkPreviewContainer();
            this.mentionAutocomplete = this.editorInstance.mentionAutocomplete;
            this.initPhotoBoothMode();
        }

        initLinkPreviewContainer() {
            // Create link preview container after photos grid
            this.$linkPreviewContainer = $('<div class="mj-testimonials__link-preview" style="display:none;"></div>');
            this.$photosGrid.after(this.$linkPreviewContainer);
        }

        bindEvents() {
            this.$form.on('submit', (e) => this.handleSubmit(e));
            if (allowFileUpload) {
                this.$addPhotoBtn.on('click', () => this.$photoInput.trigger('click'));
                this.$photoInput.on('change', (e) => this.handlePhotoSelect(e));
            }
            this.$photosGrid.on('click', '.mj-testimonials__photo-remove', (e) => this.removePhoto(e));
            
            // Link preview detection on textarea input
            this.$textarea.on('input', () => this.detectUrl());

            // Photo capture events
            this.$capturePhotoBtn.on('click', () => this.startPhotoCapture());
            this.$form.find('.mj-testimonials__camera-capture').on('click', () => this.capturePhoto());
            this.$form.find('.mj-testimonials__camera-cancel').on('click', () => this.cancelPhotoCapture());
            
            // Video events
            this.$addVideoBtn.on('click', () => this.startVideoCapture());
            this.$form.find('.mj-testimonials__video-record').on('click', () => this.toggleRecording());
            this.$form.find('.mj-testimonials__video-stop').on('click', () => this.stopRecording());
            this.$form.find('.mj-testimonials__video-cancel').on('click', () => this.cancelVideo());
            this.$form.find('.mj-testimonials__video-use').on('click', () => this.useVideo());
            this.$form.find('.mj-testimonials__video-retake').on('click', () => this.retakeVideo());
            this.$form.find('.mj-testimonials__video-remove').on('click', () => this.removeVideo());

            if (this.isPhotoBoothMode && this.$cameraModeButtons.length) {
                this.$cameraModeButtons.on('click', (e) => {
                    const nextMode = String($(e.currentTarget).data('mode') || 'photo');
                    this.setCaptureMode(nextMode);
                });
            }
        }

        initPhotoBoothMode() {
            if (!this.isPhotoBoothMode) {
                return;
            }
            this.setCaptureMode('photo');
        }

        setCaptureMode(mode) {
            const nextMode = mode === 'video' && this.photoBoothAllowVideo ? 'video' : 'photo';
            this.captureMode = nextMode;
            this.$cameraModeButtons.removeClass('is-active');
            this.$cameraModeButtons.filter(`[data-mode="${nextMode}"]`).addClass('is-active');

            if (nextMode === 'video') {
                this.cancelPhotoCapture();
                this.startVideoCapture();
            } else {
                this.cancelVideo();
                this.startPhotoCapture();
            }
        }

        handlePhotoSelect(e) {
            if (!allowFileUpload) {
                this.$photoInput.val('');
                return;
            }

            const files = e.target.files;
            if (!files || files.length === 0) return;

            const allFiles = Array.from(files);
            const videoFiles = allFiles.filter(f => f.type.startsWith('video/'));
            const photoFiles = allFiles.filter(f => !f.type.startsWith('video/'));

            // Handle video file(s) — allow multiple
            if (videoFiles.length > 0) {
                videoFiles.forEach(file => this.uploadVideoFile(file));
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
            this.appendKioskAuth(formData);

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
            this.appendKioskAuth(formData);

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
                                const statusMessage = xhr.status === 413
                                    ? (i18n.videoTooLarge || 'La vidéo est trop volumineuse.')
                                    : (xhr.responseText || 'Réponse invalide du serveur.');
                                reject(new Error(statusMessage));
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

                // Success: add to videos array and show in grid
                this.videos.push({ id: response.id, url: response.url });
                this.renderVideoInGrid(response.id, response.url);
                this.showStatus('', '');
            } catch (err) {
                this.$addVideoBtn.show();
                this.showStatus(err.message || i18n.videoUploadError || 'Échec de l\'upload vidéo.', 'error');
                console.error('Video file upload error:', err);
            }
        }

        renderVideoInGrid(videoId, videoUrl) {
            const $item = $(`
                <div class="mj-testimonials__photo-item mj-testimonials__video-item" data-video-id="${videoId}">
                    <video src="${this.escapeHtml(videoUrl)}" playsinline muted></video>
                    <button type="button" class="mj-testimonials__photo-remove mj-testimonials__video-remove" data-video-id="${videoId}">&times;</button>
                </div>
            `);
            this.$photosGrid.append($item);
            $item.find('.mj-testimonials__video-remove').on('click', (e) => {
                e.preventDefault();
                const vid = parseInt($(e.currentTarget).data('video-id'), 10);
                this.videos = this.videos.filter(v => v.id !== vid);
                $item.remove();
            });
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
                if (this.stream) {
                    this.stopStream();
                }
                this.$videoPreview.hide();

                if (this.cameraStream) {
                    this.$cameraElement[0].srcObject = this.cameraStream;
                    await this.$cameraElement[0].play();
                    this.$cameraPreview.show();
                    return;
                }

                this.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: this.isPhotoBoothMode ? 'user' : 'environment',
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
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
            if (this.isPhotoBoothMode) {
                this.startCaptureCountdown('photo');
                return;
            }

            this.capturePhotoNow();
        }

        capturePhotoNow() {
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
                    this.appendKioskAuth(formData);

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

                if (!this.isPhotoBoothMode) {
                    this.cancelPhotoCapture();
                }
            }, 'image/jpeg', 0.9);
        }

        cancelPhotoCapture() {
            this.clearCaptureCountdown();
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
                if (this.cameraStream) {
                    this.cancelPhotoCapture();
                }
                if (this.stream) {
                    this.stopStream();
                }

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
                if (this.isPhotoBoothMode) {
                    this.startCaptureCountdown('video');
                } else {
                    this.startRecording();
                }
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
            this.clearCaptureCountdown();
            if (this.mediaRecorder && this.isRecording) {
                this.mediaRecorder.stop();
                this.isRecording = false;
                this.$form.find('.mj-testimonials__video-record').removeClass('is-recording').find('span').last().text('Enregistrer');
                this.$form.find('.mj-testimonials__video-stop').hide();
            }
        }

        showVideoResult() {
            this.clearCaptureCountdown();
            this.stopStream();
            this.$videoPreview.hide();
            
            const url = URL.createObjectURL(this.videoBlob);
            this.$videoPlayback[0].src = url;
            this.$videoResult.show();
        }

        cancelVideo() {
            this.clearCaptureCountdown();
            this.stopStream();
            this.$videoPreview.hide();
            this.$addVideoBtn.show();
            this.recordedChunks = [];
        }

        async useVideo() {
            if (!this.videoBlob) return;
            const $useBtn = this.$form.find('.mj-testimonials__video-use');
            $useBtn.prop('disabled', true).text('Upload...');
            try {
                const id = await this.uploadVideo();
                if (id) {
                    const url = this.$videoPlayback[0].src;
                    this.videos.push({ id, url });
                    this.renderVideoInGrid(id, url);
                }
            } catch (err) {
                this.showStatus(err.message || i18n.videoUploadError || 'Échec de l\'upload vidéo.', 'error');
            } finally {
                $useBtn.prop('disabled', false).text(i18n.videoUse || 'Ajouter');
                this.videoBlob = null;
                this.$videoResult.hide();
                this.$addVideoBtn.show();
            }
        }

        retakeVideo() {
            this.$videoResult.hide();
            this.videoBlob = null;
            this.startVideoCapture();
        }

        removeVideo() {
            this.clearCaptureCountdown();
            this.$videoResult.hide();
            this.$addVideoBtn.show();
            this.videoBlob = null;
        }

        updateCountdownOverlay(remaining) {
            if (this.$countdownValues.length) {
                this.$countdownValues.text(String(remaining));
            }
            if (this.$countdownOverlays.length) {
                this.$countdownOverlays.prop('hidden', false).removeAttr('hidden');
            }
        }

        clearCaptureCountdown() {
            if (this.captureCountdownInterval) {
                window.clearInterval(this.captureCountdownInterval);
                this.captureCountdownInterval = null;
            }
            this.isCountingDown = false;
            if (this.$countdownOverlays.length) {
                this.$countdownOverlays.prop('hidden', true).attr('hidden', 'hidden');
            }
            if (this.$countdownValues.length) {
                this.$countdownValues.text('3');
            }
        }

        startCaptureCountdown(captureType) {
            if (!this.isPhotoBoothMode || this.isCountingDown) {
                return;
            }

            const isVideoCapture = captureType === 'video';
            this.clearCaptureCountdown();
            this.isCountingDown = true;

            let remaining = 3;
            this.updateCountdownOverlay(remaining);

            const labelPattern = isVideoCapture
                ? (i18n.recordIn || 'Enregistrement dans %s...')
                : (i18n.captureIn || 'Capture dans %s...');
            this.showStatus(labelPattern.replace('%s', String(remaining)), '');

            this.captureCountdownInterval = window.setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    this.clearCaptureCountdown();
                    if (isVideoCapture) {
                        this.startRecording();
                    } else {
                        this.capturePhotoNow();
                    }
                    return;
                }

                this.updateCountdownOverlay(remaining);
                this.showStatus(labelPattern.replace('%s', String(remaining)), '');
            }, 1000);
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
            this.appendKioskAuth(formData);

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

            if (!content && this.photos.length === 0 && this.videos.length === 0) {
                this.showStatus('Veuillez ajouter du texte, des photos ou une vidéo.', 'error');
                return;
            }

            this.isSubmitting = true;
            this.$submitBtn.addClass('is-loading').prop('disabled', true);
            this.showStatus(i18n.uploading || 'Envoi en cours...', '');

            try {
                // Submit testimonial
                const formData = new FormData();
                formData.append('action', 'mj_front_testimonial_submit');
                formData.append('_wpnonce', config.nonce);
                formData.append('content', content);
                formData.append('photo_ids', JSON.stringify(this.photos.map(p => p.id)));
                formData.append('video_ids', JSON.stringify(this.videos.map(v => v.id)));
                this.appendKioskAuth(formData);
                
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
            this.richTextEditor.clear();
            this.photos = [];
            this.videos = [];
            this.$photosGrid.empty();
            this.$photoIdsInput.val('[]');
            this.removeVideo();
            this.removeLinkPreview();
        }

        appendKioskAuth(formData) {
            if (!formData || config.isLoggedIn) {
                return;
            }

            const kioskMemberId = parseInt(config.kioskMemberId, 10) || 0;
            const kioskSignature = config.kioskSignature || '';
            const kioskEnabled = !!config.kioskSubmissionEnabled;

            if (!kioskEnabled || kioskMemberId <= 0 || !kioskSignature) {
                return;
            }

            formData.append('kiosk_member_id', String(kioskMemberId));
            formData.append('kiosk_signature', kioskSignature);
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
                        featured_only: config.featuredOnly ? '1' : '',
                        base_url: config.baseUrl || window.location.href
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
            initTestimonials();
            initSliderAutoplay();
        }

        createCard(t) {
            const baseUrl = (typeof config.baseUrl === 'string' && config.baseUrl) ? config.baseUrl : window.location.href;
            const postUrl = t.postUrl || this.buildPostUrl(baseUrl, t.id);
            const shareUrl = t.shareUrl || this.buildShareUrl(t.id, postUrl);
            const status = t.status || 'approved';
            const isFeatured = !!t.featured;
            const canManage = !!t.canManage;
            const canToggleFeatured = !!t.canToggleFeatured;

            // Avatar
            const avatarInner = t.memberAvatarUrl
                ? `<img src="${this.escapeHtml(t.memberAvatarUrl)}" alt="${this.escapeHtml(t.memberName)}" class="mj-feed-post__avatar-img">`
                : `<span class="mj-feed-post__avatar-initial">${this.escapeHtml(t.memberInitial || '?')}</span>`;

            // Date
            const dateHtml = t.createdAgo
                ? `<span class="mj-feed-post__date">Il y a ${this.escapeHtml(t.createdAgo)} · 🌍</span>`
                : '';

            // Content
            const rawContent = t.rawContent !== undefined ? t.rawContent : this.stripHtml(t.contentHtml || t.content || '');
            const renderedContent = t.contentHtml || t.content || '';
            const contentHtml = renderedContent
                ? `<div class="mj-feed-post__content" data-raw-content="${this.escapeHtml(rawContent)}">${renderedContent}</div>`
                : '';

            // Mentioned members chips
            let memberMentionsHtml = '';
            if (t.mentionedMembers && t.mentionedMembers.length > 0) {
                memberMentionsHtml = '<div class="mj-feed-post__member-mentions">';
                t.mentionedMembers.forEach(m => {
                    const avatarInner = m.avatarUrl
                        ? `<img src="${this.escapeHtml(m.avatarUrl)}" alt="" class="mj-feed-post__member-mention-avatar" loading="lazy">`
                        : `<span class="mj-feed-post__member-mention-initial">${this.escapeHtml(m.initial || '?')}</span>`;
                    memberMentionsHtml += `<div class="mj-feed-post__member-mention-chip">${avatarInner}<span class="mj-feed-post__member-mention-name">${this.escapeHtml(m.name)}</span></div>`;
                });
                memberMentionsHtml += '</div>';
            }

            // Media slider (photos + video)
            const slides = [];
            if (t.photos && t.photos.length > 0) {
                t.photos.forEach(p => slides.push({ type: 'photo', url: p.url, full: p.full }));
            }
            if (t.videos && t.videos.length > 0) {
                t.videos.forEach(v => slides.push({ type: 'video', url: v.url, poster: v.poster || '' }));
            }
            const sliderHtml = buildSliderHtml(slides, t.id);

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

            // Share picker
            const fbSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
            const waSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
            const igSvg = '<svg width="22" height="22" viewBox="0 0 24 24"><defs><linearGradient id="ig-grad" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#f09433"/><stop offset="25%" style="stop-color:#e6683c"/><stop offset="50%" style="stop-color:#dc2743"/><stop offset="75%" style="stop-color:#cc2366"/><stop offset="100%" style="stop-color:#bc1888"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="url(#ig-grad)"/><path d="M12 7a5 5 0 100 10A5 5 0 0012 7zm0 8a3 3 0 110-6 3 3 0 010 6zm5-9a1.2 1.2 0 100 2.4A1.2 1.2 0 0017 6z" fill="white"/></svg>';
            const ttSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="#000000"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.48v-7.1a8.16 8.16 0 005.58 2.18v-3.45a4.85 4.85 0 01-1.59-.27 4.83 4.83 0 01-1.41-.82V6.69h3z"/></svg>';
            const cpSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>';
            const sharePickerHtml = `<div class="mj-feed-post__share-picker">
                <a href="#" class="mj-feed-post__share-option" data-share="whatsapp" title="WhatsApp">${waSvg}</a>
                <a href="#" class="mj-feed-post__share-option" data-share="facebook" title="Facebook">${fbSvg}</a>
                <a href="#" class="mj-feed-post__share-option" data-share="instagram" title="Instagram">${igSvg}</a>
                <a href="#" class="mj-feed-post__share-option" data-share="tiktok" title="TikTok">${ttSvg}</a>
                <button type="button" class="mj-feed-post__share-option" data-share="copy" title="${this.escapeHtml(i18n.copyLink || 'Copier le lien')}">${cpSvg}</button>
            </div>`;

            // Pery Social publish button (animators only)
            const publishFbHtml = config.isAnimator
                ? `<button type="button" class="mj-feed-post__action mj-feed-post__action--publish-social" data-action="publish-social" title="${this.escapeHtml(i18n.publishSocial || 'Pery Social')}">
                    <span class="mj-feed-post__action-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg></span>
                    <span class="mj-feed-post__action-label">${this.escapeHtml(i18n.publishSocial || 'Pery Social')}</span>
                   </button>`
                : '';

            const featuredBadgeHtml = isFeatured
                ? '<span class="mj-feed-post__featured-badge" title="Mis en avant">&#11088;</span>'
                : '';
            const toggleFeaturedHtml = canToggleFeatured
                ? `<button type="button" class="mj-feed-post__owner-action mj-feed-post__owner-action--featured" data-action="toggle-featured">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="${isFeatured ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span>${this.escapeHtml(isFeatured ? 'Retirer la mise en avant' : 'Mettre en avant')}</span>
                </button>`
                : '';
            const ownerMenuHtml = canManage
                ? `${featuredBadgeHtml}
                <div class="mj-feed-post__owner-menu">
                    <button type="button" class="mj-feed-post__owner-menu-toggle" data-action="toggle-owner-menu" aria-label="Options du temoignage">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    </button>
                    <div class="mj-feed-post__owner-dropdown" style="display:none;">
                        <button type="button" class="mj-feed-post__owner-action" data-action="edit-testimonial">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span>Modifier</span>
                        </button>
                        ${toggleFeaturedHtml}
                        <button type="button" class="mj-feed-post__owner-action mj-feed-post__owner-action--danger" data-action="delete-testimonial">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <span>Supprimer</span>
                        </button>
                    </div>
                </div>`
                : '';

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
                <article class="mj-feed-post-wrapper${status === 'pending' ? ' mj-feed-post-wrapper--pending' : ''}" data-post-id="${t.id}" data-post-url="${this.escapeHtml(postUrl)}" data-share-url="${this.escapeHtml(shareUrl)}" data-post-status="${this.escapeHtml(status)}">
                    <div class="mj-feed-post${status === 'pending' ? ' mj-feed-post--pending' : ''}${isFeatured ? ' mj-feed-post--featured' : ''}" data-id="${t.id}" data-featured="${isFeatured ? '1' : '0'}" data-member-id="${t.memberId || ''}" data-created-at="${this.escapeHtml(t.createdAt || '')}" data-photos="${encodeURIComponent(JSON.stringify(t.photos || []))}" data-videos="${encodeURIComponent(JSON.stringify(t.videos || []))}">
                        <div class="mj-feed-post__header">
                            <div class="mj-feed-post__avatar">${avatarInner}</div>
                            <div class="mj-feed-post__meta">
                                <span class="mj-feed-post__author">${this.escapeHtml(t.memberName)}</span>
                                ${dateHtml}
                            </div>
                            ${ownerMenuHtml}
                        </div>
                        ${contentHtml}
                        ${memberMentionsHtml}
                        ${sliderHtml}
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
                                ${sharePickerHtml}
                            </div>
                            ${publishFbHtml}
                        </div>
                        <div class="mj-feed-post__comments" style="display: none;">
                            <div class="mj-feed-post__comments-list"></div>
                            ${commentFormHtml}
                        </div>
                    </div>
                </article>`;
        }

        formatContent(content) {
            return content;
        }

        stripHtml(content) {
            if (!content) return '';
            const div = document.createElement('div');
            div.innerHTML = content;
            return (div.textContent || '').trim();
        }

        buildPostUrl(baseUrl, postId) {
            if (config.cleanUrlsActive) {
                return baseUrl.replace(/\/?(\?.*)?$/, '/') + String(postId) + '/';
            }
            try {
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('post', String(postId));
                return url.toString();
            } catch (err) {
                const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
                return baseUrl + separator + 'post=' + encodeURIComponent(String(postId));
            }
        }

        buildShareUrl(postId, postUrl) {
            const bridgeBase = (typeof config.shareBridgeBaseUrl === 'string' && config.shareBridgeBaseUrl)
                ? config.shareBridgeBaseUrl
                : window.location.origin + '/';

            try {
                const bridgeUrl = new URL(bridgeBase, window.location.origin);
                bridgeUrl.searchParams.set('mj_testimonial_share', String(postId));
                bridgeUrl.searchParams.set('target', postUrl);
                return bridgeUrl.toString();
            } catch (err) {
                const separator = bridgeBase.indexOf('?') === -1 ? '?' : '&';
                return bridgeBase + separator + 'mj_testimonial_share=' + encodeURIComponent(String(postId)) + '&target=' + encodeURIComponent(postUrl);
            }
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

    function parseMediaAttribute($post, attribute) {
        const value = $post.attr(attribute);
        if (!value) return [];

        try {
            return JSON.parse(value) || [];
        } catch (error) {
            try {
                return JSON.parse(decodeURIComponent(value)) || [];
            } catch (decodedError) {
                return [];
            }
        }
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
    // MEDIA SLIDER HELPERS
    // =====================================================

    function buildSliderHtml(slides, postId) {
        if (!slides || slides.length === 0) return '';
        const total = slides.length;
        const prevSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
        const nextSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';

        let html = `<div class="mj-feed-post__slider" data-index="0" data-total="${total}">`;
        html += '<div class="mj-feed-post__slider-track">';
        slides.forEach(slide => {
            html += '<div class="mj-feed-post__slide">';
            if (slide.type === 'photo') {
                html += `<a href="${escapeHtml(slide.full)}" class="mj-feed-post__slide-link" data-lightbox="post-${postId}">`;
                html += `<img src="${escapeHtml(slide.url)}" alt="" loading="lazy">`;
                html += '</a>';
            } else {
                const poster = slide.poster ? ` poster="${escapeHtml(slide.poster)}"` : '';
                html += `<video controls playsinline${poster}><source src="${escapeHtml(slide.url)}" type="video/mp4"></video>`;
            }
            html += '</div>';
        });
        html += '</div>';

        if (total > 1) {
            html += `<button type="button" class="mj-feed-post__slider-btn mj-feed-post__slider-btn--prev" aria-label="Précédent" style="display:none;">${prevSvg}</button>`;
            html += `<button type="button" class="mj-feed-post__slider-btn mj-feed-post__slider-btn--next" aria-label="Suivant">${nextSvg}</button>`;
            html += '<div class="mj-feed-post__slider-dots">';
            for (let i = 0; i < total; i++) {
                html += `<span class="mj-feed-post__slider-dot${i === 0 ? ' is-active' : ''}" data-index="${i}"></span>`;
            }
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    function goToSlide($slider, index, total) {
        $slider.data('index', index);
        $slider.find('.mj-feed-post__slider-track').css('transform', `translateX(-${index * 100}%)`);
        $slider.find('.mj-feed-post__slider-dot').removeClass('is-active').eq(index).addClass('is-active');
        $slider.find('.mj-feed-post__slider-btn--prev').toggle(index > 0);
        $slider.find('.mj-feed-post__slider-btn--next').toggle(index < total - 1);
    }

    const SLIDER_AUTOPLAY_MS = 3000;

    function startSliderAutoplay($slider) {
        const total = parseInt($slider.data('total'), 10) || 1;
        if (total <= 1) return;
        stopSliderAutoplay($slider);
        const id = setInterval(() => {
            const cur = parseInt($slider.data('index'), 10) || 0;
            goToSlide($slider, (cur + 1) % total, total);
        }, SLIDER_AUTOPLAY_MS);
        $slider.data('autoplay-id', id);
    }

    function stopSliderAutoplay($slider) {
        const id = $slider.data('autoplay-id');
        if (id) { clearInterval(id); $slider.removeData('autoplay-id'); }
    }

    function initSliderAutoplay() {
        $('.mj-feed-post__slider').each(function() {
            const $slider = $(this);
            if ($slider.data('autoplay-init')) return;
            $slider.data('autoplay-init', true);
            startSliderAutoplay($slider);

            $slider.on('mouseenter.mjSlider', () => stopSliderAutoplay($slider));
            $slider.on('mouseleave.mjSlider', () => {
                if (!$slider.find('video').toArray().some(v => !v.paused)) startSliderAutoplay($slider);
            });
            $slider[0].addEventListener('touchstart', () => stopSliderAutoplay($slider), { passive: true });
            $slider[0].addEventListener('touchend',   () => {
                if (!$slider.find('video').toArray().some(v => !v.paused)) startSliderAutoplay($slider);
            }, { passive: true });

            $slider[0].addEventListener('play', (e) => {
                if (e.target.tagName === 'VIDEO') stopSliderAutoplay($slider);
            }, true);
            $slider[0].addEventListener('pause', (e) => {
                if (e.target.tagName === 'VIDEO' && e.target.ended) startSliderAutoplay($slider);
            }, true);
        });
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

        // Media slider navigation
        $(document).on('click.mjFeed', '.mj-feed-post__slider-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const $slider = $btn.closest('.mj-feed-post__slider');
            const total = parseInt($slider.data('total'), 10) || 1;
            let index = parseInt($slider.data('index'), 10) || 0;
            index = $btn.hasClass('mj-feed-post__slider-btn--prev')
                ? Math.max(0, index - 1)
                : Math.min(total - 1, index + 1);
            goToSlide($slider, index, total);
            // Reset autoplay timer after manual nav
            stopSliderAutoplay($slider);
            startSliderAutoplay($slider);
        });

        $(document).on('click.mjFeed', '.mj-feed-post__slider-dot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $dot = $(this);
            const $slider = $dot.closest('.mj-feed-post__slider');
            const total = parseInt($slider.data('total'), 10) || 1;
            const index = parseInt($dot.data('index'), 10) || 0;
            goToSlide($slider, index, total);
            // Reset autoplay timer after manual nav
            stopSliderAutoplay($slider);
            startSliderAutoplay($slider);
        });

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

            // Animator-only: read current member and date from post data attributes
            let editMemberId = parseInt($post.attr('data-member-id'), 10) || 0;
            let editMemberName = $post.find('.mj-feed-post__author').first().text().trim();
            const currentCreatedAt = $post.attr('data-created-at') || '';

            // Get current media from data attributes
            let editPhotos = [];
            let editVideos = [];
            // Only true once the user actually adds/removes media in this edit session,
            // so we never send photo_ids/video_ids (and wipe existing media) just because
            // the preview failed to load them.
            let mediaTouched = false;

            // Hide original media slider during edit
            const $origSlider = $wrapper.find('.mj-feed-post__slider');

            try {
                editPhotos = parseMediaAttribute($post, 'data-photos');
            } catch(e) {}

            try {
                editVideos = parseMediaAttribute($post, 'data-videos');
            } catch(e) {}

            if (!editPhotos.length && !editVideos.length && $origSlider.find('img, video').length) {
                console.warn('[Testimonials] Le formulaire d\'édition n\'a pas pu charger les médias existants (data-photos/data-videos manquants). Les médias existants ne seront pas modifiés tant qu\'aucune action n\'est faite dessus.');
            }

            $origSlider.hide();

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

                // Videos grid
                editVideos.forEach(function(v) {
                    html += '<div class="mj-feed-post__edit-media-item mj-feed-post__edit-video-item" data-video-id="' + v.id + '">';
                    html += '<video src="' + escapeHtml(v.url) + '" playsinline muted></video>';
                    html += '<button type="button" class="mj-feed-post__edit-media-remove" data-action="edit-remove-video" data-video-id="' + v.id + '" title="Supprimer">&times;</button>';
                    html += '</div>';
                });

                // Add media button (show if under limits)
                const canAddPhoto = editPhotos.length < (config.maxPhotos || 10);
                const canAddVideo = config.allowVideo;
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

            // Animator-only extra fields HTML
            let animatorFieldsHtml = '';
            if (config.isAnimator) {
                // Format datetime-local value from "Y-m-d H:i:s" → "Y-m-dTH:i"
                const dtLocal = currentCreatedAt ? currentCreatedAt.replace(' ', 'T').substring(0, 16) : '';
                animatorFieldsHtml =
                    '<div class="mj-feed-post__edit-animator-fields">' +
                        '<div class="mj-feed-post__edit-field">' +
                            '<label class="mj-feed-post__edit-label">Date de publication</label>' +
                            '<input type="datetime-local" class="mj-feed-post__edit-date" value="' + escapeHtml(dtLocal) + '">' +
                        '</div>' +
                        '<div class="mj-feed-post__edit-field">' +
                            '<label class="mj-feed-post__edit-label">Membre</label>' +
                            '<div class="mj-mention-wrap">' +
                                '<input type="text" class="mj-feed-post__edit-member-search" placeholder="Rechercher un membre..." value="' + escapeHtml(editMemberName) + '" autocomplete="off">' +
                                '<input type="hidden" class="mj-feed-post__edit-member-id" value="' + editMemberId + '">' +
                                '<div class="mj-mention-dropdown" style="display:none;"></div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }

            // Build edit form
            const $editForm = $('<div class="mj-feed-post__edit-form">' +
                '<textarea class="mj-feed-post__edit-textarea">' + $('<span>').text(rawContent).html() + '</textarea>' +
                buildMediaPreview() +
                animatorFieldsHtml +
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
            const $editTextarea = $content.find('.mj-feed-post__edit-textarea');
            const editEditorInstance = initTestimonialEditor($editTextarea);
            const editRichTextEditor = editEditorInstance.richTextEditor;
            editRichTextEditor.$editor.focus();
            const editMention = editEditorInstance.mentionAutocomplete;

            // Animator: member search autocomplete
            if (config.isAnimator) {
                const $memberSearch = $content.find('.mj-feed-post__edit-member-search');
                const $memberId = $content.find('.mj-feed-post__edit-member-id');
                const $memberDrop = $content.find('.mj-feed-post__edit-animator-fields .mj-mention-dropdown');
                let memberDebounce = null;

                $memberSearch.on('input', function() {
                    const q = $(this).val().trim();
                    $memberId.val(''); // invalidate until a result is selected
                    clearTimeout(memberDebounce);
                    if (q.length < 1) { $memberDrop.hide(); return; }
                    memberDebounce = setTimeout(function() {
                        $.post(config.ajaxUrl, {
                            action: 'mj_front_testimonial_search_members',
                            _wpnonce: config.nonce,
                            search: q
                        }, function(resp) {
                            if (!resp.success || !resp.data.members.length) {
                                $memberDrop.html('<div class="mj-mention-dropdown__empty">Aucun membre trouvé</div>').show();
                                return;
                            }
                            let html = '';
                            resp.data.members.forEach(function(m) {
                                html += '<div class="mj-mention-dropdown__item mj-mention-dropdown__item--member" data-id="' + m.id + '" data-name="' + escapeHtml(m.name) + '">';
                                html += '<span class="mj-mention-dropdown__item-title">' + escapeHtml(m.name) + '</span>';
                                html += '</div>';
                            });
                            $memberDrop.html(html).show();
                        }, 'json');
                    }, 250);
                });

                $memberDrop.on('click', '.mj-mention-dropdown__item', function() {
                    editMemberId = parseInt($(this).data('id'), 10);
                    editMemberName = $(this).data('name');
                    $memberSearch.val(editMemberName);
                    $memberId.val(editMemberId);
                    $memberDrop.hide();
                });

                $memberSearch.on('blur', function() {
                    setTimeout(function() { $memberDrop.hide(); }, 200);
                });
            }

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
                    mediaTouched = true;
                    refreshMediaPreview();
                });

                // Remove video
                $content.find('[data-action="edit-remove-video"]').off('click.editMedia').on('click.editMedia', function(ev) {
                    ev.preventDefault();
                    const vid = parseInt($(this).data('video-id'), 10);
                    editVideos = editVideos.filter(function(v) { return v.id !== vid; });
                    mediaTouched = true;
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
                        mediaTouched = true;
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
                        editVideos.push({ id: resp.data.id, url: resp.data.url });
                        mediaTouched = true;
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
                editMention.destroy();
                editRichTextEditor.destroy();
                $content.html(originalContentHtml);
                $origSlider.show();
            });

            // Save
            $content.find('[data-action="save-edit"]').on('click', function() {
                const newContent = $content.find('.mj-feed-post__edit-textarea').val().trim();
                const hasMedia = editPhotos.length > 0 || editVideos.length > 0;

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
                    content: newContent
                };

                // Only send photo_ids/video_ids if the user actually touched the media in
                // this session, otherwise omit them so existing media is left untouched server-side.
                if (mediaTouched) {
                    postData.photo_ids = JSON.stringify(editPhotos.map(function(p) { return p.id; }));
                    postData.video_ids = JSON.stringify(editVideos.map(function(v) { return v.id; }));
                }

                if (config.isAnimator) {
                    const dateVal = $content.find('.mj-feed-post__edit-date').val();
                    if (dateVal) postData.created_at = dateVal;
                    const newMemberId = parseInt($content.find('.mj-feed-post__edit-member-id').val(), 10);
                    if (newMemberId > 0) postData.new_member_id = newMemberId;
                }

                $.post(config.ajaxUrl, postData).done(function(response) {
                    if (response.success) {
                        // Update displayed content with linkified HTML
                        editMention.destroy();
                        editRichTextEditor.destroy();
                        $content.html(response.data.contentHtml || '');
                        $content.attr('data-raw-content', response.data.content || '');

                        // Refresh mentioned members section
                        $post.find('.mj-feed-post__member-mentions').remove();
                        if (response.data.mentionedMembersHtml) {
                            $content.after(response.data.mentionedMembersHtml);
                        }

                        // Replace media slider
                        $origSlider.remove();
                        if (response.data.sliderHtml) {
                            $post.find('.mj-feed-post__member-mentions, .mj-feed-post__content').last().after(response.data.sliderHtml);
                        }

                        // Update data attributes for future edits
                        $post.attr('data-photos', JSON.stringify(response.data.photos || []));
                        $post.attr('data-videos', JSON.stringify(response.data.videos || []));

                        // Animator: update card header if member/date changed
                        if (response.data.newMemberName) {
                            $post.find('.mj-feed-post__author').text(response.data.newMemberName);
                            $post.attr('data-member-id', response.data.newMemberId || '');
                            const $avatar = $post.find('.mj-feed-post__avatar');
                            if (response.data.newMemberAvatarUrl) {
                                $avatar.html('<img src="' + escapeHtml(response.data.newMemberAvatarUrl) + '" alt="" class="mj-feed-post__avatar-img">');
                            } else if (response.data.newMemberInitial) {
                                $avatar.html('<span class="mj-feed-post__avatar-initial">' + escapeHtml(response.data.newMemberInitial) + '</span>');
                            }
                        }
                        if (response.data.newCreatedAt) {
                            $post.attr('data-created-at', response.data.newCreatedAt);
                            if (response.data.newCreatedAgo) {
                                $post.find('.mj-feed-post__date').text('Il y a ' + response.data.newCreatedAgo + ' · 🌍');
                            }
                        }
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

        function plainTextWithLineBreaks(value) {
            const container = document.createElement('div');
            container.innerHTML = String(value || '');

            container.querySelectorAll('br').forEach(function(br) {
                br.replaceWith('\n');
            });

            container.querySelectorAll('p,div,li,blockquote,pre,h1,h2,h3,h4,h5,h6').forEach(function(block) {
                block.prepend('\n');
                block.append('\n');
            });

            return (container.textContent || '')
                .replace(/\r\n?/g, '\n')
                .replace(/[ \t]+\n/g, '\n')
                .replace(/\n[ \t]+/g, '\n')
                .replace(/\n{3,}/g, '\n\n')
                .trim();
        }

        function renderSocialPublicationResults($form, results) {
            const $results = $form.find('.mj-publish-fb-form__results');
            if (!results || typeof results !== 'object' || !$results.length) return;

            const platformLabels = {
                facebook: 'Facebook',
                instagram: 'Instagram'
            };
            const rows = Object.keys(results).map(function(platform) {
                const result = results[platform] || {};
                const isSuccess = result.success === true;
                const icon = isSuccess ? '\u2714' : '\u2716';
                const message = result.message || (isSuccess ? 'Publication réussie.' : 'Erreur inconnue.');
                return '<div style="display:flex;gap:8px;align-items:flex-start;padding:6px 0;border-top:1px solid #ddd;">' +
                    '<span style="color:' + (isSuccess ? '#155724' : '#721c24') + ';font-weight:700;">' + icon + '</span>' +
                    '<div><strong>' + escapeHtml(platformLabels[platform] || platform) + '</strong><div>' + escapeHtml(message) + '</div></div>' +
                '</div>';
            }).join('');

            $results.html('<div style="font-weight:600;margin-bottom:4px;">Résultats de publication</div>' + rows).show();
        }

        // --- Publish on Pery Social (animators only) ---
        $(document).on('click.mjFeed', '[data-action="publish-social"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (!config.isAnimator) {
                alert('Acces refuse.');
                return;
            }

            const configuredPlatforms = Array.isArray(config.socialConfiguredPlatforms)
                ? config.socialConfiguredPlatforms.filter(function(platform) {
                    return platform === 'facebook' || platform === 'instagram';
                })
                : [];

            if (!configuredPlatforms.length) {
                alert('Aucune plateforme Pery Social nâ€™est configuree.');
                return;
            }

            const $wrapper = getWrapper(this);
            const $post = $wrapper.find('.mj-feed-post');
            const testimonialId = getTestimonialId($wrapper);
            const postUrl = $wrapper.data('post-url') || window.location.href;
            const toAbs = rel => rel.startsWith('http') ? rel : window.location.origin + (rel.startsWith('/') ? '' : '/') + rel;
            const absolutePostUrl = toAbs(postUrl);

            $(this).closest('.mj-feed-post__owner-dropdown').hide();

            const rawContent = $post.find('.mj-feed-post__content').data('raw-content') || '';
            const cleanContent = plainTextWithLineBreaks(rawContent)
                .replace(/@\{\d+\}/g, '')
                .replace(/#[a-z0-9][a-z0-9\-]*\b/gi, '')
                .replace(/[ \t]{2,}/g, ' ')
                .trim();
            const author = $post.find('.mj-feed-post__author').text().trim();
            const defaultMessage = author
                ? 'Temoignage de ' + author + ' - ' + cleanContent
                : cleanContent;

            if ($wrapper.find('.mj-pery-social-form').length) return;

            let photos = [];
            photos = parseMediaAttribute($post, 'data-photos');
            photos = Array.isArray(photos) ? photos.filter(function(photo) { return photo && parseInt(photo.id, 10) > 0; }) : [];

            const platformLabels = {
                facebook: 'Facebook',
                instagram: 'Instagram'
            };

            const platformHtml = configuredPlatforms.map(function(platform) {
                return '<label class="mj-pery-social-form__check-label">' +
                    '<input type="checkbox" class="mj-pery-social-form__platform" value="' + escapeHtml(platform) + '" checked> ' +
                    '<span>' + escapeHtml(platformLabels[platform] || platform) + '</span>' +
                '</label>';
            }).join('');

            const photosHtml = photos.length
                ? '<div class="mj-pery-social-form__section-label">Photos a publier</div>' +
                  '<div class="mj-pery-social-form__photos">' +
                    photos.map(function(photo) {
                        const url = photo.url || photo.full || photo.thumb || '';
                        return '<button type="button" class="mj-pery-social-form__photo-thumb is-selected" data-photo-id="' + parseInt(photo.id, 10) + '">' +
                            '<img src="' + escapeHtml(url) + '" alt="">' +
                            '<span class="mj-pery-social-form__photo-check" aria-hidden="true">&#10003;</span>' +
                        '</button>';
                    }).join('') +
                  '</div>'
                : '<div class="mj-pery-social-form__section-label">Photos</div><p style="margin:0;color:#6b7280;font-size:13px;">Aucune photo attachee a ce temoignage.</p>';

            const $form = $(
                '<div class="mj-publish-fb-form mj-pery-social-form" style="margin:10px 0;padding:12px;background:#f0f4ff;border:1px solid #b3c6ff;border-radius:8px;">' +
                    '<div style="font-weight:600;margin-bottom:8px;color:#1877F2;display:flex;align-items:center;gap:6px;">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1877F2" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>' +
                        'Pery Social' +
                    '</div>' +
                    '<div class="mj-pery-social-form__section-label">Plateformes</div>' +
                    '<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px;">' + platformHtml + '</div>' +
                    '<div class="mj-pery-social-form__section-label">Message</div>' +
                    '<textarea class="mj-publish-fb-form__message" rows="5" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px;resize:vertical;">' + escapeHtml(defaultMessage) + '</textarea>' +
                    photosHtml +
                    '<div class="mj-pery-social-form__section-label">Liens</div>' +
                    '<div style="display:grid;gap:6px;margin-top:4px;">' +
                        '<label class="mj-pery-social-form__check-label"><input type="checkbox" class="mj-pery-social-form__include-post-url" checked> <span>Ajouter le lien du temoignage</span></label>' +
                        '<label class="mj-pery-social-form__check-label"><input type="checkbox" class="mj-pery-social-form__include-event-urls" checked> <span>Ajouter les liens des evenements mentionnes</span></label>' +
                    '</div>' +
                    '<div class="mj-publish-fb-form__status" style="display:none;margin-top:8px;font-size:13px;"></div>' +
                    '<div class="mj-publish-fb-form__results" style="display:none;margin-top:8px;padding:8px;background:#fff;border:1px solid #ddd;border-radius:4px;font-size:13px;"></div>' +
                    '<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">' +
                        '<button type="button" class="mj-btn mj-btn--small mj-publish-fb-form__submit" style="background:#1877F2;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600;">Publier</button>' +
                        '<button type="button" class="mj-btn mj-btn--small mj-btn--ghost mj-publish-fb-form__cancel" style="padding:8px 16px;border-radius:6px;cursor:pointer;">Annuler</button>' +
                    '</div>' +
                '</div>'
            );

            $wrapper.find('.mj-feed-post__actions').after($form);
            $form.find('.mj-publish-fb-form__message').focus();

            $form.on('click', '.mj-pery-social-form__photo-thumb', function() {
                $(this).toggleClass('is-selected');
            });

            $form.find('.mj-publish-fb-form__cancel').on('click', function() {
                $form.remove();
            });

            $form.find('.mj-publish-fb-form__submit').on('click', function() {
                const message = $form.find('.mj-publish-fb-form__message').val().trim();
                if (!message) { alert('Le message ne peut pas etre vide.'); return; }

                const platforms = $form.find('.mj-pery-social-form__platform:checked').map(function() {
                    return $(this).val();
                }).get();

                if (!platforms.length) {
                    alert('Selectionnez au moins une plateforme.');
                    return;
                }

                const selectedPhotoIds = $form.find('.mj-pery-social-form__photo-thumb.is-selected').map(function() {
                    return parseInt($(this).data('photo-id'), 10) || 0;
                }).get().filter(function(id) {
                    return id > 0;
                });

                const $submitBtn = $(this);
                $submitBtn.prop('disabled', true).text('Publication...');
                const $status = $form.find('.mj-publish-fb-form__status');
                $status.hide();

                $.post(config.ajaxUrl, {
                    action: 'mj_front_testimonial_publish_social',
                    _wpnonce: config.nonce,
                    testimonial_id: testimonialId,
                    message: message,
                    platforms: platforms,
                    photo_ids: JSON.stringify(selectedPhotoIds),
                    include_post_url: $form.find('.mj-pery-social-form__include-post-url').is(':checked') ? '1' : '',
                    include_event_urls: $form.find('.mj-pery-social-form__include-event-urls').is(':checked') ? '1' : '',
                    post_url: absolutePostUrl,
                }).done(function(response) {
                    if (response.success) {
                        const isPartial = response.data && response.data.hasError;
                        $status.css({
                            color: isPartial ? '#856404' : '#155724',
                            background: isPartial ? '#fff3cd' : '#d4edda',
                            border: isPartial ? '1px solid #ffeeba' : '1px solid #c3e6cb',
                            padding: '6px 10px',
                            borderRadius: '4px'
                        })
                               .text('\u2714 ' + response.data.message)
                               .show();
                           renderSocialPublicationResults($form, response.data && response.data.results);
                        if (!isPartial) {
                            setTimeout(function() { $form.remove(); }, 3000);
                        }
                    } else {
                        const responseError = response.data && response.data.message ? response.data.message : response.data;
                        $status.css({ color: '#721c24', background: '#f8d7da', border: '1px solid #f5c6cb', padding: '6px 10px', borderRadius: '4px' })
                               .text('\u2716 ' + (typeof responseError === 'string' ? responseError : 'Erreur de publication.'))
                               .show();
                           renderSocialPublicationResults($form, response.data && response.data.results);
                        $submitBtn.prop('disabled', false).text('Publier');
                    }
                }).fail(function(jqXHR) {
                    let errMsg = 'Erreur reseau.';
                    let errorData = null;
                    try {
                        const parsed = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                        if (parsed && parsed.data) {
                            errorData = parsed.data;
                            errMsg = parsed.data.message || 'Erreur de publication.';
                        }
                    } catch(e) {}
                    $status.css({ color: '#721c24', background: '#f8d7da', border: '1px solid #f5c6cb', padding: '6px 10px', borderRadius: '4px' })
                           .text('\u2716 ' + errMsg)
                           .show();
                    renderSocialPublicationResults($form, errorData && errorData.results);
                    $submitBtn.prop('disabled', false).text('Publier');
                });
            });
        });
        /**
         * Build clean share text and absolute URLs from a post wrapper element.
         */
        function buildShareData($wrapper) {
            const $post = $wrapper.find('.mj-feed-post');
            const relativeUrl = $wrapper.data('post-url') || window.location.href;
            const relativeShareUrl = $wrapper.data('share-url') || relativeUrl;
            const toAbs = rel => rel.startsWith('http') ? rel : window.location.origin + (rel.startsWith('/') ? '' : '/') + rel;
            const postUrl = toAbs(relativeUrl);
            const shareTargetUrl = toAbs(relativeShareUrl);
            const rawContent = $post.find('.mj-feed-post__content').data('raw-content') || '';
            const cleanContent = rawContent
                .replace(/@\{\d+\}/g, '')
                .replace(/#[a-z0-9][a-z0-9\-]*\b/gi, '')
                .replace(/\s{2,}/g, ' ')
                .trim();
            const author = $post.find('.mj-feed-post__author').text().trim();
            const shareText = author
                ? 'Témoignage de ' + author + ' · MJ Pery\n' + cleanContent.substring(0, 200) + (cleanContent.length > 200 ? '...' : '')
                : cleanContent.substring(0, 220) + (cleanContent.length > 220 ? '...' : '');
            let photos = [];
            photos = parseMediaAttribute($post, 'data-photos');
            let videos = [];
            videos = parseMediaAttribute($post, 'data-videos');
            const mediaUrls = [
                ...photos.slice(0, 4).map(p => p.url || p.full).filter(Boolean),
                ...videos.slice(0, 1).map(v => v.poster).filter(Boolean),
            ];
            return { postUrl, shareTargetUrl, shareText, cleanContent, author, mediaUrls };
        }

        /**
         * Native Web Share API — fetches actual images as files so Instagram/WhatsApp/TikTok
         * receive the real photos instead of just a URL.
         */
        function nativeShare($wrapper) {
            const { postUrl, shareText, mediaUrls } = buildShareData($wrapper);
            const shareBase = { title: 'MJ Pery', text: shareText, url: postUrl };

            function doShare(extra) {
                return navigator.share(Object.assign({}, shareBase, extra)).catch(() => {});
            }

            if (!mediaUrls.length || !navigator.canShare) {
                return doShare({});
            }

            Promise.all(
                mediaUrls.map(url =>
                    fetch(url, { mode: 'cors' })
                        .then(r => r.ok ? r.blob() : null)
                        .then(blob => {
                            if (!blob) return null;
                            const ext = blob.type.includes('png') ? 'png' : 'jpg';
                            return new File([blob], 'photo.' + ext, { type: blob.type });
                        })
                        .catch(() => null)
                )
            ).then(files => {
                files = files.filter(Boolean);
                if (files.length && navigator.canShare({ files })) {
                    return doShare({ files });
                }
                return doShare({});
            }).catch(() => doShare({}));
        }

        // Share button — always show the picker
        $(document).on('click.mjFeed', '.mj-feed-post__action--share', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $picker = $(this).find('.mj-feed-post__share-picker');
            $('.mj-feed-post__share-picker').not($picker).removeClass('is-visible');
            $picker.toggleClass('is-visible');
        });

        // Close share picker on outside click (desktop only)
        $(document).on('click.mjFeed', function(e) {
            if (!$(e.target).closest('.mj-feed-post__action--share').length) {
                $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
            }
        });

        // Individual platform buttons in the desktop picker
        $(document).on('click.mjFeed', '.mj-feed-post__share-option', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const platform = $(this).data('share');
            const $wrapper = getWrapper(this);
            const { postUrl, shareTargetUrl, shareText } = buildShareData($wrapper);
            const encodedUrl = encodeURIComponent(postUrl);
            const encodedText = encodeURIComponent(shareText);

            let shareUrl = '';
            switch (platform) {
                case 'whatsapp':
                    shareUrl = 'https://api.whatsapp.com/send?text=' + encodedText + '%20' + encodedUrl;
                    break;
                case 'facebook':
                    shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareTargetUrl) + '&quote=' + encodedText;
                    break;
                case 'instagram':
                case 'tiktok':
                    $('.mj-feed-post__share-picker.is-visible').removeClass('is-visible');
                    if (navigator.share) {
                        nativeShare($wrapper);
                    } else {
                        const label = platform === 'instagram' ? 'story ou publication Instagram' : 'vidéo TikTok';
                        copyToClipboard(postUrl, 'Lien copié ! Collez-le dans votre ' + label + '.');
                    }
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

        // Testimonials lightbox with gallery navigation (left/right)
        const testimonialLightbox = {
            items: [],
            index: 0,
            $overlay: null,
            $image: null,
            $counter: null,
            init: function() {
                if (this.$overlay) return;

                this.$overlay = $(
                    '<div class="mj-testimonials-lightbox" aria-hidden="true" style="display:none;">' +
                        '<button type="button" class="mj-testimonials-lightbox__close" aria-label="Fermer">&times;</button>' +
                        '<button type="button" class="mj-testimonials-lightbox__nav mj-testimonials-lightbox__nav--prev" aria-label="Image précédente">&#8249;</button>' +
                        '<div class="mj-testimonials-lightbox__stage">' +
                            '<img class="mj-testimonials-lightbox__image" alt="">' +
                            '<div class="mj-testimonials-lightbox__counter" aria-live="polite"></div>' +
                        '</div>' +
                        '<button type="button" class="mj-testimonials-lightbox__nav mj-testimonials-lightbox__nav--next" aria-label="Image suivante">&#8250;</button>' +
                    '</div>'
                );

                this.$image = this.$overlay.find('.mj-testimonials-lightbox__image');
                this.$counter = this.$overlay.find('.mj-testimonials-lightbox__counter');
                $('body').append(this.$overlay);

                const self = this;
                this.$overlay.on('click', '.mj-testimonials-lightbox__close', function() {
                    self.close();
                });
                this.$overlay.on('click', '.mj-testimonials-lightbox__nav--prev', function() {
                    self.prev();
                });
                this.$overlay.on('click', '.mj-testimonials-lightbox__nav--next', function() {
                    self.next();
                });
                this.$overlay.on('click', function(e) {
                    if ($(e.target).is('.mj-testimonials-lightbox')) {
                        self.close();
                    }
                });

                $(document).on('keydown.mjTestimonialLightbox', function(e) {
                    if (!self.isOpen()) return;
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        self.close();
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        self.prev();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        self.next();
                    }
                });
            },
            isOpen: function() {
                return this.$overlay && this.$overlay.hasClass('is-open');
            },
            openFromLink: function($link) {
                const group = String($link.data('lightbox') || '');
                if (!group) {
                    return;
                }

                const $groupLinks = $('.mj-testimonials a[data-lightbox]').filter(function() {
                    return String($(this).data('lightbox') || '') === group;
                });

                if (!$groupLinks.length) {
                    return;
                }

                this.items = $groupLinks.map(function() {
                    const $a = $(this);
                    const $img = $a.find('img').first();
                    return {
                        href: $a.attr('href') || '',
                        alt: ($img.attr('alt') || '').trim()
                    };
                }).get().filter(function(item) {
                    return !!item.href;
                });

                const clickedHref = $link.attr('href') || '';
                let foundIndex = this.items.findIndex(function(item) {
                    return item.href === clickedHref;
                });
                if (foundIndex < 0) {
                    foundIndex = 0;
                }
                this.index = foundIndex;

                this.render();
                this.$overlay.css('display', 'flex').attr('aria-hidden', 'false').addClass('is-open');
                $('body').addClass('mj-testimonials-lightbox-open');
            },
            close: function() {
                if (!this.$overlay) return;
                this.$overlay.removeClass('is-open').attr('aria-hidden', 'true').hide();
                $('body').removeClass('mj-testimonials-lightbox-open');
            },
            render: function() {
                if (!this.items.length || !this.$image) return;
                const current = this.items[this.index];
                this.$image.attr('src', current.href);
                this.$image.attr('alt', current.alt || 'Photo du témoignage');
                if (this.$counter) {
                    this.$counter.text((this.index + 1) + ' / ' + this.items.length);
                }
            },
            prev: function() {
                if (!this.items.length) return;
                this.index = (this.index - 1 + this.items.length) % this.items.length;
                this.render();
            },
            next: function() {
                if (!this.items.length) return;
                this.index = (this.index + 1) % this.items.length;
                this.render();
            }
        };

        testimonialLightbox.init();

        $(document).on('click.mjFeed', '.mj-testimonials a[data-lightbox]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            testimonialLightbox.openFromLink($(this));
        });

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
        initSliderAutoplay();
    });

    // Re-init for Elementor preview
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-testimonials.default', function() {
                initTestimonials();
                initFeedEvents();
                initSliderAutoplay();
            });
        }
    });

})(jQuery);
