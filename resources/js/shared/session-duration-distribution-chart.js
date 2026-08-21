import {
    Chart,
    CategoryScale,
    LinearScale,
    BarElement,
    Tooltip,
    BarController,
} from 'chart.js';

Chart.register(CategoryScale, LinearScale, BarElement, Tooltip, BarController);

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

/**
 * @param {string} canvasId
 * @param {{ buckets?: Array<{ label: string, count: number, pct: number }> }} distribution
 */
export function initSessionDurationDistributionChart(canvasId, distribution) {
    const el = document.getElementById(canvasId);

    if (!el || !distribution?.buckets?.length) {
        return null;
    }

    const buckets = distribution.buckets;
    const labels = buckets.map((bucket) => bucket.label);
    const percentages = buckets.map((bucket) => bucket.pct ?? 0);
    const counts = buckets.map((bucket) => bucket.count ?? 0);

    return new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Sessions',
                data: percentages,
                backgroundColor: `${accent()}cc`,
                borderColor: accent(),
                borderWidth: 1,
                borderRadius: 4,
                barThickness: 22,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tipStyle(),
                    callbacks: {
                        label: (context) => {
                            const index = context.dataIndex;
                            const count = counts[index] ?? 0;
                            const pct = percentages[index] ?? 0;

                            return `${count.toLocaleString()} sessions (${pct}%)`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: gridClr() },
                    ticks: {
                        callback: (value) => `${value}%`,
                    },
                },
                y: {
                    grid: { display: false },
                },
            },
        },
    });
}
