import {
    Chart,
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    BarElement,
    LineElement,
    PointElement,
    Tooltip,
    Legend,
    BarController,
    LineController,
} from 'chart.js';

Chart.register(
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    BarElement,
    LineElement,
    PointElement,
    Tooltip,
    Legend,
    BarController,
    LineController,
);

const compareData = window.ecomTrackerCompareData || {};
const compareSyncQuery = window.matchMedia('(min-width: 1200px)');

function clearCompareSectionHeights() {
    document.querySelectorAll('#ecom-tracker-compare-content [data-compare-sync]').forEach((element) => {
        element.style.minHeight = '';
    });
}

function syncCompareSectionHeights() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root || !compareSyncQuery.matches) {
        clearCompareSectionHeights();

        return;
    }

    const groups = new Map();

    root.querySelectorAll('[data-compare-sync]').forEach((element) => {
        const key = element.getAttribute('data-compare-sync');

        if (!key) {
            return;
        }

        if (!groups.has(key)) {
            groups.set(key, []);
        }

        groups.get(key).push(element);
    });

    groups.forEach((elements) => {
        if (elements.length < 2) {
            elements.forEach((element) => {
                element.style.minHeight = '';
            });

            return;
        }

        elements.forEach((element) => {
            element.style.minHeight = '';
        });

        const maxHeight = Math.max(...elements.map((element) => element.offsetHeight));

        elements.forEach((element) => {
            element.style.minHeight = `${maxHeight}px`;
        });
    });
}

function scheduleCompareSectionSync() {
    window.requestAnimationFrame(() => {
        syncCompareSectionHeights();
    });
}

let compareSyncObserver;

function initCompareSectionSync() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    scheduleCompareSectionSync();

    window.addEventListener('resize', scheduleCompareSectionSync, { passive: true });
    window.addEventListener('load', scheduleCompareSectionSync, { passive: true });
    window.addEventListener('beforeprint', clearCompareSectionHeights, { passive: true });
    window.addEventListener('afterprint', scheduleCompareSectionSync, { passive: true });

    if (typeof compareSyncQuery.addEventListener === 'function') {
        compareSyncQuery.addEventListener('change', scheduleCompareSectionSync);
    } else if (typeof compareSyncQuery.addListener === 'function') {
        compareSyncQuery.addListener(scheduleCompareSectionSync);
    }

    if (typeof ResizeObserver !== 'undefined') {
        compareSyncObserver = new ResizeObserver(scheduleCompareSectionSync);
        root.querySelectorAll('[data-compare-sync]').forEach((element) => {
            compareSyncObserver.observe(element);
        });
    }
}

initCompareSectionSync();

Chart.defaults.font.family = "'DM Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;

const isDark = () => document.documentElement.classList.contains('dark');
const isNarrow = () => window.matchMedia('(max-width: 639px)').matches;
const isCompactChart = () => window.matchMedia('(max-width: 1023px)').matches;
const gridClr = () => (isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)');

const TREND_SERIES_COLORS = {
    unique_visitors: '#7c3aed',
    sessions: '#2563eb',
    category_views: '#0d9488',
    product_views: '#0284c7',
    add_to_cart: '#d97706',
    begin_checkout: '#ea580c',
    proceed_checkout: '#e11d48',
    purchases: '#16a34a',
    items_sold_qty: '#65a30d',
    conversion_rate: '#a21caf',
};

function trendSeriesColor(key) {
    return TREND_SERIES_COLORS[key] || '#1D9E75';
}

const TREND_SERIES_ORDER = [
    'unique_visitors',
    'sessions',
    'category_views',
    'product_views',
    'add_to_cart',
    'begin_checkout',
    'proceed_checkout',
    'purchases',
    'items_sold_qty',
    'conversion_rate',
];

function sortTrendSeries(series) {
    return [...series].sort((a, b) => {
        const aIndex = TREND_SERIES_ORDER.indexOf(a.key);
        const bIndex = TREND_SERIES_ORDER.indexOf(b.key);

        return (aIndex === -1 ? 999 : aIndex) - (bIndex === -1 ? 999 : bIndex);
    });
}

function ctx(id) {
    const el = document.getElementById(id);

    return el ? el.getContext('2d') : null;
}

function trendTickLimit(labelCount, useHorizontalScroll = false) {
    if (useHorizontalScroll || labelCount <= 24) {
        return labelCount;
    }

    if (isNarrow()) {
        return Math.min(8, labelCount);
    }

    if (labelCount > 60) {
        return 8;
    }

    if (labelCount > 30) {
        return 10;
    }

    return Math.min(14, labelCount);
}

function trendUsesHorizontalScroll(labelCount) {
    if (labelCount <= 12) {
        return false;
    }

    return isCompactChart();
}

function trendMinWidth(labelCount) {
    if (!trendUsesHorizontalScroll(labelCount)) {
        return null;
    }

    const pixelsPerLabel = isNarrow() ? 34 : 30;

    return Math.max(labelCount * pixelsPerLabel, 320);
}

function sessionsForLogScale(values) {
    return values.map((value) => {
        const numeric = Number(value) || 0;

        return numeric > 0 ? numeric : null;
    });
}

function applyTrendChartLayout(wrapId, hintId, labelCount) {
    const wrap = document.getElementById(wrapId);
    const hint = document.getElementById(hintId);
    const useHorizontalScroll = trendUsesHorizontalScroll(labelCount);
    const minWidth = trendMinWidth(labelCount);

    if (wrap) {
        wrap.style.minWidth = minWidth ? `${minWidth}px` : '';
    }

    if (hint) {
        hint.hidden = !useHorizontalScroll;
    }
}

