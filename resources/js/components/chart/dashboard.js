export function initDashboardCharts() {
    if (!window.ApexCharts) return;
    const read = (selector) => {
        const element = document.querySelector(selector);
        if (!element) return null;
        try { return { element, data: JSON.parse(element.dataset.chart || '{}') }; } catch { return null; }
    };
    const monthly = read('#dashboard-monthly-chart');
    if (monthly && monthly.data.labels?.length) {
        new window.ApexCharts(monthly.element, { chart: { type: 'area', height: 288, toolbar: { show: false } }, series: monthly.data.datasets || [], xaxis: { categories: monthly.data.labels, axisBorder: { show: false }, axisTicks: { show: false } }, yaxis: { labels: { formatter: value => new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(value) } }, colors: ['#465FFF'], stroke: { curve: 'smooth', width: 2 }, fill: { type: 'gradient', gradient: { opacityFrom: .32, opacityTo: .02 } }, dataLabels: { enabled: false }, grid: { borderColor: '#E5E7EB', strokeDashArray: 4 }, tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' } }).render();
    }
    const types = read('#dashboard-type-chart');
    if (types && types.data.labels?.length) {
        new window.ApexCharts(types.element, { chart: { type: 'donut', height: 256 }, series: types.data.datasets?.[0]?.data || [], labels: types.data.labels, colors: ['#465FFF', '#7592FF', '#90BFFF', '#A7F3D0', '#FCD34D', '#FDA4AF'], legend: { position: 'bottom' }, dataLabels: { enabled: false }, stroke: { colors: [document.documentElement.classList.contains('dark') ? '#101828' : '#fff'] }, tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' } }).render();
    }
    const budget = read('#budget-components-chart');
    if (budget && budget.data.labels?.length) {
        new window.ApexCharts(budget.element, { chart: { type: 'bar', height: 288, toolbar: { show: false } }, series: budget.data.datasets || [], xaxis: { categories: budget.data.labels, axisBorder: { show: false }, axisTicks: { show: false } }, yaxis: { labels: { formatter: value => new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(value) } }, colors: ['#465FFF'], plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } }, dataLabels: { enabled: false }, grid: { borderColor: '#E5E7EB', strokeDashArray: 4 }, tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' } }).render();
    }
}
