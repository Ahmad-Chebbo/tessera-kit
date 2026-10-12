<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import '../charts/register.js';
import { cartesianOptions } from '../charts/options.js';
import { withAlpha } from '../charts/theme.js';
import { useChartTheme } from '../Composables/useChartTheme.js';

const props = defineProps({
    series: { type: Array, required: true },
    format: { type: Function, required: true },
    label: { type: String, required: true },
    revenueLabel: { type: String, default: 'Revenue' },
    ordersLabel: { type: String, default: 'Orders' },
    showOrders: { type: Boolean, default: true },
});

const theme = useChartTheme();

function shortDate(date) {
    const locale = document.documentElement.lang || 'en';

    return new Date(`${date}T00:00:00`).toLocaleDateString(locale, { month: 'short', day: 'numeric' });
}

const labels = computed(() => props.series.map((point) => shortDate(point.date)));

const data = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            label: props.revenueLabel,
            data: props.series.map((point) => point.revenue),
            borderColor: theme.value.accent,
            backgroundColor: withAlpha(theme.value.accent, 0.15),
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: props.series.length === 1 ? 3 : 0,
            pointHoverRadius: 4,
            yAxisID: 'y',
            order: 0,
        },
        ...(props.showOrders
            ? [
                  {
                      type: 'bar',
                      label: props.ordersLabel,
                      data: props.series.map((point) => point.orders),
                      backgroundColor: withAlpha(theme.value.info, 0.35),
                      borderRadius: 4,
                      maxBarThickness: 18,
                      yAxisID: 'y1',
                      order: 1,
                  },
              ]
            : []),
    ],
}));

const options = computed(() =>
    cartesianOptions(theme.value, {
        format: props.format,
        legend: props.showOrders,
        secondary: props.showOrders ? { format: (value) => String(value) } : null,
    }),
);

const ariaLabel = computed(() => {
    const total = props.series.reduce((sum, point) => sum + point.revenue, 0);
    const first = labels.value[0] ?? '';
    const last = labels.value[labels.value.length - 1] ?? '';

    return `${props.label}. ${first} – ${last}. ${props.revenueLabel}: ${props.format(total)}`;
});
</script>

<template>
    <Line :data="data" :options="options" :aria-label="ariaLabel" role="img" />
</template>
