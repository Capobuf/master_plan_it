import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-chart]')) {
        import('./components/chart/dashboard').then((module) => module.initDashboardCharts());
    }
});
