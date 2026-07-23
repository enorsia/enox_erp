import {
    Chart,
    CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Tooltip, Legend, Filler,
    BarController, LineController,
} from 'chart.js';

Chart.register(
    CategoryScale, LinearScale,
    BarElement, LineElement, PointElement,
    Tooltip, Legend, Filler,
    BarController, LineController,
);

const D = window.adsPerformanceChartData || {};
const isDark  = () => document.documentElement.classList.contains('dark');
const gridClr = () => isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
const tickClr = () => isDark() ? '#94a3b8' : '#64748b';
const labelClr = () => isDark() ? '#e2e8f0' : '#334155';

const tipStyle = () => ({
    backgroundColor: isDark() ? '#1e293b' : '#fff',
    titleColor     : isDark() ? '#f1f5f9' : '#1e293b',
    bodyColor      : isDark() ? '#cbd5e1' : '#475569',
    borderColor    : isDark() ? '#334155' : '#e2e8f0',
    borderWidth: 1,
    padding: 12,
    cornerRadius: 8,
    displayColors: true,
    boxPadding: 6,
    titleFont: { size: 12, weight: '600' },
    bodyFont: { size: 11 },
    caretPadding: 8,
});

Chart.defaults.font.family = "'DM Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = tickClr();

function ctx(id) {
    const el = document.getElementById(id);
    return el ? el.getContext('2d') : null;
}

function fmtNum(v) {
    return Number(v).toLocaleString('en-GB');
}

