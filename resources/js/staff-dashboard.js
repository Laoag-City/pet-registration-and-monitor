import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, DoughnutController, ArcElement } from 'chart.js';

const root = document.querySelector('[data-staff-dashboard]');
if (root) {
    Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, DoughnutController, ArcElement);
    const data = JSON.parse(document.getElementById('staff-chart-data').textContent);
    const speciesColors = ['#2f88ff', '#24c58a', '#9ba9bf', '#dfb577', '#aa97cc', '#67bcc8'];
    const statusColors = ['#2bc789', '#badbff', '#ffb74f', '#ffb7bf'];
    const base = { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#183452', padding: 10, cornerRadius: 6, displayColors: false } } };
    new Chart(document.getElementById('registration-chart'), {
        type: 'line',
        data: { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], datasets: [{ label: 'Pets registered', data: data.registrations, borderColor: '#2986ff', backgroundColor: 'rgba(47,136,255,.15)', fill: true, tension: .32, cubicInterpolationMode: 'monotone', borderWidth: 2, pointRadius: 4, pointHoverRadius: 5, pointBackgroundColor: '#2986ff', pointBorderColor: '#fff', pointBorderWidth: 2 }] },
        options: { ...base, interaction: { intersect: false, mode: 'index' }, scales: { x: { grid: { color: '#edf2f9' }, border: { display: false }, ticks: { color: '#718197', font: { size: 10 }, maxRotation: 0 } }, y: { beginAtZero: true, suggestedMax: 4, border: { display: false }, grid: { color: '#edf1f6' }, ticks: { precision: 0, maxTicksLimit: 5, color: '#718197', font: { size: 10 } } } } },
    });
    function donut(id, labels, values, colors, cutout) {
        const empty = values.every((value) => value === 0);
        new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: { labels: empty ? ['No data'] : labels, datasets: [{ data: empty ? [1] : values, backgroundColor: empty ? ['#edf1f6'] : colors, borderWidth: empty ? 0 : 3, borderColor: '#fff', hoverOffset: empty ? 0 : 3 }] },
            options: { ...base, cutout, events: empty ? [] : ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'], plugins: { ...base.plugins, tooltip: { ...base.plugins.tooltip, enabled: !empty } } },
        });
    }
    donut('species-chart', data.species.map((item) => item.label), data.species.map((item) => item.count), data.species.map((_, index) => speciesColors[index % speciesColors.length]), '59%');
    root.querySelectorAll('[data-species-dot]').forEach((dot) => { dot.style.backgroundColor = speciesColors[Number(dot.dataset.speciesDot) % speciesColors.length]; });
    Object.entries(data.statuses).forEach(([type, status]) => donut(`${type}-chart`, Object.keys(status.counts), Object.values(status.counts), statusColors, '79%'));
}
