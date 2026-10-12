import { describe, expect, it } from 'vitest';
import { cartesianOptions, compactNumber, doughnutLegend, doughnutOptions, percentOf, truncateLabel } from '../options.js';

const theme = {
    text: '#111',
    muted: '#666',
    grid: '#ddd',
    surface: '#fff',
    accent: '#c39b50',
    palette: ['#c39b50', '#4a6b86', '#506c46'],
    fontFamily: 'Geist',
};

describe('compactNumber', () => {
    it('shortens large values for axes', () => {
        expect(compactNumber(0)).toBe('0');
        expect(compactNumber(950)).toBe('950');
        expect(compactNumber(1250)).toBe('1.3K');
        expect(compactNumber(2_500_000)).toBe('2.5M');
    });
});

describe('truncateLabel', () => {
    it('keeps short labels and shortens long ones with an ellipsis', () => {
        expect(truncateLabel('Cola')).toBe('Cola');
        expect(truncateLabel('A very long product name indeed')).toBe('A very long product…');
        expect(truncateLabel('A very long product name indeed').length).toBe(20);
    });

    it('accepts a custom limit', () => {
        expect(truncateLabel('abcdefgh', 5)).toBe('abcd…');
    });
});

describe('percentOf', () => {
    it('returns a percentage with one decimal', () => {
        expect(percentOf(1, 3)).toBe(33.3);
        expect(percentOf(25, 100)).toBe(25);
    });

    it('is zero when the total is zero', () => {
        expect(percentOf(5, 0)).toBe(0);
    });
});

describe('doughnutLegend', () => {
    it('describes every slice with its colour, formatted value and share', () => {
        const rows = doughnutLegend(['Drinks', 'Food'], [75, 25], theme.palette, (value) => `$${value}`);

        expect(rows).toEqual([
            { label: 'Drinks', value: 75, formatted: '$75', percent: 75, color: '#c39b50' },
            { label: 'Food', value: 25, formatted: '$25', percent: 25, color: '#4a6b86' },
        ]);
    });

    it('handles an all-zero data set', () => {
        expect(doughnutLegend(['A'], [0], theme.palette, String)[0].percent).toBe(0);
    });
});

describe('cartesianOptions', () => {
    it('is responsive, fills its container and uses the theme for text, grid and tooltips', () => {
        const options = cartesianOptions(theme, { format: (value) => `$${value}` });

        expect(options).toMatchObject({ responsive: true, maintainAspectRatio: false });
        expect(options.scales.x.grid.display).toBe(false);
        expect(options.scales.y.grid.color).toBe('#ddd');
        expect(options.scales.y.ticks.color).toBe('#666');
        expect(options.plugins.tooltip).toMatchObject({ backgroundColor: '#fff', titleColor: '#111', bodyColor: '#111' });
    });

    it('formats tooltip values and axis ticks with the supplied formatter', () => {
        const options = cartesianOptions(theme, { format: (value) => `$${value}` });

        expect(options.plugins.tooltip.callbacks.label({ dataset: { label: 'Revenue' }, parsed: { y: 120 } })).toBe('Revenue: $120');
        expect(options.scales.y.ticks.callback(1500)).toBe('1.5K');
    });

    it('can run horizontally with the values on the x axis', () => {
        const options = cartesianOptions(theme, { format: String, horizontal: true });

        expect(options.indexAxis).toBe('y');
        expect(options.scales.x.beginAtZero).toBe(true);
        expect(options.scales.x.grid.display).toBe(true);
        expect(options.scales.y.grid.display).toBe(false);
        expect(options.plugins.tooltip.callbacks.label({ dataset: { label: 'Revenue' }, parsed: { x: 30 } })).toBe('Revenue: 30');
    });

    it('shortens long category labels on a horizontal chart axis', () => {
        const options = cartesianOptions(theme, { format: String, horizontal: true });
        const scale = { getLabelForValue: () => 'A very long product name indeed' };

        expect(options.scales.y.ticks.callback.call(scale, 0)).toBe('A very long product…');
    });

    it('adds a right-hand axis for a secondary series', () => {
        const options = cartesianOptions(theme, { format: String, secondary: { format: (value) => `${value} orders` } });

        expect(options.scales.y1).toMatchObject({ position: 'right', beginAtZero: true });
        expect(options.scales.y1.grid.drawOnChartArea).toBe(false);
        expect(options.scales.y1.ticks.precision).toBe(0);
    });

    it('shows the legend only when asked', () => {
        expect(cartesianOptions(theme, { format: String }).plugins.legend.display).toBe(false);
        expect(cartesianOptions(theme, { format: String, legend: true }).plugins.legend.display).toBe(true);
    });
});

describe('doughnutOptions', () => {
    it('hides the built-in legend and formats the tooltip with value and share', () => {
        const options = doughnutOptions(theme, { format: (value) => `$${value}` });

        expect(options.plugins.legend.display).toBe(false);
        expect(options.cutout).toBe('68%');
        expect(
            options.plugins.tooltip.callbacks.label({ label: 'Drinks', parsed: 75, dataset: { data: [75, 25] } }),
        ).toBe('Drinks: $75 (75%)');
    });
});
