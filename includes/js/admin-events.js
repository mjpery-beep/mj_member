(function ($) {
    'use strict';

    var mediaFrame;

    function pad(value) {
        value = String(value);
        if (value.length >= 2) {
            return value;
        }
        return '0' + value;
    }

    function formatDateTimeLocal(date) {
        return (
            date.getFullYear() +
            '-' + pad(date.getMonth() + 1) +
            '-' + pad(date.getDate()) +
            'T' + pad(date.getHours()) +
            ':' + pad(date.getMinutes())
        );
    }

    function setCover(imageId, imageUrl) {
        $('#mj-event-cover-id').val(imageId || 0);
        var preview = $('#mj-event-cover-preview');
        if (imageUrl) {
            preview.html('<img src="' + imageUrl + '" alt="" style="max-width:240px;height:auto;" />');
        } else {
            preview.html('<span>Aucun visuel selectionne.</span>');
        }
    }

    $('#mj-event-cover-select').on('click', function (event) {
        event.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Selectionner une image',
            button: { text: 'Utiliser cette image' },
            multiple: false
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first();
            if (!attachment) {
                return;
            }
            var data = attachment.toJSON();
            var url = data.url;
            if (data.sizes && data.sizes.medium) {
                url = data.sizes.medium.url;
            }
            setCover(data.id, url);
        });

        mediaFrame.open();
    });

    $('#mj-event-cover-remove').on('click', function (event) {
        event.preventDefault();
        setCover('', '');
    });

    var startInput = document.getElementById('mj-event-date-start');
    var deadlineInput = document.getElementById('mj-event-date-deadline');

    if (startInput && deadlineInput) {
        var deadlineTouched = deadlineInput.value !== '';

        deadlineInput.addEventListener('input', function () {
            deadlineTouched = true;
        });

        startInput.addEventListener('change', function () {
            if (deadlineTouched) {
                return;
            }

            if (!startInput.value) {
                return;
            }

            var startDate = new Date(startInput.value);
            if (isNaN(startDate.getTime())) {
                return;
            }

            var defaultDeadline = new Date(startDate.getTime() - 14 * 24 * 60 * 60 * 1000);
            deadlineInput.value = formatDateTimeLocal(defaultDeadline);
        });
    }
})(jQuery);
