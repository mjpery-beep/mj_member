(function ($) {
    'use strict';

    var mediaFrame;
    var mapContainer = $('#mj-location-map');
    var mapFrame = $('#mj-location-map-frame');

    function setCover(imageId, imageUrl) {
        $('#mj-location-cover-id').val(imageId || 0);
        var preview = $('#mj-location-cover-preview');
        if (imageUrl) {
            preview.html('<img src="' + imageUrl + '" alt="" style="max-width:240px;height:auto;" />');
        } else {
            preview.html('<span>Aucun visuel selectionne.</span>');
        }
    }

    function buildMapSrc() {
        var mapQuery = $('#mj-location-map-query').val();
        var latitude = $('#mj-location-latitude').val();
        var longitude = $('#mj-location-longitude').val();
        var address = $('#mj-location-address').val();
        var postal = $('input[name="location_postal_code"]').val();
        var city = $('input[name="location_city"]').val();
        var country = $('input[name="location_country"]').val();

        var query = '';

        if (mapQuery) {
            query = mapQuery;
        } else if (latitude && longitude) {
            query = latitude + ',' + longitude;
        } else {
            var parts = [];
            if (address) {
                parts.push(address);
            }
            if (postal) {
                parts.push(postal);
            }
            if (city) {
                parts.push(city);
            }
            if (country) {
                parts.push(country);
            }
            if (parts.length) {
                query = parts.join(', ');
            }
        }

        if (!query) {
            return '';
        }

        return 'https://www.google.com/maps?q=' + encodeURIComponent(query) + '&output=embed';
    }

    function ensureMapFrame() {
        if (!mapContainer.length) {
            return null;
        }

        if (!mapFrame.length) {
            var fallback = mapContainer.data('fallback');
            if (fallback) {
                mapContainer.empty();
                mapFrame = $('<iframe>', {
                    id: 'mj-location-map-frame',
                    width: 520,
                    height: 260,
                    style: 'border:0;',
                    loading: 'lazy',
                    referrerpolicy: 'no-referrer-when-downgrade',
                    src: fallback
                });
                mapContainer.append(mapFrame);
            } else {
                return null;
            }
        }

        return mapFrame;
    }

    function updateMap() {
        var frame = ensureMapFrame();
        if (!mapContainer.length) {
            return;
        }

        var src = buildMapSrc();
        if (!src) {
            mapContainer.find('#mj-location-map-frame').remove();
            mapContainer.find('.description').remove();
            mapContainer.append('<p class="description">Le plan apparaitra apres avoir renseigne l\'adresse ou la requete.</p>');
            mapFrame = $();
            return;
        }

        if (!frame || !frame.length) {
            mapContainer.find('.description').remove();
            frame = $('<iframe>', {
                id: 'mj-location-map-frame',
                width: 520,
                height: 260,
                style: 'border:0;',
                loading: 'lazy',
                referrerpolicy: 'no-referrer-when-downgrade'
            });
            mapContainer.empty().append(frame);
            mapFrame = frame;
        }

        frame.attr('src', src);
    }

    $('#mj-location-cover-select').on('click', function (event) {
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

    $('#mj-location-cover-remove').on('click', function (event) {
        event.preventDefault();
        setCover('', '');
    });

    $('#mj-location-name').on('blur', function () {
        var slugField = $('#mj-location-slug');
        if (!slugField.val()) {
            var generated = ($(this).val() || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugField.val(generated);
        }
    });

    $('#mj-location-address, input[name="location_postal_code"], input[name="location_city"], input[name="location_country"], #mj-location-map-query, #mj-location-latitude, #mj-location-longitude').on('input', updateMap);

    if (mapContainer.length) {
        updateMap();
    }
})(jQuery);
