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
const gridClr = () => (isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)');
const accent = () => getComputedStyle(document.querySelector('.etd-page') || document.body)
    .getPropertyValue('--etd-accent')
    .trim() || '#1D9E75';
const gold = () => '#f59e0b';
const purchaseGreen = () => '#22c55e';

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

function trendTickLimit(labelCount) {
    if (isNarrow()) {
        return Math.min(6, labelCount);
    }

    if (labelCount > 60) {
        return 8;
    }

    if (labelCount > 30) {
        return 10;
    }

    return Math.min(14, labelCount);
}

function sessionsForLogScale(values) {
    return values.map((value) => {
        const numeric = Number(value) || 0;

        return numeric > 0 ? numeric : null;
    });
}

const devicePalette = () => [accent(), gold(), '#64748b', '#3b82f6', '#8b5cf6'];

const trendCtx = ctx('etdTrendChart');
if (trendCtx && D.trend) {
    const {
        labels = [],
        sessions = [],
        purchases = [],
        conversion_rates: conversionRates = [],
        use_log_scale: useLogScale = false,
    } = D.trend;

    const sessionData = useLogScale ? sessionsForLogScale(sessions) : sessions;
    const purchaseData = useLogScale ? sessionsForLogScale(purchases) : purchases;

    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Sessions',
                    data: sessionData,
                    backgroundColor: `${accent()}8C`,
                    borderRadius: 3,
                    yAxisID: 'y',
                    order: 3,
                    barThickness: isNarrow() ? 8 : (labels.length > 30 ? 10 : 14),
                },
                {
                    type: 'line',
                    label: 'Purchases',
                    data: purchaseData,
                    borderColor: purchaseGreen(),
                    backgroundColor: purchaseGreen(),
                    pointRadius: 2.5,
                    tension: 0.3,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Conv. rate %',
                    data: conversionRates,
                    borderColor: gold(),
                    backgroundColor: gold(),
                    pointRadius: 2.5,
                    tension: 0.35,
                    yAxisID: 'y1',
                    order: 1,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        boxWidth: 10,
                        padding: isNarrow() ? 8 : 14,
                        font: { size: isNarrow() ? 9 : 11 },
                    },
                },
                tooltip: {
                    ...tipStyle(),
                    callbacks: {
                        label(context) {
                            const value = context.parsed.y ?? context.parsed ?? 0;

                            if (context.dataset.label === 'Conv. rate %') {
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
                        maxRotation: labels.length > 20 ? 45 : 0,
                        autoSkip: true,
                        maxTicksLimit: trendTickLimit(labels.length),
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
                },
                y1: {
                    position: 'right',
                    grid: { display: false },
                    min: 0,
                    ticks: { callback: (value) => `${value}%` },
                },
            },
        },
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
                    label: 'Real visitors',
                    data: botTrend.human || [],
                    backgroundColor: '#3b82f68C',
                    borderRadius: 3,
                    stack: 'traffic',
                },
                {
                    label: 'Automated traffic',
                    data: botTrend.bot || [],
                    backgroundColor: '#f59e0b8C',
                    borderRadius: 3,
                    stack: 'traffic',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { boxWidth: 10 } }, tooltip: tipStyle() },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, grid: { color: gridClr() }, beginAtZero: true },
            },
        },
    });
}
