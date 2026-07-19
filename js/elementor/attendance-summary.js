(function () {
    'use strict';

    function toInt(value) {
        var parsed = parseInt(String(value || ''), 10);
        return Number.isNaN(parsed) ? 0 : parsed;
    }

    function toDateStamp(value) {
        if (!value) {
            return null;
        }

        var date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date.getTime();
    }

    function formatDateBoundary(value, endOfDay) {
        if (!value) {
            return null;
        }

        var suffix = endOfDay ? 'T23:59:59' : 'T00:00:00';
        var date = new Date(String(value) + suffix);
        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date.getTime();
    }

    function createBarSegment(className, widthPercent, label) {
        return '<span class="' + className + '" style="width:' + widthPercent + '%" title="' + label + '"></span>';
    }

    function renderChart(container, rows) {
        var barsHost = container.querySelector('[data-att-summary-chart-bars]');
        var metaHost = container.querySelector('[data-att-summary-chart-meta]');

        if (!barsHost || !metaHost) {
            return;
        }

        if (!rows.length) {
            barsHost.innerHTML = '<div class="mj-attendance-summary__chart-empty">Aucune donnee pour les filtres selectionnes.</div>';
            metaHost.textContent = '0 evenement affiche';
            return;
        }

        var totalPresent = 0;
        var totalAbsent = 0;
        var totalPending = 0;
        var html = '';

        rows.forEach(function (row) {
            var present = toInt(row.dataset.present);
            var absent = toInt(row.dataset.absent);
            var pending = toInt(row.dataset.pending);
            var total = present + absent + pending;
            var safeTotal = total > 0 ? total : 1;

            totalPresent += present;
            totalAbsent += absent;
            totalPending += pending;

            var pWidth = Math.round((present / safeTotal) * 100);
            var aWidth = Math.round((absent / safeTotal) * 100);
            var remaining = Math.max(0, 100 - pWidth - aWidth);

            html += '' +
                '<article class="mj-attendance-summary__chart-row">' +
                    '<div class="mj-attendance-summary__chart-row-head">' +
                        '<strong>' + (row.dataset.title || 'Evenement') + '</strong>' +
                        '<span>' + present + ' P / ' + absent + ' A / ' + pending + ' a pointer</span>' +
                    '</div>' +
                    '<div class="mj-attendance-summary__chart-track">' +
                        createBarSegment('mj-attendance-summary__chart-segment is-present', pWidth, 'Presences: ' + present) +
                        createBarSegment('mj-attendance-summary__chart-segment is-absent', aWidth, 'Absences: ' + absent) +
                        createBarSegment('mj-attendance-summary__chart-segment is-pending', remaining, 'A pointer: ' + pending) +
                    '</div>' +
                '</article>';
        });

        barsHost.innerHTML = html;
        metaHost.textContent = rows.length + ' evenement(s) | Presences: ' + totalPresent + ' | Absences: ' + totalAbsent + ' | A pointer: ' + totalPending;
    }

    function applyFilters(widget) {
        var typeValue = widget.querySelector('[data-filter="type"]') ? widget.querySelector('[data-filter="type"]').value : '';
        var presenceValue = widget.querySelector('[data-filter="presence"]') ? widget.querySelector('[data-filter="presence"]').value : '';
        var dateFromValue = widget.querySelector('[data-filter="date-from"]') ? widget.querySelector('[data-filter="date-from"]').value : '';
        var dateToValue = widget.querySelector('[data-filter="date-to"]') ? widget.querySelector('[data-filter="date-to"]').value : '';

        var fromStamp = formatDateBoundary(dateFromValue, false);
        var toStamp = formatDateBoundary(dateToValue, true);
        var rows = Array.prototype.slice.call(widget.querySelectorAll('[data-att-summary-row]'));
        var visibleRows = [];

        rows.forEach(function (row) {
            var typeOk = !typeValue || (row.dataset.type === typeValue);

            var hasPresence = toInt(row.dataset.present) > 0;
            var presenceOk = true;
            if (presenceValue === 'yes') {
                presenceOk = hasPresence;
            } else if (presenceValue === 'no') {
                presenceOk = !hasPresence;
            }

            var rowStamp = toDateStamp(row.dataset.date);
            var dateOk = true;
            if (fromStamp !== null && rowStamp !== null) {
                dateOk = rowStamp >= fromStamp;
            }
            if (dateOk && toStamp !== null && rowStamp !== null) {
                dateOk = rowStamp <= toStamp;
            }

            var shouldShow = typeOk && presenceOk && dateOk;
            row.classList.toggle('is-hidden', !shouldShow);

            if (shouldShow) {
                visibleRows.push(row);
            }
        });

        var chartContainer = widget.querySelector('[data-att-summary-chart]');
        if (chartContainer) {
            renderChart(chartContainer, visibleRows);
        }
    }

    function bindWidget(widget) {
        var filterNodes = widget.querySelectorAll('[data-att-summary-filters] select, [data-att-summary-filters] input');
        Array.prototype.forEach.call(filterNodes, function (node) {
            node.addEventListener('change', function () {
                applyFilters(widget);
            });
        });

        var resetButton = widget.querySelector('[data-filter-reset]');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                var form = widget.querySelector('[data-att-summary-filters]');
                if (form) {
                    form.reset();
                }
                applyFilters(widget);
            });
        }

        applyFilters(widget);
    }

    function init() {
        var widgets = document.querySelectorAll('.mj-attendance-summary');
        Array.prototype.forEach.call(widgets, bindWidget);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
        return;
    }

    init();
})();