function fmtMoney(v) {
    return '£' + Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtPct(v, decimals = 2) {
    return Number(v).toFixed(decimals) + '%';
}

function moneyTick(v) {
    if (v >= 1_000_000) return '£' + (v / 1_000_000).toFixed(1) + 'M';
    if (v >= 1_000)     return '£' + (v / 1_000).toFixed(0) + 'K';
    return '£' + v;
}

const noDataPlugin = (msg = 'No data for the selected filters') => ({
    id: 'noData',
    afterDraw(chart) {
        const hasData = chart.data.datasets.some(d => d.data?.some(v => v !== 0 && v !== null && v !== undefined));
        if (hasData) return;
        const { ctx: c, chartArea: { left, top, right, bottom } } = chart;
        c.save();
        c.textAlign = 'center';
        c.textBaseline = 'middle';
        c.fillStyle = isDark() ? '#475569' : '#94a3b8';
        c.font = '13px DM Sans, sans-serif';
        c.fillText(msg, (left + right) / 2, (top + bottom) / 2);
        c.restore();
    },
});

/** Draw formatted values on bar endpoints (Excel-style data labels). */
const valueLabelPlugin = {
    id: 'valueLabels',
    afterDatasetsDraw(chart) {
        const { ctx: c, chartArea } = chart;
        if (!chartArea) return;

        const horizontal = chart.options.indexAxis === 'y';

        chart.data.datasets.forEach((dataset, di) => {
            const meta = chart.getDatasetMeta(di);
            if (meta.hidden) return;

            meta.data.forEach((bar, index) => {
                const raw = dataset.data[index];
                if (raw === null || raw === undefined || raw === 0) return;

                const { x, y } = bar.getProps(['x', 'y', 'base'], true);
                const base = bar.getProps(['base'], true).base;
                const text = dataset._fmt ? dataset._fmt(raw) : fmtNum(raw);

                c.save();
                c.fillStyle = labelClr();
                c.font = '600 10px DM Sans, sans-serif';

                if (horizontal) {
                    const barEnd = Math.max(x, base);
                    c.textAlign = 'left';
                    c.textBaseline = 'middle';
                    c.fillText(text, barEnd + 6, y);
                } else {
                    const barTop = Math.min(y, base);
                    c.textAlign = 'center';
                    c.textBaseline = 'bottom';
                    c.fillText(text, x, barTop - 4);
                }
                c.restore();
            });
        });
    },
};

const baseScales = () => ({
    x: {
        grid: { color: gridClr() },
        ticks: { color: tickClr(), maxRotation: 45, minRotation: 0, font: { size: 10 } },
    },
    y: {
        grid: { color: gridClr() },
        ticks: { color: tickClr(), font: { size: 10 } },
        beginAtZero: true,
    },
});

/** Chart.js scroll: outer overflow + inner width/height sized to label count */
const CHART_SCROLL = {
    hScrollThreshold: 12,
    minWidthPerLabel: 56,
    minHeightPerMonth: 52,
    maxViewportHeight: 480,
};

/** X-axis labels — show every month when chart scrolls horizontally */
const monthXScale = (labelCount = 0) => {
    const crowded = labelCount > CHART_SCROLL.hScrollThreshold;
    return {
        grid: { color: gridClr(), display: false },
        ticks: {
            color: tickClr(),
            maxRotation: crowded ? 45 : 0,
            minRotation: crowded ? 45 : 0,
            autoSkip: !crowded,
            maxTicksLimit: crowded ? labelCount : 18,
            font: { size: crowded ? 9 : 10 },
        },
    };
};

function resizeChartsIn(container) {
    if (!container) return;
    container.querySelectorAll('canvas').forEach((canvas) => {
        const chart = Chart.getChart(canvas);
        if (chart) chart.resize();
    });
}

function setupHorizontalChartScroll(labelCount) {
    document.querySelectorAll('[data-chart-hscroll]').forEach((scrollEl) => {
        const inner = scrollEl.querySelector('.ap-overview-chart-wrap');
        if (!inner) return;

        const needsScroll = labelCount > CHART_SCROLL.hScrollThreshold;
        if (!needsScroll) {
            inner.style.width = '';
            scrollEl.classList.remove('is-scrollable');
            return;
        }

        const viewport = scrollEl.clientWidth || 640;
        const width = Math.max(viewport, labelCount * CHART_SCROLL.minWidthPerLabel);
        inner.style.width = `${width}px`;
        scrollEl.classList.add('is-scrollable');
    });

    requestAnimationFrame(() => resizeChartsIn(document.getElementById('ads-performance-report-content')));
}

function setupEngagementChartScroll(payload) {
    const scrollEl = document.getElementById('apPlatformChartScroll');
    const innerEl  = document.getElementById('apPlatformChartWrap');
    if (!scrollEl || !innerEl || !payload) return;

    const monthCount   = payload.month_count || payload.labels?.length || 6;
    const metricsCount = (payload.datasets || []).length;
    const rowHeight    = Math.max(CHART_SCROLL.minHeightPerMonth, metricsCount * 12 + 28);
    const fullHeight   = Math.max(320, monthCount * rowHeight + 72);
    const needsScroll  = fullHeight > CHART_SCROLL.maxViewportHeight;

    innerEl.style.height = `${fullHeight}px`;
    scrollEl.classList.toggle('is-scrollable', needsScroll);

    requestAnimationFrame(() => {
        if (engagementChart) engagementChart.resize();
    });
}

const barTooltip = (fmt = fmtNum) => ({
    ...tipStyle(),
    mode: 'index',
    intersect: true,
    callbacks: {
        title: (items) => items[0]?.label ?? '',
        label: (item) => ` ${item.dataset.label}: ${fmt(item.raw)}`,
    },
});

function initOverviewCharts() {
    const ov = D.overview || {};
    const { labels = [] } = ov;

    const revCtx = ctx('apRevenueCostChart');
    if (revCtx) {
        const ds = [
            { label: 'Revenue (£)',     data: ov.revenue || [],     bg: 'rgba(29,158,117,0.85)', fmt: fmtMoney },
            { label: 'Total Cost (£)',  data: ov.total_cost || [],  bg: 'rgba(59,130,246,0.78)', fmt: fmtMoney },
            { label: 'Net Revenue (£)', data: ov.net_revenue || [], bg: 'rgba(139,92,246,0.75)', fmt: fmtMoney },
        ];
        new Chart(revCtx, {
            type: 'bar',
            plugins: [noDataPlugin()],
            data: {
                labels,
                datasets: ds.map(d => ({
                    label: d.label,
                    data: d.data,
                    backgroundColor: d.bg,
                    borderRadius: 5,
                    borderSkipped: false,
                    _fmt: d.fmt,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, bottom: 2, left: 0, right: 4 } },
                interaction: { mode: 'index', axis: 'x', intersect: false },
                datasets: { bar: { categoryPercentage: 0.62, barPercentage: 0.82 } },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 10, padding: 12, color: tickClr() } },
                    tooltip: barTooltip(fmtMoney),
                },
                scales: {
                    x: monthXScale(labels.length),
                    y: { ...baseScales().y, ticks: { color: tickClr(), callback: moneyTick } },
                },
            },
        });
    }

    const ordCtx = ctx('apOrdersChart');
    if (ordCtx) {
        new Chart(ordCtx, {
            type: 'bar',
            plugins: [noDataPlugin()],
            data: {
                labels,
                datasets: [{
                    label: 'Orders',
                    data: ov.orders || [],
                    backgroundColor: 'rgba(245,158,11,0.85)',
                    borderRadius: 6,
                    borderSkipped: false,
                    _fmt: fmtNum,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, bottom: 2, left: 0, right: 4 } },
                interaction: { mode: 'index', axis: 'x', intersect: true },
                plugins: {
                    legend: { display: false },
                    tooltip: barTooltip(fmtNum),
                },
                scales: {
                    x: monthXScale(labels.length),
                    y: baseScales().y,
                },
            },
        });
    }

    const roiCtx = ctx('apRoiChart');
    if (roiCtx) {
        new Chart(roiCtx, {
            type: 'line',
            plugins: [noDataPlugin()],
            data: {
                labels,
                datasets: [{
                    label: 'Avg ROI',
                    data: ov.avg_roi || [],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.12)',
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tipStyle(),
                        callbacks: {
                            title: (items) => items[0]?.label ?? '',
                            label: (item) => ` Avg ROI: ${fmtPct(item.raw, 0)}`,
                        },
                    },
                },
                scales: {
                    x: monthXScale(labels.length),
                    y: { ...baseScales().y, ticks: { color: tickClr(), callback: v => v + '%' } },
                },
            },
        });
    }

    const roasCtx = ctx('apRoasChart');
    if (roasCtx) {
        new Chart(roasCtx, {
            type: 'line',
            plugins: [noDataPlugin()],
            data: {
                labels,
                datasets: [{
                    label: 'Avg ROAS',
                    data: ov.avg_roas || [],
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6,182,212,0.12)',
                    pointBackgroundColor: '#06b6d4',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tipStyle(),
                        callbacks: {
                            title: (items) => items[0]?.label ?? '',
                            label: (item) => ` Avg ROAS: ${fmtPct(item.raw)}`,
                        },
                    },
                },
                scales: {
                    x: monthXScale(labels.length),
                    y: { ...baseScales().y, ticks: { color: tickClr(), callback: v => v + '%' } },
                },
            },
        });
    }

    setupHorizontalChartScroll(labels.length);

    window.addEventListener('resize', () => setupHorizontalChartScroll(labels.length), { passive: true });
}