function renderTrendLegend(legendId, series) {
    const legend = document.getElementById(legendId);

    if (!legend) {
        return;
    }

    if (!isCompactChart()) {
        legend.hidden = true;
        legend.innerHTML = '';
        legend.classList.remove('etd-trend-legend--active');

        return;
    }

    legend.classList.add('etd-trend-legend--active');
    legend.hidden = false;
    legend.innerHTML = series.map((entry) => {
        const color = trendSeriesColor(entry.key);

        return `<span class="etd-trend-legend-item"><span class="etd-trend-legend-swatch" style="background:${color}"></span>${entry.label}</span>`;
    }).join('');
}

function initCompareTrendChart({
    canvasId,
    wrapId,
    scrollId,
    legendId,
    hintId,
    trend,
}) {
    const chartCtx = ctx(canvasId);

    if (!chartCtx || !trend) {
        return;
    }

    const {
        labels = [],
        series = [],
        use_log_scale: useLogScale = false,
    } = trend;

    const orderedSeries = sortTrendSeries(series);
    const useHorizontalScroll = trendUsesHorizontalScroll(labels.length);

    applyTrendChartLayout(wrapId, hintId, labels.length);
    renderTrendLegend(legendId, orderedSeries);

    const datasets = orderedSeries.map((entry, index) => {
        const color = trendSeriesColor(entry.key);
        const isConversion = entry.key === 'conversion_rate';
        const isBar = entry.chart_type === 'bar';
        const rawData = entry.data || [];
        const data = useLogScale && !isConversion
            ? sessionsForLogScale(rawData)
            : rawData;
        const barThickness = isBar
            ? (useHorizontalScroll ? 10 : (isNarrow() ? 6 : (labels.length > 30 ? 8 : 12)))
            : undefined;

        return {
            type: isBar ? 'bar' : 'line',
            label: entry.label,
            data,
            borderColor: isBar ? `${color}CC` : color,
            backgroundColor: isBar ? `${color}99` : color,
            pointRadius: isBar ? 0 : (labels.length > 24 && !useHorizontalScroll ? 0 : 2.5),
            pointHoverRadius: isBar ? 0 : 4,
            tension: isBar ? 0 : 0.3,
            fill: false,
            yAxisID: entry.y_axis_id || 'y',
            order: index,
            barThickness,
            maxBarThickness: isBar ? 14 : undefined,
        };
    });

    const chart = new Chart(chartCtx, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            layout: {
                padding: {
                    right: useHorizontalScroll ? 8 : 0,
                },
            },
            plugins: {
                legend: {
                    display: !isCompactChart(),
                    position: 'top',
                    labels: {
                        boxWidth: 10,
                        padding: 14,
                        font: { size: 11 },
                        sort: (a, b) => a.datasetIndex - b.datasetIndex,
                    },
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: useHorizontalScroll || labels.length > 20 ? 45 : 0,
                        minRotation: useHorizontalScroll ? 35 : 0,
                        autoSkip: !useHorizontalScroll && labels.length > 24,
                        maxTicksLimit: trendTickLimit(labels.length, useHorizontalScroll),
                        font: { size: isNarrow() ? 9 : 11 },
                    },
                },
                y: {
                    type: useLogScale ? 'logarithmic' : 'linear',
                    position: 'left',
                    grid: { color: gridClr() },
                    beginAtZero: !useLogScale,
                    min: useLogScale ? 1 : 0,
                    ticks: {
                        precision: 0,
                        font: { size: isNarrow() ? 9 : 11 },
                    },
                },
                y1: {
                    position: 'right',
                    grid: { display: false },
                    min: 0,
                    ticks: {
                        callback: (value) => `${value}%`,
                        font: { size: isNarrow() ? 9 : 11 },
                    },
                },
            },
        },
    });

    window.addEventListener('resize', () => {
        applyTrendChartLayout(wrapId, hintId, labels.length);
        renderTrendLegend(legendId, orderedSeries);
        chart.options.plugins.legend.display = !isCompactChart();
        chart.resize();
        scheduleCompareSectionSync();
    });

    document.getElementById(scrollId)?.addEventListener('scroll', () => {}, { passive: true });
}

initCompareTrendChart({
    canvasId: 'etdTrendChartLeft',
    wrapId: 'etdTrendChartWrapLeft',
    scrollId: 'etdTrendChartScrollLeft',
    legendId: 'etdTrendLegendLeft',
    hintId: 'etdTrendChartScrollHintLeft',
    trend: compareData.left?.trend,
});

initCompareTrendChart({
    canvasId: 'etdTrendChartRight',
    wrapId: 'etdTrendChartWrapRight',
    scrollId: 'etdTrendChartScrollRight',
    legendId: 'etdTrendLegendRight',
    hintId: 'etdTrendChartScrollHintRight',
    trend: compareData.right?.trend,
});

scheduleCompareSectionSync();

document.getElementById('etdComparePrintBtn')?.addEventListener('click', () => {
    const styleId = 'etd-compare-print-page';
    let style = document.getElementById(styleId);

    if (!style) {
        style = document.createElement('style');
        style.id = styleId;
        style.textContent = '@page { size: A4 landscape; margin: 8mm; }';
        document.head.appendChild(style);
    }

    const cleanup = () => {
        document.getElementById(styleId)?.remove();
        scheduleCompareSectionSync();
    };

    window.addEventListener('afterprint', cleanup, { once: true });
    window.print();
});
