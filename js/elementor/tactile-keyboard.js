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

    function isTextInput(element) {
        if (!element || !(element instanceof HTMLElement)) {
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

    function TactileKeyboard(root) {
        this.root = root;
        this.config = parseConfig(root);
        this.layout = LAYOUTS[this.config.layout] || LAYOUTS.belgium;
        this.keyboardNode = root.querySelector('[data-role="keyboard"]');
        this.emojiNode = root.querySelector('[data-role="emoji"]');
        this.numpadNode = root.querySelector('[data-role="numpad"]');
        this.displayInput = root.querySelector('[data-role="display"]');
        this.modeBadge = root.querySelector('[data-role="mode-badge"]');
        this.mode = this.config.mode === 'emoji' ? 'emoji' : 'keyboard';
        this.symbols = false;
        this.shift = false;
        this.emojiGroup = 'smileys';
        this.lastTarget = this.displayInput || null;

        this.bind();
        this.render();
    }

    TactileKeyboard.prototype.bind = function () {
        var self = this;

        document.addEventListener('focusin', function (event) {
            if (isTextInput(event.target)) {
                self.lastTarget = event.target;
            }
        }, true);

        this.root.addEventListener('pointerdown', function (event) {
            if (event.target.closest('.mj-tactile-keyboard__key') || event.target.closest('.mj-tactile-keyboard__emoji-tab')) {
                event.preventDefault();
            }
        });

        this.root.addEventListener('click', function (event) {
            var tab = event.target.closest('.mj-tactile-keyboard__emoji-tab');
            if (tab) {
                self.emojiGroup = tab.dataset.group || 'smileys';
                self.renderEmoji();
                return;
            }

            var key = event.target.closest('.mj-tactile-keyboard__key');
            if (!key) {
                return;
            }

            if (key.dataset.action) {
                self.handleAction(key.dataset.action);
                return;
            }

            if (key.dataset.value) {
                self.insertValue(key.dataset.value);
            }
        });
    };

    TactileKeyboard.prototype.render = function () {
        this.root.dataset.view = this.mode;

        if (this.modeBadge) {
            this.modeBadge.textContent = this.mode === 'emoji' ? 'Emojis' : 'Clavier';
        }

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