function mapEngagementDatasets(payload) {
    return (payload.datasets || []).map(ds => ({
        label: ds.label,
        data: ds.data || [],
        backgroundColor: ds.color + 'D9',
        borderColor: ds.color,
        borderWidth: 1,
        borderRadius: 3,
        borderSkipped: false,
        _fmt: fmtNum,
    }));
}

function updateEngagementChartTitle(payload) {
    const chartTitle = document.getElementById('apEngagementChartTitle');
    if (chartTitle && payload) {
        chartTitle.textContent = `${payload.name} — Engagement Metrics`;
    }
}

function getEngagementPayload(slug) {
    if (slug === 'all' && D.engagement_all) {
        return D.engagement_all;
    }
    return (D.platforms || []).find(p => p.slug === slug) || D.engagement_all || (D.platforms || [])[0] || null;
}

function buildEngagementChartOptions(platformName, payload) {
    const monthCount   = payload?.month_count || payload?.labels?.length || 6;
    const metricsCount = (payload?.datasets || []).length;
    const crowded      = monthCount > CHART_SCROLL.hScrollThreshold;

    return {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 4, right: crowded ? 8 : 48, bottom: 4, left: 4 } },
        interaction: {
            mode: 'nearest',
            axis: 'y',
            intersect: true,
        },
        datasets: {
            bar: {
                categoryPercentage: crowded ? 0.88 : 0.82,
                barPercentage: metricsCount > 4 ? 0.78 : 0.86,
            },
        },
        plugins: {
            legend: {
                position: 'top',
                labels: { boxWidth: 10, padding: 10, color: tickClr(), font: { size: 11 } },
            },
            tooltip: {
                ...tipStyle(),
                mode: 'nearest',
                intersect: true,
                position: 'nearest',
                filter: (item) => item.raw !== null && item.raw !== undefined,
                callbacks: {
                    title: (items) => {
                        const item = items[0];
                        return item ? `${platformName} · ${item.label}` : '';
                    },
                    label: (item) => ` ${item.dataset.label}: ${fmtNum(item.raw)}`,
                },
            },
        },
        scales: {
            x: {
                grid: { color: gridClr() },
                ticks: { color: tickClr(), callback: v => fmtNum(v) },
                beginAtZero: true,
            },
            y: {
                grid: { display: false },
                ticks: {
                    color: tickClr(),
                    font: { size: 11, weight: '500' },
                    autoSkip: false,
                },
            },
        },
    };
}

let engagementChart = null;

