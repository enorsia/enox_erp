import {
    Chart,
    CategoryScale, LinearScale,
    BarElement, LineElement, PointElement, ArcElement,
    Tooltip, Legend, Filler,
    BarController, LineController, DoughnutController, PieController,
} from 'chart.js';

Chart.register(
    CategoryScale, LinearScale,
    BarElement, LineElement, PointElement, ArcElement,
    Tooltip, Legend, Filler,
    BarController, LineController, DoughnutController, PieController
);

const D = window.analyticsData || {};

Chart.defaults.font.family = "'DM Sans', ui-sans-serif, system-ui, sans-serif";
const isDark   = () => document.documentElement.classList.contains('dark');
const gridClr  = () => isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
const refLine  = () => isDark() ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.15)';
const tipStyle = () => ({
    backgroundColor: isDark() ? '#1e293b' : '#fff',
    titleColor     : isDark() ? '#f1f5f9' : '#1e293b',
    bodyColor      : isDark() ? '#94a3b8' : '#64748b',
    borderColor    : isDark() ? '#334155' : '#e2e8f0',
    borderWidth: 1, padding: 10, cornerRadius: 8,
    displayColors: true, boxPadding: 4,
});

const PALETTE = [
    '#1D9E75','#3b82f6','#f59e0b','#f43f5e','#8b5cf6',
    '#06b6d4','#ec4899','#fb923c','#10b981','#6366f1',
    '#64748b','#84cc16','#14b8a6','#a855f7','#ef4444',
];

function ctx(id) {
    const el = document.getElementById(id);
    return el ? el.getContext('2d') : null;
}

// No-data overlay plugin
const noDataPlugin = (msg = 'No data for the selected period') => ({
    id: 'noData',
    afterDraw(chart) {
        const hasData = chart.data.datasets.some(d => d.data && d.data.some(v => v !== 0 && v !== null));
        if (hasData) return;
        const { ctx: c, chartArea: { left, top, right, bottom } } = chart;
        c.save();
        c.textAlign = 'center'; c.textBaseline = 'middle';
        c.fillStyle = isDark() ? '#475569' : '#94a3b8';
        c.font = '13px DM Sans, sans-serif';
        c.fillText(msg, (left + right) / 2, (top + bottom) / 2);
        c.restore();
    },
});

// ── 1. Monthly Sales & Spend Trend (Line) ─────────────────────────
const salesCtx = ctx('salesTrendChart');
if (salesCtx) {
    const { labels = [], salesData = [], spentData = [] } = D.monthlySales || {};
    new Chart(salesCtx, {
        type: 'line', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'Sales', data: salesData,
              borderColor: '#1D9E75', backgroundColor: 'rgba(29,158,117,0.10)',
              pointBackgroundColor: '#1D9E75', pointRadius: 4, tension: 0.4, fill: true },
            { label: 'Spent', data: spentData,
              borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.10)',
              pointBackgroundColor: '#3b82f6', pointRadius: 4, tension: 0.4, fill: true },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 16 } }, tooltip: tipStyle() },
            scales: { x: { grid: { color: gridClr() } }, y: { grid: { color: gridClr() }, beginAtZero: true } },
        },
    });
}

// ── 2. Weekly Trend (grouped bar) ────────────────────────────────
const weekCtx = ctx('weeklyTrendChart');
if (weekCtx) {
    const { labels = [], salesData = [], spentData = [], ordersData = [] } = D.weeklyTrend || {};
    new Chart(weekCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'Sales',  data: salesData,  backgroundColor: 'rgba(29,158,117,0.80)', borderRadius: 5, borderSkipped: false },
            { label: 'Spent',  data: spentData,  backgroundColor: 'rgba(59,130,246,0.75)', borderRadius: 5, borderSkipped: false },
            { label: 'Orders', data: ordersData, backgroundColor: 'rgba(245,158,11,0.80)', borderRadius: 5, borderSkipped: false,
              yAxisID: 'y2', type: 'line', borderColor: '#f59e0b', pointRadius: 4, fill: false },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 16 } }, tooltip: tipStyle() },
            scales: {
                x:  { grid: { color: gridClr() } },
                y:  { grid: { color: gridClr() }, beginAtZero: true },
                y2: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true },
            },
        },
    });
}

