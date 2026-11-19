/* ====================================================================
   TrafStat — analytics charts (mobile-aware)
   Uses TrafChart helpers from chart-utils.js.
   Category chart swaps doughnut ↔ horizontal bar at the mobile
   breakpoint. All charts rebuild when the breakpoint is crossed.
   ==================================================================== */
(function () {
    'use strict';

    var charts = { monthly: null, category: null, yearly: null, locations: null };

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined' || typeof TrafChart === 'undefined') {
            return;
        }

        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color        = '#6B7280';
        Chart.defaults.borderColor  = '#E5E7EB';

        buildCharts();

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
        var data = window.ANALYTICS_DATA || {};
        var ORANGE = '#F97316';
        var ORANGE_LIGHT = 'rgba(249,115,22,0.15)';
        var CHARCOAL = '#1F2937';

        /* ---- Monthly line ----------------------------------------- */
        var elMonthly = document.getElementById('analyticsMonthly');
        if (elMonthly && data.monthly) {
            charts.monthly = new Chart(elMonthly, {
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
            if (window.trafHideSkeleton) { window.trafHideSkeleton(elMonthly); }
        }

        /* ---- Category — doughnut or bar depending on viewport ----- */
        var elCategory = document.getElementById('analyticsCategories');
        if (elCategory && data.category) {
            charts.category = TrafChart.renderCategoryChart('analyticsCategories', {
                labels: data.category.labels,
                values: data.category.values
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(elCategory); }
        }

        /* ---- Yearly bar ------------------------------------------- */
        var elYearly = document.getElementById('analyticsYearly');
        if (elYearly && data.yearly) {
            charts.yearly = new Chart(elYearly, {
                type: 'bar',
                data: {
                    labels: data.yearly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.yearly.values,
                        backgroundColor: CHARCOAL,
                        borderRadius: 6,
                        maxBarThickness: TrafChart.isMobile() ? 32 : 48
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
            if (window.trafHideSkeleton) { window.trafHideSkeleton(elYearly); }
        }

        /* ---- Locations horizontal bar ----------------------------- */
        var elLocations = document.getElementById('analyticsLocations');
        if (elLocations && data.location) {
            charts.locations = new Chart(elLocations, {
                type: 'bar',
                data: {
                    labels: data.location.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: data.location.values,
                        backgroundColor: ORANGE,
                        borderRadius: 6,
                        maxBarThickness: TrafChart.isMobile() ? 18 : 24
                    }]
                },
                options: TrafChart.horizontalBarOptions({
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.parsed.x + ' apprehended';
                            }
                        }
                    }
                })
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(elLocations); }
        }
    }
})();