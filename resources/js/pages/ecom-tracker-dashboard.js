import {
    Chart,
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Tooltip,
    Legend,
    BarController,
    LineController,
    DoughnutController,
} from 'chart.js';

Chart.register(
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Tooltip,
    Legend,
    BarController,
    LineController,
    DoughnutController,
);

const D = window.ecomTrackerDashboardData || {};

Chart.defaults.font.family = "'DM Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;

const isDark = () => document.documentElement.classList.contains('dark');
const isNarrow = () => window.matchMedia('(max-width: 639px)').matches;
const isCompactChart = () => window.matchMedia('(max-width: 1023px)').matches;
const gridClr = () => (isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)');
const accent = () => getComputedStyle(document.querySelector('.etd-page') || document.body)
    .getPropertyValue('--etd-accent')
    .trim() || '#1D9E75';
const gold = () => '#f59e0b';
const purchaseGreen = () => '#22c55e';

/** Semantic colors per trend metric — each series gets a unique, meaningful color. */
const TREND_SERIES_COLORS = {
    unique_visitors: '#7c3aed',   // violet — distinct people
    sessions: '#2563eb',          // blue — visits
    category_views: '#0d9488',    // teal — category browsing
    product_views: '#0284c7',     // sky — product pages
    add_to_cart: '#d97706',       // amber — cart action
    begin_checkout: '#ea580c',    // orange — checkout started
    proceed_checkout: '#e11d48',  // rose — checkout in progress
    purchases: '#16a34a',         // green — completed orders
    items_sold_qty: '#65a30d',    // lime — units sold
    conversion_rate: '#a21caf',   // fuchsia — conversion %
};

function trendSeriesColor(key) {
    return TREND_SERIES_COLORS[key] || accent();
}

const tipStyle = () => ({
    backgroundColor: isDark() ? '#1e293b' : '#fff',
    titleColor: isDark() ? '#f1f5f9' : '#1e293b',
    bodyColor: isDark() ? '#94a3b8' : '#64748b',
    borderColor: isDark() ? '#334155' : '#e2e8f0',
    borderWidth: 1,
    padding: 10,
    cornerRadius: 8,
});

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

function applyTrendChartLayout(labelCount) {
    const wrap = document.getElementById('etdTrendChartWrap');
    const hint = document.getElementById('etdTrendChartScrollHint');
    const useHorizontalScroll = trendUsesHorizontalScroll(labelCount);
    const minWidth = trendMinWidth(labelCount);

    if (wrap) {
        wrap.style.minWidth = minWidth ? `${minWidth}px` : '';
    }

    if (hint) {
        hint.hidden = !useHorizontalScroll;
    }
}

function renderTrendLegend(series) {
    const legend = document.getElementById('etdTrendLegend');

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

function sessionsForLogScale(values) {
    return values.map((value) => {
        const numeric = Number(value) || 0;

        return numeric > 0 ? numeric : null;
    });
}

const devicePalette = () => [accent(), gold(), '#64748b', '#3b82f6', '#8b5cf6'];

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

const trendCtx = ctx('etdTrendChart');
if (trendCtx && D.trend) {
    const {
        labels = [],
        series = [],
        use_log_scale: useLogScale = false,
    } = D.trend;

    const orderedSeries = sortTrendSeries(series);
    const useHorizontalScroll = trendUsesHorizontalScroll(labels.length);

    applyTrendChartLayout(labels.length);
    renderTrendLegend(orderedSeries);

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

    const trendChart = new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels,
            datasets,
        },
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
                    ...tipStyle(),
                    titleFont: { size: isNarrow() ? 13 : 14, weight: '600' },
                    bodyFont: { size: isNarrow() ? 12 : 13 },
                    padding: isNarrow() ? 10 : 12,
                    itemSort: (a, b) => a.datasetIndex - b.datasetIndex,
                    callbacks: {
                        label(context) {
                            const value = context.parsed.y ?? 0;

                            if (context.dataset.yAxisID === 'y1') {
                                return `${context.dataset.label}: ${value}%`;
                            }

                            return `${context.dataset.label}: ${value}`;
                        },
                    },
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
                    title: {
                        display: useLogScale,
                        text: 'Log scale',
                        color: isDark() ? '#94a3b8' : '#64748b',
                        font: { size: 10 },
                    },
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

    let resizeFrame = null;

    window.addEventListener('resize', () => {
        if (resizeFrame) {
            cancelAnimationFrame(resizeFrame);
        }

        resizeFrame = requestAnimationFrame(() => {
            applyTrendChartLayout(labels.length);
            renderTrendLegend(orderedSeries);
            trendChart.options.plugins.legend.display = !isCompactChart();
            trendChart.options.scales.x.ticks.autoSkip = !trendUsesHorizontalScroll(labels.length) && labels.length > 24;
            trendChart.options.scales.x.ticks.maxTicksLimit = trendTickLimit(
                labels.length,
                trendUsesHorizontalScroll(labels.length),
            );
            trendChart.resize();
        });
    });
}

