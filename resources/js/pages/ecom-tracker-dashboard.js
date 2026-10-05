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
import {
    bindTrendTooltipDismiss,
    createTrendTooltipHandler,
} from '../lib/ecom-tracker-trend-tooltip';
import './ecom-tracker-filters';

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

Chart.defaults.font.family = "'DM Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;

const ETD_FILTER_PANEL_CLOSED_CLASS = 'etd-filter-panel--closed';
const ETD_CUSTOM_DATES_CLOSED_CLASS = 'etd-custom-dates--closed';
const ETD_DRAWER_CUSTOM_CLOSED_CLASS = 'etd-filter-period__custom--closed';

function getDashboardPage() {
    return document.getElementById('ecom-tracker-dashboard-content');
}

function isDashboardFilterDrawerOpen(drawer) {
    return drawer && !drawer.classList.contains(ETD_FILTER_PANEL_CLOSED_CLASS);
}

function initDashboardFilterDrawer(page) {
    const backdrop = document.getElementById('ecom-dashboard-filter-backdrop');
    const drawer = document.getElementById('ecom-dashboard-filter-drawer');
    const openButton = page.querySelector('#ecom-dashboard-filter-open');

    if (!backdrop || !drawer) {
        return;
    }

    const setOpen = (isOpen) => {
        backdrop.classList.toggle(ETD_FILTER_PANEL_CLOSED_CLASS, !isOpen);
        drawer.classList.toggle(ETD_FILTER_PANEL_CLOSED_CLASS, !isOpen);
        backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.body.classList.toggle('etd-filter-panel-open', isOpen);
        openButton?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen && typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(drawer);
        }
    };

    const close = () => setOpen(false);
    const toggle = () => setOpen(!isDashboardFilterDrawerOpen(drawer));

    page.querySelectorAll('.js-ecom-dashboard-filter-open').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            toggle();
        });
    });

    page.querySelectorAll('.js-ecom-dashboard-filter-close').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            close();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isDashboardFilterDrawerOpen(drawer)) {
            close();
        }
    });

    window.closeFilterDrawer = close;
}

function syncDashboardCustomFlatpickr(customPanel, drawerCustom, from, to) {
    if (!from || !to) {
        return;
    }

    const rangeDisplay = customPanel?.querySelector('.etd-flatpickr-date-range');
    const fpRange = rangeDisplay?._etdFlatpickr;

    if (fpRange) {
        fpRange.setDate([from, to], false);
    }

    const drawerFrom = drawerCustom?.querySelector('[name="date_from"]');
    const drawerTo = drawerCustom?.querySelector('[name="date_to"]');

    if (drawerFrom?._etdFlatpickr) {
        drawerFrom._etdFlatpickr.setDate(from, false);
    }

    if (drawerTo?._etdFlatpickr) {
        drawerTo._etdFlatpickr.setDate(to, false);
    }
}

