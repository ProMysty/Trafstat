/* ====================================================================
   TrafStat — shared Chart.js mobile-aware utilities
   Provides breakpoint detection, font sizing, label truncation, and
   ready-made option objects that adapt to the viewport.

   Usage:
     TrafChart.lineOptions({ ... })
     TrafChart.barOptions({ ... })
     TrafChart.horizontalBarOptions({ ... })
     TrafChart.renderCategoryChart('canvasId', { labels, values })
   ==================================================================== */
window.TrafChart = (function () {
    'use strict';

    var MOBILE_MAX  = 575.98;   // Bootstrap "xs"
    var TABLET_MAX  = 991.98;   // Bootstrap "md"

    var palette = [
        '#F97316', '#EA580C', '#FB923C', '#FDBA74', '#FED7AA',
        '#F59E0B', '#FBBF24', '#FCD34D', '#1F2937', '#6B7280'
    ];

    function isMobile() {
        return window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)').matches;
    }
    function isTablet() {
        return window.matchMedia(
            '(min-width: ' + (MOBILE_MAX + 0.02) + 'px) and (max-width: ' + TABLET_MAX + 'px)'
        ).matches;
    }
    function fontSize() {
        if (isMobile()) { return 9; }
        if (isTablet()) { return 10; }
        return 11;
    }
    function truncate(str, max) {
        if (str === null || str === undefined) { return ''; }
        str = String(str);
        if (str.length <= max) { return str; }
        return str.substring(0, max - 1) + '…';
    }

    /* ---- Shared pieces --------------------------------------------- */
    function baseTooltip(extra) {
        var base = {
            padding: isMobile() ? 8 : 10,
            titleFont: { size: fontSize() + 1, weight: '600' },
            bodyFont: { size: fontSize() },
            displayColors: true,
            boxWidth: 10,
            boxHeight: 10,
            boxPadding: 4,
            caretPadding: 6
        };
        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (k) {
                base[k] = extra[k];
            });
        }
        return base;
    }

    function baseLegend(extra) {
        var base = {
            position: 'bottom',
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                padding: isMobile() ? 8 : 12,
                font: { size: fontSize() }
            }
        };
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                base[k] = extra[k];
            });
        }
        return base;
    }

    /* ---- Line options ---------------------------------------------- */
    function lineOptions(opts) {
        opts = opts || {};
        var mobile = isMobile();
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: opts.showLegend === true, labels: baseLegend().labels },
                tooltip: baseTooltip(opts.tooltip)
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: fontSize() },
                        maxRotation: mobile ? 45 : 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: mobile ? 6 : 12
                    },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: fontSize() },
                        precision: 0,
                        maxTicksLimit: mobile ? 5 : 8
                    }
                }
            }
        };
    }

    /* ---- Vertical bar options -------------------------------------- */
    function barOptions(opts) {
        opts = opts || {};
        var mobile = isMobile();
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: opts.showLegend === true, labels: baseLegend().labels },
                tooltip: baseTooltip(opts.tooltip)
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: fontSize() },
                        maxRotation: mobile ? 60 : 0,
                        minRotation: mobile ? 45 : 0,
                        autoSkip: true,
                        maxTicksLimit: mobile ? 5 : 12
                    },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: fontSize() },
                        precision: 0
                    }
                }
            }
        };
    }

    /* ---- Horizontal bar options (long category labels) ------------- */
    function horizontalBarOptions(opts) {
        opts = opts || {};
        var mobile = isMobile();
        var truncLen = mobile ? 20 : 30;
        return {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: baseTooltip(opts.tooltip)
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { font: { size: fontSize() }, precision: 0 }
                },
                y: {
                    ticks: {
                        font: { size: fontSize() },
                        autoSkip: false,
                        callback: function (value) {
                            var label = this.getLabelForValue(value);
                            return truncate(label, truncLen);
                        }
                    },
                    grid: { display: false }
                }
            }
        };
    }

    /* ---- Category chart: doughnut on desktop, bar on mobile -------- */
    function renderCategoryChart(canvasId, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !data || !data.labels || data.labels.length === 0) {
            return null;
        }

        if (isMobile()) {
            /* Horizontal bar — one row per category, instantly readable. */
            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: data.labels.map(function (_, i) {
                            return palette[i % palette.length];
                        }),
                        borderRadius: 4,
                        maxBarThickness: 22
                    }]
                },
                options: horizontalBarOptions({
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.parsed.x + ' recorded';
                            }
                        }
                    }
                })
            });
        }

        /* Desktop / tablet doughnut */
        return new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: data.labels.map(function (_, i) {
                        return palette[i % palette.length];
                    }),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                interaction: { mode: 'nearest', intersect: true },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 10,
                            font: { size: fontSize() },
                            generateLabels: function (chart) {
                                var d = chart.data;
                                return d.labels.map(function (label, i) {
                                    return {
                                        text: truncate(label, 22),
                                        fillStyle: d.datasets[0].backgroundColor[i],
                                        strokeStyle: d.datasets[0].backgroundColor[i],
                                        lineWidth: 0,
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: baseTooltip({
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) {
                                    return a + b;
                                }, 0);
                                var pct = total > 0
                                    ? Math.round((ctx.parsed / total) * 100)
                                    : 0;
                                return ' ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    })
                }
            }
        });
    }

    /* ---- Breakpoint watcher: rebuild when crossing mobile boundary - */
    function onChangeBreakpoint(callback) {
        var mq = window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)');
        var handler = function () { callback(isMobile()); };

        if (mq.addEventListener) {
            mq.addEventListener('change', handler);
        } else if (mq.addListener) {
            mq.addListener(handler);
        }
    }

    return {
        isMobile: isMobile,
        isTablet: isTablet,
        fontSize: fontSize,
        truncate: truncate,
        palette: palette,
        lineOptions: lineOptions,
        barOptions: barOptions,
        horizontalBarOptions: horizontalBarOptions,
        renderCategoryChart: renderCategoryChart,
        onChangeBreakpoint: onChangeBreakpoint
    };
})();