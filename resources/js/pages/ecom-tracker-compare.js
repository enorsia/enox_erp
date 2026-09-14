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
import {
    bindTrendTooltipDismiss,
    createTrendTooltipHandler,
} from '../lib/ecom-tracker-trend-tooltip';
import './ecom-tracker-filters';
import '../lib/etd-tip-position';

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
let comparePrintSyncPaused = false;

function syncElementHeights(elements) {
    if (!elements?.length) {
        return;
    }

    elements.forEach((element) => {
        element.style.minHeight = '';
    });

    if (elements.length < 2) {
        return;
    }

    const maxHeight = Math.max(...elements.map((element) => element.getBoundingClientRect().height));

    if (maxHeight <= 0) {
        return;
    }

    const height = `${Math.ceil(maxHeight)}px`;

    elements.forEach((element) => {
        element.style.minHeight = height;
    });
}

function groupElementsByText(root, selector, textSelector) {
    const groups = new Map();

    root.querySelectorAll(selector).forEach((element) => {
        const label = element.querySelector(textSelector)?.textContent?.trim();

        if (!label) {
            return;
        }

        if (!groups.has(label)) {
            groups.set(label, []);
        }

        groups.get(label).push(element);
    });

    return groups;
}

function clearCompareMatchedHeights() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    root.querySelectorAll('[data-compare-sync], [data-compare-sync] .etd-kpi--compact, [data-compare-sync] .etd-kpi-group, [data-compare-sync] .etd-compare-recoverable-card').forEach((element) => {
        element.style.minHeight = '';
    });
}

function syncCompareKpiHeights(root) {
    groupElementsByText(root, '[data-compare-sync="kpis"] .etd-kpi--compact', '.etd-kpi-label-text')
        .forEach((cards) => {
            syncElementHeights(cards);
        });
}

function syncCompareRecoverableHeights(root) {
    groupElementsByText(root, '[data-compare-sync="recoverable"] .etd-compare-recoverable-card', '.etd-compare-recoverable-card__title')
        .forEach((cards) => {
            syncElementHeights(cards);
        });
}

function syncCompareSectionHeights() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (comparePrintSyncPaused) {
        clearCompareMatchedHeights();

        return;
    }

    if (!root || !compareSyncQuery.matches) {
        clearCompareMatchedHeights();

        return;
    }

    clearCompareMatchedHeights();
    syncCompareKpiHeights(root);
    syncCompareRecoverableHeights(root);

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
        syncElementHeights(elements);
    });
}

function scheduleCompareSectionSync() {
    window.requestAnimationFrame(() => {
        syncCompareSectionHeights();

        window.requestAnimationFrame(() => {
            syncCompareSectionHeights();
        });
    });
}

let compareSyncObserver;

function pauseCompareSectionSyncForPrint() {
    comparePrintSyncPaused = true;
    compareSyncObserver?.disconnect();
    clearCompareMatchedHeights();
}

function resumeCompareSectionSyncAfterPrint() {
    const root = document.getElementById('ecom-tracker-compare-content');

    comparePrintSyncPaused = false;

    if (root && compareSyncObserver) {
        root.querySelectorAll('[data-compare-sync]').forEach((element) => {
            compareSyncObserver.observe(element);
        });
    }
}

function initCompareSectionSync() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    scheduleCompareSectionSync();

    window.addEventListener('resize', scheduleCompareSectionSync, { passive: true });
    window.addEventListener('load', scheduleCompareSectionSync, { passive: true });

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
        root.querySelectorAll('[data-compare-sync] .etd-kpi--compact, [data-compare-sync] .etd-kpi-group, [data-compare-sync] .etd-compare-recoverable-card').forEach((element) => {
            compareSyncObserver.observe(element);
        });
    }

    if (document.fonts?.ready) {
        document.fonts.ready.then(scheduleCompareSectionSync).catch(() => {});
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
                    enabled: false,
                    external: createTrendTooltipHandler(orderedSeries, trendSeriesColor),
                    itemSort: (a, b) => a.datasetIndex - b.datasetIndex,
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

    bindTrendTooltipDismiss(document.getElementById(scrollId));
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

const compareTrendChartConfigs = [
    {
        canvasId: 'etdTrendChartLeft',
        wrapId: 'etdTrendChartWrapLeft',
        legendId: 'etdTrendLegendLeft',
        hintId: 'etdTrendChartScrollHintLeft',
        trend: compareData.left?.trend,
    },
    {
        canvasId: 'etdTrendChartRight',
        wrapId: 'etdTrendChartWrapRight',
        legendId: 'etdTrendLegendRight',
        hintId: 'etdTrendChartScrollHintRight',
        trend: compareData.right?.trend,
    },
];

let comparePrintSession = null;

function expandCompareCategoryDepartmentsForPrint() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    root.querySelectorAll('.etd-category-departments').forEach((wrap) => {
        wrap.classList.add('etd-print-categories-expanded');
    });

    root.querySelectorAll('.etd-category-child-row').forEach((row) => {
        row.dataset.printRestoreDisplay = row.style.display;
        row.style.setProperty('display', 'table-row', 'important');
    });
}

function restoreCompareCategoryDepartmentsAfterPrint() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    root.querySelectorAll('.etd-category-departments').forEach((wrap) => {
        wrap.classList.remove('etd-print-categories-expanded');
    });

    root.querySelectorAll('.etd-category-child-row').forEach((row) => {
        row.style.display = row.dataset.printRestoreDisplay || '';
        delete row.dataset.printRestoreDisplay;
    });
}

