// The Analytics trend chart. ApexCharts comes from the build (it used to be fetched from a CDN on every visit) and
// this file is only ever loaded by the page that has a chart on it: see the dynamic import in app.js.
import ApexCharts from 'apexcharts';

const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const themed = () => ({
    colors: [token('--ix-signal-text'), token('--ix-text')],
    chart: { foreColor: token('--ix-text-3') },
    grid: { borderColor: token('--ix-rule') },
    markers: { strokeColors: token('--ix-panel') },
    xaxis: { labels: { style: { colors: token('--ix-text-3') } } },
    yaxis: { labels: { style: { colors: token('--ix-text-3') } } },
    tooltip: { theme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light' },
});

// The element carries its own numbers (data-solutions, data-questions, data-months), so no inline script is needed.
export function draw(el) {
    const solutions = JSON.parse(el.dataset.solutions || '[]');
    const questions = JSON.parse(el.dataset.questions || '[]');
    const months = JSON.parse(el.dataset.months || '[]');
    const top = Math.max(1, ...solutions, ...questions);

    const base = themed();
    // The Yard's reading face, with the same fallbacks as --ix-font.
    const font = token('--ix-font') || 'Archivo, system-ui, sans-serif';
    const chart = new ApexCharts(el, {
        chart: {
            type: 'line',
            height: 240,
            fontFamily: font,
            foreColor: base.chart.foreColor,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: false },
        },
        series: [
            { name: 'Solutions', data: solutions },
            { name: 'Questions', data: questions },
        ],
        colors: base.colors,
        stroke: { curve: 'straight', width: 2 },
        fill: { type: 'solid', opacity: 1 },
        markers: { size: 4, shape: ['circle', 'square'], strokeWidth: 2, strokeColors: base.markers.strokeColors, hover: { sizeOffset: 2 } },
        xaxis: {
            categories: months,
            labels: { style: { colors: base.xaxis.labels.style.colors, fontSize: '12px', fontFamily: font } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            max: top <= 5 ? top : undefined,
            tickAmount: top <= 5 ? top : 5,
            forceNiceScale: top > 5,
            labels: {
                style: { colors: base.yaxis.labels.style.colors, fontSize: '12px', fontFamily: font },
                formatter: (v) => String(Math.round(v)),
            },
        },
        grid: { borderColor: base.grid.borderColor, strokeDashArray: 0, padding: { left: 4, right: 12 } },
        tooltip: { theme: base.tooltip.theme, shared: true, intersect: false, style: { fontSize: '13px', fontFamily: font } },
        legend: { show: false },
        dataLabels: { enabled: false },
    });
    chart.render();

    // Re-read the tokens when the operating system flips between night and light.
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onScheme = () => {
        const t = themed();
        chart.updateOptions({
            colors: t.colors,
            chart: { foreColor: t.chart.foreColor },
            grid: { borderColor: t.grid.borderColor },
            markers: { strokeColors: t.markers.strokeColors },
            xaxis: { labels: { style: { colors: t.xaxis.labels.style.colors } } },
            yaxis: { labels: { style: { colors: t.yaxis.labels.style.colors } } },
            tooltip: { theme: t.tooltip.theme },
        });
    };
    if (mq.addEventListener) mq.addEventListener('change', () => setTimeout(onScheme, 0));
}
