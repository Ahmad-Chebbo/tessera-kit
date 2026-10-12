const FALLBACK = {
    text: '#141109',
    muted: 'rgba(20, 17, 9, 0.62)',
    grid: 'rgba(20, 17, 9, 0.10)',
    surface: '#FFFFFF',
    accent: '#B08A2C',
    info: '#4A6B86',
    success: '#506C46',
    warn: '#9A5B12',
    danger: '#A3483A',
};

export function withAlpha(color, alpha) {
    const value = (color ?? '').trim();

    const hex = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (hex) {
        const digits = hex[1].length === 3 ? [...hex[1]].map((digit) => digit + digit).join('') : hex[1];
        const [r, g, b] = [0, 2, 4].map((index) => parseInt(digits.slice(index, index + 2), 16));

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    const rgb = value.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
    if (rgb) {
        return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${alpha})`;
    }

    return color;
}

function token(element, name, fallback) {
    return (
        getComputedStyle(element).getPropertyValue(name).trim() ||
        element.style.getPropertyValue(name).trim() ||
        fallback
    );
}

export function readChartTheme(element = document.documentElement) {
    const read = (name, fallback) => token(element, name, fallback);

    const accent = read('--gold-on', FALLBACK.accent);
    const info = read('--status-info', FALLBACK.info);
    const success = read('--status-success', FALLBACK.success);
    const warn = read('--status-warn', FALLBACK.warn);
    const danger = read('--status-danger', FALLBACK.danger);
    const muted = read('--muted', FALLBACK.muted);

    return {
        text: read('--text', FALLBACK.text),
        muted,
        grid: read('--line', FALLBACK.grid),
        surface: read('--surface', FALLBACK.surface),
        accent,
        info,
        success,
        warn,
        danger,
        palette: [accent, info, success, warn, danger, muted],
        fontFamily: (document.body && getComputedStyle(document.body).fontFamily) || 'sans-serif',
    };
}

export function paletteColor(theme, index) {
    return theme.palette[index % theme.palette.length];
}

export function resolveColor(theme, color, index) {
    if (!color) {
        return paletteColor(theme, index);
    }

    return typeof theme[color] === 'string' ? theme[color] : color;
}