function prepareCompareExecutiveSummaryForPrint() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    root.querySelectorAll('.etd-compare-exec').forEach((section) => {
        section.classList.add('etd-print-exec-expanded');

        const body = section.querySelector('.etd-compare-exec__body');

        if (!body) {
            return;
        }

        body.dataset.printRestoreStyle = body.getAttribute('style') || '';
        body.style.setProperty('display', 'block', 'important');
        body.style.setProperty('height', 'auto', 'important');
        body.style.setProperty('max-height', 'none', 'important');
        body.style.setProperty('overflow', 'visible', 'important');
    });
}

function restoreCompareExecutiveSummaryAfterPrint() {
    const root = document.getElementById('ecom-tracker-compare-content');

    if (!root) {
        return;
    }

    root.querySelectorAll('.etd-compare-exec').forEach((section) => {
        section.classList.remove('etd-print-exec-expanded');

        const body = section.querySelector('.etd-compare-exec__body');

        if (!body) {
            return;
        }

        if (body.dataset.printRestoreStyle !== undefined) {
            body.setAttribute('style', body.dataset.printRestoreStyle);
            delete body.dataset.printRestoreStyle;
        }
    });
}

function resetCompareTrendChartForPrint({ canvasId, wrapId, legendId, hintId, trend }) {
    const wrap = document.getElementById(wrapId);
    const hint = document.getElementById(hintId);
    const legend = document.getElementById(legendId);
    const labels = trend?.labels || [];
    const chart = Chart.getChart(canvasId);

    if (wrap) {
        wrap.dataset.printRestoreMinWidth = wrap.style.minWidth;
        wrap.style.minWidth = '';
    }

    if (hint) {
        hint.hidden = true;
    }

    if (legend) {
        legend.hidden = true;
        legend.classList.remove('etd-trend-legend--active');
    }

    if (!chart || labels.length === 0) {
        return;
    }

    chart.options.scales.x.ticks.autoSkip = labels.length > 12;
    chart.options.scales.x.ticks.maxTicksLimit = Math.min(12, labels.length);
    chart.options.scales.x.ticks.maxRotation = labels.length > 8 ? 45 : 0;
    chart.options.scales.x.ticks.minRotation = labels.length > 8 ? 35 : 0;
    chart.options.layout.padding.right = 0;
    chart.options.plugins.legend.display = false;
    chart.update('none');
}

function restoreCompareTrendChartAfterPrint({ canvasId, wrapId, legendId, hintId, trend }) {
    const wrap = document.getElementById(wrapId);
    const labels = trend?.labels || [];
    const chart = Chart.getChart(canvasId);
    const orderedSeries = sortTrendSeries(trend?.series || []);
    const useHorizontalScroll = trendUsesHorizontalScroll(labels.length);

    if (wrap) {
        wrap.style.minWidth = wrap.dataset.printRestoreMinWidth || '';
        delete wrap.dataset.printRestoreMinWidth;
    }

    applyTrendChartLayout(wrapId, hintId, labels.length);
    renderTrendLegend(legendId, orderedSeries);

    if (!chart || labels.length === 0) {
        return;
    }

    chart.options.scales.x.ticks.autoSkip = !useHorizontalScroll && labels.length > 24;
    chart.options.scales.x.ticks.maxTicksLimit = trendTickLimit(labels.length, useHorizontalScroll);
    chart.options.scales.x.ticks.maxRotation = useHorizontalScroll || labels.length > 20 ? 45 : 0;
    chart.options.scales.x.ticks.minRotation = useHorizontalScroll ? 35 : 0;
    chart.options.layout.padding.right = useHorizontalScroll ? 8 : 0;
    chart.options.plugins.legend.display = !isCompactChart();
    chart.update('none');
    chart.resize();
}

function resizeCompareTrendChartsForPrint() {
    compareTrendChartConfigs.forEach(({ canvasId }) => {
        Chart.getChart(canvasId)?.resize();
    });
}

function beginComparePrintSession() {
    const main = document.querySelector('main');

    if (!main || comparePrintSession) {
        return comparePrintSession;
    }

    comparePrintSession = {
        scrollTop: main.scrollTop,
        scrollLeft: main.scrollLeft,
    };

    return comparePrintSession;
}

function prepareCompareForPrint() {
    const main = document.querySelector('main');

    beginComparePrintSession();
    document.body.classList.add('etd-print-measure');
    pauseCompareSectionSyncForPrint();

    if (main) {
        main.scrollTop = 0;
        main.scrollLeft = 0;
    }

    expandCompareCategoryDepartmentsForPrint();
    prepareCompareExecutiveSummaryForPrint();
    compareTrendChartConfigs.forEach(resetCompareTrendChartForPrint);
}

function restoreCompareAfterPrint() {
    const main = document.querySelector('main');

    document.body.classList.remove('etd-print-measure');
    restoreCompareCategoryDepartmentsAfterPrint();
    restoreCompareExecutiveSummaryAfterPrint();
    compareTrendChartConfigs.forEach(restoreCompareTrendChartAfterPrint);
    resumeCompareSectionSyncAfterPrint();
    scheduleCompareSectionSync();

    if (main && comparePrintSession) {
        main.scrollTop = comparePrintSession.scrollTop;
        main.scrollLeft = comparePrintSession.scrollLeft;
    } else if (main) {
        main.scrollLeft = 0;
    }

    comparePrintSession = null;
}

function printEcomTrackerCompare() {
    beginComparePrintSession();
    prepareCompareForPrint();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            resizeCompareTrendChartsForPrint();

            requestAnimationFrame(() => {
                clearCompareMatchedHeights();
                window.print();
            });
        });
    });
}

window.printEcomTrackerCompare = printEcomTrackerCompare;

window.addEventListener('beforeprint', prepareCompareForPrint);
window.addEventListener('afterprint', restoreCompareAfterPrint);

document.getElementById('etdComparePrintBtn')?.addEventListener('click', printEcomTrackerCompare);
