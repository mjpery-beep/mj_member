(function (window, document) {
    'use strict';

    var preact = window.preact || null;
    var emojiModule = window.MjRegMgrEmojiPicker || window.MjRegMgrEmojiHelper || null;

    function strings() {
        var cfg = window.mjRequestRoomCatalog || {};
        return {
            selectPhotoTitle: cfg.selectPhotoTitle || 'Selectionner une photo',
            selectPhotoButton: cfg.selectPhotoButton || 'Utiliser cette photo',
            emptyTitlePlaceholder: cfg.emptyTitlePlaceholder || 'Titre',
            emptyEmojiPlaceholder: cfg.emptyEmojiPlaceholder || 'Emoji',
            photoButton: cfg.photoButton || 'Photo',
            clearButton: cfg.clearButton || 'Retirer',
            removeButton: cfg.removeButton || 'Supprimer',
            emojiPickerPlaceholder: cfg.emojiPickerPlaceholder || 'Ex : 🎯',
            emojiPickerLabel: cfg.emojiPickerLabel || 'Choisir',
            emojiPickerClose: cfg.emojiPickerClose || 'Fermer',
            emojiPickerClear: cfg.emojiPickerClear || 'Effacer',
            emojiPickerSuggestions: cfg.emojiPickerSuggestions || 'Suggestions',
            emojiPickerSearchPlaceholder: cfg.emojiPickerSearchPlaceholder || 'Rechercher un emoji',
            emojiPickerSearchNoResult: cfg.emojiPickerSearchNoResult || 'Aucun emoji ne correspond a votre recherche.',
            emojiPickerAllCategory: cfg.emojiPickerAllCategory || 'Tout'
        };
    }

    function sanitizeEmojiInput(value) {
        if (typeof value !== 'string') {
            return '';
        }

        return value.replace(/[\x00-\x1F\x7F]+/g, '').trim().slice(0, 16);
    }

    function rowTemplate(baseName, index) {
        var t = strings();
        return '' +
            '<div class="mj-request-room-catalog__row" data-room-catalog-row>' +
                '<label class="mj-request-room-catalog__label">' +
                    '<span class="screen-reader-text">Titre</span>' +
                    '<input type="text" class="regular-text" name="' + baseName + '[' + index + '][title]" value="" placeholder="' + t.emptyTitlePlaceholder + '" data-room-catalog-title />' +
                '</label>' +
                '<label class="mj-request-room-catalog__label">' +
                    '<span class="screen-reader-text">Emoji</span>' +
                    '<span class="mj-request-room-catalog__emoji" data-room-catalog-emoji-field>' +
                        '<span class="mj-request-room-catalog__emoji-picker" data-room-catalog-emoji-root></span>' +
                        '<input type="text" class="small-text" name="' + baseName + '[' + index + '][emoji]" value="" placeholder="' + t.emptyEmojiPlaceholder + '" maxlength="16" data-room-catalog-emoji-input />' +
                    '</span>' +
                '</label>' +
                '<div class="mj-request-room-catalog__photo-actions">' +
                    '<input type="hidden" name="' + baseName + '[' + index + '][photo_id]" value="0" data-room-catalog-photo-id />' +
                    '<button type="button" class="button" data-room-catalog-photo-select>' + t.photoButton + '</button> ' +
                    '<button type="button" class="button" data-room-catalog-photo-clear>' + t.clearButton + '</button>' +
                '</div>' +
                '<button type="button" class="button mj-request-room-catalog__remove" data-room-catalog-remove>' + t.removeButton + '</button>' +
                '<div class="mj-request-room-catalog__preview is-empty" data-room-catalog-photo-preview></div>' +
            '</div>';
    }

    function reindex(field) {
        var baseName = field.getAttribute('data-base-name') || '';
        var rows = field.querySelectorAll('[data-room-catalog-row]');
        Array.prototype.forEach.call(rows, function (row, idx) {
            var title = row.querySelector('[data-room-catalog-title]');
            var emoji = row.querySelector('[data-room-catalog-emoji-input]');
            var photoId = row.querySelector('[data-room-catalog-photo-id]');
            if (title) {
                title.name = baseName + '[' + idx + '][title]';
            }
            if (emoji) {
                emoji.name = baseName + '[' + idx + '][emoji]';
            }
            if (photoId) {
                photoId.name = baseName + '[' + idx + '][photo_id]';
            }
        });
    }

    function renderPreview(preview, url) {
        if (!preview) {
            return;
        }

        if (!url) {
            preview.innerHTML = '';
            preview.classList.add('is-empty');
            return;
        }

        preview.innerHTML = '<img src="' + String(url).replace(/"/g, '&quot;') + '" alt="" class="mj-request-room-catalog__thumb" />';
        preview.classList.remove('is-empty');
    }

    function mountEmojiPicker(row) {
        if (!preact || !preact.h || !preact.render || !emojiModule || !emojiModule.EmojiPickerField) {
            return;
        }

        var wrapper = row.querySelector('[data-room-catalog-emoji-field]');
        var input = row.querySelector('[data-room-catalog-emoji-input]');
        var mount = row.querySelector('[data-room-catalog-emoji-root]');
        if (!wrapper || !input || !mount) {
            return;
        }

        if (wrapper.getAttribute('data-emoji-enhanced') === '1') {
            return;
        }

        wrapper.setAttribute('data-emoji-enhanced', '1');

        var h = preact.h;
        var render = preact.render;
        var EmojiPickerField = emojiModule.EmojiPickerField;
        var txt = strings();
        var value = sanitizeEmojiInput(input.value || '');

        function pickerStrings() {
            return {
                eventEmojiPlaceholder: txt.emojiPickerPlaceholder,
                eventEmojiPicker: txt.emojiPickerLabel,
                eventEmojiPickerClose: txt.emojiPickerClose,
                eventEmojiClear: txt.emojiPickerClear,
                eventEmojiSuggestions: txt.emojiPickerSuggestions,
                eventEmojiSearchPlaceholder: txt.emojiPickerSearchPlaceholder,
                eventEmojiSearchNoResult: txt.emojiPickerSearchNoResult,
                eventEmojiAllCategory: txt.emojiPickerAllCategory
            };
        }

        function applyValue(nextValue) {
            value = sanitizeEmojiInput(nextValue);
            input.value = value;
            renderField();
        }

        function renderField() {
            var labels = pickerStrings();
            render(h(EmojiPickerField, {
                value: value,
                onChange: applyValue,
                strings: labels,
                labels: labels,
                disabled: false,
                fallbackPlaceholder: txt.emojiPickerPlaceholder
            }), mount);
        }

        renderField();
    }

    function onAddRow(field) {
        var baseName = field.getAttribute('data-base-name') || '';
        var rowsWrap = field.querySelector('[data-room-catalog-rows]');
        if (!rowsWrap) {
            return;
        }

        var index = rowsWrap.querySelectorAll('[data-room-catalog-row]').length;
        rowsWrap.insertAdjacentHTML('beforeend', rowTemplate(baseName, index));
        reindex(field);

        var rows = rowsWrap.querySelectorAll('[data-room-catalog-row]');
        if (rows.length) {
            mountEmojiPicker(rows[rows.length - 1]);
        }
    }

    function selectPhotoForRow(row) {
        if (!window.wp || !window.wp.media) {
            return;
        }

        var t = strings();
        var input = row.querySelector('[data-room-catalog-photo-id]');
        var preview = row.querySelector('[data-room-catalog-photo-preview]');
        if (!input || !preview) {
            return;
        }

        var frame = window.wp.media({
            title: t.selectPhotoTitle,
            button: { text: t.selectPhotoButton },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first();
            if (!attachment) {
                return;
            }
            var data = attachment.toJSON();
            var id = parseInt(data && data.id ? data.id : 0, 10);
            var url = data && data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url
                ? data.sizes.thumbnail.url
                : (data && data.url ? data.url : '');

            input.value = id > 0 ? String(id) : '0';
            renderPreview(preview, url);
        });

        frame.open();
    }

    function clearPhotoForRow(row) {
        var input = row.querySelector('[data-room-catalog-photo-id]');
        var preview = row.querySelector('[data-room-catalog-photo-preview]');
        if (input) {
            input.value = '0';
        }
        renderPreview(preview, '');
    }

    function mountField(field) {
        var addBtn = field.querySelector('[data-room-catalog-add]');
        var rowsWrap = field.querySelector('[data-room-catalog-rows]');
        if (!addBtn || !rowsWrap) {
            return;
        }

        addBtn.addEventListener('click', function () {
            onAddRow(field);
        });

        field.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.getAttribute) {
                return;
            }

            if (target.hasAttribute('data-room-catalog-remove')) {
                event.preventDefault();
                var row = target.closest('[data-room-catalog-row]');
                if (!row) {
                    return;
                }

                var rows = rowsWrap.querySelectorAll('[data-room-catalog-row]');
                if (rows.length <= 1) {
                    var title = row.querySelector('[data-room-catalog-title]');
                    var emoji = row.querySelector('[data-room-catalog-emoji-input]');
                    if (title) {
                        title.value = '';
                    }
                    if (emoji) {
                        emoji.value = '';
                    }
                    clearPhotoForRow(row);
                    return;
                }

                row.remove();
                reindex(field);
                return;
            }

            if (target.hasAttribute('data-room-catalog-photo-select')) {
                event.preventDefault();
                var selectRow = target.closest('[data-room-catalog-row]');
                if (selectRow) {
                    selectPhotoForRow(selectRow);
                }
                return;
            }

            if (target.hasAttribute('data-room-catalog-photo-clear')) {
                event.preventDefault();
                var clearRow = target.closest('[data-room-catalog-row]');
                if (clearRow) {
                    clearPhotoForRow(clearRow);
                }
            }
        });

        reindex(field);

        var rows = field.querySelectorAll('[data-room-catalog-row]');
        Array.prototype.forEach.call(rows, mountEmojiPicker);
    }

    function init() {
        var fields = document.querySelectorAll('.mj-request-admin [data-room-catalog-field]');
        Array.prototype.forEach.call(fields, mountField);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window, document));