function updateEngagementView(slug) {
    const payload = getEngagementPayload(slug);
    if (!payload) return;

    updateEngagementChartTitle(payload);

    const pCtx = ctx('apPlatformChart');
    if (!pCtx) return;

    const datasets = mapEngagementDatasets(payload);
    const platformName = payload.name || 'Engagement';

    if (engagementChart) {
        engagementChart.data.labels = payload.labels || [];
        engagementChart.data.datasets = datasets;
        engagementChart.options = buildEngagementChartOptions(platformName, payload);
        engagementChart.update();
    } else {
        engagementChart = new Chart(pCtx, {
            type: 'bar',
            plugins: [noDataPlugin('No engagement data for this platform')],
            data: {
                labels: payload.labels || [],
                datasets,
            },
            options: buildEngagementChartOptions(platformName, payload),
        });
    }

    setupEngagementChartScroll(payload);
}

function bindEngagementSelect(select, onChange) {
    const defaultSlug = window.adsPerformanceDefaultEngagement
        || select.value
        || select.options[0]?.value
        || 'all';

    const setInitial = () => {
        if (select.tomselect) {
            select.tomselect.setValue(defaultSlug, true);
        } else {
            select.value = defaultSlug;
        }
        onChange(defaultSlug);
    };

    setInitial();

    if (!select.dataset.apEngagementBound) {
        select.dataset.apEngagementBound = '1';
        select.addEventListener('change', () => onChange(select.value));
    }

    if (!select.tomselect) {
        setTimeout(() => {
            if (!select.tomselect) return;
            if (select.tomselect.getValue() !== defaultSlug) {
                select.tomselect.setValue(defaultSlug, true);
            }
            onChange(select.tomselect.getValue() || defaultSlug);
        }, 150);
    }
}

function getPlatformEngagementSection(slug) {
    const data = window.adsPerformancePlatformData || {};
    if (slug === 'all' && data.all) {
        return data.all;
    }
    return (data.sections || []).find(s => s.slug === slug) || data.all || (data.sections || [])[0] || null;
}

function renderPlatformEngagementTable(slug) {
    const section = getPlatformEngagementSection(slug);
    const wrap    = document.getElementById('apPlatformEngagementTable');
    const title   = document.getElementById('apPlatformEngagementTitle');

    if (!wrap || !section) return;

    if (title) title.textContent = section.name || 'Platform Engagement';

    let html = '<table class="sr-report-table ap-report-table ap-sticky-table w-full min-w-[600px]"><thead class="ap-thead"><tr>';
    html += '<th class="ap-th ap-th-left ap-sticky-col ap-sticky-col-1">Month</th>';
    Object.values(section.columns || {}).forEach(label => {
        html += `<th class="ap-th ap-th-right">${label}</th>`;
    });
    html += '</tr></thead><tbody>';

    (section.rows || []).forEach(row => {
        html += `<tr class="${row.row_class || ''}"><td class="sr-td font-medium ap-sticky-col ap-sticky-col-1">${row.label}</td>`;
        Object.keys(section.columns || {}).forEach(key => {
            html += `<td class="sr-td sr-td-num">${row.cells?.[key] ?? '—'}</td>`;
        });
        html += '</tr>';
    });

    html += '<tr class="ap-row-total ap-row-total-sticky"><td class="sr-td font-bold ap-sticky-col ap-sticky-col-1">TOTAL</td>';
    Object.keys(section.columns || {}).forEach(key => {
        html += `<td class="sr-td sr-td-num font-bold">${section.totals?.[key] ?? '—'}</td>`;
    });
    html += '</tr></tbody></table>';

    wrap.innerHTML = html;
}

function initPerformanceTableLayout() {
    const table = document.querySelector('.ap-performance-table');
    if (!table) return;

    const measureSticky = () => {
        const ths = table.querySelectorAll('thead tr th');
        if (ths.length < 3) return;

        const w1 = ths[0].getBoundingClientRect().width;
        const w2 = ths[1].getBoundingClientRect().width;

        table.style.setProperty('--ap-sticky-left-2', `${w1}px`);
        table.style.setProperty('--ap-sticky-left-3', `${w1 + w2}px`);
    };

    measureSticky();
    requestAnimationFrame(measureSticky);

    if (!table.dataset.stickyMeasured) {
        table.dataset.stickyMeasured = '1';
        window.addEventListener('resize', measureSticky, { passive: true });
    }
}

function initPlatformEngagementTable() {
    const select = document.getElementById('apPlatformEngagementSelect');
    if (!select) return;

    bindEngagementSelect(select, renderPlatformEngagementTable);
}

function initEngagementChart() {
    const select = document.getElementById('apEngagementPlatformSelect');
    if (!select) return;

    bindEngagementSelect(select, updateEngagementView);
}

