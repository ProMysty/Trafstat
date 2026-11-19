/* ====================================================================
   TrafStat — public statistics charts (mobile-aware)
   Uses TrafChart helpers from chart-utils.js.
   Null-valued buckets create visible gaps (below suppression threshold).
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
        var stats = window.PUBLIC_STATS || {};
        var ORANGE = '#F97316';
        var ORANGE_LIGHT = 'rgba(249,115,22,0.15)';
        var CHARCOAL = '#1F2937';

        /* ---- Monthly line ----------------------------------------- */
        var monthlyEl = document.getElementById('publicMonthly');
        if (monthlyEl && stats.monthly) {
            charts.monthly = new Chart(monthlyEl, {
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
                        spanGaps: false,
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
                                if (ctx.parsed.y === null || ctx.parsed.y === undefined) {
                                    return 'Below reporting threshold';
                                }
                                return ctx.parsed.y + ' apprehended';
                            }
                        }
                    }
                })
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(monthlyEl); }
        }

        /* ---- Category — doughnut or bar depending on viewport ----- */
        var categoryEl = document.getElementById('publicCategories');
        if (categoryEl && stats.category && stats.category.labels.length > 0) {
            charts.category = TrafChart.renderCategoryChart('publicCategories', {
                labels: stats.category.labels,
                values: stats.category.values
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(categoryEl); }
        }

        /* ---- Yearly bar ------------------------------------------- */
        var yearlyEl = document.getElementById('publicYearly');
        if (yearlyEl && stats.yearly) {
            charts.yearly = new Chart(yearlyEl, {
                type: 'bar',
                data: {
                    labels: stats.yearly.labels,
                    datasets: [{
                        label: 'Apprehensions',
                        data: stats.yearly.values,
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
                                if (ctx.parsed.y === null || ctx.parsed.y === undefined) {
                                    return 'Below reporting threshold';
                                }
                                return ctx.parsed.y + ' apprehended';
                            }
                        }
                    }
                })
            });
            if (window.trafHideSkeleton) { window.trafHideSkeleton(yearlyEl); }
        }
    }
})();