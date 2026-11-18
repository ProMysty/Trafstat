/* ====================================================================
   TrafStat — dashboard charts
   Reads window.DASHBOARD_DATA (injected server-side) and renders:
     - Monthly apprehension trend (last 12 months) — line
     - Violation category distribution          — doughnut
     - Yearly apprehension trend (last 5 years) — bar
   ==================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') { return; }
        var data = window.DASHBOARD_DATA || {};

        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color        = '#6B7280';
        Chart.defaults.borderColor  = '#E5E7EB';

        var ORANGE = '#F97316';
        var ORANGE_LIGHT = 'rgba(249,115,22,0.15)';
        var CHARCOAL = '#1F2937';

        /* ---- Monthly trend ------------------------------------------ */
        var monthlyCanvas = document.getElementById('chartMonthly');
        if (monthlyCanvas && data.monthly && data.monthly.labels) {
            new Chart(monthlyCanvas, {
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
                        pointBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { intersect: false, mode: 'index' }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        /* ---- Category doughnut ------------------------------------- */
        var categoryCanvas = document.getElementById('chartCategories');
        if (categoryCanvas && data.category && data.category.labels) {
            var palette = [
                '#F97316', '#EA580C', '#FB923C', '#FDBA74', '#FED7AA',
                '#F59E0B', '#FBBF24', '#FCD34D', '#1F2937', '#6B7280'
            ];
            var bg = data.category.labels.map(function (_, i) {
                return palette[i % palette.length];
            });
            new Chart(categoryCanvas, {
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
                    cutout: '62%'
                }
            });
        }

        /* ---- Yearly bar -------------------------------------------- */
        var yearlyCanvas = document.getElementById('chartYearly');
        if (yearlyCanvas && data.yearly && data.yearly.labels) {
            new Chart(yearlyCanvas, {
                type: 'bar',
                data: {
                    labels: data.yearly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.yearly.values,
                        backgroundColor: CHARCOAL,
                        borderRadius: 6,
                        maxBarThickness: 56
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }
    });
})();