/* ====================================================================
   TrafStat — hotspot map
   Renders window.HOTSPOT_DATA.markers using Leaflet + marker clustering.
   ==================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('hotspotMap');
        if (!container || typeof L === 'undefined') { return; }

        var data = window.HOTSPOT_DATA || { markers: [], center: { lat: 7.8257, lng: 123.4370 }, zoom: 12 };
        if (!Array.isArray(data.markers) || data.markers.length === 0) { return; }

        var map = L.map(container, { scrollWheelZoom: true })
                   .setView([data.center.lat, data.center.lng], data.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var cluster = (typeof L.markerClusterGroup === 'function')
            ? L.markerClusterGroup({
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                removeOutsideVisibleBounds: true
            })
            : L.layerGroup();

        var markerIcon = L.divIcon({
            className: '',
            html: '<div class="trafstat-marker-dot"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9]
        });

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        data.markers.forEach(function (m) {
            var timePart = m.time ? ' ' + esc(m.time) : '';
            var platePart = m.plate
                ? '<dt>Plate</dt><dd><code>' + esc(m.plate) + '</code></dd>'
                : '';

            var popupHtml = ''
                + '<h6>' + esc(m.category) + '</h6>'
                + '<dl>'
                +   '<dt>Reference</dt><dd><code>' + esc(m.ref) + '</code></dd>'
                +   '<dt>Date</dt><dd>' + esc(m.date) + timePart + '</dd>'
                +   '<dt>Location</dt><dd>' + esc(m.location) + '</dd>'
                +   '<dt>Violator</dt><dd>' + esc(m.violator) + '</dd>'
                +   platePart
                + '</dl>'
                + '<div style="margin-top:0.5rem;">'
                +   '<a href="' + esc(m.edit_url) + '" '
                +      'style="font-size:0.8rem; color:#F97316; text-decoration:none;">'
                +      'Open record &rarr;'
                +   '</a>'
                + '</div>';

            L.marker([m.lat, m.lng], { icon: markerIcon })
                .bindPopup(popupHtml)
                .addTo(cluster);
        });

        map.addLayer(cluster);

        if (data.markers.length > 1 && typeof cluster.getBounds === 'function') {
            try {
                map.fitBounds(cluster.getBounds(), { padding: [30, 30], maxZoom: 14 });
            } catch (e) {
                /* Ignore — single marker clusters can throw */
            }
        }
    });
})();