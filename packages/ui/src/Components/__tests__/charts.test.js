import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import BarChart from '../BarChart.vue';
import ChartCard from '../ChartCard.vue';
import LineChart from '../LineChart.vue';
import DoughnutChart from '../DoughnutChart.vue';
import TrendChart from '../TrendChart.vue';
import { i18n } from './helpers';

vi.mock('vue-chartjs', () => {
    const stub = (name) => ({
        name,
        props: ['data', 'options', 'ariaLabel'],
        template: `<canvas role="img" :aria-label="ariaLabel" />`,
    });

    return { Line: stub('Line'), Bar: stub('Bar'), Doughnut: stub('Doughnut') };
});

const setTokens = () => {
    const root = document.documentElement.style;
    root.setProperty('--text', '#111111');
    root.setProperty('--muted', '#666666');
    root.setProperty('--line', '#dddddd');
    root.setProperty('--surface', '#ffffff');
    root.setProperty('--gold-on', '#B08A2C');
    root.setProperty('--status-info', '#4A6B86');
    root.setProperty('--status-success', '#506C46');
    root.setProperty('--status-warn', '#9A5B12');
    root.setProperty('--status-danger', '#A3483A');
};

const money = (value) => `$${value}`;

describe('charts', () => {
    beforeEach(setTokens);

    afterEach(() => {
        document.documentElement.removeAttribute('style');
    });

    describe('TrendChart', () => {
        const series = [
            { date: '2026-05-09', revenue: 10, orders: 1 },
            { date: '2026-05-10', revenue: 0, orders: 0 },
            { date: '2026-05-11', revenue: 40, orders: 3 },
        ];

        const mountTrend = (props = {}) =>
            mount(TrendChart, {
                props: { series, format: money, revenueLabel: 'Revenue', ordersLabel: 'Orders', label: 'Sales trend', ...props },
                global: { plugins: [i18n] },
            });

        it('draws revenue as a filled line and orders as bars on a second axis', () => {
            const chart = mountTrend().findComponent({ name: 'Line' });
            const { labels, datasets } = chart.props('data');

            expect(labels).toEqual(['May 9', 'May 10', 'May 11']);
            expect(datasets[0]).toMatchObject({ label: 'Revenue', data: [10, 0, 40], fill: true, borderColor: '#B08A2C', yAxisID: 'y' });
            expect(datasets[1]).toMatchObject({ type: 'bar', label: 'Orders', data: [1, 0, 3], yAxisID: 'y1' });
            expect(chart.props('options').scales.y1.position).toBe('right');
        });

        it('exposes an accessible name that summarises the data', () => {
            const label = mountTrend().findComponent({ name: 'Line' }).props('ariaLabel');

            expect(label).toContain('Sales trend');
            expect(label).toContain('May 9');
        });

        it('marks the point when the range is a single day so the line is visible', () => {
            const chart = mountTrend({ series: [{ date: '2026-05-09', revenue: 10, orders: 1 }] }).findComponent({ name: 'Line' });

            expect(chart.props('data').datasets[0].pointRadius).toBe(3);
            expect(mountTrend().findComponent({ name: 'Line' }).props('data').datasets[0].pointRadius).toBe(0);
        });

        it('can hide the orders overlay', () => {
            const chart = mountTrend({ showOrders: false }).findComponent({ name: 'Line' });

            expect(chart.props('data').datasets).toHaveLength(1);
            expect(chart.props('options').scales.y1).toBeUndefined();
        });

        it('re-colours the chart when the light/dark theme changes', async () => {
            const wrapper = mountTrend();
            document.documentElement.style.setProperty('--gold-on', '#C39B50');
            document.documentElement.setAttribute('data-theme', 'dark');
            await new Promise((resolve) => setTimeout(resolve, 0));
            await wrapper.vm.$nextTick();

            expect(wrapper.findComponent({ name: 'Line' }).props('data').datasets[0].borderColor).toBe('#C39B50');
            document.documentElement.removeAttribute('data-theme');
        });
    });

    describe('BarChart', () => {
        const mountBar = (props = {}) =>
            mount(BarChart, {
                props: {
                    labels: ['Cola', 'Burger'],
                    datasets: [{ label: 'Revenue', data: [30, 70] }],
                    format: money,
                    label: 'Top products',
                    ...props,
                },
                global: { plugins: [i18n] },
            });

        it('gives every dataset its own palette colour', () => {
            const chart = mountBar({
                datasets: [
                    { label: 'Revenue', data: [30, 70] },
                    { label: 'Cost', data: [10, 20] },
                ],
            }).findComponent({ name: 'Bar' });

            const [first, second] = chart.props('data').datasets;
            expect(first.backgroundColor).not.toBe(second.backgroundColor);
            expect(first.backgroundColor).toBe('#B08A2C');
        });

        it('honours an explicit dataset colour', () => {
            const chart = mountBar({ datasets: [{ label: 'Profit', data: [1, 2], color: '#506C46' }] }).findComponent({ name: 'Bar' });

            expect(chart.props('data').datasets[0].backgroundColor).toBe('#506C46');
        });

        it('accepts named theme colours for datasets', () => {
            const chart = mountBar({ datasets: [{ label: 'Profit', data: [1, 2], color: 'success' }] }).findComponent({ name: 'Bar' });

            expect(chart.props('data').datasets[0].backgroundColor).toBe('#506C46');
        });

        it('runs horizontally when asked', () => {
            const chart = mountBar({ horizontal: true }).findComponent({ name: 'Bar' });

            expect(chart.props('options').indexAxis).toBe('y');
        });

        it('shows the legend for several datasets only', () => {
            expect(mountBar().findComponent({ name: 'Bar' }).props('options').plugins.legend.display).toBe(false);

            const multiple = mountBar({
                datasets: [
                    { label: 'Revenue', data: [1, 2] },
                    { label: 'Cost', data: [1, 2] },
                ],
            }).findComponent({ name: 'Bar' });
            expect(multiple.props('options').plugins.legend.display).toBe(true);
        });
    });

    describe('LineChart', () => {
        const series = [{ date: '2026-05-09' }, { date: '2026-05-10' }];

        const mountLine = (props = {}) =>
            mount(LineChart, {
                props: {
                    dates: series.map((point) => point.date),
                    datasets: [
                        { label: 'Revenue', data: [10, 20], color: 'accent' },
                        { label: 'Cost', data: [4, 6], color: 'danger' },
                        { label: 'Profit', data: [6, 14], color: 'success', fill: true },
                    ],
                    format: money,
                    label: 'Revenue, cost and profit',
                    ...props,
                },
                global: { plugins: [i18n] },
            });

        it('draws one line per dataset using named theme colours', () => {
            const { labels, datasets } = mountLine().findComponent({ name: 'Line' }).props('data');

            expect(labels).toEqual(['May 9', 'May 10']);
            expect(datasets.map((dataset) => dataset.borderColor)).toEqual(['#B08A2C', '#A3483A', '#506C46']);
            expect(datasets.map((dataset) => dataset.fill)).toEqual([false, false, true]);
        });

        it('marks the point when there is a single day', () => {
            const chart = mountLine({ dates: ['2026-05-09'], datasets: [{ label: 'Revenue', data: [10], color: 'accent' }] }).findComponent({ name: 'Line' });

            expect(chart.props('data').datasets[0].pointRadius).toBe(3);
        });

        it('shows a legend because there are several lines', () => {
            expect(mountLine().findComponent({ name: 'Line' }).props('options').plugins.legend.display).toBe(true);
        });

        it('describes itself for assistive technology', () => {
            expect(mountLine().findComponent({ name: 'Line' }).props('ariaLabel')).toContain('Revenue, cost and profit');
        });
    });

    describe('DoughnutChart', () => {
        const mountDoughnut = (props = {}) =>
            mount(DoughnutChart, {
                props: { labels: ['Drinks', 'Food'], values: [75, 25], format: money, label: 'Sales by category', ...props },
                global: { plugins: [i18n] },
            });

        it('colours each slice from the palette', () => {
            const dataset = mountDoughnut().findComponent({ name: 'Doughnut' }).props('data').datasets[0];

            expect(dataset.data).toEqual([75, 25]);
            expect(dataset.backgroundColor).toEqual(['#B08A2C', '#4A6B86']);
        });

        it('lists every slice in a readable legend with value and share', () => {
            const rows = mountDoughnut().findAll('[data-legend-row]');

            expect(rows).toHaveLength(2);
            expect(rows[0].text()).toContain('Drinks');
            expect(rows[0].text()).toContain('$75');
            expect(rows[0].text()).toContain('75%');
            expect(rows[1].text()).toContain('25%');
        });

        it('renders one legend row per slice even when two slices share a label', () => {
            const rows = mountDoughnut({ labels: ['Other', 'Other'], values: [5, 5] }).findAll('[data-legend-row]');

            expect(rows).toHaveLength(2);
        });

        it('shows the total in the middle', () => {
            expect(mountDoughnut().get('[data-total]').text()).toContain('$100');
        });
    });

    describe('ChartCard', () => {
        it('renders its chart with a title and description', () => {
            const wrapper = mount(ChartCard, {
                props: { title: 'Sales trend', description: 'Last 30 days' },
                slots: { default: '<div data-chart>chart</div>' },
                global: { plugins: [i18n] },
            });

            expect(wrapper.text()).toContain('Sales trend');
            expect(wrapper.text()).toContain('Last 30 days');
            expect(wrapper.find('[data-chart]').exists()).toBe(true);
        });

        it('shows an empty state instead of the chart when there is no data', () => {
            const wrapper = mount(ChartCard, {
                props: { title: 'Sales trend', empty: true },
                slots: { default: '<div data-chart>chart</div>' },
                global: { plugins: [i18n] },
            });

            expect(wrapper.find('[data-chart]').exists()).toBe(false);
            expect(wrapper.text()).toContain('No data for this period');
        });
    });
});