const deviceCtx = ctx('etdDeviceChart');
if (deviceCtx && D.devices) {
    new Chart(deviceCtx, {
        type: 'doughnut',
        data: {
            labels: D.devices.labels || [],
            datasets: [{ data: D.devices.values || [], backgroundColor: devicePalette(), borderWidth: 0 }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { display: false }, tooltip: tipStyle() },
        },
    });
}

const loginCtx = ctx('etdLoginChart');
if (loginCtx && D.devices?.login) {
    new Chart(loginCtx, {
        type: 'doughnut',
        data: {
            labels: D.devices.login.labels || [],
            datasets: [{ data: D.devices.login.values || [], backgroundColor: ['#64748b', gold()], borderWidth: 0 }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { display: false }, tooltip: tipStyle() },
        },
    });
}

const dwellCtx = ctx('etdDwellChart');
if (dwellCtx && D.engagement) {
    const { labels = [], buyers = [], non_buyers: nonBuyers = [] } = D.engagement;

    new Chart(dwellCtx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Buyers (avg active sec)', data: buyers, backgroundColor: gold(), borderRadius: 4, barPercentage: 0.5 },
                { label: 'Non-buyers (avg active sec)', data: nonBuyers, backgroundColor: `${accent()}80`, borderRadius: 4, barPercentage: 0.5 },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { boxWidth: 10 } }, tooltip: tipStyle() },
            scales: {
                x: { grid: { color: gridClr() }, beginAtZero: true },
                y: { grid: { display: false } },
            },
        },
    });
}

const botTrendCtx = ctx('botTrafficTrendChart');
const botTrend = window.botTrafficTrendData || {};
if (botTrendCtx && botTrend.labels) {
    new Chart(botTrendCtx, {
        type: 'bar',
        data: {
            labels: botTrend.labels,
            datasets: [
                {
                    label: 'Automated traffic',
                    data: botTrend.bot || [],
                    backgroundColor: '#f59e0b8C',
                    borderRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { boxWidth: 10 } }, tooltip: tipStyle() },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: gridClr() }, beginAtZero: true },
            },
        },
    });
}

function syncKpiPanelCardHeights() {
    const panel = document.querySelector('.etd-kpi-panel');

    if (!panel) {
        return;
    }

    const cards = panel.querySelectorAll('.etd-kpi--compact');

    if (!cards.length) {
        return;
    }

    panel.style.removeProperty('--etd-kpi-sync-height');

    let maxHeight = 0;

    cards.forEach((card) => {
        card.style.minHeight = '';
        maxHeight = Math.max(maxHeight, card.getBoundingClientRect().height);
    });

    if (maxHeight <= 0) {
        return;
    }

    const height = `${Math.ceil(maxHeight)}px`;
    panel.style.setProperty('--etd-kpi-sync-height', height);
    cards.forEach((card) => {
        card.style.minHeight = height;
    });
}

const kpiPanel = document.querySelector('.etd-kpi-panel');

if (kpiPanel) {
    syncKpiPanelCardHeights();

    let syncFrame = null;

    const scheduleKpiHeightSync = () => {
        if (syncFrame !== null) {
            cancelAnimationFrame(syncFrame);
        }

        syncFrame = requestAnimationFrame(() => {
            syncFrame = null;
            syncKpiPanelCardHeights();
        });
    };

    window.addEventListener('resize', scheduleKpiHeightSync);

    if (typeof ResizeObserver !== 'undefined') {
        const kpiResizeObserver = new ResizeObserver(scheduleKpiHeightSync);
        kpiResizeObserver.observe(kpiPanel);
        kpiPanel.querySelectorAll('.etd-kpi-group').forEach((group) => kpiResizeObserver.observe(group));
    }
}
