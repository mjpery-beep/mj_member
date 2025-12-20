/**
 * MJ Member Admin Dashboard Charts
 * 
 * Uses Chart.js for rendering dashboard charts with dynamic loading.
 * Pattern inspired by admin-hours-dashboard.js
 */
(function() {
    'use strict';

    var CHART_JS_SCRIPT_ID = 'mj-member-chartjs';
    var CHART_JS_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js';
    var chartJsReadyPromise = null;
    var scriptPromises = {};

    var COLOR_PALETTE = [
        '#6366f1', // indigo
        '#22c55e', // green
        '#f97316', // orange
        '#a855f7', // purple
        '#ef4444', // red
        '#14b8a6', // teal
        '#f59e0b', // amber
        '#3b82f6', // blue
        '#ec4899', // pink
        '#84cc16', // lime
    ];

    var CHART_COLORS = {
        registrations: '#2563eb',
        payments: '#0ea5e9',
        grid: 'rgba(148, 163, 184, 0.25)',
    };

    /**
     * Load external script once
     */
    function loadScriptOnce(id, url) {
        if (scriptPromises[id]) {
            return scriptPromises[id];
        }

        var existing = document.getElementById(id);
        if (existing) {
            scriptPromises[id] = Promise.resolve();
            return scriptPromises[id];
        }

        scriptPromises[id] = new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.id = id;
            script.src = url;
            script.async = true;
            script.onload = resolve;
            script.onerror = function() {
                reject(new Error('Unable to load script ' + url));
            };
            document.head.appendChild(script);
        });

        return scriptPromises[id];
    }

    /**
     * Ensure Chart.js is loaded
     */
    function ensureChartJs() {
        if (typeof window !== 'undefined' && typeof window.Chart !== 'undefined') {
            return Promise.resolve(window.Chart);
        }

        if (!chartJsReadyPromise) {
            chartJsReadyPromise = loadScriptOnce(CHART_JS_SCRIPT_ID, CHART_JS_SCRIPT_URL)
                .then(function() {
                    if (typeof window === 'undefined' || typeof window.Chart === 'undefined') {
                        throw new Error('Chart.js introuvable');
                    }
                    return window.Chart;
                })
                .catch(function(error) {
                    console.error('MJ Member Dashboard failed to load Chart.js', error);
                    throw error;
                });
        }

        return chartJsReadyPromise;
    }

    /**
     * Parse JSON config from data attribute
     */
    function parseConfig(raw) {
        if (typeof raw !== 'string' || raw === '') {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            try {
                var textarea = document.createElement('textarea');
                textarea.innerHTML = raw;
                return JSON.parse(textarea.value);
            } catch (secondError) {
                return {};
            }
        }
    }

    /**
     * Format percentage for display
     */
    function formatPercentage(value) {
        if (!Number.isFinite(value) || value <= 0) {
            return '0%';
        }
        if (value >= 99.5) {
            return '100%';
        }
        return Math.round(value * 10) / 10 + '%';
    }

    /**
     * Create bar chart for monthly series
     */
    function createMonthlyChart(container, series) {
        if (!container || !Array.isArray(series) || series.length === 0) {
            return null;
        }

        var canvas = document.createElement('canvas');
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', 'Inscriptions et paiements mensuels');
        container.appendChild(canvas);

        var labels = series.map(function(item) {
            return item.label || '';
        });

        var registrationsData = series.map(function(item) {
            return parseInt(item.registrations, 10) || 0;
        });

        var paymentsData = series.map(function(item) {
            return parseInt(item.payments, 10) || 0;
        });

        return ensureChartJs().then(function(Chart) {
            return new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Inscriptions',
                            data: registrationsData,
                            backgroundColor: CHART_COLORS.registrations,
                            borderRadius: 6,
                            maxBarThickness: 40,
                        },
                        {
                            label: 'Paiements',
                            data: paymentsData,
                            backgroundColor: CHART_COLORS.payments,
                            borderRadius: 6,
                            maxBarThickness: 40,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: CHART_COLORS.grid,
                                drawBorder: false,
                            },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                        }
                    }
                }
            });
        });
    }

    /**
     * Create donut chart for distribution data
     */
    function createDonutChart(container, items, config) {
        if (!container || !Array.isArray(items) || items.length === 0) {
            return null;
        }

        config = config || {};
        var title = config.title || '';
        var centerLabel = config.centerLabel || '';
        var centerValue = config.centerValue || '';

        // Create wrapper structure
        var wrapper = document.createElement('div');
        wrapper.className = 'mj-dashboard-donut';

        var canvasWrapper = document.createElement('div');
        canvasWrapper.className = 'mj-dashboard-donut__canvas-wrapper';
        
        var canvas = document.createElement('canvas');
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', title);
        canvasWrapper.appendChild(canvas);

        // Center label
        if (centerValue || centerLabel) {
            var center = document.createElement('div');
            center.className = 'mj-dashboard-donut__center';
            
            if (centerValue) {
                var valueEl = document.createElement('span');
                valueEl.className = 'mj-dashboard-donut__center-value';
                valueEl.textContent = centerValue;
                center.appendChild(valueEl);
            }
            
            if (centerLabel) {
                var labelEl = document.createElement('span');
                labelEl.className = 'mj-dashboard-donut__center-label';
                labelEl.textContent = centerLabel;
                center.appendChild(labelEl);
            }
            
            canvasWrapper.appendChild(center);
        }

        wrapper.appendChild(canvasWrapper);

        // Legend
        var legend = document.createElement('ul');
        legend.className = 'mj-dashboard-donut__legend';
        
        var total = items.reduce(function(sum, item) {
            return sum + (parseInt(item.count, 10) || 0);
        }, 0);

        items.forEach(function(item, index) {
            var count = parseInt(item.count, 10) || 0;
            var percent = total > 0 ? (count / total) * 100 : 0;
            
            var li = document.createElement('li');
            li.className = 'mj-dashboard-donut__legend-item';
            
            var swatch = document.createElement('span');
            swatch.className = 'mj-dashboard-donut__legend-swatch';
            swatch.style.backgroundColor = COLOR_PALETTE[index % COLOR_PALETTE.length];
            
            var labelSpan = document.createElement('span');
            labelSpan.className = 'mj-dashboard-donut__legend-label';
            labelSpan.textContent = item.label || '';
            
            var valueSpan = document.createElement('span');
            valueSpan.className = 'mj-dashboard-donut__legend-value';
            valueSpan.textContent = count + ' · ' + formatPercentage(percent);
            
            li.appendChild(swatch);
            li.appendChild(labelSpan);
            li.appendChild(valueSpan);
            legend.appendChild(li);
        });

        wrapper.appendChild(legend);
        container.appendChild(wrapper);

        var labels = items.map(function(item) {
            return item.label || '';
        });

        var data = items.map(function(item) {
            return parseInt(item.count, 10) || 0;
        });

        var colors = items.map(function(item, index) {
            return COLOR_PALETTE[index % COLOR_PALETTE.length];
        });

        return ensureChartJs().then(function(Chart) {
            return new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed || 0;
                                    var percent = total > 0 ? (value / total) * 100 : 0;
                                    return label + ': ' + value + ' (' + formatPercentage(percent) + ')';
                                }
                            }
                        }
                    }
                }
            });
        });
    }

    /**
     * Initialize all dashboard charts
     */
    function initDashboardCharts() {
        var configEl = document.getElementById('mj-dashboard-charts-config');
        if (!configEl) {
            return;
        }

        var config = parseConfig(configEl.getAttribute('data-config'));
        if (!config) {
            return;
        }

        // Monthly series bar chart
        var monthlyContainer = document.getElementById('mj-dashboard-monthly-chart');
        if (monthlyContainer && config.series && config.series.length > 0) {
            createMonthlyChart(monthlyContainer, config.series).catch(function(err) {
                console.error('Failed to create monthly chart', err);
            });
        }

        // Member roles donut
        var rolesContainer = document.getElementById('mj-dashboard-roles-chart');
        if (rolesContainer && config.memberStats && config.memberStats.roles) {
            createDonutChart(rolesContainer, config.memberStats.roles, {
                title: 'Répartition par rôle',
                centerValue: config.memberStats.total || '0',
                centerLabel: 'membres'
            }).catch(function(err) {
                console.error('Failed to create roles chart', err);
            });
        }

        // Member statuses donut
        var statusesContainer = document.getElementById('mj-dashboard-statuses-chart');
        if (statusesContainer && config.memberStats && config.memberStats.statuses) {
            createDonutChart(statusesContainer, config.memberStats.statuses, {
                title: 'Statut des membres',
                centerValue: config.memberStats.total || '0',
                centerLabel: 'membres'
            }).catch(function(err) {
                console.error('Failed to create statuses chart', err);
            });
        }

        // Payment status donut
        var paymentsContainer = document.getElementById('mj-dashboard-payments-chart');
        if (paymentsContainer && config.memberStats && config.memberStats.payments) {
            createDonutChart(paymentsContainer, config.memberStats.payments, {
                title: 'Cotisations',
                centerValue: config.memberStats.total || '0',
                centerLabel: 'membres'
            }).catch(function(err) {
                console.error('Failed to create payments chart', err);
            });
        }

        // Age brackets donut
        var agesContainer = document.getElementById('mj-dashboard-ages-chart');
        if (agesContainer && config.memberStats && config.memberStats.age_brackets) {
            createDonutChart(agesContainer, config.memberStats.age_brackets, {
                title: 'Tranches d\'âge',
                centerValue: config.memberStats.total || '0',
                centerLabel: 'membres'
            }).catch(function(err) {
                console.error('Failed to create age brackets chart', err);
            });
        }

        // Event registration status donut
        var eventRegsContainer = document.getElementById('mj-dashboard-event-registrations-chart');
        if (eventRegsContainer && config.eventStats && config.eventStats.registration_breakdown) {
            var totalRegs = config.eventStats.registration_breakdown.reduce(function(sum, item) {
                return sum + (parseInt(item.count, 10) || 0);
            }, 0);
            createDonutChart(eventRegsContainer, config.eventStats.registration_breakdown, {
                title: 'Inscriptions par statut',
                centerValue: String(totalRegs),
                centerLabel: 'inscriptions'
            }).catch(function(err) {
                console.error('Failed to create event registrations chart', err);
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardCharts);
    } else {
        initDashboardCharts();
    }

})();
