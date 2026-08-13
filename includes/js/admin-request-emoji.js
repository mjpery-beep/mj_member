(function (window, document) {
    'use strict';

    var preact = window.preact || null;
    var emojiModule = window.MjRegMgrEmojiPicker || window.MjRegMgrEmojiHelper || null;

    if (!preact || !preact.h || !preact.render || !emojiModule || !emojiModule.EmojiPickerField) {
        return;
    }

    function getString(key, fallback) {
        var strings = window.mjRequestAdminEmoji || {};
        return typeof strings[key] === 'string' && strings[key] !== '' ? strings[key] : fallback;
    }

    function sanitizeEmojiInput(value) {
        if (typeof value !== 'string') {
            return '';
        }

        return value.replace(/[\x00-\x1F\x7F]+/g, '').trim().slice(0, 16);
    }

    function mountField(field) {
        var input = field.querySelector('[data-emoji-input]');
        var mount = field.querySelector('[data-emoji-picker-root]');
        if (!input || !mount) {
            return;
        }

        field.classList.add('mj-form-field--emoji-enhanced');

        var h = preact.h;
        var render = preact.render;
        var EmojiPickerField = emojiModule.EmojiPickerField;
        var value = sanitizeEmojiInput(input.value || '');
        var strings = {
            eventEmojiPlaceholder: getString('eventEmojiPlaceholder', 'Ex : 🎯'),
            eventEmojiPicker: getString('eventEmojiPicker', 'Choisir'),
            eventEmojiPickerClose: getString('eventEmojiPickerClose', 'Fermer'),
            eventEmojiClear: getString('eventEmojiClear', 'Effacer'),
            eventEmojiSuggestions: getString('eventEmojiSuggestions', 'Suggestions'),
            eventEmojiSearchPlaceholder: getString('eventEmojiSearchPlaceholder', 'Rechercher un emoji'),
            eventEmojiSearchNoResult: getString('eventEmojiSearchNoResult', 'Aucun emoji ne correspond à votre recherche.'),
            eventEmojiAllCategory: getString('eventEmojiAllCategory', 'Tout')
        };

        function applyValue(nextValue) {
            value = sanitizeEmojiInput(nextValue);
            input.value = value;
            renderField();
        }

        function renderField() {
            render(h(EmojiPickerField, {
                value: value,
                onChange: applyValue,
                strings: strings,
                labels: strings,
                disabled: false,
                fallbackPlaceholder: strings.eventEmojiPlaceholder,
                'aria-describedby': input.getAttribute('aria-describedby') || undefined
            }), mount);
        }

        renderField();
    }

    function init() {
        var fields = document.querySelectorAll('.mj-request-admin [data-emoji-field]');
        Array.prototype.forEach.call(fields, mountField);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window, document));
