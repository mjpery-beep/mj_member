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
            status: root.querySelector('[data-photo-grimlins="status"]'),
            resetBtn: root.querySelector('[data-photo-grimlins="reset"]'),
            submitBtn: root.querySelector('[data-photo-grimlins="submit"]'),
            cameraBtn: root.querySelector('[data-photo-grimlins="camera"]'),
            cameraModal: root.querySelector('[data-photo-grimlins="camera-modal"]'),
            cameraVideo: root.querySelector('[data-photo-grimlins="camera-video"]'),
            cameraCaptureBtn: root.querySelector('[data-photo-grimlins="camera-capture"]'),
            cameraCancelBtn: root.querySelector('[data-photo-grimlins="camera-cancel"]'),
            historySection: root.querySelector('[data-photo-grimlins="history"]'),
            historyList: root.querySelector('[data-photo-grimlins="history-list"]'),
            historyEmpty: root.querySelector('[data-photo-grimlins="history-empty"]'),
            historyLimit: root.querySelector('[data-photo-grimlins="history-limit"]')
        };

        this.cameraStream = null;
        this.canStreamCamera = this.hasMediaDevices();
        this.cameraSupported = this.isCameraSupported();
        this.boundEscHandler = null;
        this.lastResult = null;
        this.isApplyingAvatar = false;
        this.isPreviewLoading = false;
        this.history = [];
        this.historyLimit = 0;
        this.historyCount = 0;
        this.limitReached = false;
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
            this.setHistory(this.initialHistory.items, {
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

        if (this.dom.historyList) {
            this.dom.historyList.addEventListener('click', function(event) {
                var target = event.target;
                if (!target || typeof target.closest !== 'function') {
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
        if (this.dom.submitBtn) {
            this.dom.submitBtn.disabled = !globalEnabled || busy || limitReached;
            this.dom.submitBtn.classList.toggle('is-disabled-by-limit', limitReached);
        }
        if (this.dom.resetBtn) {
            this.dom.resetBtn.disabled = busy;
        }
        if (this.dom.cameraBtn) {
            this.dom.cameraBtn.disabled = busy || !this.cameraSupported || limitReached;
            this.dom.cameraBtn.classList.toggle('is-hidden', !this.cameraSupported || limitReached);
        }
        if (this.dom.fileInput) {
            this.dom.fileInput.disabled = busy || limitReached;
        }
        if (this.dom.cameraInput) {
            this.dom.cameraInput.disabled = busy || limitReached;
        }
        if (this.dom.dropzone) {
            this.dom.dropzone.classList.toggle('is-disabled', limitReached);
            if (limitReached) {
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
        this.updateApplyAvatarButton();
        this.hideCameraModal();
        this.updateStatus('', '');
    };

    PhotoGrimlins.prototype.showCameraModal = function() {
        if (!this.dom.cameraModal) {
            return;
        }
        this.dom.cameraModal.hidden = false;
        this.dom.cameraModal.setAttribute('aria-hidden', 'false');
        this.dom.cameraModal.classList.add('is-visible');
        this.boundEscHandler = this.boundEscHandler || this.handleEscape.bind(this);
        document.addEventListener('keydown', this.boundEscHandler);
    };

    PhotoGrimlins.prototype.hideCameraModal = function(silent) {
        if (!this.dom.cameraModal) {
            return;
        }
        if (this.boundEscHandler) {
            document.removeEventListener('keydown', this.boundEscHandler);
            this.boundEscHandler = null;
        }
        this.dom.cameraModal.classList.remove('is-visible');
        this.dom.cameraModal.setAttribute('aria-hidden', 'true');
        this.dom.cameraModal.hidden = true;
        if (!silent) {
            this.stopCameraPreview();
        }
        if (this.dom.cameraCaptureBtn) {
            this.dom.cameraCaptureBtn.disabled = false;
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
            }
        }
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

    domReady(function() {
        scan(document);
    });

    if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-photo-grimlins.default', function(scope) {
            scan(scope);
        });
    }
})();