// ── 3. Budget vs Actual (Bar) ──────────────────────────────────────
const bvaCtx = ctx('budgetVsActualChart');
if (bvaCtx) {
    const { labels = [], budget = [], actual = [] } = D.budgetVsActual || {};
    new Chart(bvaCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'Budget',       data: budget, backgroundColor: 'rgba(139,92,246,0.70)', borderRadius: 6, borderSkipped: false },
            { label: 'Actual Sales', data: actual, backgroundColor: 'rgba(29,158,117,0.80)', borderRadius: 6, borderSkipped: false },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 16 } }, tooltip: tipStyle() },
            scales: { x: { grid: { color: gridClr() } }, y: { grid: { color: gridClr() }, beginAtZero: true } },
        },
    });
}

// ── 4. Net Profit by Month (Bar green/red) ────────────────────────
const npCtx = ctx('netProfitChart');
if (npCtx) {
    const { labels = [], salesData = [], spentData = [] } = D.monthlySales || {};
    const profit = salesData.map((s, i) => +(s - (spentData[i] || 0)).toFixed(2));
    const colors = profit.map(v => v >= 0 ? 'rgba(16,185,129,0.82)' : 'rgba(244,63,94,0.77)');
    new Chart(npCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [{ label: 'Net Profit', data: profit, backgroundColor: colors, borderRadius: 6, borderSkipped: false }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: tipStyle() },
            scales: { x: { grid: { color: gridClr() } }, y: { grid: { color: gridClr() } } },
        },
    });
}

// ── 5. Orders vs Returns (grouped bar) ───────────────────────────
const ovrCtx = ctx('ordersVsReturnsChart');
if (ovrCtx) {
    const { labels = [], ordersData = [], returnsData = [] } = D.monthlySales || {};
    new Chart(ovrCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'Orders',  data: ordersData,  backgroundColor: 'rgba(245,158,11,0.80)', borderRadius: 6, borderSkipped: false },
            { label: 'Returns', data: returnsData, backgroundColor: 'rgba(244,63,94,0.75)', borderRadius: 6, borderSkipped: false },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 16 } }, tooltip: tipStyle() },
            scales: { x: { grid: { color: gridClr() } }, y: { grid: { color: gridClr() }, beginAtZero: true } },
        },
    });
}

// ── 6. ROAS Trend by Month (Line) ─────────────────────────────────
const roasCtx = ctx('roasTrendChart');
if (roasCtx) {
    const { labels = [], salesData = [], spentData = [] } = D.monthlySales || {};
    const roasData = salesData.map((s, i) => {
        const sp = spentData[i] || 0;
        return sp > 0 ? +((s / sp) * 100).toFixed(2) : 0;
    });
    new Chart(roasCtx, {
        type: 'line', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'ROAS %', data: roasData,
              borderColor: '#06b6d4', backgroundColor: 'rgba(6,182,212,0.10)',
              pointBackgroundColor: '#06b6d4', pointRadius: 5, tension: 0.35, fill: true },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: tipStyle() },
            scales: {
                x: { grid: { color: gridClr() } },
                y: {
                    grid: { color: gridClr() }, beginAtZero: true,
                    ticks: { callback: v => v + '%' },
                },
            },
        },
    });
}

// ── 7. Platform Sales (Horizontal Bar) ────────────────────────────
const psCtx = ctx('platformSalesChart');
if (psCtx) {
    const { labels = [], sales = [] } = D.platformSales || {};
    new Chart(psCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [{
            label: 'Sales', data: sales,
            backgroundColor: PALETTE.slice(0, labels.length),
            borderRadius: 6, borderSkipped: false,
        }]},
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: tipStyle() },
            scales: { x: { grid: { color: gridClr() }, beginAtZero: true }, y: { grid: { display: false } } },
        },
    });
}

// ── 8. Platform Cost vs Sales + ROAS (mixed bar + line) ───────────
const pcsCtx = ctx('platformCostSalesChart');
if (pcsCtx) {
    const { labels = [], cost = [], sales = [], roas = [] } = D.platformCostVsSales || {};
    new Chart(pcsCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'Cost',  data: cost,  type: 'bar',  backgroundColor: 'rgba(59,130,246,0.72)', borderRadius: 5, borderSkipped: false },
            { label: 'Sales', data: sales, type: 'bar',  backgroundColor: 'rgba(29,158,117,0.80)', borderRadius: 5, borderSkipped: false },
            { label: 'ROAS',  data: roas,  type: 'line', borderColor: '#f59e0b', backgroundColor: 'transparent',
              pointBackgroundColor: '#f59e0b', pointRadius: 4, tension: 0.3, yAxisID: 'y2' },
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 14 } }, tooltip: tipStyle() },
            scales: {
                x:  { grid: { color: gridClr() } },
                y:  { grid: { color: gridClr() }, beginAtZero: true },
                y2: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true,
                      ticks: { callback: v => v + 'x' } },
            },
        },
    });
}

