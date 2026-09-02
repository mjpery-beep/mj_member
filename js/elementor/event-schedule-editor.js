(function ($) {
    'use strict';

    function refreshOccurrenceBatchChoices(panel, model) {
        var settings = model.get('settings');
        var eventId = String(settings.get('event_id') || '');
        var select = panel.$el.find('.elementor-control-occurrence_batch_ids select')[0];

        if (!select) {
            return;
        }

        if (!select._mjEventScheduleOptions) {
            select._mjEventScheduleOptions = Array.prototype.map.call(select.options, function (option) {
                return {
                    value: option.value,
                    label: option.text,
                };
            });
        }

        var selectedValues = settings.get('occurrence_batch_ids') || [];
        if (!Array.isArray(selectedValues)) {
            selectedValues = [selectedValues];
        }

        var availableValues = select._mjEventScheduleOptions
            .filter(function (optionData) {
                return eventId && optionData.value.indexOf(eventId + ':') === 0;
            })
            .map(function (optionData) { return optionData.value; });
        var validValues = selectedValues.filter(function (value) {
            return availableValues.indexOf(String(value)) !== -1;
        });

        select.innerHTML = '';
        select._mjEventScheduleOptions.forEach(function (optionData) {
            if (!eventId || optionData.value.indexOf(eventId + ':') !== 0) {
                return;
            }

            var option = document.createElement('option');
            option.value = optionData.value;
            option.text = optionData.label;
            option.selected = validValues.indexOf(optionData.value) !== -1;
            select.appendChild(option);
        });

        if (selectedValues.length !== validValues.length) {
            settings.set('occurrence_batch_ids', validValues);
        }

        $(select).trigger('change.select2');
    }

    elementor.hooks.addAction('panel/open_editor/widget/mj-member-event-schedule', function (panel, model) {
        var settings = model.get('settings');

        refreshOccurrenceBatchChoices(panel, model);
        settings.off('change:event_id.mjEventSchedule');
        settings.on('change:event_id.mjEventSchedule', function () {
            refreshOccurrenceBatchChoices(panel, model);
        });
    });
}(jQuery));