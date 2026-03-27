(function() {
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var globalConfig = window.mjMemberPhotoGrimlins || null;

    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function(callback) {
            if (typeof callback !== 'function') {
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, { once: true });
            } else {
                callback();
            }
        };

    var instances = new WeakMap();

    function noop() {}

    function parseConfig(root) {
        if (!root) {
            return {};
        }
        var raw = root.getAttribute('data-config');
        if (!raw) {
            return {};
        }
        try {
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        } catch (error) {
            console.error('[MJ Member] Photo Grimlins configuration invalide', error);
        }
        return {};
    }

    function resolveAccessScope(config) {
        if (config && typeof config.accessScope === 'string') {
            var lowered = config.accessScope.toLowerCase();
            if (lowered === 'public' || lowered === 'members') {
                return lowered;
            }
        }
        if (config && config.membersOnly === false) {
            return 'public';
        }
        return 'members';
    }

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) {
            return '0 B';
        }
        var units = ['B', 'KB', 'MB', 'GB'];
        var exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        var size = bytes / Math.pow(1024, exponent);
        return size.toFixed(exponent === 0 ? 0 : 1) + ' ' + units[exponent];
    }

    function PhotoGrimlins(root) {
        this.root = root;
        this.config = parseConfig(root);
        this.accessScope = resolveAccessScope(this.config);
        this.accessNonce = this.config && typeof this.config.accessNonce === 'string' ? this.config.accessNonce : '';
        this.file = null;
        this.previewUrl = '';
        this.controller = null;
        this.isSubmitting = false;

        this.dom = {
            dropzone: root.querySelector('[data-photo-grimlins="dropzone"]'),
            fileInput: root.querySelector('[data-photo-grimlins="file"]'),
            cameraInput: root.querySelector('[data-photo-grimlins="camera-input"]'),
            form: root.querySelector('[data-photo-grimlins="form"]'),
            previewImg: root.querySelector('[data-photo-grimlins="preview"]'),
            previewBox: root.querySelector('[data-photo-grimlins="preview-box"]'),
            previewStage: root.querySelector('[data-photo-grimlins="preview-stage"]'),
            previewLoader: root.querySelector('[data-photo-grimlins="preview-loader"]'),
            resultImg: root.querySelector('[data-photo-grimlins="result"]'),
            resultBox: root.querySelector('[data-photo-grimlins="result-box"]'),
            downloadLink: root.querySelector('[data-photo-grimlins="download"]'),
            applyAvatarBtn: root.querySelector('[data-photo-grimlins="apply-avatar"]'),
            ctaRegisterBtn: root.querySelector('[data-photo-grimlins="cta-register"]'),
            status: root.querySelector('[data-photo-grimlins="status"]'),
            resetBtn: root.querySelector('[data-photo-grimlins="reset"]'),
            submitBtn: root.querySelector('[data-photo-grimlins="submit"]'),
            cameraBtn: root.querySelector('[data-photo-grimlins="camera"]'),
            cameraModal: root.querySelector('[data-photo-grimlins="camera-modal"]'),
            cameraVideo: root.querySelector('[data-photo-grimlins="camera-video"]'),
            cameraCaptureBtn: root.querySelector('[data-photo-grimlins="camera-capture"]'),
            cameraCancelBtn: root.querySelector('[data-photo-grimlins="camera-cancel"]'),
            openYoungSearchBtn: root.querySelector('[data-photo-grimlins="open-young-search"]'),
            youngSearchModal: root.querySelector('[data-photo-grimlins="young-search-modal"]'),
            youngSearchCloseBtn: root.querySelector('[data-photo-grimlins="close-young-search"]'),
            youngSearchForm: root.querySelector('[data-photo-grimlins="young-search-form"]'),
            youngSearchInput: root.querySelector('[data-photo-grimlins="young-search-input"]'),
            youngSearchSubmit: root.querySelector('[data-photo-grimlins="young-search-submit"]'),
            youngSearchStatus: root.querySelector('[data-photo-grimlins="young-search-status"]'),
            youngSearchResults: root.querySelector('[data-photo-grimlins="young-search-results"]'),
            historySection: root.querySelector('[data-photo-grimlins="history"]'),
            historyList: root.querySelector('[data-photo-grimlins="history-list"]'),
            historyEmpty: root.querySelector('[data-photo-grimlins="history-empty"]'),
            historyLimit: root.querySelector('[data-photo-grimlins="history-limit"]'),
            historyTitle: root.querySelector('[data-photo-grimlins="history-title"]'),
            mosaic: root.querySelector('.mj-photo-grimlins__mosaic')
        };

        this.cameraStream = null;
        this.canStreamCamera = this.hasMediaDevices();
        this.cameraSupported = this.isCameraSupported();
        this.boundEscHandler = null;
        this.lastResult = null;
        this.isApplyingAvatar = false;
        this.isPreviewLoading = false;
        this.history = [];
        this.historyByMember = globalConfig && globalConfig.historiesByMember && typeof globalConfig.historiesByMember === 'object'
            ? Object.assign({}, globalConfig.historiesByMember)
            : {};
        this.activeTargetMemberId = this.config && this.config.targetMemberId !== undefined
            ? (parseInt(this.config.targetMemberId, 10) || 0)
            : 0;
        this.historyLimit = 0;
        this.historyCount = 0;
        this.limitReached = false;
        this.isYoungSearchLoading = false;
        this.initialHistory = {
            items: globalConfig && Array.isArray(globalConfig.history) ? globalConfig.history.slice() : [],
            count: globalConfig && typeof globalConfig.historyCount === 'number' ? globalConfig.historyCount : null,
            limit: globalConfig && typeof globalConfig.memberLimit === 'number' ? globalConfig.memberLimit : null,
            reached: globalConfig && typeof globalConfig.limitReached === 'boolean' ? globalConfig.limitReached : null
        };

        this.init();
    }

    function createFileFromBlob(input, fallbackName) {
        if (!input) {
            return null;
        }
        if (input instanceof File) {
            return input;
        }
        var name = fallbackName || ('capture-' + Date.now() + '.jpg');
        if (typeof File === 'function') {
            try {
                return new File([input], name, {
                    type: input.type || 'image/jpeg',
                    lastModified: Date.now()
                });
            } catch (error) {
                // Ignore and fallback to assigning name directly on the blob.
            }
        }
        if (input instanceof Blob) {
            try {
                input = input.slice(0, input.size, input.type || 'image/jpeg');
            } catch (error) {
                // slice may throw in older browsers; ignore.
            }
            input.name = name;
            input.lastModified = Date.now();
            return input;
        }
        return null;
    }

    function dataUrlToBlob(dataUrl) {
        if (typeof dataUrl !== 'string') {
            return null;
        }
        var parts = dataUrl.split(',');
        if (parts.length < 2) {
            return null;
        }
        var header = parts[0];
        var base64 = parts[1];
        var mimeMatch = header.match(/data:(.*?);base64/);
        var mimeType = mimeMatch && mimeMatch[1] ? mimeMatch[1] : 'image/jpeg';
        try {
            var binary = atob(base64);
            var length = binary.length;
            var buffer = new Uint8Array(length);
            for (var i = 0; i < length; i += 1) {
                buffer[i] = binary.charCodeAt(i);
            }
            return new Blob([buffer], { type: mimeType });
        } catch (error) {
            return null;
        }
    }

    PhotoGrimlins.prototype.init = function() {
        if (!globalConfig || !globalConfig.ajaxUrl) {
            this.updateStatus('Configuration AJAX manquante.', 'error');
            return;
        }

        if (this.dom.cameraBtn) {
            if (!this.cameraSupported) {
                this.dom.cameraBtn.disabled = true;
                this.dom.cameraBtn.classList.add('is-hidden');
            } else {
                this.dom.cameraBtn.disabled = false;
                this.dom.cameraBtn.classList.remove('is-hidden');
            }
        }

        if (this.dom.cameraModal) {
            this.hideCameraModal(true);
        }

        this.bindEvents();
        if (this.initialHistory) {
            var initialItems = this.getHistoryForMember(this.activeTargetMemberId);
            if (!Array.isArray(initialItems)) {
                initialItems = this.initialHistory.items;
            }

            this.setHistory(initialItems, {
                historyCount: this.initialHistory.count,
                memberLimit: this.initialHistory.limit,
                limitReached: this.initialHistory.reached
            });
            this.initialHistory = null;
        } else {
            this.renderHistory();
            this.updateLimitState();
        }
        this.refreshUi();

        if (this.config.isPreview && this.dom.previewBox && this.dom.previewBox.dataset.placeholder) {
            this.dom.previewBox.hidden = false;
        }
    };

    PhotoGrimlins.prototype.hasMediaDevices = function() {
        return typeof navigator !== 'undefined'
            && navigator.mediaDevices
            && typeof navigator.mediaDevices.getUserMedia === 'function';
    };

    PhotoGrimlins.prototype.isCameraSupported = function() {
        if (!globalConfig || globalConfig.cameraEnabled === false) {
            return false;
        }

        if (this.canStreamCamera) {
            return true;
        }

        return !!(this.dom && this.dom.cameraInput && typeof this.dom.cameraInput.click === 'function');
    };

    PhotoGrimlins.prototype.bindEvents = function() {
        var self = this;

        if (this.dom.fileInput) {
            this.dom.fileInput.addEventListener('change', function(event) {
                var target = event.currentTarget;
                var selected = target && target.files ? target.files[0] : null;
                if (selected) {
                    self.setFile(selected);
                }
            });
        }

        if (this.dom.cameraInput) {
            this.dom.cameraInput.addEventListener('change', function(event) {
                var target = event.currentTarget;
                var selected = target && target.files ? target.files[0] : null;
                if (selected) {
                    self.setFile(selected);
                }
            });
        }

        if (this.dom.dropzone) {
            ['dragenter', 'dragover'].forEach(function(type) {
                self.dom.dropzone.addEventListener(type, function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.dom.dropzone.classList.add('mj-photo-grimlins__dropzone--hover');
                });
            });

            ['dragleave', 'drop'].forEach(function(type) {
                self.dom.dropzone.addEventListener(type, function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.dom.dropzone.classList.remove('mj-photo-grimlins__dropzone--hover');
                });
            });

            this.dom.dropzone.addEventListener('drop', function(event) {
                var dt = event.dataTransfer;
                if (!dt || !dt.files || dt.files.length === 0) {
                    return;
                }
                var candidate = dt.files[0];
                if (candidate) {
                    self.setFile(candidate);
                }
            });
        }

        if (this.dom.form) {
            this.dom.form.addEventListener('submit', function(event) {
                event.preventDefault();
                self.submit();
            });
        }

        if (this.dom.resetBtn) {
            this.dom.resetBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.reset();
            });
        }

        if (this.dom.cameraBtn) {
            this.dom.cameraBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.openCamera();
            });
        }

        if (this.dom.cameraCaptureBtn) {
            this.dom.cameraCaptureBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.captureFromCamera();
            });
        }

        if (this.dom.cameraCancelBtn) {
            this.dom.cameraCancelBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.hideCameraModal();
            });
        }

        if (this.dom.cameraModal) {
            this.dom.cameraModal.addEventListener('click', function(event) {
                if (event.target === self.dom.cameraModal) {
                    event.preventDefault();
                    self.hideCameraModal();
                }
            });
        }

        if (this.dom.openYoungSearchBtn) {
            this.dom.openYoungSearchBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.openYoungSearchModal();
            });
        }

        if (this.dom.youngSearchCloseBtn) {
            this.dom.youngSearchCloseBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.closeYoungSearchModal();
            });
        }

        if (this.dom.youngSearchModal) {
            this.dom.youngSearchModal.addEventListener('click', function(event) {
                if (event.target === self.dom.youngSearchModal) {
                    event.preventDefault();
                    self.closeYoungSearchModal();
                }
            });
        }

        if (this.dom.youngSearchForm) {
            this.dom.youngSearchForm.addEventListener('submit', function(event) {
                event.preventDefault();
                var query = self.dom.youngSearchInput ? self.dom.youngSearchInput.value : '';
                self.performYoungSearch(query);
            });
        }

        if (this.dom.youngSearchResults) {
            this.dom.youngSearchResults.addEventListener('click', function(event) {
                var target = event.target;
                if (!target || typeof target.closest !== 'function') {
                    return;
                }
                var pickBtn = target.closest('[data-photo-grimlins-select-young]');
                if (!pickBtn) {
                    return;
                }
                event.preventDefault();
                var pickedId = parseInt(pickBtn.getAttribute('data-member-id'), 10);
                var pickedLabel = pickBtn.getAttribute('data-member-label') || '';
                if (!pickedId || Number.isNaN(pickedId)) {
                    return;
                }
                self.selectTargetMemberFromSearch(pickedId, pickedLabel);
            });
        }

        this.root.addEventListener('click', function(event) {
            var target = event.target;
            if (!target) {
                return;
            }
            if (target.matches('[data-photo-grimlins="choose"]') && self.dom.fileInput) {
                event.preventDefault();
                self.dom.fileInput.click();
            }
        });

        if (this.dom.applyAvatarBtn) {
            this.dom.applyAvatarBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.applyAvatar();
            });
        }

        if (this.dom.ctaRegisterBtn) {
            this.dom.ctaRegisterBtn.addEventListener('click', function(event) {
                event.preventDefault();
                self.redirectToRegistration();
            });
        }

        var canToggleFullscreenOnDblClick = !(this.config && this.config.fullscreenDblClick === false);
        if (this.dom.mosaic && canToggleFullscreenOnDblClick) {
            this.dom.mosaic.addEventListener('dblclick', function(event) {
                event.preventDefault();
                self.toggleFullscreen();
            });
        }

        if (this.dom.historyList) {
            this.dom.historyList.addEventListener('click', function(event) {
                var target = event.target;
                if (!target || typeof target.closest !== 'function') {
                    return;
                }

                var deleteTarget = target.closest('[data-photo-grimlins-history-delete]');
                if (deleteTarget) {
                    event.preventDefault();
                    if (deleteTarget.disabled || self.isSubmitting || self.isApplyingAvatar) {
                        return;
                    }
                    var deleteId = parseInt(deleteTarget.getAttribute('data-attachment-id'), 10);
                    if (!deleteId || Number.isNaN(deleteId)) {
                        return;
                    }
                    var confirmMessage = globalConfig && globalConfig.i18n && globalConfig.i18n.historyDeleteConfirm
                        ? globalConfig.i18n.historyDeleteConfirm
                        : 'Supprimer définitivement cet avatar Grimlins ?';
                    if (!window.confirm(confirmMessage)) {
                        return;
                    }
                    self.deleteHistoryAvatar(deleteId);
                    return;
                }

                var applyTarget = target.closest('[data-photo-grimlins-history-apply]');
                if (!applyTarget) {
                    return;
                }
                event.preventDefault();
                var attachmentId = parseInt(applyTarget.getAttribute('data-attachment-id'), 10);
                if (!attachmentId || Number.isNaN(attachmentId)) {
                    return;
                }
                self.applyAvatar(attachmentId);
            });
        }
    };

    PhotoGrimlins.prototype.toggleFullscreen = function() {
        var el = this.root;
        var isFullscreen = document.fullscreenElement || document.webkitFullscreenElement;
        if (isFullscreen) {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        } else {
            if (el.requestFullscreen) {
                el.requestFullscreen();
            } else if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
            }
        }
    };

    PhotoGrimlins.prototype.openCamera = function() {
        if (this.limitReached && !this.config.isPreview) {
            var limitMessage = globalConfig && globalConfig.i18n && globalConfig.i18n.historyLimitReached
                ? globalConfig.i18n.historyLimitReached
                : 'Limite atteinte.';
            this.updateStatus(limitMessage, 'error');
            return;
        }

        if (!this.cameraSupported) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraNotSupported : 'Caméra non disponible sur ce navigateur.', 'error');
            return;
        }

        if (this.canStreamCamera && this.dom.cameraModal && this.dom.cameraVideo) {
            this.stopCameraPreview();
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraStarting : 'Initialisation de la caméra…', 'info');
            if (this.dom.cameraCaptureBtn) {
                this.dom.cameraCaptureBtn.disabled = true;
            }
            var previewPromise = this.startCameraPreview();
            if (!previewPromise || typeof previewPromise.then !== 'function') {
                this.triggerCameraInput();
            } else if (typeof previewPromise.catch === 'function') {
                previewPromise.catch(noop);
            }
            return;
        }

        this.triggerCameraInput();
    };

    PhotoGrimlins.prototype.setFile = function(file) {
        if (this.limitReached && !this.config.isPreview) {
            var limitMessage = globalConfig && globalConfig.i18n && globalConfig.i18n.historyLimitReached
                ? globalConfig.i18n.historyLimitReached
                : 'Limite atteinte.';
            this.updateStatus(limitMessage, 'error');
            return;
        }

        var normalized = createFileFromBlob(file, file && file.name ? file.name : ('capture-' + Date.now() + '.jpg'));
        if (!normalized) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraError : 'Impossible de traiter la photo.', 'error');
            return;
        }

        this.hideCameraModal();

        this.file = normalized;
        if (!this.file.name) {
            this.file.name = 'capture-' + Date.now() + '.jpg';
        }
        if (this.dom.cameraInput) {
            this.dom.cameraInput.value = '';
        }
        this.renderPreview();
        this.updateStatus(formatBytes(this.file.size), 'info');
        this.refreshUi();
    };

    PhotoGrimlins.prototype.renderPreview = function() {
        this.setPreviewLoading(false);
        if (!this.dom.previewImg || !this.dom.previewBox) {
            return;
        }
        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
        }
        if (!this.file) {
            this.dom.previewBox.hidden = true;
            this.dom.previewImg.removeAttribute('src');
            return;
        }

        this.previewUrl = URL.createObjectURL(this.file);
        this.dom.previewImg.src = this.previewUrl;
        this.dom.previewImg.alt = this.file.name;
        this.dom.previewBox.hidden = false;
    };

    PhotoGrimlins.prototype.setPreviewLoading = function(active) {
        if (!this.dom.previewStage || !this.dom.previewStage.isConnected) {
            this.dom.previewStage = this.root ? this.root.querySelector('[data-photo-grimlins="preview-stage"]') : null;
        }
        if (!this.dom.previewLoader || !this.dom.previewLoader.isConnected) {
            this.dom.previewLoader = this.root ? this.root.querySelector('[data-photo-grimlins="preview-loader"]') : null;
        }

        if (!this.dom.previewStage || !this.dom.previewLoader) {
            this.isPreviewLoading = false;
            return;
        }

        var shouldShow = !!active;
        this.isPreviewLoading = shouldShow;
        this.dom.previewStage.classList.toggle('is-loading', shouldShow);
        if (shouldShow) {
            this.dom.previewLoader.hidden = false;
            this.dom.previewLoader.removeAttribute('hidden');
            if (this.dom.previewBox) {
                this.dom.previewBox.hidden = false;
            }
        } else {
            this.dom.previewLoader.hidden = true;
            this.dom.previewLoader.setAttribute('hidden', '');
        }
    };

    PhotoGrimlins.prototype.updateStatus = function(message, type) {
        if (!this.dom.status) {
            return;
        }
        this.dom.status.textContent = message || '';
        this.dom.status.setAttribute('data-status-type', type || '');
    };

    PhotoGrimlins.prototype.updateApplyAvatarButton = function() {
        var button = this.dom.applyAvatarBtn;
        if (!button) {
            return;
        }
        var allowedGlobal = !!(globalConfig && globalConfig.canApplyAvatar);
        var allowedInstance = !!(this.config && this.config.canApplyAvatar);
        var hasAttachment = !!(this.lastResult && this.lastResult.attachmentId);
        var shouldShow = allowedGlobal && allowedInstance && hasAttachment;
        button.classList.toggle('is-hidden', !shouldShow);
        button.disabled = !shouldShow || this.isSubmitting || this.isApplyingAvatar;
    };

    PhotoGrimlins.prototype.updateCtaRegisterButton = function() {
        var button = this.dom.ctaRegisterBtn;
        if (!button) {
            return;
        }
        var enabled = !!(this.config && this.config.ctaRegister);
        var hasResult = !!(this.lastResult && (this.lastResult.attachmentId || this.lastResult.resultUrl));
        var shouldShow = enabled && hasResult && !this.isSubmitting;
        button.classList.toggle('is-hidden', !shouldShow);
    };

    PhotoGrimlins.prototype.redirectToRegistration = function() {
        if (!this.config || !this.config.ctaRegister || !this.config.ctaRegisterUrl) {
            return;
        }
        var avatarData = {};
        if (this.lastResult) {
            if (this.lastResult.attachmentId) {
                avatarData.attachmentId = this.lastResult.attachmentId;
            }
            if (this.lastResult.resultUrl) {
                avatarData.url = this.lastResult.resultUrl;
            }
        }
        if (this.dom.resultImg) {
            var src = this.dom.resultImg.getAttribute('src');
            if (src && !avatarData.url) {
                avatarData.url = src;
            }
        }
        if (!avatarData.attachmentId && !avatarData.url) {
            return;
        }
        try {
            sessionStorage.setItem('mj_grimlins_avatar', JSON.stringify(avatarData));
        } catch (e) {
            // sessionStorage unavailable – fall through to URL param
        }
        var targetUrl = this.config.ctaRegisterUrl;
        var separator = targetUrl.indexOf('?') !== -1 ? '&' : '?';
        if (avatarData.attachmentId) {
            targetUrl += separator + 'grimlins_avatar=' + encodeURIComponent(avatarData.attachmentId);
        } else if (avatarData.url) {
            targetUrl += separator + 'grimlins_avatar_url=' + encodeURIComponent(avatarData.url);
        }
        window.location.href = targetUrl;
    };

    PhotoGrimlins.prototype.normalizeHistoryItem = function(item) {
        if (!item || typeof item !== 'object') {
            return null;
        }
        var id = parseInt(item.id, 10);
        if (!id || Number.isNaN(id)) {
            return null;
        }

        var normalized = {
            id: id,
            url: typeof item.url === 'string' ? item.url : '',
            thumbnail: '',
            downloadUrl: typeof item.downloadUrl === 'string' ? item.downloadUrl : '',
            downloadName: typeof item.downloadName === 'string' ? item.downloadName : '',
            createdAt: typeof item.createdAt === 'string' ? item.createdAt : '',
            createdLabel: typeof item.createdLabel === 'string' ? item.createdLabel : '',
            isCurrent: !!item.isCurrent,
            canApply: item.canApply !== false,
            session: typeof item.session === 'string' ? item.session : ''
        };

        if (!normalized.createdLabel && typeof item.createdHuman === 'string') {
            normalized.createdLabel = item.createdHuman;
        }
        if (!normalized.downloadUrl && typeof item.url === 'string') {
            normalized.downloadUrl = item.url;
        }
        if (typeof item.thumbnail === 'string' && item.thumbnail !== '') {
            normalized.thumbnail = item.thumbnail;
        } else if (typeof item.thumb === 'string' && item.thumb !== '') {
            normalized.thumbnail = item.thumb;
        } else if (normalized.downloadUrl) {
            normalized.thumbnail = normalized.downloadUrl;
        }

        return normalized;
    };

    PhotoGrimlins.prototype.setHistory = function(items, options) {
        var normalized = [];
        if (Array.isArray(items)) {
            for (var i = 0; i < items.length; i += 1) {
                var entry = this.normalizeHistoryItem(items[i]);
                if (entry) {
                    normalized.push(entry);
                }
            }
        }

        this.history = normalized;

        if (options && typeof options.memberLimit === 'number') {
            this.historyLimit = options.memberLimit;
        } else if (!options && this.historyLimit === 0 && globalConfig && typeof globalConfig.memberLimit === 'number') {
            this.historyLimit = globalConfig.memberLimit;
        }

        if (options && typeof options.historyCount === 'number') {
            this.historyCount = options.historyCount;
        } else {
            this.historyCount = normalized.length;
        }

        this.renderHistory();

        var limitOverride = options && Object.prototype.hasOwnProperty.call(options, 'limitReached') ? options.limitReached : undefined;
        this.updateLimitState(limitOverride);
    };

    PhotoGrimlins.prototype.addHistoryItem = function(item, options) {
        var normalized = this.normalizeHistoryItem(item);
        if (!normalized) {
            return;
        }

        var list = Array.isArray(this.history) ? this.history.slice() : [];
        list = list.filter(function(entry) {
            return entry && typeof entry === 'object' && entry.id !== normalized.id;
        });
        list.unshift(normalized);
        this.history = list;

        if (options && typeof options.memberLimit === 'number') {
            this.historyLimit = options.memberLimit;
        }
        if (options && typeof options.historyCount === 'number') {
            this.historyCount = options.historyCount;
        } else {
            this.historyCount = list.length;
        }

        this.renderHistory();
        var limitOverride = options && Object.prototype.hasOwnProperty.call(options, 'limitReached') ? options.limitReached : undefined;
        this.updateLimitState(limitOverride);
    };

    PhotoGrimlins.prototype.updateHistoryFromPayload = function(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        var options = {
            memberLimit: typeof data.memberLimit === 'number' ? data.memberLimit : undefined,
            historyCount: typeof data.historyCount === 'number' ? data.historyCount : undefined,
            limitReached: typeof data.limitReached === 'boolean' ? data.limitReached : undefined
        };

        if (Array.isArray(data.history)) {
            this.setHistory(data.history, options);
            return;
        }

        if (data.historyItem) {
            this.addHistoryItem(data.historyItem, options);
            return;
        }

        if (typeof options.memberLimit === 'number') {
            this.historyLimit = options.memberLimit;
            this.updateLimitState(options.limitReached);
            this.syncGlobalHistory();
        }
    };

    PhotoGrimlins.prototype.getHistoryForMember = function(targetMemberId) {
        var memberKey = String(parseInt(targetMemberId, 10) || 0);
        if (!this.historyByMember || typeof this.historyByMember !== 'object') {
            return null;
        }
        var candidate = this.historyByMember[memberKey];
        return Array.isArray(candidate) ? candidate : null;
    };

    PhotoGrimlins.prototype.switchTargetMember = function(targetMemberId, memberLabel) {
        var normalizedTarget = parseInt(targetMemberId, 10);
        if (Number.isNaN(normalizedTarget) || normalizedTarget < 0) {
            normalizedTarget = 0;
        }

        this.activeTargetMemberId = normalizedTarget;
        if (this.config) {
            this.config.targetMemberId = normalizedTarget;
        }

        var activeLabel = typeof memberLabel === 'string' ? memberLabel : null;
        if (!activeLabel && this.root) {
            var activeTab = this.root.querySelector('[data-mj-pg-tab-btn][aria-selected="true"]');
            if (activeTab) {
                activeLabel = activeTab.getAttribute('data-mj-pg-member-label');
            }
        }
        this.updateHistoryTitle(normalizedTarget, activeLabel);

        var memberHistory = this.getHistoryForMember(normalizedTarget);
        if (!Array.isArray(memberHistory) && normalizedTarget <= 0) {
            memberHistory = this.getHistoryForMember(0);
        }
        if (!Array.isArray(memberHistory)) {
            memberHistory = [];
        }

        this.setHistory(memberHistory, {
            historyCount: memberHistory.length,
            memberLimit: this.historyLimit,
            limitReached: this.historyLimit > 0 && memberHistory.length >= this.historyLimit && !this.config.isPreview
        });
    };

    PhotoGrimlins.prototype.updateHistoryTitle = function(targetMemberId, memberLabel) {
        if (!this.dom.historyTitle) {
            return;
        }

        var defaultTitle = this.dom.historyTitle.getAttribute('data-history-title-default') || 'Mes avatars Grimlins';
        var normalizedTarget = parseInt(targetMemberId, 10) || 0;

        if (normalizedTarget <= 0) {
            this.dom.historyTitle.textContent = defaultTitle;
            return;
        }

        var label = (memberLabel || '').trim();
        if (!label) {
            this.dom.historyTitle.textContent = defaultTitle;
            return;
        }

        var template = globalConfig && globalConfig.i18n && globalConfig.i18n.historyForMember
            ? globalConfig.i18n.historyForMember
            : 'Avatars de %name%';

        this.dom.historyTitle.textContent = template.replace(/%name%/g, label);
    };

    PhotoGrimlins.prototype.renderHistory = function() {
        if (!this.dom.historyList) {
            return;
        }

        while (this.dom.historyList.firstChild) {
            this.dom.historyList.removeChild(this.dom.historyList.firstChild);
        }

        var list = Array.isArray(this.history) ? this.history : [];

        if (this.dom.historyEmpty) {
            this.dom.historyEmpty.hidden = list.length > 0;
        }

        if (!list.length) {
            this.dom.historyList.hidden = true;
            return;
        }

        this.dom.historyList.hidden = false;

        for (var i = 0; i < list.length; i += 1) {
            var node = this.createHistoryNode(list[i]);
            if (node) {
                this.dom.historyList.appendChild(node);
            }
        }

        this.updateHistoryButtonsState();
    };

    PhotoGrimlins.prototype.createHistoryNode = function(item) {
        if (!item || typeof item !== 'object') {
            return null;
        }

        var container = document.createElement('div');
        container.className = 'mj-photo-grimlins__history-item';
        container.setAttribute('role', 'listitem');
        container.setAttribute('data-attachment-id', String(item.id));
        if (item.isCurrent) {
            container.classList.add('is-current');
        }

        var thumbWrapper = document.createElement('div');
        thumbWrapper.className = 'mj-photo-grimlins__history-thumb';

        if (item.thumbnail) {
            var placeholderImg = document.createElement('img');
            placeholderImg.src = item.thumbnail;
            placeholderImg.alt = '';
            placeholderImg.loading = 'lazy';
            if (item.url) {
                var link = document.createElement('a');
                link.href = item.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.appendChild(placeholderImg);
                thumbWrapper.appendChild(link);
            } else {
                thumbWrapper.appendChild(placeholderImg);
            }
        }

        container.appendChild(thumbWrapper);

        var meta = document.createElement('div');
        meta.className = 'mj-photo-grimlins__history-meta';

        if (item.createdLabel) {
            var time = document.createElement('time');
            time.className = 'mj-photo-grimlins__history-date';
            time.textContent = item.createdLabel;
            if (item.createdAt) {
                time.dateTime = item.createdAt;
            }
            meta.appendChild(time);
        }

        if (item.isCurrent && globalConfig && globalConfig.i18n && globalConfig.i18n.historyCurrent) {
            var currentBadge = document.createElement('span');
            currentBadge.className = 'mj-photo-grimlins__history-current';
            currentBadge.textContent = globalConfig.i18n.historyCurrent;
            meta.appendChild(currentBadge);
        }

        var actions = document.createElement('div');
        actions.className = 'mj-photo-grimlins__history-actions';

        if (item.downloadUrl) {
            var download = document.createElement('a');
            download.href = item.downloadUrl;
            if (item.downloadName) {
                download.download = item.downloadName;
            }
            download.className = 'mj-photo-grimlins__history-download';
            download.textContent = globalConfig && globalConfig.i18n && globalConfig.i18n.historyDownload
                ? globalConfig.i18n.historyDownload
                : 'Télécharger';
            actions.appendChild(download);
        }

        var allowApply = !!(globalConfig && globalConfig.canApplyAvatar) && !!(this.config && this.config.canApplyAvatar);
        var allowDelete = !!(globalConfig && globalConfig.canDeleteAvatar) && !!(this.config && this.config.canDeleteAvatar);
        if (allowApply && item.canApply !== false) {
            var applyButton = document.createElement('button');
            applyButton.type = 'button';
            applyButton.className = 'mj-photo-grimlins__history-apply';
            applyButton.setAttribute('data-photo-grimlins-history-apply', '1');
            applyButton.setAttribute('data-attachment-id', String(item.id));
            applyButton.textContent = globalConfig && globalConfig.i18n && globalConfig.i18n.historyApply
                ? globalConfig.i18n.historyApply
                : 'Utiliser cet avatar';
            actions.appendChild(applyButton);
        }

        if (allowDelete) {
            var deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'mj-photo-grimlins__history-delete';
            deleteButton.setAttribute('data-photo-grimlins-history-delete', '1');
            deleteButton.setAttribute('data-attachment-id', String(item.id));
            deleteButton.disabled = !!item.isCurrent;
            deleteButton.textContent = globalConfig && globalConfig.i18n && globalConfig.i18n.historyDelete
                ? globalConfig.i18n.historyDelete
                : 'Supprimer';
            actions.appendChild(deleteButton);
        }

        if (actions.children.length > 0) {
            meta.appendChild(actions);
        }

        container.appendChild(meta);

        return container;
    };

    PhotoGrimlins.prototype.updateLimitState = function(limitReachedOverride) {
        this.historyCount = Array.isArray(this.history) ? this.history.length : 0;

        var enforceLimit = this.accessScope !== 'public';

        if (!enforceLimit) {
            this.limitReached = false;
        } else if (typeof limitReachedOverride === 'boolean') {
            this.limitReached = limitReachedOverride;
        } else {
            this.limitReached = this.historyLimit > 0 && this.historyCount >= this.historyLimit && !this.config.isPreview;
        }

        if (this.dom.historyLimit) {
            if (enforceLimit && this.historyLimit > 0) {
                var template = globalConfig && globalConfig.i18n && globalConfig.i18n.historyLimitCounter
                    ? globalConfig.i18n.historyLimitCounter
                    : '%count% / %limit%';
                var limitText = template
                    .replace(/%count%/g, String(this.historyCount))
                    .replace(/%limit%/g, String(this.historyLimit));
                if (this.limitReached && globalConfig && globalConfig.i18n && globalConfig.i18n.historyLimitReached) {
                    limitText += ' - ' + globalConfig.i18n.historyLimitReached;
                }
                this.dom.historyLimit.textContent = limitText;
                this.dom.historyLimit.hidden = false;
            } else {
                this.dom.historyLimit.textContent = '';
                this.dom.historyLimit.hidden = true;
            }
        }

        if (this.dom.historySection) {
            this.dom.historySection.classList.toggle('is-empty', this.historyCount === 0);
        }

        if (this.dom.historySection) {
            this.dom.historySection.classList.toggle('is-limit-reached', this.limitReached);
        }

        this.syncGlobalHistory();
        this.refreshUi();
    };

    PhotoGrimlins.prototype.syncGlobalHistory = function() {
        if (!globalConfig) {
            return;
        }
        var exported = [];
        if (Array.isArray(this.history)) {
            for (var i = 0; i < this.history.length; i += 1) {
                var entry = this.history[i];
                if (!entry || typeof entry !== 'object') {
                    continue;
                }
                var copy = {};
                for (var key in entry) {
                    if (Object.prototype.hasOwnProperty.call(entry, key)) {
                        copy[key] = entry[key];
                    }
                }
                exported.push(copy);
            }
        }
        globalConfig.history = exported;
        if (!globalConfig.historiesByMember || typeof globalConfig.historiesByMember !== 'object') {
            globalConfig.historiesByMember = {};
        }
        globalConfig.historiesByMember[String(this.activeTargetMemberId || 0)] = exported;
        this.historyByMember[String(this.activeTargetMemberId || 0)] = exported;
        globalConfig.historyCount = this.historyCount;
        globalConfig.memberLimit = this.historyLimit;
        globalConfig.limitReached = this.limitReached;
    };

    PhotoGrimlins.prototype.updateHistoryButtonsState = function() {
        if (!this.dom.historyList) {
            return;
        }
        var buttons = this.dom.historyList.querySelectorAll('[data-photo-grimlins-history-apply]');
        if (!buttons || !buttons.length) {
            return;
        }
        var allowed = !!(globalConfig && globalConfig.canApplyAvatar) && !!(this.config && this.config.canApplyAvatar);
        for (var i = 0; i < buttons.length; i += 1) {
            var button = buttons[i];
            var attachmentId = parseInt(button.getAttribute('data-attachment-id'), 10);
            if (!attachmentId || Number.isNaN(attachmentId)) {
                button.disabled = true;
                continue;
            }
            var isCurrent = false;
            var entries = Array.isArray(this.history) ? this.history : [];
            for (var j = 0; j < entries.length; j += 1) {
                var entry = entries[j];
                if (entry && entry.id === attachmentId) {
                    isCurrent = !!entry.isCurrent;
                    break;
                }
            }
            button.disabled = !allowed || this.isApplyingAvatar || isCurrent;
            button.classList.toggle('is-current', isCurrent);
        }

        var deleteButtons = this.dom.historyList.querySelectorAll('[data-photo-grimlins-history-delete]');
        if (!deleteButtons || !deleteButtons.length) {
            return;
        }
        var deleteAllowed = !!(globalConfig && globalConfig.canDeleteAvatar) && !!(this.config && this.config.canDeleteAvatar);
        for (var k = 0; k < deleteButtons.length; k += 1) {
            var deleteButton = deleteButtons[k];
            var deleteAttachmentId = parseInt(deleteButton.getAttribute('data-attachment-id'), 10);
            if (!deleteAttachmentId || Number.isNaN(deleteAttachmentId)) {
                deleteButton.disabled = true;
                continue;
            }
            var isDeleteCurrent = false;
            var deleteEntries = Array.isArray(this.history) ? this.history : [];
            for (var l = 0; l < deleteEntries.length; l += 1) {
                var deleteEntry = deleteEntries[l];
                if (deleteEntry && deleteEntry.id === deleteAttachmentId) {
                    isDeleteCurrent = !!deleteEntry.isCurrent;
                    break;
                }
            }
            deleteButton.disabled = !deleteAllowed || this.isSubmitting || this.isApplyingAvatar || isDeleteCurrent;
            deleteButton.classList.toggle('is-current', isDeleteCurrent);
        }
    };

    PhotoGrimlins.prototype.markCurrentAvatar = function(attachmentId) {
        var id = parseInt(attachmentId, 10);
        if (!id || Number.isNaN(id) || !Array.isArray(this.history)) {
            return;
        }

        var mutated = false;

        for (var i = 0; i < this.history.length; i += 1) {
            var entry = this.history[i];
            if (!entry || typeof entry !== 'object') {
                continue;
            }
            var isCurrent = entry.id === id;
            if (entry.isCurrent !== isCurrent) {
                entry.isCurrent = isCurrent;
                mutated = true;
            }
        }

        if (mutated) {
            this.renderHistory();
            this.updateHistoryButtonsState();
            this.syncGlobalHistory();
        }
    };

    PhotoGrimlins.prototype.refreshUi = function() {
        var globalEnabled = !!(globalConfig && globalConfig.enabled);
        var busy = this.isSubmitting || this.isApplyingAvatar;
        var limitReached = this.limitReached && !this.config.isPreview;
        var hasFile = !!this.file;
        if (this.dom.submitBtn) {
            this.dom.submitBtn.disabled = !globalEnabled || busy || limitReached;
            this.dom.submitBtn.classList.toggle('is-disabled-by-limit', limitReached);
            if (hasFile) {
                this.dom.submitBtn.removeAttribute('hidden');
                this.dom.submitBtn.style.display = '';
            } else {
                this.dom.submitBtn.setAttribute('hidden', '');
                this.dom.submitBtn.style.display = 'none';
            }
        }
        if (this.dom.resetBtn) {
            this.dom.resetBtn.disabled = busy;
            if (hasFile) {
                this.dom.resetBtn.removeAttribute('hidden');
                this.dom.resetBtn.style.display = '';
            } else {
                this.dom.resetBtn.setAttribute('hidden', '');
                this.dom.resetBtn.style.display = 'none';
            }
        }
        if (this.dom.cameraBtn) {
            this.dom.cameraBtn.disabled = busy || !this.cameraSupported || limitReached;
            this.dom.cameraBtn.classList.toggle('is-hidden', !this.cameraSupported || limitReached || hasFile);
        }
        if (this.dom.fileInput) {
            this.dom.fileInput.disabled = busy || limitReached;
        }
        if (this.dom.cameraInput) {
            this.dom.cameraInput.disabled = busy || limitReached;
        }
        if (this.dom.dropzone) {
            var hideDropzone = hasFile || limitReached;
            this.dom.dropzone.classList.toggle('is-disabled', limitReached);
            this.dom.dropzone.classList.toggle('is-hidden', hideDropzone);
            if (hideDropzone) {
                this.dom.dropzone.setAttribute('aria-disabled', 'true');
            } else {
                this.dom.dropzone.removeAttribute('aria-disabled');
            }
        }
        if (this.dom.form) {
            this.dom.form.classList.toggle('is-submitting', this.isSubmitting);
        }
        if (this.root) {
            this.root.classList.toggle('is-updating-avatar', this.isApplyingAvatar);
            this.root.classList.toggle('is-generating', this.isSubmitting || this.isPreviewLoading);
        }
        this.updateApplyAvatarButton();
        this.updateCtaRegisterButton();
        if (typeof this.updateHistoryButtonsState === 'function') {
            this.updateHistoryButtonsState();
        }
    };

    PhotoGrimlins.prototype.reset = function() {
        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = '';
        }
        if (this.dom.fileInput) {
            this.dom.fileInput.value = '';
        }
        if (this.dom.cameraInput) {
            this.dom.cameraInput.value = '';
        }
        this.file = null;
        if (this.dom.previewBox) {
            this.dom.previewBox.hidden = true;
        }
        if (this.dom.resultBox) {
            this.dom.resultBox.hidden = true;
        }
        if (this.dom.resultImg) {
            this.dom.resultImg.removeAttribute('src');
            this.dom.resultImg.removeAttribute('alt');
        }
        if (this.dom.downloadLink) {
            this.dom.downloadLink.classList.add('is-hidden');
            this.dom.downloadLink.removeAttribute('href');
        }
        this.lastResult = null;
        this.isApplyingAvatar = false;
        this.setPreviewLoading(false);
        this.hideCameraModal();
        this.updateStatus('', '');
        this.refreshUi();
    };

    PhotoGrimlins.prototype.showCameraModal = function() {
        if (!this.dom.cameraModal) {
            return;
        }
        this.dom.cameraModal.hidden = false;
        this.dom.cameraModal.setAttribute('aria-hidden', 'false');
        this.dom.cameraModal.classList.add('is-visible');
        if (this.dom.dropzone) {
            this.dom.dropzone.classList.add('is-hidden');
        }
        if (this.dom.cameraBtn) {
            this.dom.cameraBtn.classList.add('is-hidden');
        }
        this.updateEscapeBinding();
    };

    PhotoGrimlins.prototype.hideCameraModal = function(silent) {
        if (!this.dom.cameraModal) {
            return;
        }
        this.dom.cameraModal.classList.remove('is-visible');
        this.dom.cameraModal.setAttribute('aria-hidden', 'true');
        this.dom.cameraModal.hidden = true;
        if (this.dom.dropzone) {
            this.dom.dropzone.classList.remove('is-hidden');
        }
        if (this.dom.cameraBtn) {
            this.dom.cameraBtn.classList.remove('is-hidden');
        }
        if (!silent) {
            this.stopCameraPreview();
        }
        if (this.dom.cameraCaptureBtn) {
            this.dom.cameraCaptureBtn.disabled = false;
        }
        this.updateEscapeBinding();
    };

    PhotoGrimlins.prototype.openYoungSearchModal = function() {
        if (!this.dom.youngSearchModal || !(this.config && this.config.canSearchYoung)) {
            return;
        }
        this.dom.youngSearchModal.hidden = false;
        this.dom.youngSearchModal.setAttribute('aria-hidden', 'false');
        this.updateEscapeBinding();
        if (this.dom.youngSearchInput) {
            this.dom.youngSearchInput.focus();
        }
        this.performYoungSearch(this.dom.youngSearchInput ? this.dom.youngSearchInput.value : '');
    };

    PhotoGrimlins.prototype.closeYoungSearchModal = function() {
        if (!this.dom.youngSearchModal) {
            return;
        }
        this.dom.youngSearchModal.setAttribute('aria-hidden', 'true');
        this.dom.youngSearchModal.hidden = true;
        this.updateEscapeBinding();
    };

    PhotoGrimlins.prototype.updateEscapeBinding = function() {
        var hasOpenCamera = !!(this.dom.cameraModal && !this.dom.cameraModal.hidden);
        var hasOpenSearch = !!(this.dom.youngSearchModal && !this.dom.youngSearchModal.hidden);
        if (hasOpenCamera || hasOpenSearch) {
            this.boundEscHandler = this.boundEscHandler || this.handleEscape.bind(this);
            document.addEventListener('keydown', this.boundEscHandler);
            return;
        }
        if (this.boundEscHandler) {
            document.removeEventListener('keydown', this.boundEscHandler);
            this.boundEscHandler = null;
        }
    };

    PhotoGrimlins.prototype.handleEscape = function(event) {
        if (!event) {
            return;
        }
        if (event.key === 'Escape' || event.key === 'Esc') {
            if (this.dom.cameraModal && !this.dom.cameraModal.hidden) {
                event.preventDefault();
                this.hideCameraModal();
                return;
            }
            if (this.dom.youngSearchModal && !this.dom.youngSearchModal.hidden) {
                event.preventDefault();
                this.closeYoungSearchModal();
            }
        }
    };

    PhotoGrimlins.prototype.performYoungSearch = function(query) {
        var _this = this;
        if (!this.dom.youngSearchResults || !globalConfig || !globalConfig.ajaxUrl || !(this.config && this.config.canSearchYoung)) {
            return;
        }

        var message = globalConfig && globalConfig.i18n && globalConfig.i18n.youngSearchLoading
            ? globalConfig.i18n.youngSearchLoading
            : 'Recherche des jeunes…';
        if (this.dom.youngSearchStatus) {
            this.dom.youngSearchStatus.textContent = message;
        }
        this.isYoungSearchLoading = true;
        if (this.dom.youngSearchSubmit) {
            this.dom.youngSearchSubmit.disabled = true;
        }

        var formData = new FormData();
        formData.append('action', 'mj_member_photo_grimlins_search_young');
        formData.append('nonce', globalConfig.searchYoungNonce || '');
        formData.append('search', (query || '').trim());
        formData.append('limit', '20');

        fetch(globalConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || !payload.success || !payload.data) {
                    throw new Error('Réponse invalide');
                }
                _this.renderYoungSearchResults(Array.isArray(payload.data.items) ? payload.data.items : []);
            })
            .catch(function() {
                _this.renderYoungSearchResults([]);
                if (_this.dom.youngSearchStatus) {
                    _this.dom.youngSearchStatus.textContent = globalConfig && globalConfig.i18n && globalConfig.i18n.youngSearchError
                        ? globalConfig.i18n.youngSearchError
                        : 'Impossible de charger la recherche des jeunes.';
                }
            })
            .finally(function() {
                _this.isYoungSearchLoading = false;
                if (_this.dom.youngSearchSubmit) {
                    _this.dom.youngSearchSubmit.disabled = false;
                }
            });
    };

    PhotoGrimlins.prototype.renderYoungSearchResults = function(items) {
        if (!this.dom.youngSearchResults) {
            return;
        }
        while (this.dom.youngSearchResults.firstChild) {
            this.dom.youngSearchResults.removeChild(this.dom.youngSearchResults.firstChild);
        }

        if (!items.length) {
            if (this.dom.youngSearchStatus) {
                this.dom.youngSearchStatus.textContent = globalConfig && globalConfig.i18n && globalConfig.i18n.youngSearchNoResult
                    ? globalConfig.i18n.youngSearchNoResult
                    : 'Aucun jeune trouvé.';
            }
            return;
        }

        if (this.dom.youngSearchStatus) {
            this.dom.youngSearchStatus.textContent = '';
        }

        for (var i = 0; i < items.length; i += 1) {
            var item = items[i] || {};
            var memberId = parseInt(item.id, 10);
            if (!memberId || Number.isNaN(memberId)) {
                continue;
            }

            var row = document.createElement('div');
            row.className = 'mj-photo-grimlins-young-search__item';
            row.setAttribute('role', 'listitem');

            var avatar = document.createElement('span');
            avatar.className = 'mj-photo-grimlins-young-search__avatar';
            if (item.photoUrl) {
                var img = document.createElement('img');
                img.src = item.photoUrl;
                img.alt = '';
                img.loading = 'lazy';
                avatar.appendChild(img);
            } else {
                var fallback = document.createElement('span');
                fallback.className = 'mj-photo-grimlins-young-search__avatar-fallback';
                fallback.textContent = item.initials || 'J';
                avatar.appendChild(fallback);
            }

            var meta = document.createElement('div');
            meta.className = 'mj-photo-grimlins-young-search__meta';
            var label = document.createElement('p');
            label.className = 'mj-photo-grimlins-young-search__label';
            label.textContent = item.label || ('Jeune #' + memberId);
            meta.appendChild(label);

            var pick = document.createElement('button');
            pick.type = 'button';
            pick.className = 'mj-photo-grimlins-young-search__pick';
            pick.setAttribute('data-photo-grimlins-select-young', '1');
            pick.setAttribute('data-member-id', String(memberId));
            pick.setAttribute('data-member-label', item.label || ('Jeune #' + memberId));
            pick.textContent = globalConfig && globalConfig.i18n && globalConfig.i18n.youngSearchPick
                ? globalConfig.i18n.youngSearchPick
                : 'Choisir';

            row.appendChild(avatar);
            row.appendChild(meta);
            row.appendChild(pick);
            this.dom.youngSearchResults.appendChild(row);
        }
    };

    PhotoGrimlins.prototype.selectTargetMemberFromSearch = function(memberId, memberLabel) {
        var normalizedId = parseInt(memberId, 10);
        if (!normalizedId || Number.isNaN(normalizedId)) {
            return;
        }

        var tab = this.root ? this.root.querySelector('[data-mj-pg-tab-btn][data-mj-pg-target-member="' + String(normalizedId) + '"]') : null;
        if (tab) {
            tab.click();
        } else {
            this.activeTargetMemberId = normalizedId;
            if (this.config) {
                this.config.targetMemberId = normalizedId;
            }
            this.switchTargetMember(normalizedId, memberLabel || '');
        }

        if (this.dom.openYoungSearchBtn && memberLabel) {
            this.dom.openYoungSearchBtn.textContent = memberLabel;
        }

        this.closeYoungSearchModal();
    };

    PhotoGrimlins.prototype.triggerCameraInput = function() {
        if (!this.dom.cameraInput) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraUnavailable : 'Impossible d’ouvrir la caméra.', 'error');
            return;
        }
        try {
            this.dom.cameraInput.click();
        } catch (error) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraUnavailable : 'Impossible d’ouvrir la caméra.', 'error');
        }
    };

    PhotoGrimlins.prototype.startCameraPreview = function() {
        var _this = this;
        if (!this.canStreamCamera || !this.dom.cameraVideo) {
            return Promise.reject(new Error('Streaming camera non disponible'));
        }

        this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraPermission : 'Autorise l’accès à la caméra pour continuer.', 'info');

        return navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false
        }).then(function(stream) {
            _this.cameraStream = stream;
            var video = _this.dom.cameraVideo;
            video.srcObject = stream;
            video.playsInline = true;

            var enableCapture = function() {
                video.removeEventListener('loadedmetadata', enableCapture);
                video.play().catch(noop);
                _this.showCameraModal();
                if (_this.dom.cameraCaptureBtn) {
                    _this.dom.cameraCaptureBtn.disabled = false;
                }
                var readyMessage = globalConfig.i18n && globalConfig.i18n.cameraReady
                    ? globalConfig.i18n.cameraReady
                    : 'Caméra prête. Capture la photo.';
                _this.updateStatus(readyMessage, 'info');
            };

            if (video.readyState >= 2) {
                enableCapture();
            } else {
                video.addEventListener('loadedmetadata', enableCapture);
            }

            return stream;
        }).catch(function(error) {
            _this.handleCameraError(error);
            return null;
        });
    };

    PhotoGrimlins.prototype.stopCameraPreview = function() {
        var video = this.dom.cameraVideo;
        if (video) {
            try {
                video.pause();
            } catch (error) {
                // Ignore pause errors.
            }
            video.srcObject = null;
            video.removeAttribute('src');
        }
        if (this.cameraStream) {
            try {
                this.cameraStream.getTracks().forEach(function(track) {
                    track.stop();
                });
            } catch (error) {
                // Ignore stop errors.
            }
            this.cameraStream = null;
        }
    };

    PhotoGrimlins.prototype.handleCameraError = function(error) {
        var message = globalConfig.i18n ? globalConfig.i18n.cameraError : 'Impossible d’utiliser la caméra.';
        if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) {
            message = globalConfig.i18n && globalConfig.i18n.cameraPermissionDenied
                ? globalConfig.i18n.cameraPermissionDenied
                : 'Permission caméra refusée. Autorise l’accès à la caméra puis réessaie.';
        } else if (error && error.name === 'NotFoundError') {
            message = globalConfig.i18n && globalConfig.i18n.cameraUnavailable
                ? globalConfig.i18n.cameraUnavailable
                : 'Impossible de trouver une caméra sur cet appareil.';
        }
        this.stopCameraPreview();
        this.updateStatus(message, 'error');
        if (this.dom.cameraCaptureBtn) {
            this.dom.cameraCaptureBtn.disabled = true;
        }
    };

    PhotoGrimlins.prototype.captureFromCamera = function() {
        var _this = this;
        if (!this.cameraStream || !this.dom.cameraVideo) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraError : 'Caméra inactive.', 'error');
            return;
        }

        if (this.dom.cameraCaptureBtn) {
            this.dom.cameraCaptureBtn.disabled = true;
        }

        var video = this.dom.cameraVideo;
        var width = video.videoWidth || 0;
        var height = video.videoHeight || 0;

        if (!width || !height) {
            width = 1280;
            height = 960;
        }

        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraError : 'Capture impossible.', 'error');
            if (this.dom.cameraCaptureBtn) {
                this.dom.cameraCaptureBtn.disabled = false;
            }
            return;
        }
        ctx.drawImage(video, 0, 0, width, height);

        var finalize = function(blob) {
            if (!blob) {
                _this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraError : 'Capture impossible.', 'error');
                if (_this.dom.cameraCaptureBtn) {
                    _this.dom.cameraCaptureBtn.disabled = false;
                }
                return;
            }
            var filename = 'camera-' + Date.now() + '.jpg';
            var file = createFileFromBlob(blob, filename);
            if (!file) {
                _this.updateStatus(globalConfig.i18n ? globalConfig.i18n.cameraError : 'Capture impossible.', 'error');
                if (_this.dom.cameraCaptureBtn) {
                    _this.dom.cameraCaptureBtn.disabled = false;
                }
                return;
            }
            _this.stopCameraPreview();
            _this.hideCameraModal(true);
            _this.setFile(file);
            if (_this.dom.cameraCaptureBtn) {
                _this.dom.cameraCaptureBtn.disabled = false;
            }
        };

        if (typeof canvas.toBlob === 'function') {
            canvas.toBlob(function(blob) {
                finalize(blob);
            }, 'image/jpeg', 0.92);
        } else {
            finalize(dataUrlToBlob(canvas.toDataURL('image/jpeg', 0.92)));
        }
    };

    PhotoGrimlins.prototype.validateFile = function(file) {
        if (this.limitReached && !this.config.isPreview) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.historyLimitReached : 'Limite atteinte.', 'error');
            return false;
        }

        if (!file) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.missingFile : 'Sélectionne une photo.', 'error');
            return false;
        }

        if (!globalConfig.enabled) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.disabled : 'Fonctionnalité indisponible.', 'error');
            return false;
        }

        var maxSize = typeof globalConfig.maxSize === 'number' ? globalConfig.maxSize : 0;
        if (maxSize > 0 && file.size > maxSize) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.fileTooLarge : 'Fichier trop volumineux.', 'error');
            return false;
        }

        if (Array.isArray(globalConfig.allowedMimes) && globalConfig.allowedMimes.length > 0) {
            if (!globalConfig.allowedMimes.includes(file.type)) {
                this.updateStatus(globalConfig.i18n ? globalConfig.i18n.mimeNotAllowed : 'Format non supporté.', 'error');
                return false;
            }
        }

        return true;
    };

    PhotoGrimlins.prototype.submit = function() {
        var _this = this;
        if (this.isSubmitting) {
            return;
        }

        if (this.limitReached && !this.config.isPreview) {
            this.updateStatus(globalConfig.i18n ? globalConfig.i18n.historyLimitReached : 'Limite atteinte.', 'error');
            return;
        }

        if (!this.validateFile(this.file)) {
            return;
        }

        if (this.controller) {
            this.controller.abort();
        }
        this.controller = typeof window.AbortController === 'function' ? new AbortController() : null;

        var formData = new FormData();
        formData.append('action', 'mj_member_generate_grimlins');
        formData.append('nonce', globalConfig.nonce || '');
        formData.append('source', this.file, this.file.name);
        var submitTargetMemberId = parseInt((this.config && this.config.targetMemberId) || this.activeTargetMemberId || 0, 10);
        if (submitTargetMemberId && !Number.isNaN(submitTargetMemberId) && submitTargetMemberId > 0) {
            formData.append('targetMemberId', String(submitTargetMemberId));
        }
        var scope = this.accessScope === 'public' ? 'public' : 'members';
        formData.append('accessScope', scope);
        if (this.accessNonce) {
            formData.append('accessNonce', this.accessNonce);
        }

        this.isSubmitting = true;
        this.setPreviewLoading(true);
        this.refreshUi();
        this.updateStatus(globalConfig.i18n ? globalConfig.i18n.loading : 'Génération en cours…', 'info');

        var fetchOptions = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        };
        if (this.controller) {
            fetchOptions.signal = this.controller.signal;
        }

        fetch(globalConfig.ajaxUrl, fetchOptions)
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || typeof payload !== 'object') {
                    throw new Error('Payload vide');
                }
                if (!payload.success) {
                    var errorMessage = payload.data && payload.data.message ? payload.data.message : (globalConfig.i18n ? globalConfig.i18n.genericError : 'Erreur inconnue');
                    throw new Error(errorMessage);
                }

                var data = payload.data || {};
                _this.updateHistoryFromPayload(data);
                _this.updateStatus(globalConfig.i18n ? globalConfig.i18n.ready : 'Avatar prêt !', 'success');
                _this.renderResult(data);
            })
            .catch(function(error) {
                if (error && typeof error.json === 'function') {
                    error.json().then(function(details) {
                        if (details && details.data && typeof details.data.limit === 'number') {
                            _this.historyLimit = details.data.limit;
                            _this.updateLimitState(true);
                        }
                        var message = details && details.data && details.data.message
                            ? details.data.message
                            : (globalConfig.i18n ? globalConfig.i18n.genericError : 'Erreur lors de la génération.');
                        _this.updateStatus(message, 'error');
                    }).catch(function() {
                        _this.updateStatus(error.message || (globalConfig.i18n ? globalConfig.i18n.genericError : 'Erreur lors de la génération.'), 'error');
                    });
                    return;
                }
                var message = error && error.message ? error.message : (globalConfig.i18n ? globalConfig.i18n.genericError : 'Erreur lors de la génération.');
                _this.updateStatus(message, 'error');
            })
            .finally(function() {
                _this.isSubmitting = false;
                _this.setPreviewLoading(false);
                _this.refreshUi();
            });
    };

    PhotoGrimlins.prototype.applyAvatar = function(attachmentId) {
        if (!globalConfig || !globalConfig.ajaxUrl) {
            this.updateStatus('Configuration AJAX manquante.', 'error');
            return;
        }

        if (!globalConfig.canApplyAvatar || !(this.config && this.config.canApplyAvatar)) {
            var unauthorizedMessage = globalConfig.i18n && globalConfig.i18n.applyAvatarUnauthorized
                ? globalConfig.i18n.applyAvatarUnauthorized
                : 'Tu dois être connecté pour mettre à jour ton avatar.';
            this.updateStatus(unauthorizedMessage, 'error');
            return;
        }

        var targetId = attachmentId;
        if (targetId && typeof targetId !== 'number') {
            targetId = parseInt(targetId, 10);
        }
        if (!targetId || Number.isNaN(targetId)) {
            targetId = this.lastResult && this.lastResult.attachmentId ? parseInt(this.lastResult.attachmentId, 10) : 0;
        }

        if (!targetId || Number.isNaN(targetId)) {
            var missingMessage = globalConfig.i18n && globalConfig.i18n.applyAvatarError
                ? globalConfig.i18n.applyAvatarError
                : 'Impossible de mettre à jour ton avatar.';
            this.updateStatus(missingMessage, 'error');
            return;
        }

        if (!globalConfig.applyAvatarNonce) {
            this.updateStatus(globalConfig.i18n && globalConfig.i18n.applyAvatarError
                ? globalConfig.i18n.applyAvatarError
                : 'Impossible de mettre à jour ton avatar.', 'error');
            return;
        }

        if (this.isApplyingAvatar) {
            return;
        }

        var pendingMessage = globalConfig.i18n && globalConfig.i18n.applyAvatarPending
            ? globalConfig.i18n.applyAvatarPending
            : 'Mise à jour de ton avatar…';

        this.isApplyingAvatar = true;
        this.refreshUi();
        this.updateStatus(pendingMessage, 'info');

        var formData = new FormData();
        formData.append('action', 'mj_member_apply_grimlins_avatar');
        formData.append('nonce', globalConfig.applyAvatarNonce);
        formData.append('attachmentId', String(targetId));

        var targetMemberId = 0;
        if (this.config && this.config.targetMemberId !== undefined && this.config.targetMemberId !== null) {
            targetMemberId = parseInt(this.config.targetMemberId, 10);
            if (!targetMemberId || Number.isNaN(targetMemberId)) {
                targetMemberId = 0;
            }
        }
        if (!targetMemberId && this.root) {
            var rootTargetMemberId = parseInt(this.root.getAttribute('data-target-member-id'), 10);
            if (rootTargetMemberId && !Number.isNaN(rootTargetMemberId)) {
                targetMemberId = rootTargetMemberId;
            }
        }
        if (targetMemberId > 0) {
            formData.append('targetMemberId', String(targetMemberId));
        }

        var _this = this;

        fetch(globalConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || typeof payload !== 'object') {
                    throw new Error('Payload vide');
                }
                if (!payload.success) {
                    var errorMessage = payload.data && payload.data.message
                        ? payload.data.message
                        : (globalConfig.i18n ? globalConfig.i18n.applyAvatarError : 'Impossible de mettre à jour ton avatar.');
                    throw new Error(errorMessage);
                }

                if (payload.data && payload.data.nonce) {
                    globalConfig.applyAvatarNonce = payload.data.nonce;
                }

                if (payload.data && payload.data.attachmentId) {
                    var returnedId = parseInt(payload.data.attachmentId, 10);
                    if (returnedId && !Number.isNaN(returnedId)) {
                        targetId = returnedId;
                    }
                }

                if (_this.lastResult && _this.lastResult.attachmentId) {
                    _this.lastResult.attachmentId = targetId;
                }

                if (typeof _this.markCurrentAvatar === 'function') {
                    _this.markCurrentAvatar(targetId);
                }

                var successMessage = payload.data && payload.data.message
                    ? payload.data.message
                    : (globalConfig.i18n ? globalConfig.i18n.applyAvatarSuccess : 'Avatar mis à jour !');
                _this.updateStatus(successMessage, 'success');
                _this.updateApplyAvatarButton();
            })
            .catch(function(error) {
                if (error && typeof error.json === 'function') {
                    error.json().then(function(details) {
                        var message = details && details.data && details.data.message
                            ? details.data.message
                            : (globalConfig.i18n ? globalConfig.i18n.applyAvatarError : 'Impossible de mettre à jour ton avatar.');
                        _this.updateStatus(message, 'error');
                    }).catch(function() {
                        _this.updateStatus(error.message || (globalConfig.i18n ? globalConfig.i18n.applyAvatarError : 'Impossible de mettre à jour ton avatar.'), 'error');
                    });
                    return;
                }
                var message = error && error.message
                    ? error.message
                    : (globalConfig.i18n ? globalConfig.i18n.applyAvatarError : 'Impossible de mettre à jour ton avatar.');
                _this.updateStatus(message, 'error');
            })
            .finally(function() {
                _this.isApplyingAvatar = false;
                _this.refreshUi();
            });
    };

    PhotoGrimlins.prototype.deleteHistoryAvatar = function(attachmentId) {
        if (!globalConfig || !globalConfig.ajaxUrl) {
            this.updateStatus('Configuration AJAX manquante.', 'error');
            return;
        }

        if (!globalConfig.canDeleteAvatar || !(this.config && this.config.canDeleteAvatar)) {
            var unauthorizedMessage = globalConfig.i18n && globalConfig.i18n.historyDeleteError
                ? globalConfig.i18n.historyDeleteError
                : 'Impossible de supprimer cet avatar.';
            this.updateStatus(unauthorizedMessage, 'error');
            return;
        }

        var targetId = parseInt(attachmentId, 10);
        if (!targetId || Number.isNaN(targetId)) {
            this.updateStatus(globalConfig.i18n && globalConfig.i18n.historyDeleteError
                ? globalConfig.i18n.historyDeleteError
                : 'Impossible de supprimer cet avatar.', 'error');
            return;
        }

        if (!globalConfig.deleteAvatarNonce) {
            this.updateStatus(globalConfig.i18n && globalConfig.i18n.historyDeleteError
                ? globalConfig.i18n.historyDeleteError
                : 'Impossible de supprimer cet avatar.', 'error');
            return;
        }

        if (this.isSubmitting || this.isApplyingAvatar) {
            return;
        }

        this.isApplyingAvatar = true;
        this.refreshUi();
        this.updateStatus(globalConfig.i18n && globalConfig.i18n.historyDeletePending
            ? globalConfig.i18n.historyDeletePending
            : 'Suppression en cours…', 'info');

        var formData = new FormData();
        formData.append('action', 'mj_member_delete_grimlins_avatar');
        formData.append('nonce', globalConfig.deleteAvatarNonce);
        formData.append('attachmentId', String(targetId));

        var _this = this;

        fetch(globalConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || typeof payload !== 'object') {
                    throw new Error('Payload vide');
                }
                if (!payload.success) {
                    var errorMessage = payload.data && payload.data.message
                        ? payload.data.message
                        : (globalConfig.i18n ? globalConfig.i18n.historyDeleteError : 'Impossible de supprimer cet avatar.');
                    throw new Error(errorMessage);
                }

                if (payload.data && payload.data.nonce) {
                    globalConfig.deleteAvatarNonce = payload.data.nonce;
                }

                var deletedAttachmentId = payload.data && payload.data.attachmentId
                    ? parseInt(payload.data.attachmentId, 10)
                    : targetId;
                if (deletedAttachmentId && !Number.isNaN(deletedAttachmentId)) {
                    _this.removeHistoryItem(deletedAttachmentId);
                }

                var successMessage = payload.data && payload.data.message
                    ? payload.data.message
                    : (globalConfig.i18n ? globalConfig.i18n.historyDeleteSuccess : 'Avatar supprimé.');
                _this.updateStatus(successMessage, 'success');
            })
            .catch(function(error) {
                if (error && typeof error.json === 'function') {
                    error.json().then(function(details) {
                        var message = details && details.data && details.data.message
                            ? details.data.message
                            : (globalConfig.i18n ? globalConfig.i18n.historyDeleteError : 'Impossible de supprimer cet avatar.');
                        _this.updateStatus(message, 'error');
                    }).catch(function() {
                        _this.updateStatus(error.message || (globalConfig.i18n ? globalConfig.i18n.historyDeleteError : 'Impossible de supprimer cet avatar.'), 'error');
                    });
                    return;
                }
                var message = error && error.message
                    ? error.message
                    : (globalConfig.i18n ? globalConfig.i18n.historyDeleteError : 'Impossible de supprimer cet avatar.');
                _this.updateStatus(message, 'error');
            })
            .finally(function() {
                _this.isApplyingAvatar = false;
                _this.refreshUi();
            });
    };

    PhotoGrimlins.prototype.removeHistoryItem = function(attachmentId) {
        var targetId = parseInt(attachmentId, 10);
        if (!targetId || Number.isNaN(targetId) || !Array.isArray(this.history)) {
            return;
        }

        var nextHistory = [];
        for (var i = 0; i < this.history.length; i += 1) {
            var entry = this.history[i];
            if (!entry || typeof entry !== 'object') {
                continue;
            }
            if (entry.id === targetId) {
                continue;
            }
            nextHistory.push(entry);
        }

        this.setHistory(nextHistory, {
            historyCount: nextHistory.length,
            memberLimit: this.historyLimit,
            limitReached: this.historyLimit > 0 && nextHistory.length >= this.historyLimit && !this.config.isPreview
        });
    };

    PhotoGrimlins.prototype.renderResult = function(data) {
        this.setPreviewLoading(false);
        this.lastResult = (data && typeof data === 'object') ? data : null;

        if (!this.lastResult) {
            if (this.dom.resultBox) {
                this.dom.resultBox.hidden = true;
            }
            if (this.dom.downloadLink) {
                this.dom.downloadLink.classList.add('is-hidden');
                this.dom.downloadLink.removeAttribute('href');
            }
            this.updateApplyAvatarButton();
            this.updateCtaRegisterButton();
            return;
        }

        var result = this.lastResult;

        if (this.dom.resultImg && result.imageUrl) {
            this.dom.resultImg.src = result.imageUrl;
            this.dom.resultImg.alt = result.prompt || 'Résultat Grimlins';
        }
        if (this.dom.resultBox) {
            this.dom.resultBox.hidden = !result.imageUrl;
        }
        if (this.dom.downloadLink) {
            if (result.imageUrl) {
                this.dom.downloadLink.href = result.imageUrl;
                this.dom.downloadLink.download = result.downloadName || 'grimlins.png';
                this.dom.downloadLink.classList.remove('is-hidden');
            } else {
                this.dom.downloadLink.classList.add('is-hidden');
                this.dom.downloadLink.removeAttribute('href');
            }
        }
        this.root.classList.add('has-result');
        this.updateApplyAvatarButton();
        this.updateCtaRegisterButton();
    };

    function mount(root) {
        if (!root || instances.has(root)) {
            return;
        }
        instances.set(root, new PhotoGrimlins(root));
    }

    function scan(container) {
        if (!container || !container.querySelectorAll) {
            return;
        }
        var nodes = container.querySelectorAll('[data-mj-photo-grimlins]');
        if (!nodes.length) {
            return;
        }
        nodes.forEach(mount);
    }

    function initWizardTabs(context) {
        var containers = (context || document).querySelectorAll('.mj-photo-grimlins-nsub--tabs');
        var inlineTablists = (context || document).querySelectorAll('[data-mj-photo-grimlins] .mj-photo-grimlins-tabs');
        var allContainers = [];

        if (containers && containers.length) {
            containers.forEach(function(container) {
                allContainers.push(container);
            });
        }
        if (inlineTablists && inlineTablists.length) {
            inlineTablists.forEach(function(tablist) {
                allContainers.push(tablist);
            });
        }
        if (!allContainers.length) {
            return;
        }

        allContainers.forEach(function(container) {
            var buttons = container.querySelectorAll('[data-mj-pg-tab-btn]');
            if (!buttons || !buttons.length) {
                return;
            }

            var widgetRoot = container.closest('[data-mj-photo-grimlins]');
            var widgetInstance = widgetRoot ? instances.get(widgetRoot) : null;

            var setActiveButton = function(btn) {
                var panelId = btn.getAttribute('aria-controls');
                var targetMemberAttr = btn.getAttribute('data-mj-pg-target-member');
                var targetMemberLabel = btn.getAttribute('data-mj-pg-member-label');
                var targetMemberId = parseInt(targetMemberAttr, 10);
                if (Number.isNaN(targetMemberId)) {
                    targetMemberId = 0;
                }

                buttons.forEach(function(b) {
                    b.classList.remove('mj-photo-grimlins-tabs__btn--active');
                    b.setAttribute('aria-selected', 'false');
                    b.setAttribute('tabindex', '-1');
                });
                btn.classList.add('mj-photo-grimlins-tabs__btn--active');
                btn.setAttribute('aria-selected', 'true');
                btn.removeAttribute('tabindex');

                if (panelId) {
                    var panels = container.querySelectorAll('[role="tabpanel"]');
                    panels.forEach(function(panel) {
                        panel.hidden = panel.id !== panelId;
                    });
                }

                if (widgetRoot) {
                    widgetRoot.setAttribute('data-target-member-id', String(targetMemberId));
                }
                if (widgetInstance && widgetInstance.config) {
                    widgetInstance.config.targetMemberId = targetMemberId;
                }
                if (widgetInstance && typeof widgetInstance.switchTargetMember === 'function') {
                    widgetInstance.switchTargetMember(targetMemberId, targetMemberLabel);
                }
            };

            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    setActiveButton(btn);
                });
            });

            // Keyboard navigation (arrows)
            buttons.forEach(function(btn, idx) {
                btn.addEventListener('keydown', function(event) {
                    var key = event.key;
                    var nextIdx = -1;
                    if (key === 'ArrowRight' || key === 'ArrowDown') {
                        nextIdx = (idx + 1) % buttons.length;
                    } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
                        nextIdx = (idx - 1 + buttons.length) % buttons.length;
                    } else if (key === 'Home') {
                        nextIdx = 0;
                    } else if (key === 'End') {
                        nextIdx = buttons.length - 1;
                    }
                    if (nextIdx >= 0) {
                        event.preventDefault();
                        buttons[nextIdx].click();
                        buttons[nextIdx].focus();
                    }
                });
            });

            var initial = container.querySelector('[data-mj-pg-tab-btn][aria-selected="true"]') || buttons[0];
            if (initial) {
                setActiveButton(initial);
            }
        });
    }

    domReady(function() {
        scan(document);
        initWizardTabs(document);
    });

    if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-photo-grimlins.default', function(scope) {
            scan(scope);
            initWizardTabs(scope);
        });
    }
})();
