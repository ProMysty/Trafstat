/* ====================================================================
   TrafStat — dashboard charts (mobile-aware)
   Uses TrafChart helpers from chart-utils.js for responsive configs.
   Rebuilds all three charts when the viewport crosses the mobile
   breakpoint so the category chart swaps doughnut ↔ bar appropriately.
   ==================================================================== */
(function () {
    'use strict';

    var charts = { monthly: null, category: null, yearly: null };

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined' || typeof TrafChart === 'undefined') {
            return;
        }

        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color        = '#6B7280';
        Chart.defaults.borderColor  = '#E5E7EB';

        buildCharts();

        /* Rebuild when the viewport crosses the mobile breakpoint. */
        TrafChart.onChangeBreakpoint(function () {
            Object.keys(charts).forEach(function (key) {
                if (charts[key]) {
                    charts[key].destroy();
                    charts[key] = null;
                }
            });
            buildCharts();
        });
    });

    function buildCharts() {
        var data = window.DASHBOARD_DATA || {};
        var ORANGE = '#F97316';
        var ORANGE_LIGHT = 'rgba(249,115,22,0.15)';
        var CHARCOAL = '#1F2937';

        /* ---- Monthly line ----------------------------------------- */
        var monthlyCanvas = document.getElementById('chartMonthly');
        if (monthlyCanvas && data.monthly && data.monthly.labels) {
            charts.monthly = new Chart(monthlyCanvas, {
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
                        pointRadius: TrafChart.isMobile() ? 3 : 4,
                        pointHoverRadius: TrafChart.isMobile() ? 6 : 5,
                        pointHitRadius: TrafChart.isMobile() ? 18 : 10,
                        pointBackgroundColor: ORANGE,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: TrafChart.lineOptions({
                    showLegend: false,
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.parsed.y + ' apprehended';
                            }
                        }
                    }
                })
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(monthlyCanvas); }
        }

        /* ---- Category — doughnut on desktop, bar on mobile -------- */
        var categoryCanvas = document.getElementById('chartCategories');
        if (categoryCanvas && data.category && data.category.labels) {
            charts.category = TrafChart.renderCategoryChart('chartCategories', {
                labels: data.category.labels,
                values: data.category.values
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(categoryCanvas); }
        }

        /* ---- Yearly bar ------------------------------------------- */
        var yearlyCanvas = document.getElementById('chartYearly');
        if (yearlyCanvas && data.yearly && data.yearly.labels) {
            charts.yearly = new Chart(yearlyCanvas, {
                type: 'bar',
                data: {
                    labels: data.yearly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.yearly.values,
                        backgroundColor: CHARCOAL,
                        borderRadius: 6,
                        maxBarThickness: TrafChart.isMobile() ? 32 : 56
                    }]
                },
                options: TrafChart.barOptions({
                    showLegend: false,
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.parsed.y + ' apprehended';
                            }
                        }
                    }
                })
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(yearlyCanvas); }
        }
    }
})();