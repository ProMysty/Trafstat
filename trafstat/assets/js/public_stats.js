/* ====================================================================
   TrafStat — public statistics charts
   Reads window.PUBLIC_STATS and renders three Chart.js canvases.
   Null-valued buckets create visible gaps (below suppression threshold).
   ==================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') { return; }
        var stats = window.PUBLIC_STATS || {};

        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color        = '#6B7280';
        Chart.defaults.borderColor  = '#E5E7EB';

        var ORANGE = '#F97316';
        var ORANGE_LIGHT = 'rgba(249,115,22,0.15)';
        var CHARCOAL = '#1F2937';
        var PALETTE = ['#F97316','#EA580C','#FB923C','#FDBA74','#FED7AA',
                       '#F59E0B','#FBBF24','#FCD34D','#1F2937','#6B7280'];

        /* Monthly */
        var monthlyEl = document.getElementById('publicMonthly');
        if (monthlyEl && stats.monthly) {
            new Chart(monthlyEl, {
                type: 'line',
                data: {
                    labels: stats.monthly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: stats.monthly.values,
                        borderColor: ORANGE,
                        backgroundColor: ORANGE_LIGHT,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: ORANGE,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        spanGaps: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            intersect: false,
                            mode: 'index',
                            callbacks: {
                                label: function (ctx) {
                                    if (ctx.parsed.y === null || ctx.parsed.y === undefined) {
                                        return 'Below reporting threshold';
                                    }
                                    return ctx.parsed.y + ' apprehended';
                                }
                            }
                        }
                    },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        /* Categories */
        var categoryEl = document.getElementById('publicCategories');
        if (categoryEl && stats.category) {
            var bg = stats.category.labels.map(function (_, i) {
                return PALETTE[i % PALETTE.length];
            });
            new Chart(categoryEl, {
                type: 'doughnut',
                data: {
                    labels: stats.category.labels,
                    datasets: [{
                        data: stats.category.values,
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

        /* Yearly */
        var yearlyEl = document.getElementById('publicYearly');
        if (yearlyEl && stats.yearly) {
            new Chart(yearlyEl, {
                type: 'bar',
                data: {
                    labels: stats.yearly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: stats.yearly.values,
                        backgroundColor: CHARCOAL,
                        borderRadius: 6,
                        maxBarThickness: 56
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    if (ctx.parsed.y === null || ctx.parsed.y === undefined) {
                                        return 'Below reporting threshold';
                                    }
                                    return ctx.parsed.y + ' apprehended';
                                }
                            }
                        }
                    },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    });
})();