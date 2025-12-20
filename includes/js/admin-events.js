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
    var endInput = document.getElementById('mj-event-date-end');
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

    var articleCatSelect = $('#mj-event-article-cat');
    var articleSelect = $('#mj-event-article-id');
    var articlePreview = $('#mj-event-article-preview');
    var articleImageButton = $('#mj-event-article-image');
    var articleCache = {};
    var restConfig = window.mjAdminEvents || {};
    var restRoot = typeof restConfig.restRoot === 'string' ? restConfig.restRoot : (window.location.origin + '/wp-json/wp/v2/');
    if (restRoot.slice(-1) !== '/') {
        restRoot += '/';
    }
    var restPostsEndpoint = restRoot + 'posts';

    function decodeHtml(text) {
        return $('<textarea>').html(text || '').text();
    }

    function storeArticleOption(option) {
        var el = $(option);
        var id = parseInt(el.val(), 10);
        if (!id) {
            return;
        }
        articleCache[id] = articleCache[id] || {};
        articleCache[id].id = id;
        articleCache[id].title = el.text();
        articleCache[id].link = el.data('link') || '';
        articleCache[id].featuredMediaId = parseInt(el.data('image-id'), 10) || 0;
        articleCache[id].featuredMediaUrl = el.data('image-src') || '';
    }

    function populateCacheFromSelect() {
        if (!articleSelect.length) {
            return;
        }
        articleSelect.find('option').each(function () {
            storeArticleOption(this);
        });
    }

    function renderArticlePreviewById(articleId) {
        articleId = parseInt(articleId, 10) || 0;
        if (!articlePreview.length) {
            return;
        }

        if (!articleId) {
            articlePreview.hide().empty();
            if (articleImageButton.length) {
                articleImageButton.hide().data('article-id', 0);
            }
            return;
        }

        var articleData = articleCache[articleId];
        if (!articleData) {
            fetchArticleById(articleId, function () {
                renderArticlePreviewById(articleId);
            });
            return;
        }

        var html = '';
        if (articleData.link) {
            var linkLabel = restConfig.i18n && restConfig.i18n.viewArticle ? restConfig.i18n.viewArticle : "Voir l'article sur le site";
            html += '<p><a href="' + escapeHtml(articleData.link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(linkLabel) + '</a></p>';
        }
        if (articleData.featuredMediaUrl) {
            html += '<div class="mj-event-article-thumb"><img src="' + escapeHtml(articleData.featuredMediaUrl) + '" alt="" style="max-width:240px;height:auto;" /></div>';
        }
        if (!html) {
            var fallback = restConfig.i18n && restConfig.i18n.noPreview ? restConfig.i18n.noPreview : "Cet article ne comporte pas d'aperçu disponible.";
            html = '<p class="description">' + escapeHtml(fallback) + '</p>';
        }

        articlePreview.html(html).show();

        if (articleImageButton.length) {
            if (articleData.featuredMediaId && articleData.featuredMediaUrl) {
                articleImageButton.show().data('article-id', articleId);
            } else {
                articleImageButton.hide().data('article-id', articleId);
            }
        }
    }

    function buildArticleOption(post) {
        var option = $('<option />');
        var title = post && post.title && post.title.rendered ? decodeHtml(post.title.rendered) : 'Article #' + post.id;
        option.val(post.id).text(title);
        if (post.link) {
            option.attr('data-link', post.link);
        }

        var mediaId = parseInt(post.featured_media, 10) || 0;
        var mediaUrl = '';
        if (post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]) {
            var media = post._embedded['wp:featuredmedia'][0];
            mediaId = mediaId || parseInt(media.id, 10) || 0;
            if (media.media_details && media.media_details.sizes && media.media_details.sizes.medium) {
                mediaUrl = media.media_details.sizes.medium.source_url || '';
            }
            mediaUrl = mediaUrl || media.source_url || '';
        }
        if (mediaId) {
            option.attr('data-image-id', mediaId);
        }
        if (mediaUrl) {
            option.attr('data-image-src', mediaUrl);
        }

        return option;
    }

    function fetchArticlesByCategory(categoryId) {
        if (!articleSelect.length) {
            return;
        }
        categoryId = parseInt(categoryId, 10) || 0;

        var loadingText = restConfig.i18n && restConfig.i18n.loading ? restConfig.i18n.loading : 'Chargement…';
        articleSelect.prop('disabled', true).html('<option value="0">' + escapeHtml(loadingText) + '</option>');

        var params = {
            per_page: restConfig.perPage || 50,
            status: 'publish',
            orderby: 'date',
            order: 'desc',
            _fields: 'id,title.rendered,link,featured_media',
            _embed: 'wp:featuredmedia'
        };
        if (categoryId) {
            params.categories = categoryId;
        }

        $.ajax({
            url: restPostsEndpoint,
            method: 'GET',
            data: params,
            beforeSend: function (xhr) {
                if (restConfig.restNonce) {
                    xhr.setRequestHeader('X-WP-Nonce', restConfig.restNonce);
                }
            }
        }).done(function (response) {
            articleSelect.empty();
            var noneText = restConfig.i18n && restConfig.i18n.none ? restConfig.i18n.none : 'Aucun article';
            articleSelect.append('<option value="0">' + escapeHtml(noneText) + '</option>');
            articleCache = {};
            if ($.isArray(response) && response.length) {
                response.forEach(function (post) {
                    var option = buildArticleOption(post);
                    articleSelect.append(option);
                    storeArticleOption(option);
                });
            } else {
                var emptyText = restConfig.i18n && restConfig.i18n.empty ? restConfig.i18n.empty : 'Aucun article disponible pour cette catégorie.';
                articleSelect.append('<option value="0" disabled>' + escapeHtml(emptyText) + '</option>');
            }
            articleSelect.prop('disabled', false).val('0');
            articleSelect.trigger('change');
        }).fail(function () {
            articleSelect.prop('disabled', false);
            var errorText = restConfig.i18n && restConfig.i18n.error ? restConfig.i18n.error : 'Erreur lors du chargement des articles.';
            articleSelect.html('<option value="0" disabled>' + escapeHtml(errorText) + '</option>');
            articlePreview.hide();
            if (articleImageButton.length) {
                articleImageButton.hide();
            }
        });
    }

    function fetchArticleById(articleId, callback) {
        articleId = parseInt(articleId, 10) || 0;
        if (!articleId) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        $.ajax({
            url: restPostsEndpoint + '/' + articleId,
            method: 'GET',
            data: {
                _embed: 'wp:featuredmedia'
            },
            beforeSend: function (xhr) {
                if (restConfig.restNonce) {
                    xhr.setRequestHeader('X-WP-Nonce', restConfig.restNonce);
                }
            }
        }).done(function (post) {
            if (post && post.id) {
                var option = buildArticleOption(post);
                storeArticleOption(option);
                if (articleSelect.length && articleSelect.find('option[value="' + post.id + '"]').length === 0) {
                    option.prop('selected', true);
                    articleSelect.append(option);
                }
            }
        }).always(function () {
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    populateCacheFromSelect();

    if (articleSelect.length) {
        articleSelect.on('change', function () {
            renderArticlePreviewById($(this).val());
        });
        renderArticlePreviewById(articleSelect.val());
    }

    if (articleCatSelect.length) {
        articleCatSelect.on('change', function () {
            fetchArticlesByCategory($(this).val());
        });
    }

    if (articleImageButton.length) {
        articleImageButton.on('click', function (event) {
            event.preventDefault();
            var articleId = $(this).data('article-id') || articleSelect.val();
            articleId = parseInt(articleId, 10) || 0;
            if (!articleId) {
                return;
            }
            var articleData = articleCache[articleId];
            if (!articleData) {
                fetchArticleById(articleId, function () {
                    var refreshed = articleCache[articleId];
                    if (refreshed && refreshed.featuredMediaId && refreshed.featuredMediaUrl) {
                        setCover(refreshed.featuredMediaId, refreshed.featuredMediaUrl);
                    } else {
                        var noImage = restConfig.i18n && restConfig.i18n.noImage ? restConfig.i18n.noImage : "Cet article ne possède pas d'image mise en avant.";
                        window.alert(noImage);
                    }
                });
                return;
            }
            if (articleData.featuredMediaId && articleData.featuredMediaUrl) {
                setCover(articleData.featuredMediaId, articleData.featuredMediaUrl);
            } else {
                var fallback = restConfig.i18n && restConfig.i18n.noImage ? restConfig.i18n.noImage : "Cet article ne possède pas d'image mise en avant.";
                window.alert(fallback);
            }
        });
    }

    var scheduleModeInputs = $('input[name="event_schedule_mode"]');
    var scheduleSections = $('.mj-schedule-section');
    var recurringFrequencySelect = $('#mj-event-recurring-frequency');
    var recurringDateInput = $('#mj-event-recurring-start-date');
    var recurringStartTimeInput = $('#mj-event-recurring-start-time');
    var recurringEndTimeInput = $('#mj-event-recurring-end-time');
    var recurringUntilInput = $('#mj-event-recurring-until');
    var fixedDateInput = $('#mj-event-fixed-date');
    var fixedStartTimeInput = $('#mj-event-fixed-start-time');
    var fixedEndTimeInput = $('#mj-event-fixed-end-time');
    $(function () {

        var typeColors = (typeof mjAdminEvents !== 'undefined' && mjAdminEvents.typeColors) ? mjAdminEvents.typeColors : {};
        var accentColorInput = $('#mj-event-accent-color');
        var accentDefaultLabel = $('#mj-event-accent-default-label');
        var accentDefaultSwatch = $('#mj-event-accent-default-swatch');
        var typeSelect = $('#mj-event-type');

        if (accentColorInput.length && typeof accentColorInput.wpColorPicker === 'function') {
            accentColorInput.wpColorPicker({
                defaultColor: accentColorInput.data('default-color') || false,
                change: function (event, ui) {
                    var target = $(event.target);
                    var colorValue = ui && ui.color ? ui.color.toString() : '';
                    target.val(colorValue);
                },
                clear: function (event) {
                    $(event.target).val('');
                }
            });
        }

        function refreshAccentDefaults(typeKey) {
            var normalizedKey = typeof typeKey === 'string' ? typeKey : '';
            var defaultColor = (typeColors && normalizedKey && typeColors[normalizedKey]) ? typeColors[normalizedKey] : '';

            if (accentColorInput.length) {
                accentColorInput.attr('data-default-color', defaultColor || '');
                var pickerInstance = accentColorInput.data('wpWpColorPicker');
                if (pickerInstance && pickerInstance.options) {
                    pickerInstance.options.defaultColor = defaultColor || false;
                    if (pickerInstance.button && pickerInstance.button.length) {
                        pickerInstance.button.attr('aria-label', defaultColor ? 'Revenir à la couleur par défaut ' + defaultColor : 'Pas de couleur par défaut');
                    }
                }
            }

            if (accentDefaultLabel && accentDefaultLabel.length) {
                accentDefaultLabel.text(defaultColor || '—');
            }

            if (accentDefaultSwatch && accentDefaultSwatch.length) {
                if (defaultColor) {
                    accentDefaultSwatch.css({
                        display: 'inline-block',
                        backgroundColor: defaultColor
                    });
                } else {
                    accentDefaultSwatch.css({
                        display: 'none',
                        backgroundColor: 'transparent'
                    });
                }
            }
        }

        if (typeSelect.length) {
            refreshAccentDefaults(typeSelect.val());
            typeSelect.on('change', function () {
                refreshAccentDefaults($(this).val());
            });
        } else {
            refreshAccentDefaults('');
        }

        var scheduleModeSelector = 'input[name="event_schedule_mode"]';
        var scheduleModeInputs = $(scheduleModeSelector);
        if (!scheduleModeInputs.length) {
            return;
        }

        var scheduleSections = $('.mj-schedule-section');
        var recurringFrequencySelect = $('#mj-event-recurring-frequency');
        var recurringDateInput = $('#mj-event-recurring-start-date');
        var recurringStartTimeInput = $('#mj-event-recurring-start-time');
        var recurringEndTimeInput = $('#mj-event-recurring-end-time');
        var recurringUntilInput = $('#mj-event-recurring-until');
        var fixedDateInput = $('#mj-event-fixed-date');
        var fixedStartTimeInput = $('#mj-event-fixed-start-time');
        var fixedEndTimeInput = $('#mj-event-fixed-end-time');
        var rangeStartInput = $('#mj-event-range-start');
        var rangeEndInput = $('#mj-event-range-end');
        var seriesField = $('#mj-event-series-items');
        var seriesTableBody = $('#mj-event-series-table tbody');
        var seriesEmptyRow = $('#mj-event-series-empty');
        var seriesDateInput = $('#mj-event-series-date');
        var seriesStartInput = $('#mj-event-series-start');
        var seriesEndInput = $('#mj-event-series-end');
        var seriesAddButton = $('#mj-event-series-add');
        var seriesConfig = (typeof mjAdminEvents !== 'undefined' && mjAdminEvents.series) ? mjAdminEvents.series : {};
        var seriesEntries = [];

        function normalizeSeriesEntry(entry) {
            if (!entry || typeof entry !== 'object') {
                return null;
            }

            var dateValue = '';
            var startValue = '';
            var endValue = '';

            if (typeof entry.date === 'string') {
                dateValue = entry.date.trim();
            } else if (typeof entry.day === 'string') {
                dateValue = entry.day.trim();
            }

            if (typeof entry.start_time === 'string') {
                startValue = entry.start_time.trim();
            } else if (typeof entry.start === 'string') {
                startValue = entry.start.trim();
            }

            if (typeof entry.end_time === 'string') {
                endValue = entry.end_time.trim();
            } else if (typeof entry.end === 'string') {
                endValue = entry.end.trim();
            }

            if (!dateValue || !startValue) {
                return null;
            }

            return {
                date: dateValue,
                start_time: startValue,
                end_time: endValue
            };
        }

        function buildSeriesKey(dateValue, timeValue) {
            var datePart = (dateValue || '').trim();
            var timePart = (timeValue || '').trim();
            if (!datePart) {
                return '';
            }
            if (!timePart) {
                timePart = '00:00';
            }
            return datePart + 'T' + timePart;
        }

        function sortSeriesEntries() {
            if (!seriesEntries.length) {
                return;
            }
            seriesEntries.sort(function (left, right) {
                var leftKey = buildSeriesKey(left.date, left.start_time);
                var rightKey = buildSeriesKey(right.date, right.start_time);
                if (leftKey === rightKey) {
                    return 0;
                }
                if (!leftKey) {
                    return 1;
                }
                if (!rightKey) {
                    return -1;
                }
                return leftKey < rightKey ? -1 : 1;
            });
        }

        function serializeSeries() {
            if (!seriesField.length) {
                return;
            }

            var jsonValue = '[]';
            if (seriesEntries.length && window.JSON && typeof window.JSON.stringify === 'function') {
                jsonValue = window.JSON.stringify(seriesEntries);
            }
            seriesField.val(jsonValue);
        }

        function renderSeries() {
            sortSeriesEntries();

            if (seriesTableBody.length) {
                var preservedEmpty = seriesEmptyRow.length ? seriesEmptyRow : $();
                seriesTableBody.find('tr').not(preservedEmpty).remove();

                var emptyText = seriesConfig.emptyLabel || 'Aucune date ajoutee pour le moment.';
                if (seriesEmptyRow.length) {
                    seriesEmptyRow.find('.mj-series-builder__empty').text(emptyText);
                }

                if (!seriesEntries.length) {
                    if (seriesEmptyRow.length) {
                        seriesEmptyRow.show();
                    }
                } else {
                    if (seriesEmptyRow.length) {
                        seriesEmptyRow.hide();
                    }

                    var removeLabel = seriesConfig.removeLabel || 'Supprimer';
                    seriesEntries.forEach(function (entry, index) {
                        var row = $('<tr />').attr('data-series-index', index);
                        row.append(
                            $('<td />').append(
                                $('<span />').addClass('mj-series-builder__cell').text(entry.date)
                            )
                        );
                        row.append(
                            $('<td />').append(
                                $('<span />').addClass('mj-series-builder__cell').text(entry.start_time)
                            )
                        );
                        var endDisplay = entry.end_time && entry.end_time.trim() ? entry.end_time : '--';
                        row.append(
                            $('<td />').append(
                                $('<span />').addClass('mj-series-builder__cell').text(endDisplay)
                            )
                        );
                        row.append(
                            $('<td />')
                                .addClass('mj-series-builder__actions')
                                .append(
                                    $('<button />', {
                                        type: 'button',
                                        class: 'button button-link-delete mj-event-series-remove',
                                        'data-series-index': index,
                                        text: removeLabel
                                    })
                                )
                        );
                        seriesTableBody.append(row);
                    });
                }
            }

            serializeSeries();
            refreshScheduleUI('series-render');
        }

        function loadSeriesFromField() {
            if (!seriesField.length) {
                seriesEntries = [];
                return;
            }

            var rawValue = seriesField.val();
            var parsed = [];
            if (typeof rawValue === 'string' && rawValue.trim() !== '') {
                try {
                    parsed = window.JSON && typeof window.JSON.parse === 'function' ? window.JSON.parse(rawValue) : [];
                } catch (parseError) {
                    parsed = [];
                }
            }

            seriesEntries = [];
            if (Array.isArray(parsed)) {
                parsed.forEach(function (candidate) {
                    var normalized = normalizeSeriesEntry(candidate);
                    if (normalized) {
                        seriesEntries.push(normalized);
                    }
                });
            }
        }

        function getSeriesBounds() {
            if (!seriesEntries.length) {
                return { start: '', end: '' };
            }

            var startKey = '';
            var endKey = '';

            seriesEntries.forEach(function (entry) {
                var candidateStart = buildSeriesKey(entry.date, entry.start_time);
                if (candidateStart && (!startKey || candidateStart < startKey)) {
                    startKey = candidateStart;
                }

                var candidateEnd = buildSeriesKey(entry.date, entry.end_time && entry.end_time.trim() ? entry.end_time : entry.start_time);
                if (candidateEnd && (!endKey || candidateEnd > endKey)) {
                    endKey = candidateEnd;
                }
            });

            return {
                start: startKey,
                end: endKey
            };
        }

        if (seriesField.length) {
            seriesField.on('change', function () {
                loadSeriesFromField();
                renderSeries();
            });

            loadSeriesFromField();
            renderSeries();
        }

        function toggleScheduleSections(mode) {
            if (!scheduleSections.length) {
                return;
            }

            scheduleSections.each(function () {
                var section = $(this);
                var modesAttr = section.attr('data-schedule-mode') || '';
                var modes = modesAttr.split(',').map(function (item) {
                    return $.trim(item);
                }).filter(function (item) {
                    return item.length > 0;
                });
                var show = modes.indexOf(mode) !== -1;
                section.toggleClass('is-active', show);
            });
        }

        function updateRecurringSections() {
            if (!recurringFrequencySelect.length) {
                return;
            }

            var value = recurringFrequencySelect.val();
            $('.mj-recurring-weekly').toggle(value === 'weekly');
            $('.mj-recurring-monthly').toggle(value === 'monthly');
        }

        function updateFieldRequirements(mode) {
            fixedDateInput.add(fixedStartTimeInput).prop('required', mode === 'fixed');
            fixedEndTimeInput.prop('required', false);
            rangeStartInput.add(rangeEndInput).prop('required', mode === 'range');
            recurringDateInput.add(recurringStartTimeInput).add(recurringEndTimeInput).prop('required', mode === 'recurring');
            if (recurringUntilInput.length) {
                recurringUntilInput.prop('disabled', mode !== 'recurring');
            }
        }

        function computeScheduleDatetimes(mode) {
            var startValue = '';
            var endValue = '';

            if (mode === 'fixed') {
                var fixedDateValue = fixedDateInput.val();
                var fixedStartValue = fixedStartTimeInput.val();
                var fixedEndValue = fixedEndTimeInput.val();
                if (fixedDateValue && fixedStartValue) {
                    startValue = fixedDateValue + 'T' + fixedStartValue;
                }
                if (fixedDateValue && fixedEndValue) {
                    endValue = fixedDateValue + 'T' + fixedEndValue;
                }
            } else if (mode === 'range') {
                startValue = rangeStartInput.val() || '';
                endValue = rangeEndInput.val() || '';
            } else if (mode === 'series') {
                var bounds = getSeriesBounds();
                if (bounds.start) {
                    startValue = bounds.start;
                }
                if (bounds.end) {
                    endValue = bounds.end;
                }
            } else if (mode === 'recurring') {
                var recurringDateValue = recurringDateInput.val();
                var recurringStartValue = recurringStartTimeInput.val();
                var recurringEndValue = recurringEndTimeInput.val();
                if (recurringDateValue && recurringStartValue) {
                    startValue = recurringDateValue + 'T' + recurringStartValue;
                }
                if (recurringDateValue && recurringEndValue) {
                    endValue = recurringDateValue + 'T' + recurringEndValue;
                }
            }

            if (startInput) {
                startInput.value = startValue || '';
                if (startValue) {
                    $(startInput).trigger('change');
                }
            }
            if (endInput) {
                endInput.value = endValue || '';
            }
        }

        function refreshScheduleUI(source) {
            var modeInput = $(scheduleModeSelector).filter(':checked');
            var mode = modeInput.length ? modeInput.val() : 'fixed';
            toggleScheduleSections(mode);
            updateFieldRequirements(mode);
            updateRecurringSections();
            computeScheduleDatetimes(mode);
        }
        $(document).on('change', scheduleModeSelector, function () {
            refreshScheduleUI('mode');
        });

        recurringFrequencySelect.on('change', function () {
            refreshScheduleUI('frequency');
        });

        // Toggle per-weekday time inputs visibility
        function initWeekdayTimeToggles() {
            $('.mj-weekday-row').each(function() {
                var $row = $(this);
                var $checkbox = $row.find('.mj-weekday-checkbox');
                var $times = $row.find('.mj-weekday-times');
                var isChecked = $checkbox.is(':checked');
                $times.toggle(isChecked);
                $row.css({
                    'background': isChecked ? '#f0f7ff' : '#f9f9f9',
                    'border-color': isChecked ? '#2271b1' : '#ddd'
                });
            });
        }

        $(document).on('change', '.mj-weekday-checkbox', function() {
            var $row = $(this).closest('.mj-weekday-row');
            var $times = $row.find('.mj-weekday-times');
            var isChecked = this.checked;
            $times.toggle(isChecked);
            $row.css({
                'background': isChecked ? '#f0f7ff' : '#f9f9f9',
                'border-color': isChecked ? '#2271b1' : '#ddd'
            });
        });

        // Initialize weekday toggles on page load
        initWeekdayTimeToggles();

        var recurringInputs = recurringDateInput.add(recurringStartTimeInput).add(recurringEndTimeInput).add(recurringUntilInput);
        recurringInputs.on('change', function () {
            refreshScheduleUI('recurring');
        });

        fixedDateInput.add(fixedStartTimeInput).add(fixedEndTimeInput).on('change', function () {
            refreshScheduleUI('fixed');
        });

        rangeStartInput.add(rangeEndInput).on('change', function () {
            refreshScheduleUI('range');
        });

        if (seriesAddButton.length) {
            seriesAddButton.on('click', function (event) {
                event.preventDefault();

                var dateValue = seriesDateInput.length ? String(seriesDateInput.val() || '').trim() : '';
                var startValue = seriesStartInput.length ? String(seriesStartInput.val() || '').trim() : '';
                var endValue = seriesEndInput.length ? String(seriesEndInput.val() || '').trim() : '';

                if (!dateValue) {
                    window.alert(seriesConfig.missingDate || 'Merci de donner une date valide.');
                    return;
                }

                if (!startValue) {
                    window.alert(seriesConfig.missingStart || 'Merci d indiquer une heure de debut.');
                    return;
                }

                if (endValue) {
                    var startKey = buildSeriesKey(dateValue, startValue);
                    var endKey = buildSeriesKey(dateValue, endValue);
                    if (endKey <= startKey) {
                        window.alert(seriesConfig.invalidEnd || 'L heure de fin doit etre superieure a l heure de debut.');
                        return;
                    }
                }

                seriesEntries.push({
                    date: dateValue,
                    start_time: startValue,
                    end_time: endValue
                });

                renderSeries();

                if (seriesDateInput.length) {
                    seriesDateInput.val('').focus();
                }
                if (seriesStartInput.length) {
                    seriesStartInput.val('');
                }
                if (seriesEndInput.length) {
                    seriesEndInput.val('');
                }
            });
        }

        $(document).on('click', '.mj-event-series-remove', function (event) {
            event.preventDefault();

            var button = $(this);
            var index = parseInt(button.attr('data-series-index'), 10);
            if (isNaN(index) || index < 0 || index >= seriesEntries.length) {
                return;
            }

            seriesEntries.splice(index, 1);
            renderSeries();
        });

        var paymentConfig = (typeof mjAdminEvents !== 'undefined' && mjAdminEvents.payment) ? mjAdminEvents.payment : {};
        var ajaxUrl = (typeof mjAdminEvents !== 'undefined' && mjAdminEvents.ajaxUrl) ? mjAdminEvents.ajaxUrl : (window.ajaxurl || '');

        function formatAmount(amountValue) {
            if (!amountValue) {
                return '';
            }
            if (typeof paymentConfig.amountLabel === 'string' && paymentConfig.amountLabel.indexOf('%s') !== -1) {
                return paymentConfig.amountLabel.replace('%s', amountValue);
            }
            return 'Montant : ' + amountValue;
        }

        $(document).on('click', '.mj-event-payment-link', function (clickEvent) {
            clickEvent.preventDefault();

            if (!ajaxUrl) {
                window.alert(paymentConfig.error || 'Lien de paiement indisponible.');
                return;
            }

            var button = $(this);
            var eventId = parseInt(button.data('eventId'), 10) || 0;
            var registrationId = parseInt(button.data('registrationId'), 10) || 0;
            if (!eventId || !registrationId) {
                window.alert(paymentConfig.error || 'Lien de paiement indisponible.');
                return;
            }

            var originalText = button.data('originalText');
            if (typeof originalText === 'undefined') {
                originalText = $.trim(button.text());
                button.data('originalText', originalText);
            }

            var workingText = paymentConfig.generating || 'Generation en cours...';
            button.prop('disabled', true).text(workingText);

            var output = button.closest('td').find('.mj-event-payment-output').filter(function () {
                return parseInt($(this).data('registrationId'), 10) === registrationId;
            }).first();
            if (!output.length) {
                output = $('.mj-event-payment-output').filter(function () {
                    return parseInt($(this).data('registrationId'), 10) === registrationId;
                }).first();
            }

            output.removeClass('is-error').empty();

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'mj_admin_generate_event_payment_link',
                    nonce: paymentConfig.nonce || '',
                    event_id: eventId,
                    registration_id: registrationId
                }
            }).done(function (response) {
                if (!response || !response.success || !response.data) {
                    output.addClass('is-error').text(paymentConfig.error || 'Impossible de generer le lien.');
                    return;
                }

                var data = response.data;
                var fragments = [];
                var message = data.message || paymentConfig.success || '';
                if (message) {
                    fragments.push($('<span />').text(message));
                }
                if (data.checkout_url) {
                    fragments.push(
                        $('<a />', {
                            href: data.checkout_url,
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }).text(paymentConfig.linkLabel || 'Ouvrir le lien')
                    );
                }
                var amountText = formatAmount(data.amount);
                if (amountText) {
                    fragments.push($('<span />').text(amountText));
                }

                if (!fragments.length) {
                    fragments.push($('<span />').text(paymentConfig.success || 'Lien de paiement genere.'));
                }

                output.removeClass('is-error').empty();
                $.each(fragments, function (index, node) {
                    if (index > 0) {
                        output.append(document.createTextNode(' '));
                    }
                    output.append(node);
                });
            }).fail(function () {
                output.addClass('is-error').text(paymentConfig.error || 'Impossible de generer le lien.');
            }).always(function () {
                button.prop('disabled', false).text(originalText);
            });
        });

        refreshScheduleUI('init');
    });
    })(jQuery);
