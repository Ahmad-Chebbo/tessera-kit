import { afterEach, describe, expect, it } from 'vitest';
import { paletteColor, readChartTheme, resolveColor, withAlpha } from '../theme.js';

describe('withAlpha', () => {
    it('converts hex colours', () => {
        expect(withAlpha('#F8D38D', 0.5)).toBe('rgba(248, 211, 141, 0.5)');
        expect(withAlpha('#fff', 0.2)).toBe('rgba(255, 255, 255, 0.2)');
    });

    it('replaces the alpha of rgb and rgba colours', () => {
        expect(withAlpha('rgb(10, 20, 30)', 0.4)).toBe('rgba(10, 20, 30, 0.4)');
        expect(withAlpha('rgba(10, 20, 30, 0.9)', 0.4)).toBe('rgba(10, 20, 30, 0.4)');
    });

    it('leaves colours it cannot parse untouched', () => {
        expect(withAlpha('rebeccapurple', 0.4)).toBe('rebeccapurple');
        expect(withAlpha('', 0.4)).toBe('');
    });
});

describe('readChartTheme', () => {
    afterEach(() => {
        ['--text', '--muted', '--line', '--surface', '--gold-on', '--status-info'].forEach((name) =>
            document.documentElement.style.removeProperty(name),
        );
    });

    it('reads the design tokens from the document', () => {
        const root = document.documentElement.style;
        root.setProperty('--text', '#111111');
        root.setProperty('--muted', 'rgba(17, 17, 17, 0.6)');
        root.setProperty('--line', 'rgba(17, 17, 17, 0.1)');
        root.setProperty('--surface', '#ffffff');
        root.setProperty('--gold-on', '#B08A2C');

        const theme = readChartTheme();

        expect(theme).toMatchObject({
            text: '#111111',
            muted: 'rgba(17, 17, 17, 0.6)',
            grid: 'rgba(17, 17, 17, 0.1)',
            surface: '#ffffff',
            accent: '#B08A2C',
        });
    });

    it('starts the palette with the accent and always has enough distinct colours', () => {
        document.documentElement.style.setProperty('--gold-on', '#C39B50');
        document.documentElement.style.setProperty('--muted', 'rgba(17, 17, 17, 0.6)');

        const { palette } = readChartTheme();

        expect(palette[0]).toBe('#C39B50');
        expect(palette[5]).toBe('rgba(17, 17, 17, 0.6)');
        expect(palette.length).toBeGreaterThanOrEqual(6);
        expect(new Set(palette).size).toBe(palette.length);
    });

    it('falls back to usable colours when a token is missing', () => {
        const theme = readChartTheme();

        for (const key of ['text', 'muted', 'grid', 'surface', 'accent']) {
            expect(theme[key]).toBeTruthy();
        }
    });
});

describe('paletteColor', () => {
    it('cycles when there are more series than colours', () => {
        const theme = { palette: ['a', 'b', 'c'] };

        expect([0, 1, 2, 3, 4].map((i) => paletteColor(theme, i))).toEqual(['a', 'b', 'c', 'a', 'b']);
    });
});

describe('resolveColor', () => {
    const theme = { palette: ['#a', '#b', '#c'], success: '#506C46', danger: '#A3483A' };

    it('maps a theme colour name to its current value', () => {
        expect(resolveColor(theme, 'success', 0)).toBe('#506C46');
        expect(resolveColor(theme, 'danger', 1)).toBe('#A3483A');
    });

    it('passes explicit colours through', () => {
        expect(resolveColor(theme, '#123456', 0)).toBe('#123456');
    });

    it('falls back to the palette by index when no colour is given', () => {
        expect(resolveColor(theme, undefined, 1)).toBe('#b');
    });
});
