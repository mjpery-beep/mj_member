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

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    var locationSelect = $('#mj-event-location');
    var locationPreview = $('#mj-event-location-preview');

    function renderLocationPreview() {
        if (!locationSelect.length || !locationPreview.length) {
            return;
        }

        var option = locationSelect.find('option:selected');
        if (!option.length || option.val() === '0') {
            locationPreview.html('<p class="description">Choisissez un lieu pour afficher un apercu.</p>');
            return;
        }

        var name = escapeHtml(option.text());
        var address = escapeHtml(option.data('address'));
        var notes = escapeHtml(option.data('notes'));
        var mapSrc = option.data('map') || '';

        var html = '<strong>' + name + '</strong><br />';
        if (address) {
            html += '<span>' + address + '</span><br />';
        }
        if (notes) {
            html += '<span class="description">Notes: ' + notes + '</span><br />';
        }
        if (mapSrc) {
            html += '<div class="mj-event-location-map" style="margin-top:10px; max-width:520px;"><iframe src="' + mapSrc + '" width="520" height="260" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
        }

        locationPreview.html(html);
    }

    locationSelect.on('change', renderLocationPreview);
    renderLocationPreview();
})(jQuery);