function initDashboardPeriodControls(page) {
    const headerNav = page.querySelector('.etd-header-period-nav');
    const customToggle = headerNav?.querySelector('.js-ecom-dashboard-period-custom-toggle');
    const customPanel = document.getElementById('ecom-dashboard-header-custom-dates');
    const fromInput = document.getElementById('ecom-dashboard-header-date-from');
    const toInput = document.getElementById('ecom-dashboard-header-date-to');
    const applyBtn = customPanel?.querySelector('.js-ecom-dashboard-header-custom-apply');
    const presetLinks = headerNav?.querySelectorAll('.etd-segmented .etd-segmented-btn[href]');
    const drawerCustom = document.getElementById('ecom-dashboard-drawer-custom-dates');
    const periodInput = document.getElementById('ecom-dashboard-filter-period');

    const showHeaderCustom = (show) => {
        if (!customPanel) {
            return;
        }

        customPanel.classList.toggle(ETD_CUSTOM_DATES_CLOSED_CLASS, !show);
        customToggle?.classList.toggle('active', show);

        if (show) {
            presetLinks?.forEach((link) => link.classList.remove('active'));

            if (typeof window.refreshEtdFilterControls === 'function') {
                window.refreshEtdFilterControls(customPanel);
            }

            syncDashboardCustomFlatpickr(
                customPanel,
                drawerCustom,
                fromInput?.value ?? '',
                toInput?.value ?? '',
            );
        }
    };

    customToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = customPanel && !customPanel.classList.contains(ETD_CUSTOM_DATES_CLOSED_CLASS);
        showHeaderCustom(!isOpen);
    });

    applyBtn?.addEventListener('click', () => {
        const url = new URL(window.location.href);

        url.searchParams.set('period', 'custom');

        const from = fromInput?.value?.trim() ?? '';
        const to = toInput?.value?.trim() ?? '';

        if (from) {
            url.searchParams.set('date_from', from);
        } else {
            url.searchParams.delete('date_from');
        }

        if (to) {
            url.searchParams.set('date_to', to);
        } else {
            url.searchParams.delete('date_to');
        }

        window.location.href = url.toString();
    });

    page.querySelector('.js-ecom-dashboard-drawer-custom-preset')?.addEventListener('click', () => {
        if (periodInput) {
            periodInput.value = 'custom';
        }

        drawerCustom?.classList.remove(ETD_DRAWER_CUSTOM_CLOSED_CLASS);
        drawerCustom?.querySelectorAll('[name="date_from"], [name="date_to"]').forEach((input) => {
            input.disabled = false;
        });

        if (typeof window.syncEtdFlatpickrEnabled === 'function') {
            window.syncEtdFlatpickrEnabled(drawerCustom, true);
        }

        if (typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(drawerCustom);
        }

        syncDashboardCustomFlatpickr(
            customPanel,
            drawerCustom,
            drawerCustom?.querySelector('[name="date_from"]')?.value ?? '',
            drawerCustom?.querySelector('[name="date_to"]')?.value ?? '',
        );
    });

    const from = fromInput?.value?.trim() ?? '';
    const to = toInput?.value?.trim() ?? '';

    if (!from || !to) {
        return;
    }

    if (customPanel && !customPanel.classList.contains(ETD_CUSTOM_DATES_CLOSED_CLASS)) {
        if (typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(customPanel);
        }
    }

    if (drawerCustom && !drawerCustom.classList.contains(ETD_DRAWER_CUSTOM_CLOSED_CLASS)) {
        if (typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(drawerCustom);
        }
    }

    syncDashboardCustomFlatpickr(customPanel, drawerCustom, from, to);
}

/** Sample trend series for UI preview (no API). */
const DEMO_DASHBOARD_TREND = {
    labels: ['28 Sep', '29 Sep', '30 Sep', '1 Oct', '2 Oct', '3 Oct', '4 Oct'],
    use_log_scale: true,
    series: [
        { key: 'unique_visitors', label: 'Unique visitors', chart_type: 'line', data: [418, 392, 508, 476, 612, 568, 704] },
        { key: 'sessions', label: 'Sessions', chart_type: 'line', data: [502, 468, 598, 562, 718, 672, 847] },
        { key: 'product_views', label: 'Product views', chart_type: 'line', data: [820, 760, 980, 910, 1180, 1090, 1324] },
        { key: 'add_to_cart', label: 'Add to cart', chart_type: 'bar', data: [42, 38, 52, 48, 64, 58, 72] },
        { key: 'begin_checkout', label: 'Begin checkout', chart_type: 'bar', data: [22, 18, 28, 24, 34, 30, 38] },
        { key: 'purchases', label: 'Purchases', chart_type: 'bar', data: [8, 6, 10, 9, 12, 11, 14] },
        {
            key: 'conversion_rate',
            label: 'Conversion rate',
            chart_type: 'line',
            y_axis_id: 'y1',
            data: [1.6, 1.3, 1.7, 1.6, 1.7, 1.6, 1.9],
        },
    ],
};

const DEMO_NEW_RETURNING = {
    labels: ['Unique', 'Returning'],
    values: [704, 143],
};

window.ecomTrackerDashboardData = window.ecomTrackerDashboardData || {
    trend: DEMO_DASHBOARD_TREND,
    new_returning: DEMO_NEW_RETURNING,
};

const D = window.ecomTrackerDashboardData;
const dashboardRoot = document.getElementById('ecom-tracker-dashboard-content');

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

const isDark = () => document.documentElement.classList.contains('dark');
const isNarrow = () => window.matchMedia('(max-width: 639px)').matches;
const isCompactChart = () => window.matchMedia('(max-width: 1023px)').matches;
const gridClr = () => (isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)');
const accent = () => getComputedStyle(document.querySelector('.etd-page') || document.body)
    .getPropertyValue('--etd-accent')
    .trim() || '#1D9E75';

const tipStyle = () => ({
    backgroundColor: isDark() ? '#1e293b' : '#fff',
    titleColor: isDark() ? '#f1f5f9' : '#1e293b',
    bodyColor: isDark() ? '#94a3b8' : '#64748b',
    borderColor: isDark() ? '#334155' : '#e2e8f0',
    borderWidth: 1,
    padding: 10,
    cornerRadius: 8,
});

