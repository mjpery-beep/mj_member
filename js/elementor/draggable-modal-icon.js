(function () {
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
            if (typeof callback !== 'function') {
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, { once: true });
            } else {
                callback();
            }
        };

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function initWidget(root) {
        if (!root || root.__mjDraggableModalBound) {
            return;
        }

        var iconButton = root.querySelector('.mj-dmiw__icon-button');
        var modal = root.querySelector('.mj-dmiw__modal');
        var overlay = root.querySelector('.mj-dmiw__overlay');
        var closeButton = root.querySelector('.mj-dmiw__close');
        var dragHandle = root.querySelector('[data-modal-drag-handle="1"]');
        var modalResizable = root.getAttribute('data-modal-resizable') === '1';

        if (!iconButton || !modal || !overlay || !closeButton || !dragHandle) {
            return;
        }

        root.__mjDraggableModalBound = true;

        var modalDrag = {
            pointerId: null,
            startX: 0,
            startY: 0,
            originLeft: 0,
            originTop: 0,
            moved: false,
        };

        var storageKey = 'mj-member:dmiw:' + String(root.getAttribute('data-widget-id') || 'default');

        function readState() {
            try {
                var raw = window.localStorage.getItem(storageKey);
                if (!raw) {
                    return {};
                }
                var parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (error) {
                return {};
            }
        }

        function writeState(nextState) {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(nextState || {}));
            } catch (error) {
                // Ignore storage errors (private mode or quota exceeded).
            }
        }

        function saveModalPosition() {
            var state = readState();
            var left = parseFloat(modal.style.left || '0');
            var top = parseFloat(modal.style.top || '0');
            var width = parseFloat(modal.style.width || '0');
            var height = parseFloat(modal.style.height || '0');
            state.modal = {
                left: isFinite(left) ? left : 0,
                top: isFinite(top) ? top : 0,
            };

            if (modalResizable) {
                state.modal.width = isFinite(width) ? width : 0;
                state.modal.height = isFinite(height) ? height : 0;
            }

            writeState(state);
        }

        function applySavedState() {
            var state = readState();

            if (state.modal && typeof state.modal === 'object') {
                var modalLeft = Number(state.modal.left);
                var modalTop = Number(state.modal.top);
                if (isFinite(modalLeft) && isFinite(modalTop)) {
                    modal.style.left = modalLeft + 'px';
                    modal.style.top = modalTop + 'px';
                    modal.style.transform = 'none';
                }

                if (modalResizable) {
                    var modalWidth = Number(state.modal.width);
                    var modalHeight = Number(state.modal.height);
                    if (isFinite(modalWidth) && modalWidth > 0) {
                        modal.style.width = modalWidth + 'px';
                    }
                    if (isFinite(modalHeight) && modalHeight > 0) {
                        modal.style.height = modalHeight + 'px';
                    }
                }
            }
        }

        function showModal() {
            overlay.hidden = false;
            modal.hidden = false;
            iconButton.classList.add('is-hidden');
            root.classList.add('is-open');
        }

        function hideModal() {
            modal.hidden = true;
            overlay.hidden = true;
            iconButton.classList.remove('is-hidden');
            root.classList.remove('is-open');
        }

        function resetModalPosition() {
            modal.style.left = '';
            modal.style.top = '';
            modal.style.transform = '';
        }

        function makeModalFreePosition() {
            var rect = modal.getBoundingClientRect();
            modal.style.left = rect.left + 'px';
            modal.style.top = rect.top + 'px';
            modal.style.transform = 'none';
        }

        function onIconClick(event) {
            if (modal.hidden) {
                showModal();
            }
        }

        function onModalPointerDown(event) {
            if (event.button !== undefined && event.button !== 0) {
                return;
            }

            if (event.target && event.target.closest('.mj-dmiw__close')) {
                return;
            }

            makeModalFreePosition();

            modalDrag.pointerId = event.pointerId;
            modalDrag.startX = event.clientX;
            modalDrag.startY = event.clientY;
            modalDrag.originLeft = parseFloat(modal.style.left || '0');
            modalDrag.originTop = parseFloat(modal.style.top || '0');
            modalDrag.moved = false;

            if (typeof dragHandle.setPointerCapture === 'function') {
                dragHandle.setPointerCapture(event.pointerId);
            }
        }

        function onModalPointerMove(event) {
            if (modalDrag.pointerId !== event.pointerId) {
                return;
            }

            var dx = event.clientX - modalDrag.startX;
            var dy = event.clientY - modalDrag.startY;
            var nextLeft = modalDrag.originLeft + dx;
            var nextTop = modalDrag.originTop + dy;

            var rect = modal.getBoundingClientRect();
            var maxLeft = Math.max(0, window.innerWidth - rect.width);
            var maxTop = Math.max(0, window.innerHeight - rect.height);

            modal.style.left = clamp(nextLeft, 0, maxLeft) + 'px';
            modal.style.top = clamp(nextTop, 0, maxTop) + 'px';
            modal.style.transform = 'none';
            modalDrag.moved = true;
            event.preventDefault();
        }

        function onModalPointerUp(event) {
            if (modalDrag.pointerId !== event.pointerId) {
                return;
            }

            if (typeof dragHandle.releasePointerCapture === 'function') {
                dragHandle.releasePointerCapture(event.pointerId);
            }

            modalDrag.pointerId = null;
            saveModalPosition();
        }

        function onModalPointerCancel(event) {
            if (modalDrag.pointerId !== event.pointerId) {
                return;
            }
            modalDrag.pointerId = null;
        }

        function onOverlayClick() {
            return;
        }

        function onEscape(event) {
            if (event.key === 'Escape' && !modal.hidden) {
                hideModal();
            }
        }

        function onResize() {
            if (!modal.hidden) {
                var modalRect = modal.getBoundingClientRect();
                if (modal.style.transform === 'none') {
                    var maxLeft = Math.max(0, window.innerWidth - modalRect.width);
                    var maxTop = Math.max(0, window.innerHeight - modalRect.height);
                    modal.style.left = clamp(parseFloat(modal.style.left || '0'), 0, maxLeft) + 'px';
                    modal.style.top = clamp(parseFloat(modal.style.top || '0'), 0, maxTop) + 'px';
                }
            }

            if (modal.style.transform === 'none' && !modal.hidden) {
                saveModalPosition();
            }
        }

        function onWindowMouseUp() {
            if (!modal.hidden && modalResizable) {
                saveModalPosition();
            }
        }

        iconButton.addEventListener('click', onIconClick);

        dragHandle.addEventListener('pointerdown', onModalPointerDown);
        dragHandle.addEventListener('pointermove', onModalPointerMove);
        dragHandle.addEventListener('pointerup', onModalPointerUp);
        dragHandle.addEventListener('pointercancel', onModalPointerCancel);

        closeButton.addEventListener('click', hideModal);
        overlay.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onEscape);
        window.addEventListener('resize', onResize);
        window.addEventListener('mouseup', onWindowMouseUp);

        applySavedState();
    }

    function init(rootNode) {
        var roots = (rootNode || document).querySelectorAll('[data-mj-draggable-modal-widget="1"]');
        for (var i = 0; i < roots.length; i += 1) {
            initWidget(roots[i]);
        }
    }

    domReady(function () {
        init(document);
    });

    if (window.elementorFrontend && typeof window.elementorFrontend.hooks !== 'undefined') {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function (scope) {
            var root = scope && scope[0] ? scope[0] : null;
            if (root) {
                init(root);
            }
        });
    }
})();
