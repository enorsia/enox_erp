import { Chart, CategoryScale, LinearScale, LineElement, PointElement, ArcElement, Tooltip, Legend, LineController, DoughnutController } from 'chart.js';

Chart.register(CategoryScale, LinearScale, LineElement, PointElement, ArcElement, Tooltip, Legend, LineController, DoughnutController);

const D = window.visitorAnalyticsData || {};

Chart.defaults.font.family = "'DM Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;

const isDark = () => document.documentElement.classList.contains('dark');
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

function ctx(id) {
    const el = document.getElementById(id);

    return el ? el.getContext('2d') : null;
}

const trendCtx = ctx('vaTrendMini');
if (trendCtx && D.trend) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: D.trend.labels || [],
            datasets: [
                {
                    label: 'Visitors',
                    data: D.trend.visitors || [],
                    borderColor: accent(),
                    backgroundColor: `${accent()}33`,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                },
                {
                    label: 'Sessions',
                    data: D.trend.sessions || [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    pointRadius: 2,
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
                    position: 'bottom',
                    labels: { boxWidth: 10, padding: 12 },
                },
                tooltip: tipStyle(),
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: gridClr() } },
            },
        },
    });
}

const donutCtx = ctx('vaNewReturningMini');
if (donutCtx && D.new_returning) {
    new Chart(donutCtx, {
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
