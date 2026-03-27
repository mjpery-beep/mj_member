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
    var GOOGLE_MAPS_CALLBACK_NAME = '__mj_google_maps_ready';

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

    function ensureGoogleMaps(apiKey) {
        if (typeof window !== 'undefined' && window.google && window.google.maps && typeof window.google.maps.Map === 'function') {
            return Promise.resolve(window.google.maps);
        }

        if (scriptPromises.googleMaps) {
            return scriptPromises.googleMaps;
        }

        scriptPromises.googleMaps = new Promise(function(resolve, reject) {
            if (!apiKey) {
                reject(new Error('Google Maps API key missing'));
                return;
            }

            window[GOOGLE_MAPS_CALLBACK_NAME] = function() {
                try { delete window[GOOGLE_MAPS_CALLBACK_NAME]; } catch (e) { /* noop */ }
                if (window.google && window.google.maps) {
                    resolve(window.google.maps);
                } else {
                    reject(new Error('Google Maps namespace unavailable'));
                }
            };

            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js'
                + '?key=' + encodeURIComponent(apiKey)
                + '&callback=' + GOOGLE_MAPS_CALLBACK_NAME
                + '&loading=async';
            script.async = true;
            script.defer = true;
            script.onerror = function() {
                try { delete window[GOOGLE_MAPS_CALLBACK_NAME]; } catch (e) { /* noop */ }
                delete scriptPromises.googleMaps;
                reject(new Error('Unable to load Google Maps script'));
            };
            document.head.appendChild(script);
        });

        scriptPromises.googleMaps = scriptPromises.googleMaps.catch(function(error) {
            delete scriptPromises.googleMaps;
            throw error;
        });

        return scriptPromises.googleMaps;
    }

    function parseNumber(value) {
        var number = parseFloat(value);
        if (typeof number !== 'number' || !isFinite(number)) {
            return null;
        }
        return number;
    }

    function formatDescriptor(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var trimmed = value.replace(/[_-]+/g, ' ').trim();
        if (trimmed === '') {
            return '';
        }
        return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
    }

    function mergeMapOptions(defaults, overrides) {
        var result = Object.assign({}, defaults);
        if (!overrides || typeof overrides !== 'object') {
            return result;
        }

        Object.keys(overrides).forEach(function(key) {
            if (key === 'center' && overrides.center) {
                var center = overrides.center;
                if (center && typeof center === 'object') {
                    if (typeof center.lat === 'function' && typeof center.lng === 'function') {
                        result.center = center;
                    } else {
                        var lat = parseNumber(center.lat);
                        var lng = parseNumber(center.lng);
                        if (lat !== null && lng !== null) {
                            result.center = { lat: lat, lng: lng };
                        }
                    }
                }
            } else {
                result[key] = overrides[key];
            }
        });

        return result;
    }

    function createInfoContent(item, strings) {
        var container = document.createElement('div');
        container.className = 'mj-members-map__info-window';

        var title = document.createElement('strong');
        title.textContent = item.label || '';
        container.appendChild(title);

        var addressLines = [];
        if (item.address) {
            addressLines.push(item.address);
        }
        var localityParts = [];
        if (item.postalCode) {
            localityParts.push(item.postalCode);
        }
        if (item.city) {
            localityParts.push(item.city);
        }
        if (localityParts.length) {
            addressLines.push(localityParts.join(' '));
        }
        if (item.country) {
            addressLines.push(item.country);
        }

        if (addressLines.length) {
            var addressEl = document.createElement('p');
            addressEl.style.margin = '6px 0 0';
            addressEl.style.fontSize = '12px';
            addressEl.textContent = addressLines.join(', ');
            container.appendChild(addressEl);
        }

        var descriptorLines = [];
        if (item.role) {
            var roleLabel = formatDescriptor(item.role);
            if (roleLabel) {
                descriptorLines.push(strings.roleLabel ? strings.roleLabel.replace('%s', roleLabel) : 'Rôle : ' + roleLabel);
            }
        }
        if (item.status) {
            var statusLabel = formatDescriptor(item.status);
            if (statusLabel) {
                descriptorLines.push(strings.statusLabel ? strings.statusLabel.replace('%s', statusLabel) : 'Statut : ' + statusLabel);
            }
        }

        descriptorLines.forEach(function(line) {
            var lineEl = document.createElement('p');
            lineEl.style.margin = '4px 0 0';
            lineEl.style.fontSize = '12px';
            lineEl.textContent = line;
            container.appendChild(lineEl);
        });

        return container;
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
     * Initialize map for member markers using Google Maps
     */
    function initMembersMap() {
        var container = document.getElementById('mj-dashboard-members-map');
        if (!container) {
            return;
        }

        var config = parseConfig(container.getAttribute('data-config'));
        if (!config) {
            return;
        }

        var markersData = Array.isArray(config.markers) ? config.markers : [];
        var strings = config.strings && typeof config.strings === 'object' ? config.strings : {};
        var settings = config.settings && typeof config.settings === 'object' ? config.settings : {};
        var googleMapsApiKey = typeof config.googleMapsApiKey === 'string' ? config.googleMapsApiKey : '';
        var ajaxConfig = config.ajax && typeof config.ajax === 'object' ? config.ajax : {};
        var messageEl = container.parentElement ? container.parentElement.querySelector('.mj-members-map__message') : null;

        if (markersData.length === 0) {
            if (messageEl) {
                messageEl.textContent = strings.noMarkers || 'Aucun membre geolocalisable pour le moment.';
                messageEl.classList.remove('is-hidden');
            }
            return;
        }

        if (!googleMapsApiKey) {
            if (messageEl) {
                messageEl.textContent = strings.noApiKey || 'Cle API Google Maps manquante.';
                messageEl.classList.remove('is-hidden');
            }
            return;
        }

        if (messageEl && strings.loading) {
            messageEl.textContent = strings.loading;
            messageEl.classList.remove('is-hidden');
        }

        ensureGoogleMaps(googleMapsApiKey).then(function(maps) {
            var maxAutoZoom = parseInt(settings.autoFitMaxZoom, 10);
            if (!Number.isFinite(maxAutoZoom) || maxAutoZoom <= 0) {
                maxAutoZoom = 14;
            }

            var configOptions = config.options && typeof config.options === 'object' ? config.options : {};
            var gmOptions = {
                center: { lat: 50.5, lng: 4.5 },
                zoom: 7,
                mapTypeId: maps.MapTypeId.ROADMAP,
            };

            var centerOpt = configOptions.center && typeof configOptions.center === 'object' ? configOptions.center : null;
            if (centerOpt) {
                var cLat = parseNumber(centerOpt.lat);
                var cLng = parseNumber(centerOpt.lng);
                if (cLat !== null && cLng !== null) {
                    gmOptions.center = { lat: cLat, lng: cLng };
                }
            }
            if (configOptions.zoom !== undefined) {
                var z = parseInt(configOptions.zoom, 10);
                if (Number.isFinite(z)) {
                    gmOptions.zoom = z;
                }
            }

            var map = new maps.Map(container, gmOptions);
            var bounds = new maps.LatLngBounds();
            var markerCount = 0;
            var infoWindow = new maps.InfoWindow();
            var geocoder = new maps.Geocoder();

            function hideMessage() {
                if (messageEl) {
                    messageEl.classList.add('is-hidden');
                }
            }

            function showMessage(text) {
                if (messageEl) {
                    if (typeof text === 'string') {
                        messageEl.textContent = text;
                    }
                    messageEl.classList.remove('is-hidden');
                }
            }

            function attachMarker(item, latLng) {
                var marker = new maps.Marker({
                    position: latLng,
                    map: map,
                    title: item.label || '',
                });
                bounds.extend(latLng);
                markerCount++;
                hideMessage();

                var infoContent = createInfoContent(item, strings);
                if (infoContent) {
                    var html = infoContent.outerHTML || infoContent.innerHTML || '';
                    if (html) {
                        marker.addListener('click', function() {
                            infoWindow.setContent(html);
                            infoWindow.open(map, marker);
                        });
                    }
                }
            }

            var geocodeQueue = [];

            markersData.forEach(function(item) {
                var lat = parseNumber(item.latitude);
                var lng = parseNumber(item.longitude);

                if (lat !== null && lng !== null) {
                    attachMarker(item, new maps.LatLng(lat, lng));
                } else if (typeof item.query === 'string' && item.query !== '') {
                    geocodeQueue.push(item);
                }
            });

            var geocodeDelay = parseInt(settings.geocodeDelay, 10);
            if (!Number.isFinite(geocodeDelay) || geocodeDelay < 0) {
                geocodeDelay = 200;
            }
            var batchSize = parseInt(settings.geocodeBatchSize, 10);
            if (!Number.isFinite(batchSize) || batchSize < 1) {
                batchSize = 1;
            }

            function saveGeocodeResult(item, lat, lng) {
                if (!ajaxConfig.url || !ajaxConfig.nonce || !item.id || !item.hash) {
                    return;
                }
                var data = new URLSearchParams();
                data.append('action', 'mj_save_geocode_result');
                data.append('nonce', ajaxConfig.nonce);
                data.append('member_id', item.id);
                data.append('lat', lat);
                data.append('lng', lng);
                data.append('hash', item.hash);
                fetch(ajaxConfig.url, { method: 'POST', body: data, credentials: 'same-origin' })
                    .catch(function(err) { console.warn('MJ Member Map: failed to save geocode result', err); });
            }

            function geocodeItem(item) {
                return new Promise(function(resolve) {
                    geocoder.geocode({ address: item.query }, function(results, status) {
                        if (status === maps.GeocoderStatus.OK && results && results.length > 0) {
                            var location = results[0].geometry.location;
                            attachMarker(item, location);
                            saveGeocodeResult(item, location.lat(), location.lng());
                        } else {
                            console.warn('MJ Member Map: geocode failed for', item.query, status);
                        }
                        resolve();
                    });
                });
            }

            function processQueue() {
                if (geocodeQueue.length === 0) {
                    return Promise.resolve();
                }
                var batch = geocodeQueue.splice(0, batchSize);
                return Promise.all(batch.map(geocodeItem)).then(function() {
                    if (geocodeQueue.length === 0) {
                        return;
                    }
                    return new Promise(function(resolve) {
                        setTimeout(function() {
                            resolve(processQueue());
                        }, geocodeDelay);
                    });
                });
            }

            processQueue().then(function() {
                if (markerCount > 0 && !bounds.isEmpty()) {
                    if (markerCount === 1) {
                        map.setCenter(bounds.getCenter());
                        map.setZoom(Math.min(maxAutoZoom, 14));
                    } else {
                        map.fitBounds(bounds);
                        maps.event.addListenerOnce(map, 'idle', function() {
                            if (map.getZoom() > maxAutoZoom) {
                                map.setZoom(maxAutoZoom);
                            }
                        });
                    }
                    hideMessage();
                } else {
                    showMessage(strings.noMarkers || 'Aucun membre geolocalisable pour le moment.');
                }
            });

        }).catch(function(error) {
            console.error('MJ Member Dashboard failed to load Google Maps', error);
            var fallbackMessage = strings && strings.loadError ? strings.loadError : 'Impossible de charger la carte Google Maps.';
            if (messageEl) {
                messageEl.textContent = fallbackMessage;
                messageEl.classList.remove('is-hidden');
            }
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

    function initDashboard() {
        try {
            initDashboardCharts();
        } catch (chartError) {
            console.error('MJ Member Dashboard: charts initialization failed', chartError);
        }

        try {
            initMembersMap();
        } catch (mapError) {
            console.error('MJ Member Dashboard: map initialization failed', mapError);
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }

})();