function updateFsButton(btn, isOpen) {
    btn.setAttribute('aria-pressed', isOpen ? 'true' : 'false');
    btn.setAttribute('aria-label', isOpen ? 'Exit full screen' : 'Full screen');
    btn.setAttribute('title', isOpen ? 'Exit full screen' : 'Full screen');
    btn.querySelector('[data-fs-icon-expand]')?.classList.toggle('hidden', isOpen);
    btn.querySelector('[data-fs-icon-exit]')?.classList.toggle('hidden', !isOpen);
    btn.querySelector('[data-fs-label]')?.classList.toggle('hidden', !isOpen);
}

function detachFsButton(panel) {
    const btn = panel.querySelector('[data-ap-fs-toggle]');
    if (!btn || btn.dataset.apFsBtnDetached) return;

    btn._apFsBtnParent = btn.parentNode;
    btn._apFsBtnNext = btn.nextSibling;
    panel.insertBefore(btn, panel.firstChild);
    btn.dataset.apFsBtnDetached = '1';
}

function restoreFsButton(panel) {
    const btn = panel.querySelector('[data-ap-fs-toggle]');
    if (!btn || !btn.dataset.apFsBtnDetached) return;

    if (btn._apFsBtnParent) {
        btn._apFsBtnParent.insertBefore(btn, btn._apFsBtnNext || null);
    }
    delete btn.dataset.apFsBtnDetached;
}

function restoreFullscreenPanel(panel) {
    restoreFsButton(panel);
    if (panel._apFsParent) {
        panel._apFsParent.insertBefore(panel, panel._apFsNext || null);
    }
    delete panel.dataset.apFsPortaled;
}

function mountFullscreenPanel(panel) {
    if (panel.dataset.apFsPortaled) return;
    panel._apFsParent = panel.parentNode;
    panel._apFsNext = panel.nextSibling;
    document.body.appendChild(panel);
    panel.dataset.apFsPortaled = '1';
}

function closeAllFullscreen(except = null) {
    document.querySelectorAll('.ap-fs-panel.ap-fs-open').forEach((panel) => {
        if (panel === except) return;
        panel.classList.remove('ap-fs-open');
        restoreFsButton(panel);
        restoreFullscreenPanel(panel);
        const btn = panel.querySelector('[data-ap-fs-toggle]');
        if (btn) updateFsButton(btn, false);
    });
    document.body.classList.toggle('ap-fs-active', !!document.querySelector('.ap-fs-panel.ap-fs-open'));
}

function refreshPanelLayout(panel) {
    requestAnimationFrame(() => {
        resizeChartsIn(panel);

        if (panel.querySelector('.ap-performance-table')) {
            initPerformanceTableLayout();
        }

        const engagementSelect = panel.querySelector('#apEngagementPlatformSelect');
        if (engagementSelect) {
            const payload = getEngagementPayload(engagementSelect.value || 'all');
            if (payload) setupEngagementChartScroll(payload);
        }

        const labels = window.adsPerformanceChartData?.overview?.labels || [];
        if (labels.length && panel.querySelector('[data-chart-hscroll]')) {
            setupHorizontalChartScroll(labels.length);
        }
    });
}

function toggleFullscreenPanel(panel, btn) {
    const willOpen = !panel.classList.contains('ap-fs-open');

    if (willOpen) {
        closeAllFullscreen(panel);
        mountFullscreenPanel(panel);
        detachFsButton(panel);
        panel.classList.add('ap-fs-open');
        document.body.classList.add('ap-fs-active');
    } else {
        panel.classList.remove('ap-fs-open');
        restoreFsButton(panel);
        restoreFullscreenPanel(panel);
        document.body.classList.toggle('ap-fs-active', !!document.querySelector('.ap-fs-panel.ap-fs-open'));
    }

    updateFsButton(btn, willOpen);
    refreshPanelLayout(panel);
}

function initFullscreenPanels() {
    document.querySelectorAll('[data-ap-fs-panel]').forEach((panel) => {
        const btn = panel.querySelector('[data-ap-fs-toggle]');
        if (!btn || btn.dataset.apFsBound) return;

        btn.dataset.apFsBound = '1';
        btn.addEventListener('click', () => toggleFullscreenPanel(panel, btn));
    });

    if (!document.body.dataset.apFsEscapeBound) {
        document.body.dataset.apFsEscapeBound = '1';
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAllFullscreen();
        });
    }
}

if (window.adsPerformanceChartView === 'charts') {
    initOverviewCharts();
    initEngagementChart();
}

if (window.adsPerformanceChartView === 'platforms') {
    initPlatformEngagementTable();
}

initPerformanceTableLayout();
initFullscreenPanels();
