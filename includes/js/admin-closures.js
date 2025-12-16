(function ($) {
    'use strict';

    $(function () {
        var mediaFrame = null;
        var $coverField = $('#mj-closure-cover-id');
        var $preview = $('#mj-closure-cover-preview');
        var $selectButton = $('#mj-closure-cover-select');
        var $removeButton = $('#mj-closure-cover-remove');

        if (!$coverField.length || !$preview.length) {
            return;
        }

        function getEmptyLabel() {
            var label = $preview.attr('data-empty-label');
            return label ? label : 'Aucune image sélectionnée.';
        }

        function renderPlaceholder() {
            $preview.html('<span>' + getEmptyLabel() + '</span>');
        }

        function setCover(attachmentId, attachmentUrl) {
            var id = attachmentId ? parseInt(attachmentId, 10) : 0;
            if (isNaN(id) || id < 0) {
                id = 0;
            }

            $coverField.val(id);

            if (attachmentUrl) {
                $preview.html(
                    '<img src="' + attachmentUrl + '" alt="" style="max-width:200px;height:auto;border-radius:8px;" />'
                );
            } else {
                renderPlaceholder();
            }
        }

        $selectButton.on('click', function (event) {
            event.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: 'Sélectionner une image',
                button: { text: 'Utiliser cette image' },
                multiple: false
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first();
                if (!attachment) {
                    return;
                }

                var data = attachment.toJSON();
                var url = data.url || '';
                if (data.sizes && data.sizes.medium) {
                    url = data.sizes.medium.url;
                }

                setCover(data.id, url);
            });

            mediaFrame.open();
        });

        $removeButton.on('click', function (event) {
            event.preventDefault();
            setCover(0, '');
        });

        if (!$preview.children().length) {
            renderPlaceholder();
        }
    });
})(jQuery);
