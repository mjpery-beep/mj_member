(function (window, document) {
    'use strict';

    function getStrings() {
        var cfg = window.mjRequestRoomMedia || {};
        return {
            title: cfg.title || 'Selectionner des images de salle',
            button: cfg.button || 'Utiliser ces images'
        };
    }

    function parseIds(raw) {
        if (typeof raw !== 'string' || raw.trim() === '') {
            return [];
        }

        var out = [];
        var seen = {};
        raw.split(',').forEach(function (part) {
            var id = parseInt((part || '').trim(), 10);
            if (id > 0 && !seen[id]) {
                seen[id] = true;
                out.push(id);
            }
        });

        return out;
    }

    function renderPreview(preview, ids, urlsById) {
        if (!preview) {
            return;
        }

        if (!ids.length) {
            preview.innerHTML = '';
            return;
        }

        var html = ids.map(function (id) {
            var url = urlsById[id] || '';
            if (!url) {
                return '';
            }
            return '' +
                '<span class="mj-request-room-media__item" data-room-media-id="' + id + '">' +
                    '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" class="mj-request-room-media__thumb" />' +
                    '<button type="button" class="button button-small" data-room-media-remove="' + id + '">×</button>' +
                '</span>';
        }).join('');

        preview.innerHTML = html;
    }

    function mountField(field) {
        var input = field.querySelector('[data-room-media-input]');
        var preview = field.querySelector('[data-room-media-preview]');
        var selectBtn = field.querySelector('[data-room-media-select]');
        var clearBtn = field.querySelector('[data-room-media-clear]');

        if (!input || !preview || !selectBtn || !clearBtn || !window.wp || !window.wp.media) {
            return;
        }

        var urlsById = {};
        Array.prototype.forEach.call(preview.querySelectorAll('[data-room-media-id]'), function (item) {
            var id = parseInt(item.getAttribute('data-room-media-id') || '0', 10);
            var img = item.querySelector('img');
            if (id > 0 && img && img.src) {
                urlsById[id] = img.src;
            }
        });

        var selectedIds = parseIds(input.value || '');
        var frame = null;

        function syncInput() {
            input.value = selectedIds.join(',');
        }

        function removeId(id) {
            selectedIds = selectedIds.filter(function (current) { return current !== id; });
            syncInput();
            renderPreview(preview, selectedIds, urlsById);
        }

        selectBtn.addEventListener('click', function () {
            var strings = getStrings();

            if (!frame) {
                frame = window.wp.media({
                    title: strings.title,
                    button: { text: strings.button },
                    library: { type: 'image' },
                    multiple: true
                });

                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    var ids = [];
                    var seen = {};

                    selection.each(function (attachment) {
                        var data = attachment.toJSON();
                        var id = parseInt(data && data.id ? data.id : 0, 10);
                        if (id <= 0 || seen[id]) {
                            return;
                        }

                        seen[id] = true;
                        ids.push(id);

                        var thumb = data && data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url
                            ? data.sizes.thumbnail.url
                            : (data && data.url ? data.url : '');
                        if (thumb) {
                            urlsById[id] = thumb;
                        }
                    });

                    selectedIds = ids;
                    syncInput();
                    renderPreview(preview, selectedIds, urlsById);
                });
            }

            frame.open();
        });

        clearBtn.addEventListener('click', function () {
            selectedIds = [];
            syncInput();
            renderPreview(preview, selectedIds, urlsById);
        });

        preview.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.getAttribute) {
                return;
            }
            var rawId = target.getAttribute('data-room-media-remove');
            if (!rawId) {
                return;
            }
            event.preventDefault();
            var id = parseInt(rawId, 10);
            if (id > 0) {
                removeId(id);
            }
        });

        syncInput();
        renderPreview(preview, selectedIds, urlsById);
    }

    function init() {
        var fields = document.querySelectorAll('.mj-request-admin [data-room-media-field]');
        Array.prototype.forEach.call(fields, mountField);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window, document));