function trendSeriesColor(key) {
    return TREND_SERIES_COLORS[key] || '#1D9E75';
}

function sortTrendSeries(series) {
    return [...series].sort((a, b) => {
        const aIndex = TREND_SERIES_ORDER.indexOf(a.key);
        const bIndex = TREND_SERIES_ORDER.indexOf(b.key);

        return (aIndex === -1 ? 999 : aIndex) - (bIndex === -1 ? 999 : bIndex);
    });
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

function initDashboardTrendChart(trend) {
    const canvas = document.getElementById('etdTrendChart');

    if (!canvas || !trend) {
        return;
    }

    const chartCtx = canvas.getContext('2d');
    const {
        labels = [],
        series = [],
        use_log_scale: useLogScale = false,
    } = trend;

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
        applyTrendChartLayout(labels.length);
        renderTrendLegend(orderedSeries);
        chart.options.plugins.legend.display = !isCompactChart();
        chart.resize();
    });

    bindTrendTooltipDismiss(document.getElementById('etdTrendChartScroll'));
}

function initDashboardNewReturningChart() {
    const canvas = document.getElementById('etdNewReturningChart');

    if (!canvas || !D.new_returning) {
        return;
    }

    const chartCtx = canvas.getContext('2d');

    new Chart(chartCtx, {
        type: 'doughnut',
        data: {
            labels: D.new_returning.labels || [],
            datasets: [{
                data: D.new_returning.values || [],
                backgroundColor: [accent(), '#64748b'],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, padding: 12 },
                },
                tooltip: tipStyle(),
            },
        },
    });
}

const DASHBOARD_CHART_IDS = ['etdTrendChart', 'etdNewReturningChart'];

let dashboardPrintSession = null;

function resizeDashboardChartsForPrint() {
    DASHBOARD_CHART_IDS.forEach((id) => {
        const chart = Chart.getChart(id);

        if (chart) {
            chart.resize();
        }
    });
}

function resetNewReturningChartForPrint() {
    const chart = Chart.getChart('etdNewReturningChart');

    if (!chart) {
        return;
    }

    chart.options.maintainAspectRatio = true;
    chart.options.aspectRatio = 1;
    chart.options.plugins.legend.display = false;
    chart.options.layout = { padding: 4 };
    chart.update('none');
}

function restoreNewReturningChartAfterPrint() {
    const chart = Chart.getChart('etdNewReturningChart');

    if (!chart) {
        return;
    }

    chart.options.maintainAspectRatio = false;
    chart.options.aspectRatio = undefined;
    chart.options.plugins.legend.display = true;
    chart.options.layout = { padding: 0 };
    chart.update('none');
}

