import { paletteColor } from './theme.js';

const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });

export function compactNumber(value) {
    return compact.format(value);
}

export function truncateLabel(label, max = 20) {
    const text = String(label ?? '');

    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

export function percentOf(value, total) {
    return total ? Math.round((value / total) * 1000) / 10 : 0;
}

export function doughnutLegend(labels, values, colors, format) {
    const total = values.reduce((sum, value) => sum + value, 0);

    return labels.map((label, index) => ({
        label,
        value: values[index],
        formatted: format(values[index]),
        percent: percentOf(values[index], total),
        color: colors[index % colors.length],
    }));
}

function tooltip(theme, callbacks) {
    return {
        backgroundColor: theme.surface,
        titleColor: theme.text,
        bodyColor: theme.text,
        borderColor: theme.grid,
        borderWidth: 1,
        padding: 10,
        cornerRadius: 10,
        titleFont: { family: theme.fontFamily },
        bodyFont: { family: theme.fontFamily },
        callbacks,
    };
}

export function cartesianOptions(theme, { format, horizontal = false, secondary = null, legend = false }) {
    const categoryAxis = {
        grid: { display: false },
        border: { display: false },
        ticks: {
            color: theme.muted,
            maxRotation: 0,
            autoSkip: !horizontal,
            font: { family: theme.fontFamily },
            ...(horizontal
                ? {
                      callback(value) {
                          return truncateLabel(this.getLabelForValue(value));
                      },
                  }
                : {}),
        },
    };

    const valueAxis = {
        beginAtZero: true,
        grid: { display: true, color: theme.grid },
        border: { display: false },
        ticks: { color: theme.muted, callback: (value) => compactNumber(value), font: { family: theme.fontFamily } },
    };

    const scales = horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis };

    if (secondary) {
        scales.y1 = {
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            border: { display: false },
            ticks: { color: theme.muted, precision: 0, font: { family: theme.fontFamily } },
        };
    }

    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        interaction: { mode: horizontal ? 'nearest' : 'index', intersect: false },
        plugins: {
            legend: {
                display: legend,
                labels: { color: theme.muted, usePointStyle: true, boxWidth: 8, font: { family: theme.fontFamily } },
            },
            tooltip: tooltip(theme, {
                label: (context) => {
                    const formatter = context.dataset.yAxisID === 'y1' && secondary?.format ? secondary.format : format;
                    const value = horizontal ? context.parsed.x : context.parsed.y;

                    return `${context.dataset.label}: ${formatter(value)}`;
                },
            }),
        },
        scales,
    };
}

export function doughnutOptions(theme, { format }) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: tooltip(theme, {
                label: (context) => {
                    const total = context.dataset.data.reduce((sum, value) => sum + value, 0);

                    return `${context.label}: ${format(context.parsed)} (${percentOf(context.parsed, total)}%)`;
                },
            }),
        },
    };
}

export { paletteColor };
