(function () {
    'use strict';

    var ROOT_SELECTOR = '.mj-tactile-keyboard[data-config]';

    var EMOJI_GROUPS = {
        smileys: {
            label: 'Visages',
            items: ['😀', '😁', '😂', '🙂', '😉', '😍', '🤩', '😎', '🤔', '😴', '😭', '😡']
        },
        gestures: {
            label: 'Gestes',
            items: ['👍', '👎', '👏', '🙌', '👋', '✌️', '🤝', '🙏', '💪', '👌', '🤞', '🫶']
        },
        nature: {
            label: 'Nature',
            items: ['🌿', '🌸', '🌞', '🌈', '🔥', '⭐', '🌍', '🍀', '🌊', '❄️', '🍎', '🍕']
        },
        symbols: {
            label: 'Symboles',
            items: ['❤️', '💛', '💚', '💙', '💜', '✨', '🎉', '✅', '❌', '⚠️', '➕', '➜']
        }
    };

    var LAYOUTS = {
        belgium: {
            label: 'Belgique',
            letters: [
                ['a', 'z', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
                ['q', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm'],
                ['w', 'x', 'c', 'v', 'b', 'n']
            ],
            numbers: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            symbols: [
                ['&', 'é', '"', "'", '(', '§', 'è', '!', 'ç', 'à'],
                ['@', '#', '€', '-', '+', '=', '/', '*', '?', '.'],
                [';', ':', ',', '_', '(', ')']
            ],
            decimal: ','
        },
        france: {
            label: 'France',
            letters: [
                ['a', 'z', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
                ['q', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm'],
                ['w', 'x', 'c', 'v', 'b', 'n']
            ],
            numbers: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            symbols: [
                ['&', 'é', '"', "'", '(', '-', 'è', '_', 'ç', 'à'],
                ['@', '#', '€', '+', '=', '/', '*', '?', '!', '.'],
                [';', ':', ',', '(', ')', '%']
            ],
            decimal: ','
        },
        us: {
            label: 'US',
            letters: [
                ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
                ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
                ['z', 'x', 'c', 'v', 'b', 'n', 'm']
            ],
            numbers: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            symbols: [
                ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')'],
                ['-', '_', '=', '+', '/', '|', '[', ']', '?', '.'],
                [';', ':', ',', '"', "'", '`']
            ],
            decimal: '.'
        }
    };

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function initAll(scope) {
        var roots = (scope || document).querySelectorAll(ROOT_SELECTOR);
        roots.forEach(function (root) {
            if (root.dataset.keyboardReady === 'yes') {
                return;
            }

            root.dataset.keyboardReady = 'yes';
            new TactileKeyboard(root);
        });
    }

    function parseConfig(root) {
        try {
            var parsed = JSON.parse(root.dataset.config || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function isElementInteractive(element) {
        var style;

        if (!element || !(element instanceof HTMLElement) || !element.isConnected) {
            return false;
        }

        if (element.closest('[hidden], [aria-hidden="true"], .is-hidden')) {
            return false;
        }

        style = window.getComputedStyle(element);
        if (!style || style.display === 'none' || style.visibility === 'hidden') {
            return false;
        }

        return element.getClientRects().length > 0;
    }

    function isTextInput(element) {
        if (!element || !(element instanceof HTMLElement)) {
            return false;
        }

        if (!isElementInteractive(element)) {
            return false;
        }

        if (element instanceof HTMLTextAreaElement) {
            return !element.disabled && !element.readOnly;
        }

        if (element instanceof HTMLInputElement) {
            var type = (element.type || 'text').toLowerCase();
            var supportedTypes = ['text', 'search', 'url', 'tel', 'password', 'email', 'number'];
            return supportedTypes.indexOf(type) !== -1 && !element.disabled && !element.readOnly;
        }

        return element.isContentEditable;
    }

    function isFormTextField(element) {
        if (!element || !(element instanceof HTMLElement)) {
            return false;
        }

        if (!isElementInteractive(element)) {
            return false;
        }

        if (element instanceof HTMLTextAreaElement) {
            return !element.disabled && !element.readOnly;
        }

        if (element instanceof HTMLInputElement) {
            var type = (element.type || 'text').toLowerCase();
            var supportedTypes = ['text', 'search', 'url', 'tel', 'password', 'email', 'number'];
            return supportedTypes.indexOf(type) !== -1 && !element.disabled && !element.readOnly;
        }

        return false;
    }

    function createRow() {
        var row = document.createElement('div');
        row.className = 'mj-tactile-keyboard__row';
        return row;
    }

    function createButton(options) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'mj-tactile-keyboard__key';
        button.textContent = options.label;

        if (options.value) {
            button.dataset.value = options.value;
        }

        if (options.action) {
            button.dataset.action = options.action;
            button.classList.add('mj-tactile-keyboard__key--action');
        }

        if (options.size) {
            button.classList.add('mj-tactile-keyboard__key--' + options.size);
        }

        if (options.active) {
            button.classList.add('is-active');
        }

        if (options.emoji) {
            button.classList.add('mj-tactile-keyboard__key--emoji');
        }

        if (options.title) {
            button.title = options.title;
            button.setAttribute('aria-label', options.title);
        }

        return button;
    }

    function parseNumber(value, fallback) {
        var parsed = parseFloat(value);
        return isFinite(parsed) ? parsed : fallback;
    }

    function isDragBlockedTarget(target) {
        if (!target || !(target instanceof HTMLElement)) {
            return true;
        }

        return Boolean(target.closest('button, input, textarea, select, option, a, [contenteditable="true"], .mj-tactile-keyboard__key, .mj-tactile-keyboard__emoji-tab'));
    }

    function createAudioEngine() {
        var AudioContextConstructor = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextConstructor) {
            return null;
        }

        return {
            context: null,
            getContext: function () {
                if (!this.context) {
                    this.context = new AudioContextConstructor();
                }

                if (this.context.state === 'suspended' && typeof this.context.resume === 'function') {
                    this.context.resume();
                }

                return this.context;
            },
            play: function (tone) {
                var context;
                var oscillator;
                var gain;
                var startAt;
                var stopAt;

                try {
                    context = this.getContext();
                } catch (error) {
                    return;
                }

                if (!context) {
                    return;
                }

                oscillator = context.createOscillator();
                gain = context.createGain();
                startAt = context.currentTime;
                stopAt = startAt + 0.05;

                oscillator.type = tone === 'action' ? 'triangle' : 'sine';
                oscillator.frequency.setValueAtTime(tone === 'action' ? 420 : 520, startAt);

                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(0.035, startAt + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, stopAt);

                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(startAt);
                oscillator.stop(stopAt);
            }
        };
    }

    function TactileKeyboard(root) {
        this.root = root;
        this.config = parseConfig(root);
        this.layout = LAYOUTS[this.config.layout] || LAYOUTS.belgium;
        this.keyboardNode = root.querySelector('[data-role="keyboard"]');
        this.emojiNode = root.querySelector('[data-role="emoji"]');
        this.numpadNode = root.querySelector('[data-role="numpad"]');
        this.displayInput = root.querySelector('[data-role="display"]');
        this.mode = this.config.mode === 'emoji' ? 'emoji' : 'keyboard';
        this.symbols = false;
        this.shift = false;
        this.emojiGroup = 'smileys';
        this.lastTarget = this.displayInput || null;
        this.audio = createAudioEngine();
        this.keepVisible = root.dataset.preview === 'yes';
        this.dragState = {
            pointerId: null,
            startClientX: 0,
            startClientY: 0,
            startDragX: 0,
            startDragY: 0,
            startRect: null
        };

        this.bind();
        this.render();
        this.syncVisibility(document.activeElement);
    }

    TactileKeyboard.prototype.bind = function () {
        var self = this;
        var shell = this.root.querySelector('.mj-tactile-keyboard__shell');

        document.addEventListener('focusin', function (event) {
            if (isTextInput(event.target)) {
                self.lastTarget = event.target;
            }

            self.syncVisibility(event.target);
        }, true);

        document.addEventListener('focusout', function () {
            window.setTimeout(function () {
                self.syncVisibility(document.activeElement);
            }, 0);
        }, true);

        this.root.addEventListener('pointerdown', function (event) {
            if (event.target.closest('.mj-tactile-keyboard__key') || event.target.closest('.mj-tactile-keyboard__emoji-tab')) {
                event.preventDefault();
            }
        });

        this.root.addEventListener('click', function (event) {
            var tab = event.target.closest('.mj-tactile-keyboard__emoji-tab');
            if (tab) {
                self.playKeySound('action');
                self.emojiGroup = tab.dataset.group || 'smileys';
                self.renderEmoji();
                return;
            }

            var key = event.target.closest('.mj-tactile-keyboard__key');
            if (!key) {
                return;
            }

            self.playKeySound(key.dataset.action ? 'action' : 'key');

            if (key.dataset.action) {
                self.handleAction(key.dataset.action);
                return;
            }

            if (key.dataset.value) {
                self.insertValue(key.dataset.value);
            }
        });

        if (shell) {
            shell.addEventListener('pointerdown', function (event) {
                self.onDragStart(event, shell);
            });
            shell.addEventListener('pointermove', function (event) {
                self.onDragMove(event, shell);
            });
            shell.addEventListener('pointerup', function (event) {
                self.onDragEnd(event, shell);
            });
            shell.addEventListener('pointercancel', function (event) {
                self.onDragEnd(event, shell);
            });
        }
    };

    TactileKeyboard.prototype.onDragStart = function (event, shell) {
        if (!event || !shell) {
            return;
        }

        if (event.button !== undefined && event.button !== 0) {
            return;
        }

        if (isDragBlockedTarget(event.target)) {
            return;
        }

        this.dragState.pointerId = event.pointerId;
        this.dragState.startClientX = event.clientX;
        this.dragState.startClientY = event.clientY;
        this.dragState.startDragX = parseNumber(this.root.dataset.dragX, 0);
        this.dragState.startDragY = parseNumber(this.root.dataset.dragY, 0);
        this.dragState.startRect = this.root.getBoundingClientRect();

        this.root.classList.add('is-dragging');

        if (typeof shell.setPointerCapture === 'function') {
            shell.setPointerCapture(event.pointerId);
        }

        event.preventDefault();
    };

    TactileKeyboard.prototype.onDragMove = function (event) {
        var dx;
        var dy;
        var requestedX;
        var requestedY;
        var minLeft = 8;
        var minTop = 8;
        var startRect;
        var maxLeft;
        var maxTop;
        var nextLeft;
        var nextTop;
        var clampedLeft;
        var clampedTop;

        if (this.dragState.pointerId !== event.pointerId || !this.dragState.startRect) {
            return;
        }

        dx = event.clientX - this.dragState.startClientX;
        dy = event.clientY - this.dragState.startClientY;
        requestedX = this.dragState.startDragX + dx;
        requestedY = this.dragState.startDragY + dy;

        startRect = this.dragState.startRect;
        maxLeft = Math.max(minLeft, window.innerWidth - startRect.width - minLeft);
        maxTop = Math.max(minTop, window.innerHeight - startRect.height - minTop);

        nextLeft = startRect.left + (requestedX - this.dragState.startDragX);
        nextTop = startRect.top + (requestedY - this.dragState.startDragY);
        clampedLeft = clamp(nextLeft, minLeft, maxLeft);
        clampedTop = clamp(nextTop, minTop, maxTop);

        requestedX = this.dragState.startDragX + (clampedLeft - startRect.left);
        requestedY = this.dragState.startDragY + (clampedTop - startRect.top);

        this.root.dataset.dragX = String(requestedX);
        this.root.dataset.dragY = String(requestedY);
        this.root.style.setProperty('--mj-tactile-drag-x', requestedX + 'px');
        this.root.style.setProperty('--mj-tactile-drag-y', requestedY + 'px');
        this.root.style.transform = 'translate(' + requestedX + 'px, ' + requestedY + 'px)';

        event.preventDefault();
    };

    TactileKeyboard.prototype.onDragEnd = function (event, shell) {
        if (this.dragState.pointerId !== event.pointerId) {
            return;
        }

        if (shell && typeof shell.releasePointerCapture === 'function') {
            shell.releasePointerCapture(event.pointerId);
        }

        this.dragState.pointerId = null;
        this.dragState.startRect = null;
        this.root.classList.remove('is-dragging');
    };

    TactileKeyboard.prototype.playKeySound = function (tone) {
        if (!this.audio) {
            return;
        }

        this.audio.play(tone);
    };

    TactileKeyboard.prototype.syncVisibility = function (activeElement) {
        if (this.keepVisible) {
            this.root.classList.add('is-active');
            this.root.setAttribute('aria-hidden', 'false');
            return;
        }

        var shouldShow = isFormTextField(activeElement);
        this.root.classList.toggle('is-active', shouldShow);
        this.root.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
    };

    TactileKeyboard.prototype.render = function () {
        this.root.dataset.view = this.mode;

        this.renderKeyboard();
        this.renderEmoji();
        this.renderNumpad();
    };

    TactileKeyboard.prototype.renderKeyboard = function () {
        var self = this;
        this.keyboardNode.innerHTML = '';

        if (this.mode === 'emoji') {
            return;
        }

        if (this.config.showFunctionKeys) {
            var functionRow = createRow();
            functionRow.appendChild(createButton({ action: 'f1', label: 'F1', title: 'Touche F1' }));
            functionRow.appendChild(createButton({ action: 'f2', label: 'F2', title: 'Touche F2' }));
            this.keyboardNode.appendChild(functionRow);
        }

        if (this.config.showNumericRow) {
            var numberRow = createRow();
            this.layout.numbers.forEach(function (value) {
                numberRow.appendChild(createButton({ value: value, label: value }));
            });
            this.keyboardNode.appendChild(numberRow);
        }

        var rows = this.symbols ? this.layout.symbols : this.layout.letters;
        rows.forEach(function (rowValues, rowIndex) {
            var row = createRow();

            if (rowIndex === rows.length - 1) {
                row.appendChild(createButton({
                    action: 'shift',
                    label: self.shift ? 'MAJ' : 'Maj',
                    size: 'wide',
                    active: self.shift,
                    title: 'Majuscule'
                }));
            }

            rowValues.forEach(function (value) {
                var label = self.shift && !self.symbols ? value.toUpperCase() : value;
                row.appendChild(createButton({ value: label, label: label }));
            });

            if (rowIndex === rows.length - 1) {
                row.appendChild(createButton({ action: 'backspace', label: '⌫', size: 'wide', title: 'Effacer' }));
            }

            self.keyboardNode.appendChild(row);
        });

        var utilityRow = createRow();
        if (this.config.mode === 'toggle') {
            utilityRow.appendChild(createButton({ action: 'emoji', label: '😊', title: 'Basculer vers le selecteur d\'emoji' }));
        }

        utilityRow.appendChild(createButton({
            action: this.symbols ? 'letters' : 'symbols',
            label: this.symbols ? 'ABC' : '123',
            size: 'wide',
            title: this.symbols ? 'Revenir aux lettres' : 'Afficher les symboles'
        }));
        utilityRow.appendChild(createButton({ action: 'space', label: 'Espace', size: 'space', title: 'Espace' }));
        utilityRow.appendChild(createButton({ action: 'enter', label: 'Entree', size: 'wide', title: 'Entree' }));
        this.keyboardNode.appendChild(utilityRow);

        if (this.config.showArrows) {
            var arrowsRow = createRow();
            arrowsRow.appendChild(createButton({ action: 'arrow-left', label: '←', title: 'Fleche gauche' }));
            arrowsRow.appendChild(createButton({ action: 'arrow-up', label: '↑', title: 'Fleche haut' }));
            arrowsRow.appendChild(createButton({ action: 'arrow-down', label: '↓', title: 'Fleche bas' }));
            arrowsRow.appendChild(createButton({ action: 'arrow-right', label: '→', title: 'Fleche droite' }));
            this.keyboardNode.appendChild(arrowsRow);
        }
    };

    TactileKeyboard.prototype.renderEmoji = function () {
        var self = this;
        this.emojiNode.innerHTML = '';

        if (this.mode !== 'emoji') {
            return;
        }

        var tabs = document.createElement('div');
        tabs.className = 'mj-tactile-keyboard__emoji-tabs';

        Object.keys(EMOJI_GROUPS).forEach(function (groupName) {
            var tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'mj-tactile-keyboard__emoji-tab';
            tab.dataset.group = groupName;
            tab.textContent = EMOJI_GROUPS[groupName].label;
            if (groupName === self.emojiGroup) {
                tab.classList.add('is-active');
            }
            tabs.appendChild(tab);
        });

        var grid = document.createElement('div');
        grid.className = 'mj-tactile-keyboard__emoji-grid';

        EMOJI_GROUPS[this.emojiGroup].items.forEach(function (emoji) {
            grid.appendChild(createButton({ value: emoji, label: emoji, emoji: true, title: 'Inserer ' + emoji }));
        });

        this.emojiNode.appendChild(tabs);
        this.emojiNode.appendChild(grid);

        if (this.config.mode === 'toggle') {
            var footer = document.createElement('div');
            footer.className = 'mj-tactile-keyboard__emoji-footer';
            footer.appendChild(createButton({ action: 'keyboard', label: 'ABC', size: 'wide', title: 'Retour au clavier' }));
            this.emojiNode.appendChild(footer);
        }
    };

    TactileKeyboard.prototype.renderNumpad = function () {
        var self = this;
        this.numpadNode.innerHTML = '';

        if (!this.config.showNumpad || this.mode === 'emoji') {
            return;
        }

        ['7', '8', '9', '4', '5', '6', '1', '2', '3', '0', this.layout.decimal, '⌫'].forEach(function (value) {
            if (value === '⌫') {
                self.numpadNode.appendChild(createButton({ action: 'backspace', label: value, title: 'Effacer' }));
                return;
            }

            self.numpadNode.appendChild(createButton({ value: value, label: value }));
        });
    };

    TactileKeyboard.prototype.handleAction = function (action) {
        switch (action) {
            case 'shift':
                this.shift = !this.shift;
                this.renderKeyboard();
                return;
            case 'backspace':
                this.deleteBackward();
                return;
            case 'space':
                this.insertValue(' ');
                return;
            case 'enter':
                this.handleEnter();
                return;
            case 'symbols':
                this.symbols = true;
                this.shift = false;
                this.renderKeyboard();
                return;
            case 'letters':
            case 'keyboard':
                this.mode = 'keyboard';
                this.symbols = false;
                this.render();
                return;
            case 'emoji':
                this.mode = 'emoji';
                this.symbols = false;
                this.render();
                return;
            case 'arrow-left':
                this.moveCaret(-1);
                return;
            case 'arrow-right':
                this.moveCaret(1);
                return;
            case 'arrow-up':
                this.dispatchSpecialKey('ArrowUp');
                return;
            case 'arrow-down':
                this.dispatchSpecialKey('ArrowDown');
                return;
            case 'f1':
                this.dispatchSpecialKey('F1');
                return;
            case 'f2':
                this.dispatchSpecialKey('F2');
                return;
            default:
                return;
        }
    };

    TactileKeyboard.prototype.getTarget = function () {
        if (isTextInput(this.lastTarget)) {
            return this.lastTarget;
        }

        if (isTextInput(this.displayInput)) {
            this.lastTarget = this.displayInput;
            return this.displayInput;
        }

        return null;
    };

    TactileKeyboard.prototype.insertValue = function (value) {
        var target = this.getTarget();
        if (!target) {
            return;
        }

        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
            var valueText = target.value || '';
            var start = typeof target.selectionStart === 'number' ? target.selectionStart : valueText.length;
            var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : valueText.length;

            target.focus({ preventScroll: true });

            if (typeof target.setRangeText === 'function') {
                target.setRangeText(value, start, end, 'end');
            } else {
                target.value = valueText.slice(0, start) + value + valueText.slice(end);
                target.setSelectionRange(start + value.length, start + value.length);
            }

            target.dispatchEvent(new Event('input', { bubbles: true }));
        } else if (target.isContentEditable) {
            target.focus({ preventScroll: true });
            if (document.queryCommandSupported && document.queryCommandSupported('insertText')) {
                document.execCommand('insertText', false, value);
            } else {
                target.textContent += value;
            }
            target.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (this.shift && !this.symbols) {
            this.shift = false;
            this.renderKeyboard();
        }
    };

    TactileKeyboard.prototype.deleteBackward = function () {
        var target = this.getTarget();
        if (!target) {
            return;
        }

        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
            var valueText = target.value || '';
            var start = typeof target.selectionStart === 'number' ? target.selectionStart : valueText.length;
            var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : valueText.length;

            if (start === end && start > 0) {
                start -= 1;
            }

            target.focus({ preventScroll: true });

            if (typeof target.setRangeText === 'function') {
                target.setRangeText('', start, end, 'end');
            } else {
                target.value = valueText.slice(0, start) + valueText.slice(end);
                target.setSelectionRange(start, start);
            }

            target.dispatchEvent(new Event('input', { bubbles: true }));
        } else if (target.isContentEditable) {
            target.focus({ preventScroll: true });
            document.execCommand('delete', false);
            target.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    TactileKeyboard.prototype.handleEnter = function () {
        var target = this.getTarget();
        if (!target) {
            return;
        }

        if (target instanceof HTMLTextAreaElement || target.isContentEditable) {
            this.insertValue('\n');
            return;
        }

        this.dispatchSpecialKey('Enter');
    };

    TactileKeyboard.prototype.moveCaret = function (delta) {
        var target = this.getTarget();
        if (!target) {
            return;
        }

        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
            var valueText = target.value || '';
            var current = typeof target.selectionStart === 'number' ? target.selectionStart : valueText.length;
            var next = Math.max(0, Math.min(valueText.length, current + delta));
            target.focus({ preventScroll: true });
            target.setSelectionRange(next, next);
            return;
        }

        this.dispatchSpecialKey(delta < 0 ? 'ArrowLeft' : 'ArrowRight');
    };

    TactileKeyboard.prototype.dispatchSpecialKey = function (key) {
        var target = this.getTarget();
        if (!target) {
            return;
        }

        target.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: key }));
        target.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: key }));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll(document);
        });
    } else {
        initAll(document);
    }

    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', function () {
            if (window.elementorFrontend && window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function (scope) {
                    initAll(scope && scope[0] ? scope[0] : document);
                });
            }
        });
    }
}());