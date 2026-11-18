/* ====================================================================
   TrafStat — analytics charts
   Reads window.ANALYTICS_DATA and renders:
     - Monthly trend          (line)
     - Category distribution  (doughnut)
     - Yearly comparison      (bar)
     - Top locations          (horizontal bar)
   ==================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') { return; }
        var data = window.ANALYTICS_DATA || {};

        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color        = '#6B7280';
        Chart.defaults.borderColor  = '#E5E7EB';

        var ORANGE = '#F97316';
        var ORANGE_LIGHT = 'rgba(249,115,22,0.15)';
        var CHARCOAL = '#1F2937';
        var PALETTE = ['#F97316','#EA580C','#FB923C','#FDBA74','#FED7AA',
                       '#F59E0B','#FBBF24','#FCD34D','#1F2937','#6B7280'];

        /* ---- Monthly ----------------------------------------------- */
        var elMonthly = document.getElementById('analyticsMonthly');
        if (elMonthly && data.monthly) {
            new Chart(elMonthly, {
                type: 'line',
                data: {
                    labels: data.monthly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.monthly.values,
                        borderColor: ORANGE,
                        backgroundColor: ORANGE_LIGHT,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: ORANGE,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { intersect: false, mode: 'index' }
                    },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        /* ---- Category doughnut ------------------------------------ */
        var elCategory = document.getElementById('analyticsCategories');
        if (elCategory && data.category) {
            var bg = data.category.labels.map(function (_, i) {
                return PALETTE[i % PALETTE.length];
            });
            new Chart(elCategory, {
                type: 'doughnut',
                data: {
                    labels: data.category.labels,
                    datasets: [{
                        data: data.category.values,
                        backgroundColor: bg,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 12, padding: 10, font: { size: 11 } }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        /* ---- Yearly bar ------------------------------------------- */
        var elYearly = document.getElementById('analyticsYearly');
        if (elYearly && data.yearly) {
            new Chart(elYearly, {
                type: 'bar',
                data: {
                    labels: data.yearly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.yearly.values,
                        backgroundColor: CHARCOAL,
                        borderRadius: 6,
                        maxBarThickness: 48
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        /* ---- Locations horizontal bar ----------------------------- */
        var elLocations = document.getElementById('analyticsLocations');
        if (elLocations && data.location) {
            new Chart(elLocations, {
                type: 'bar',
                data: {
                    labels: data.location.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.location.values,
                        backgroundColor: ORANGE,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 } },
                        y: {
                            ticks: {
                                font: { size: 11 },
                                callback: function (value) {
                                    var label = this.getLabelForValue(value);
                                    return label.length > 28
                                        ? label.slice(0, 27) + '…'
                                        : label;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
})();