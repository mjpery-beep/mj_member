/**
 * Mileage Expense Claims Widget
 *
 * Vanilla JS widget for managing mileage reimbursement requests.
 * Uses Google Maps Directions API for route calculation.
 */
(function () {
    'use strict';

    if (typeof mjMileage === 'undefined') return;

    var D = mjMileage;
    D.costPerKm = parseFloat(D.costPerKm) || 0;
    D.defaultOriginId = D.defaultOriginId ? String(D.defaultOriginId) : '';
    D.memberId = parseInt(D.memberId, 10) || 0;
    var i18n = D.i18n || {};
    var state = {
        tab: 'own',
        ownMileage: D.ownMileage || [],
        allMileage: D.allMileage || [],
        filterMember: '',
        filterStatus: '',
        editingId: null,
        showForm: false,
        map: null,
        directionsService: null,
        directionsRenderer: null,
        calculatedDistance: 0,
    };

    var root = document.getElementById('mj-mileage-app');
    if (!root) return;

    // ── Helpers ──

    function escapeHtml(str) {
        if (typeof window.mjUtils !== 'undefined' && window.mjUtils.escapeHtml) {
            return window.mjUtils.escapeHtml(str);
        }
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatAmount(val) {
        return parseFloat(val || 0).toFixed(2).replace('.', ',') + ' ' + (i18n.currency || '€');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length === 3) return parts[2] + '/' + parts[1] + '/' + parts[0];
        return dateStr;
    }

    function formatKm(val) {
        return parseFloat(val || 0).toFixed(1).replace('.', ',') + ' km';
    }

    function statusClass(status) {
        var map = { pending: 'warning', approved: 'info', rejected: 'danger', reimbursed: 'success' };
        return 'mj-mileage-badge--' + (map[status] || 'default');
    }

    function statusLabel(status) {
        return D.statusLabels && D.statusLabels[status] ? D.statusLabels[status] : status;
    }

    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function getLocationById(id) {
        if (!id || !D.locations) return null;
        for (var i = 0; i < D.locations.length; i++) {
            if (String(D.locations[i].id) === String(id)) return D.locations[i];
        }
        return null;
    }

    function getDefaultOriginLocation() {
        if (D.defaultOriginId) return getLocationById(D.defaultOriginId);
        // Fallback: first available location
        if (D.locations && D.locations.length > 0) return D.locations[0];
        return null;
    }

    // ── AJAX ──

    function ajax(action, data, cb) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', D.nonce);
        for (var k in data) {
            if (data.hasOwnProperty(k) && data[k] !== null && data[k] !== undefined) {
                fd.append(k, data[k]);
            }
        }
        fetch(D.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success) {
                    cb(null, resp.data);
                } else {
                    cb(resp.data && resp.data.message ? resp.data.message : (i18n.error || 'Erreur'));
                }
            })
            .catch(function () { cb(i18n.error || 'Erreur réseau'); });
    }

    // ── Google Maps ──

    var mapsLoadState = 'idle'; // idle | loading | ready | error
    var mapsCallbacks = [];

    function isGoogleMapsReady() {
        return window.google && window.google.maps && window.google.maps.DirectionsService;
    }

    function loadGoogleMaps(cb) {
        // Already available globally (loaded by Elementor or other plugin)
        if (isGoogleMapsReady()) {
            mapsLoadState = 'ready';
            cb();
            return;
        }
        if (mapsLoadState === 'ready') { cb(); return; }
        if (mapsLoadState === 'error') { cb(); return; }
        if (!D.googleApiKey) { cb(); return; }

        mapsCallbacks.push(cb);
        if (mapsLoadState === 'loading') return;
        mapsLoadState = 'loading';

        window.__mjMileageMapsReady = function () {
            mapsLoadState = 'ready';
            var cbs = mapsCallbacks.slice();
            mapsCallbacks = [];
            for (var i = 0; i < cbs.length; i++) cbs[i]();
        };

        var script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(D.googleApiKey) + '&libraries=places&callback=__mjMileageMapsReady';
        script.async = true;
        script.defer = true;
        script.onerror = function () {
            mapsLoadState = 'error';
            var cbs = mapsCallbacks.slice();
            mapsCallbacks = [];
            for (var i = 0; i < cbs.length; i++) cbs[i]();
        };
        document.head.appendChild(script);
    }

    function initMap(container) {
        if (!isGoogleMapsReady()) return;
        container.style.display = 'block';
        state.map = new google.maps.Map(container, {
            center: { lat: 50.45, lng: 4.85 },
            zoom: 9,
            mapTypeControl: false,
            streetViewControl: false,
        });
        state.directionsService = new google.maps.DirectionsService();
        state.directionsRenderer = new google.maps.DirectionsRenderer({ suppressMarkers: false });
        state.directionsRenderer.setMap(state.map);
        // Force resize so map tiles render inside scrollable modal
        google.maps.event.trigger(state.map, 'resize');
    }

    function ensureMapReady(container, cb) {
        if (state.directionsService) { cb(); return; }
        loadGoogleMaps(function () {
            if (!state.map && container) initMap(container);
            cb();
        });
    }

    function calculateRoute(originAddr, destAddr, mapContainer, cb) {
        ensureMapReady(mapContainer, function () {
            if (!state.directionsService) {
                alert(i18n.error || 'Google Maps non disponible. Entrez la distance manuellement.');
                cb(null);
                return;
            }
            state.directionsService.route({
                origin: originAddr,
                destination: destAddr,
                travelMode: google.maps.TravelMode.DRIVING,
            }, function (response, status) {
                if (status === 'OK') {
                    state.directionsRenderer.setDirections(response);
                    var leg = response.routes[0].legs[0];
                    var km = leg.distance.value / 1000;
                    cb(km);
                } else {
                    alert((i18n.error || 'Erreur') + ': ' + status);
                    cb(null);
                }
            });
        });
    }

    // ── Render ──

    function render() {
        var trips = state.tab === 'own' ? state.ownMileage : state.allMileage;

        // Apply filters
        if (state.filterMember) {
            trips = trips.filter(function (t) { return String(t.member_id) === String(state.filterMember); });
        }
        if (state.filterStatus) {
            trips = trips.filter(function (t) { return t.status === state.filterStatus; });
        }

        var html = '';

        // Tabs (segmented control)
        if (D.isCoordinator) {
            var pendingCount = 0;
            state.allMileage.forEach(function (t) { if (t.status === 'pending') pendingCount++; });
            html += '<div class="mj-mileage__tabs">';
            html += '<button class="mj-mileage__tab' + (state.tab === 'own' ? ' mj-mileage__tab--active' : '') + '" data-tab="own">';
            html += '<span class="mj-mileage__tab-icon">🚗</span> ';
            html += escapeHtml(i18n.myTrips || 'Mes trajets');
            if (state.ownMileage.length > 0) html += ' <span class="mj-mileage__tab-badge">' + state.ownMileage.length + '</span>';
            html += '</button>';
            html += '<button class="mj-mileage__tab' + (state.tab === 'all' ? ' mj-mileage__tab--active' : '') + '" data-tab="all">';
            html += '<span class="mj-mileage__tab-icon">📊</span> ';
            html += escapeHtml(i18n.allTrips || 'Tous les trajets');
            if (pendingCount > 0) html += ' <span class="mj-mileage__tab-badge mj-mileage__tab-badge--warning">' + pendingCount + '</span>';
            else if (state.allMileage.length > 0) html += ' <span class="mj-mileage__tab-badge">' + state.allMileage.length + '</span>';
            html += '</button>';
            html += '</div>';
        }

        // Add button
        html += '<div class="mj-mileage-toolbar">';
        html += '<button class="mj-mileage-btn mj-mileage-btn--primary" data-action="show-form">+ ' + escapeHtml(i18n.newTrip || 'Nouveau trajet') + '</button>';
        html += '</div>';

        // Disclaimer
        if (D.disclaimer) {
            html += '<div class="mj-mileage-disclaimer">';
            html += '<span class="mj-mileage-disclaimer__icon">⚠️</span> ';
            html += escapeHtml(D.disclaimer);
            html += '</div>';
        }

        // Filters (coordinator all tab)
        if (state.tab === 'all' && D.isCoordinator) {
            html += '<div class="mj-mileage-filters">';
            html += '<select class="mj-mileage-select" data-filter="member"><option value="">' + escapeHtml(i18n.allMembers) + '</option>';
            (D.members || []).forEach(function (m) {
                html += '<option value="' + m.id + '"' + (String(state.filterMember) === String(m.id) ? ' selected' : '') + '>' + escapeHtml(m.name) + '</option>';
            });
            html += '</select>';
            html += '<select class="mj-mileage-select" data-filter="status"><option value="">' + escapeHtml(i18n.allStatuses) + '</option>';
            Object.keys(D.statusLabels || {}).forEach(function (s) {
                html += '<option value="' + s + '"' + (state.filterStatus === s ? ' selected' : '') + '>' + escapeHtml(D.statusLabels[s]) + '</option>';
            });
            html += '</select>';
            html += '</div>';
        }

        // Table
        if (trips.length === 0) {
            html += '<p class="mj-mileage-empty">' + escapeHtml(i18n.noTrips) + '</p>';
        } else {
            // Summary
            var totalKm = 0, totalCost = 0, pendingCost = 0, reimbursedCost = 0;
            trips.forEach(function (t) {
                totalKm += t.distance_km;
                totalCost += t.total_cost;
                if (t.status === 'pending' || t.status === 'approved') pendingCost += t.total_cost;
                if (t.status === 'reimbursed') reimbursedCost += t.total_cost;
            });

            html += '<div class="mj-mileage-summary">';
            html += '<span class="mj-mileage-summary__item"><strong>' + escapeHtml(i18n.totalKm || 'Total km') + ':</strong> ' + formatKm(totalKm) + '</span>';
            html += '<span class="mj-mileage-summary__item"><strong>' + escapeHtml(i18n.total) + ':</strong> ' + formatAmount(totalCost) + '</span>';
            html += '<span class="mj-mileage-summary__item mj-mileage-summary__item--pending"><strong>' + escapeHtml(i18n.pendingAmount) + ':</strong> ' + formatAmount(pendingCost) + '</span>';
            html += '<span class="mj-mileage-summary__item mj-mileage-summary__item--reimbursed"><strong>' + escapeHtml(i18n.reimbursedAmount) + ':</strong> ' + formatAmount(reimbursedCost) + '</span>';
            html += '</div>';

            html += '<div class="mj-mileage-table-wrap"><table class="mj-mileage-table">';
            html += '<thead><tr>';
            html += '<th>' + escapeHtml(i18n.details || 'Détails') + '</th>';
            html += '<th>' + escapeHtml(i18n.origin) + '</th>';
            html += '<th>' + escapeHtml(i18n.destination) + '</th>';
            html += '<th>' + escapeHtml(i18n.distance) + '</th>';
            html += '<th>' + escapeHtml(i18n.totalCost) + '</th>';
            html += '<th>' + escapeHtml(i18n.status) + '</th>';
            html += '<th>' + escapeHtml(i18n.actions) + '</th>';
            html += '</tr></thead><tbody>';

            var colCount = 7;
            trips.forEach(function (t) {
                html += '<tr>';
                html += '<td data-label="' + escapeHtml(i18n.details || 'Détails') + '" class="mj-mileage-table__details">';
                html += '<span class="mj-mileage-detail-date">' + formatDate(t.trip_date) + '</span>';
                if (state.tab === 'all' && t.member_name) {
                    html += '<span class="mj-mileage-detail-member">' + escapeHtml(t.member_name) + '</span>';
                }
                if (t.description) {
                    html += '<span class="mj-mileage-detail-desc">' + escapeHtml(t.description) + '</span>';
                }
                html += '</td>';
                html += '<td data-label="' + escapeHtml(i18n.origin) + '">' + escapeHtml(t.origin) + (t.round_trip ? ' ↔' : '') + '</td>';
                html += '<td data-label="' + escapeHtml(i18n.destination) + '">' + escapeHtml(t.destination) + '</td>';
                html += '<td data-label="' + escapeHtml(i18n.distance) + '">' + formatKm(t.distance_km) + '</td>';
                html += '<td data-label="' + escapeHtml(i18n.totalCost) + '">' + formatAmount(t.total_cost) + '</td>';
                html += '<td data-label="' + escapeHtml(i18n.status) + '"><span class="mj-mileage-badge ' + statusClass(t.status) + '">' + escapeHtml(statusLabel(t.status)) + '</span>';
                if (t.reviewer_comment) {
                    html += '<span class="mj-mileage-comment" title="' + escapeHtml(t.reviewer_comment) + '">💬</span>';
                }
                html += '</td>';
                html += '<td data-label="' + escapeHtml(i18n.actions) + '">';
                html += renderActions(t);
                html += '</td>';
                html += '</tr>';
                // Expandable map row
                html += '<tr class="mj-mileage-map-row" id="mj-mil-maprow-' + t.id + '" style="display:none;">';
                html += '<td colspan="' + colCount + '">';
                html += '<div class="mj-mileage-inline-map" id="mj-mil-inlinemap-' + t.id + '"></div>';
                html += '</td></tr>';
            });

            html += '</tbody></table></div>';
        }

        root.innerHTML = html;
        bindEvents();
    }

    function renderActions(t) {
        var html = '';
        var isOwn = t.member_id === D.memberId;

        // Show map button (for all users)
        if (D.googleApiKey && t.origin && t.destination) {
            html += '<button class="mj-mileage-action mj-mileage-action--map" data-action="toggle-map" data-id="' + t.id + '" data-origin="' + escapeHtml(t.origin) + '" data-dest="' + escapeHtml(t.destination) + '" title="' + escapeHtml(i18n.showRoute || 'Voir le trajet') + '">🗺️</button>';
        }

        if (isOwn && t.status === 'pending') {
            html += '<button class="mj-mileage-action mj-mileage-action--edit" data-action="edit" data-id="' + t.id + '" title="' + escapeHtml(i18n.edit) + '">✏️</button>';
            html += '<button class="mj-mileage-action mj-mileage-action--delete" data-action="delete" data-id="' + t.id + '" title="' + escapeHtml(i18n.delete) + '">🗑️</button>';
        }

        if (D.isCoordinator) {
            if (t.status === 'pending') {
                html += '<button class="mj-mileage-action mj-mileage-action--approve" data-action="approve" data-id="' + t.id + '" title="' + escapeHtml(i18n.approve) + '">✅</button>';
                html += '<button class="mj-mileage-action mj-mileage-action--reject" data-action="reject" data-id="' + t.id + '" title="' + escapeHtml(i18n.reject) + '">❌</button>';
            }
            if (t.status === 'approved') {
                html += '<button class="mj-mileage-action mj-mileage-action--reimburse" data-action="reimburse" data-id="' + t.id + '" title="' + escapeHtml(i18n.reimburse) + '">💰</button>';
            }
            if (!isOwn || t.status !== 'pending') {
                html += '<button class="mj-mileage-action mj-mileage-action--delete" data-action="delete" data-id="' + t.id + '" title="' + escapeHtml(i18n.delete) + '">🗑️</button>';
            }
        }

        return html;
    }

    // ── Form Modal ──

    function showFormModal(trip) {
        var isEdit = !!trip;
        var defOrigin = getDefaultOriginLocation();
        var originText = isEdit ? (trip.origin || '') : (defOrigin ? defOrigin.address || defOrigin.name : '');
        var originLocId = isEdit ? (trip.origin_location_id || '') : (defOrigin ? defOrigin.id : '');
        var destText = isEdit ? (trip.destination || '') : '';
        var destLocId = isEdit ? (trip.destination_location_id || '') : '';

        var overlay = document.createElement('div');
        overlay.className = 'mj-mileage-overlay';

        var html = '<div class="mj-mileage-modal">';
        html += '<div class="mj-mileage-modal__header"><h2>' + escapeHtml(isEdit ? (i18n.editTrip || 'Modifier le trajet') : (i18n.newTrip || 'Nouveau trajet')) + '</h2>';
        html += '<button class="mj-mileage-modal__close" data-action="close-modal">&times;</button></div>';
        html += '<div class="mj-mileage-modal__body">';

        // Trip date
        html += '<div class="mj-mileage-field">';
        html += '<label>' + escapeHtml(i18n.tripDate || 'Date') + '</label>';
        html += '<input type="date" id="mj-mil-date" value="' + escapeHtml(isEdit ? trip.trip_date : todayStr()) + '" />';
        html += '</div>';

        // Origin
        html += '<div class="mj-mileage-field">';
        html += '<label>' + escapeHtml(i18n.origin || 'Départ') + '</label>';
        html += '<div class="mj-mileage-location-picker">';
        html += '<select id="mj-mil-origin-loc">';
        html += '<option value="">' + escapeHtml(i18n.manualAddress || 'Adresse manuelle') + '</option>';
        (D.locations || []).forEach(function (loc) {
            html += '<option value="' + loc.id + '" data-address="' + escapeHtml(loc.address) + '"' + (String(originLocId) === String(loc.id) ? ' selected' : '') + '>' + escapeHtml(loc.name) + '</option>';
        });
        html += '</select>';
        html += '<input type="text" id="mj-mil-origin" placeholder="' + escapeHtml(i18n.manualAddress || 'Adresse') + '" value="' + escapeHtml(originText) + '" />';
        html += '</div></div>';

        // Destination
        html += '<div class="mj-mileage-field">';
        html += '<label>' + escapeHtml(i18n.destination || 'Destination') + '</label>';
        html += '<div class="mj-mileage-location-picker">';
        html += '<select id="mj-mil-dest-loc">';
        html += '<option value="">' + escapeHtml(i18n.manualAddress || 'Adresse manuelle') + '</option>';
        (D.locations || []).forEach(function (loc) {
            html += '<option value="' + loc.id + '" data-address="' + escapeHtml(loc.address) + '"' + (String(destLocId) === String(loc.id) ? ' selected' : '') + '>' + escapeHtml(loc.name) + '</option>';
        });
        html += '</select>';
        html += '<input type="text" id="mj-mil-dest" placeholder="' + escapeHtml(i18n.manualAddress || 'Adresse') + '" value="' + escapeHtml(destText) + '" />';
        html += '</div></div>';

        // Round trip
        html += '<div class="mj-mileage-field mj-mileage-field--inline">';
        html += '<label><input type="checkbox" id="mj-mil-roundtrip"' + (isEdit && trip.round_trip ? ' checked' : '') + ' /> ' + escapeHtml(i18n.roundTrip || 'Aller-retour') + '</label>';
        html += '</div>';

        // Map + Calculate
        html += '<div class="mj-mileage-field">';
        html += '<button class="mj-mileage-btn mj-mileage-btn--secondary" id="mj-mil-calc">📍 ' + escapeHtml(i18n.calculateRoute || 'Calculer l\'itinéraire') + '</button>';
        html += '<div id="mj-mil-map" class="mj-mileage-map"></div>';
        html += '</div>';

        // Distance
        html += '<div class="mj-mileage-field">';
        html += '<label>' + escapeHtml(i18n.distance || 'Distance (km)') + '</label>';
        html += '<input type="number" step="0.1" min="0" id="mj-mil-distance" value="' + (isEdit ? trip.distance_km : '') + '" />';
        html += '<small class="mj-mileage-cost-display" id="mj-mil-cost-display">';
        if (isEdit) {
            html += escapeHtml(i18n.totalCost || 'Montant') + ': ' + formatAmount(trip.total_cost);
        }
        html += '</small>';
        html += '</div>';

        // Description
        html += '<div class="mj-mileage-field">';
        html += '<label>' + escapeHtml(i18n.description || 'Description') + ' *</label>';
        html += '<textarea id="mj-mil-desc" rows="3" placeholder="' + escapeHtml(i18n.description) + '">' + escapeHtml(isEdit ? trip.description : '') + '</textarea>';
        html += '</div>';

        // Cost info
        html += '<div class="mj-mileage-cost-info">';
        html += escapeHtml(i18n.costPerKm || 'Coût/km') + ': ' + D.costPerKm.toFixed(4).replace('.', ',') + ' ' + (i18n.currency || '€');
        html += '</div>';

        html += '</div>'; // body

        html += '<div class="mj-mileage-modal__footer">';
        html += '<button class="mj-mileage-btn mj-mileage-btn--secondary" data-action="close-modal">' + escapeHtml(i18n.cancel) + '</button>';
        html += '<button class="mj-mileage-btn mj-mileage-btn--primary" id="mj-mil-submit">' + escapeHtml(isEdit ? (i18n.save || 'Enregistrer') : (i18n.submit || 'Soumettre')) + '</button>';
        html += '</div>';
        html += '</div>';

        overlay.innerHTML = html;
        document.body.appendChild(overlay);

        // Preload Maps JS (non-blocking); map container stays hidden until Calculate click
        if (D.googleApiKey) {
            loadGoogleMaps(function () {});
        }

        bindFormEvents(overlay, isEdit ? trip.id : null);
    }

    function bindFormEvents(overlay, editId) {
        // Close
        overlay.querySelectorAll('[data-action="close-modal"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                overlay.remove();
                state.map = null;
                state.directionsService = null;
                state.directionsRenderer = null;
            });
        });

        // Close on backdrop click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                overlay.remove();
                state.map = null;
            }
        });

        // Location selectors
        var originLoc = overlay.querySelector('#mj-mil-origin-loc');
        var originInput = overlay.querySelector('#mj-mil-origin');
        var destLoc = overlay.querySelector('#mj-mil-dest-loc');
        var destInput = overlay.querySelector('#mj-mil-dest');

        if (originLoc) {
            originLoc.addEventListener('change', function () {
                var opt = originLoc.options[originLoc.selectedIndex];
                if (opt.value) {
                    originInput.value = opt.getAttribute('data-address') || opt.textContent;
                    originInput.readOnly = true;
                } else {
                    originInput.value = '';
                    originInput.readOnly = false;
                }
            });
            // Init state
            if (originLoc.value) originInput.readOnly = true;
        }

        if (destLoc) {
            destLoc.addEventListener('change', function () {
                var opt = destLoc.options[destLoc.selectedIndex];
                if (opt.value) {
                    destInput.value = opt.getAttribute('data-address') || opt.textContent;
                    destInput.readOnly = true;
                } else {
                    destInput.value = '';
                    destInput.readOnly = false;
                }
            });
            if (destLoc.value) destInput.readOnly = true;
        }

        // Calculate route
        var calcBtn = overlay.querySelector('#mj-mil-calc');
        var mapContainer = overlay.querySelector('#mj-mil-map');
        if (calcBtn) {
            calcBtn.addEventListener('click', function () {
                var o = originInput.value;
                var d = destInput.value;
                if (!o || !d) {
                    alert(i18n.originRequired || 'Remplissez le départ et la destination.');
                    return;
                }

                calcBtn.disabled = true;
                calcBtn.textContent = '⏳ Calcul…';

                calculateRoute(o, d, mapContainer, function (km) {
                    calcBtn.disabled = false;
                    calcBtn.textContent = '📍 ' + (i18n.calculateRoute || 'Calculer l\'itinéraire');
                    if (km !== null) {
                        var distEl = overlay.querySelector('#mj-mil-distance');
                        if (distEl) {
                            distEl.value = km.toFixed(1);
                            updateCostDisplay(overlay);
                        }
                        calcBtn.textContent = '✅ ' + (i18n.routeCalculated || 'Itinéraire calculé');
                    }
                });
            });
        }

        // Distance update -> cost display
        var distanceField = overlay.querySelector('#mj-mil-distance');
        if (distanceField) {
            distanceField.addEventListener('input', function () {
                updateCostDisplay(overlay);
            });
        }

        var roundtripField = overlay.querySelector('#mj-mil-roundtrip');
        if (roundtripField) {
            roundtripField.addEventListener('change', function () {
                updateCostDisplay(overlay);
            });
        }

        // Submit
        var submitBtn = overlay.querySelector('#mj-mil-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var dateVal = overlay.querySelector('#mj-mil-date').value;
                var originVal = originInput.value;
                var originLocVal = originLoc.value;
                var destVal = destInput.value;
                var destLocVal = destLoc.value;
                var distVal = parseFloat(distanceField.value || 0);
                var descVal = overlay.querySelector('#mj-mil-desc').value.trim();
                var roundTrip = roundtripField && roundtripField.checked;

                if (!originVal) { alert(i18n.originRequired || 'Départ requis'); return; }
                if (!destVal) { alert(i18n.destinationRequired || 'Destination requise'); return; }
                if (distVal <= 0) { alert(i18n.distanceRequired || 'Distance requise'); return; }
                if (!descVal) { alert(i18n.descriptionRequired || 'Description requise'); return; }

                submitBtn.disabled = true;
                submitBtn.textContent = '⏳…';

                var data = {
                    trip_date: dateVal,
                    origin: originVal,
                    origin_location_id: originLocVal || '',
                    destination: destVal,
                    destination_location_id: destLocVal || '',
                    distance_km: distVal,
                    description: descVal,
                    round_trip: roundTrip ? '1' : '0',
                };

                if (editId) {
                    data.id = editId;
                    ajax('mj_mileage_update', data, function (err) {
                        if (err) {
                            alert(err);
                            submitBtn.disabled = false;
                            submitBtn.textContent = i18n.save || 'Enregistrer';
                            return;
                        }
                        overlay.remove();
                        reloadData();
                    });
                } else {
                    ajax('mj_mileage_create', data, function (err) {
                        if (err) {
                            alert(err);
                            submitBtn.disabled = false;
                            submitBtn.textContent = i18n.submit || 'Soumettre';
                            return;
                        }
                        overlay.remove();
                        reloadData();
                    });
                }
            });
        }
    }

    function updateCostDisplay(overlay) {
        var distEl = overlay.querySelector('#mj-mil-distance');
        var roundEl = overlay.querySelector('#mj-mil-roundtrip');
        var displayEl = overlay.querySelector('#mj-mil-cost-display');
        if (!distEl || !displayEl) return;

        var km = parseFloat(distEl.value || 0);
        if (roundEl && roundEl.checked) km = km * 2;
        var cost = km * D.costPerKm;
        displayEl.textContent = (i18n.totalCost || 'Montant') + ': ' + formatAmount(cost) + ' (' + formatKm(km) + ')';
    }

    // ── Data reload ──

    function reloadData() {
        // Reload page to get fresh data
        window.location.reload();
    }

    // ── Events ──

    function bindEvents() {
        // Tabs
        root.querySelectorAll('.mj-mileage__tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.tab = btn.getAttribute('data-tab');
                state.filterMember = '';
                state.filterStatus = '';
                render();
            });
        });

        // New form
        root.querySelectorAll('[data-action="show-form"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showFormModal(null);
            });
        });

        // Filters
        root.querySelectorAll('[data-filter="member"]').forEach(function (sel) {
            sel.addEventListener('change', function () {
                state.filterMember = sel.value;
                render();
            });
        });
        root.querySelectorAll('[data-filter="status"]').forEach(function (sel) {
            sel.addEventListener('change', function () {
                state.filterStatus = sel.value;
                render();
            });
        });

        // Actions
        root.querySelectorAll('[data-action="edit"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = parseInt(btn.getAttribute('data-id'));
                var trip = findTrip(id);
                if (trip) showFormModal(trip);
            });
        });

        root.querySelectorAll('[data-action="delete"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm(i18n.confirmDelete || 'Supprimer ?')) return;
                var id = btn.getAttribute('data-id');
                btn.disabled = true;
                ajax('mj_mileage_delete', { id: id }, function (err) {
                    if (err) { alert(err); btn.disabled = false; return; }
                    reloadData();
                });
            });
        });

        root.querySelectorAll('[data-action="approve"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                btn.disabled = true;
                ajax('mj_mileage_update_status', { id: id, status: 'approved' }, function (err) {
                    if (err) { alert(err); btn.disabled = false; return; }
                    reloadData();
                });
            });
        });

        root.querySelectorAll('[data-action="reject"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var reason = prompt(i18n.rejectionReason || 'Motif du refus');
                if (reason === null) return;
                if (!reason.trim()) { alert(i18n.rejectionReasonRequired || 'Motif requis'); return; }
                var id = btn.getAttribute('data-id');
                btn.disabled = true;
                ajax('mj_mileage_update_status', { id: id, status: 'rejected', comment: reason }, function (err) {
                    if (err) { alert(err); btn.disabled = false; return; }
                    reloadData();
                });
            });
        });

        root.querySelectorAll('[data-action="reimburse"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                btn.disabled = true;
                ajax('mj_mileage_update_status', { id: id, status: 'reimbursed' }, function (err) {
                    if (err) { alert(err); btn.disabled = false; return; }
                    reloadData();
                });
            });
        });

        // Toggle inline map
        root.querySelectorAll('[data-action="toggle-map"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var mapRow = document.getElementById('mj-mil-maprow-' + id);
                if (!mapRow) return;

                // Toggle visibility
                if (mapRow.style.display !== 'none') {
                    mapRow.style.display = 'none';
                    return;
                }
                mapRow.style.display = '';

                var mapContainer = document.getElementById('mj-mil-inlinemap-' + id);
                if (!mapContainer || mapContainer.getAttribute('data-loaded')) return;

                var origin = btn.getAttribute('data-origin');
                var dest = btn.getAttribute('data-dest');
                mapContainer.textContent = '⏳ ' + (i18n.calculateRoute || 'Chargement…');

                loadGoogleMaps(function () {
                    if (!isGoogleMapsReady()) {
                        mapContainer.textContent = i18n.error || 'Google Maps non disponible.';
                        return;
                    }
                    mapContainer.textContent = '';
                    mapContainer.setAttribute('data-loaded', '1');

                    var inlineMap = new google.maps.Map(mapContainer, {
                        center: { lat: 50.45, lng: 4.85 },
                        zoom: 9,
                        mapTypeControl: false,
                        streetViewControl: false,
                    });
                    var renderer = new google.maps.DirectionsRenderer({ suppressMarkers: false });
                    renderer.setMap(inlineMap);
                    var service = new google.maps.DirectionsService();
                    service.route({
                        origin: origin,
                        destination: dest,
                        travelMode: google.maps.TravelMode.DRIVING,
                    }, function (response, status) {
                        if (status === 'OK') {
                            renderer.setDirections(response);
                        } else {
                            mapContainer.textContent = (i18n.error || 'Erreur') + ': ' + status;
                        }
                    });
                });
            });
        });
    }

    function findTrip(id) {
        var all = state.ownMileage.concat(state.allMileage);
        for (var i = 0; i < all.length; i++) {
            if (all[i].id === id) return all[i];
        }
        return null;
    }

    // ── Init ──

    render();
})();