// ── 9. Budget Balance per Platform (Horizontal grouped bar) ───────
const bbCtx = ctx('budgetBalanceChart');
if (bbCtx) {
    const { labels = [], budget = [], spent = [], balance = [] } = D.platformBudgets || {};
    new Chart(bbCtx, {
        type: 'bar', plugins: [noDataPlugin()],
        data: { labels, datasets: [
            { label: 'Budget',  data: budget,  backgroundColor: 'rgba(139,92,246,0.70)', borderRadius: 5, borderSkipped: false },
            { label: 'Spent',   data: spent,   backgroundColor: 'rgba(244,63,94,0.72)',  borderRadius: 5, borderSkipped: false },
            { label: 'Balance', data: balance, backgroundColor: 'rgba(16,185,129,0.80)',  borderRadius: 5, borderSkipped: false },
        ]},
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, padding: 14 } }, tooltip: tipStyle() },
            scales: { x: { grid: { color: gridClr() }, beginAtZero: true }, y: { grid: { display: false } } },
        },
    });
}

// ── 10. Platform Returns (Doughnut) ──────────────────────────────
const prCtx = ctx('platformReturnsChart');
if (prCtx) {
    const { labels = [], returns = [] } = D.platformReturns || {};
    new Chart(prCtx, {
        type: 'doughnut', plugins: [noDataPlugin()],
        data: { labels, datasets: [{ data: returns, backgroundColor: PALETTE.slice(0, labels.length), hoverOffset: 8, borderWidth: 2, borderColor: isDark() ? '#1e293b' : '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
            plugins: { legend: { position: 'right', labels: { padding: 14, boxWidth: 12 } }, tooltip: tipStyle() } },
    });
}

// ── 11. Return Reasons (Doughnut) ────────────────────────────────
const rrCtx = ctx('returnReasonsChart');
if (rrCtx) {
    const { labels = [], data = [] } = D.returnReasons || {};
    new Chart(rrCtx, {
        type: 'doughnut', plugins: [noDataPlugin()],
        data: { labels, datasets: [{ data, backgroundColor: PALETTE.slice(0, labels.length), hoverOffset: 8, borderWidth: 2, borderColor: isDark() ? '#1e293b' : '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%',
            plugins: { legend: { position: 'bottom', labels: { padding: 10, boxWidth: 10 } }, tooltip: tipStyle() } },
    });
}

// ── 12. Gender Orders (Pie) ──────────────────────────────────────
const goCtx = ctx('genderOrdersChart');
if (goCtx) {
    const g = D.genderBreakdown?.orders || { male: 0, female: 0, kids: 0 };
    new Chart(goCtx, {
        type: 'pie', plugins: [noDataPlugin()],
        data: { labels: ['Male', 'Female', 'Kids'], datasets: [{ data: [g.male, g.female, g.kids], backgroundColor: ['#3b82f6','#ec4899','#f59e0b'], hoverOffset: 6, borderWidth: 2, borderColor: isDark() ? '#1e293b' : '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 10 } }, tooltip: tipStyle() } },
    });
}

// ── 13. Gender Returns (Pie) ─────────────────────────────────────
const grCtx = ctx('genderReturnsChart');
if (grCtx) {
    const g = D.genderBreakdown?.returns || { male: 0, female: 0, kids: 0 };
    new Chart(grCtx, {
        type: 'pie', plugins: [noDataPlugin()],
        data: { labels: ['Male', 'Female', 'Kids'], datasets: [{ data: [g.male, g.female, g.kids], backgroundColor: ['#3b82f6','#ec4899','#f59e0b'], hoverOffset: 6, borderWidth: 2, borderColor: isDark() ? '#1e293b' : '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 10 } }, tooltip: tipStyle() } },
    });
}