function expandCategoryDepartmentsForPrint() {
    const root = document.getElementById('ecom-tracker-dashboard-content');

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

function restoreCategoryDepartmentsAfterPrint() {
    const root = document.getElementById('ecom-tracker-dashboard-content');

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

function resetTrendChartForPrint() {
    const wrap = document.getElementById('etdTrendChartWrap');
    const hint = document.getElementById('etdTrendChartScrollHint');
    const labels = D.trend?.labels || [];
    const chart = Chart.getChart('etdTrendChart');

    if (wrap) {
        wrap.dataset.printRestoreMinWidth = wrap.style.minWidth;
        wrap.style.minWidth = '';
    }

    if (hint) {
        hint.hidden = true;
    }

    if (!chart || labels.length === 0) {
        return;
    }

    chart.options.scales.x.ticks.autoSkip = labels.length > 12;
    chart.options.scales.x.ticks.maxTicksLimit = trendTickLimit(labels.length, false);
    chart.options.scales.x.ticks.maxRotation = labels.length > 10 ? 45 : 0;
    chart.options.scales.x.ticks.minRotation = labels.length > 10 ? 35 : 0;
    chart.options.layout.padding.right = 0;
    chart.options.plugins.legend.display = true;
    chart.update('none');
}

function restoreTrendChartAfterPrint() {
    const wrap = document.getElementById('etdTrendChartWrap');
    const labels = D.trend?.labels || [];
    const chart = Chart.getChart('etdTrendChart');

    if (wrap) {
        wrap.style.minWidth = wrap.dataset.printRestoreMinWidth || '';
        delete wrap.dataset.printRestoreMinWidth;
    }

    if (labels.length === 0) {
        return;
    }

    applyTrendChartLayout(labels.length);

    if (!chart) {
        return;
    }

    const useHorizontalScroll = trendUsesHorizontalScroll(labels.length);

    chart.options.scales.x.ticks.autoSkip = !useHorizontalScroll && labels.length > 24;
    chart.options.scales.x.ticks.maxTicksLimit = trendTickLimit(labels.length, useHorizontalScroll);
    chart.options.scales.x.ticks.maxRotation = useHorizontalScroll || labels.length > 20 ? 45 : 0;
    chart.options.scales.x.ticks.minRotation = useHorizontalScroll ? 35 : 0;
    chart.options.layout.padding.right = useHorizontalScroll ? 8 : 0;
    chart.options.plugins.legend.display = !isCompactChart();
    chart.update('none');
}

function beginDashboardPrintSession() {
    const main = document.querySelector('main');

    if (!main || dashboardPrintSession) {
        return dashboardPrintSession;
    }

    dashboardPrintSession = {
        scrollTop: main.scrollTop,
        scrollLeft: main.scrollLeft,
    };

    return dashboardPrintSession;
}

function prepareDashboardForPrint() {
    const main = document.querySelector('main');

    beginDashboardPrintSession();
    document.body.classList.add('etd-print-measure');

    if (main) {
        main.scrollTop = 0;
        main.scrollLeft = 0;
    }

    resetTrendChartForPrint();
    resetNewReturningChartForPrint();
    expandCategoryDepartmentsForPrint();
    resizeDashboardChartsForPrint();
}

function restoreDashboardAfterPrint() {
    const main = document.querySelector('main');

    document.body.classList.remove('etd-print-measure');
    restoreCategoryDepartmentsAfterPrint();
    restoreTrendChartAfterPrint();
    restoreNewReturningChartAfterPrint();
    resizeDashboardChartsForPrint();

    if (main && dashboardPrintSession) {
        main.scrollTop = dashboardPrintSession.scrollTop;
        main.scrollLeft = dashboardPrintSession.scrollLeft;
    } else if (main) {
        main.scrollLeft = 0;
    }

    dashboardPrintSession = null;
}

function printEcomTrackerDashboard() {
    beginDashboardPrintSession();
    prepareDashboardForPrint();

    requestAnimationFrame(() => {
        resizeDashboardChartsForPrint();

        requestAnimationFrame(() => {
            window.print();
        });
    });
}

function initDashboardSyncButton(page) {
    const syncBtn = document.getElementById('ecom-dashboard-sync');
    const syncUrl = page?.dataset?.syncUrl ?? '';

    if (!syncBtn || !syncUrl || syncBtn.dataset.etdSyncBound === '1') {
        return;
    }

    syncBtn.dataset.etdSyncBound = '1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    syncBtn.addEventListener('click', async () => {
        if (syncBtn.disabled) {
            return;
        }

        const label = syncBtn.querySelector('.etd-header-btn-text');
        const prevText = label?.textContent ?? 'Sync';

        syncBtn.disabled = true;
        if (label) {
            label.textContent = 'Syncing…';
        }

        try {
            const response = await fetch(syncUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(body.message || `Sync failed (${response.status})`);
            }

            if (label) {
                label.textContent = 'Queued';
            }
            window.setTimeout(() => {
                if (label) {
                    label.textContent = prevText;
                }
            }, 2000);
        } catch (err) {
            window.alert(err instanceof Error ? err.message : 'Could not queue sync.');
            if (label) {
                label.textContent = prevText;
            }
        } finally {
            syncBtn.disabled = false;
        }
    });
}

function bootDashboardPage() {
    const page = getDashboardPage();

    if (!page) {
        return;
    }

    initDashboardFilterDrawer(page);
    initDashboardPeriodControls(page);
    initDashboardSyncButton(page);
    initDashboardTrendChart(D.trend);
    initDashboardNewReturningChart();
}

window.printEcomTrackerDashboard = printEcomTrackerDashboard;

function bindDashboardPrintControls() {
    if (!dashboardRoot || dashboardRoot.dataset.etdPrintBound === '1') {
        return;
    }

    dashboardRoot.dataset.etdPrintBound = '1';
    window.addEventListener('beforeprint', prepareDashboardForPrint);
    window.addEventListener('afterprint', restoreDashboardAfterPrint);
    document.getElementById('etdDashboardPrintBtn')?.addEventListener('click', printEcomTrackerDashboard);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bindDashboardPrintControls();
        bootDashboardPage();
    });
} else {
    bindDashboardPrintControls();
    bootDashboardPage();
}
