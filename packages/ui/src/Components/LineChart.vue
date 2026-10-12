<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import '../charts/register.js';
import { cartesianOptions } from '../charts/options.js';
import { resolveColor, withAlpha } from '../charts/theme.js';
import { useChartTheme } from '../Composables/useChartTheme.js';

const props = defineProps({
    dates: { type: Array, required: true },
    datasets: { type: Array, required: true },
    format: { type: Function, required: true },
    label: { type: String, required: true },
});

const theme = useChartTheme();

function shortDate(date) {
    const locale = document.documentElement.lang || 'en';

    return new Date(`${date}T00:00:00`).toLocaleDateString(locale, { month: 'short', day: 'numeric' });
}

const labels = computed(() => props.dates.map(shortDate));

const data = computed(() => ({
    labels: labels.value,
    datasets: props.datasets.map((dataset, index) => {
        const color = resolveColor(theme.value, dataset.color, index);

        return {
            label: dataset.label,
            data: dataset.data,
            borderColor: color,
            backgroundColor: withAlpha(color, 0.15),
            fill: dataset.fill ?? false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: props.dates.length === 1 ? 3 : 0,
            pointHoverRadius: 4,
        };
    }),
}));

const options = computed(() => cartesianOptions(theme.value, { format: props.format, legend: props.datasets.length > 1 }));

const ariaLabel = computed(
    () => `${props.label}. ${labels.value[0] ?? ''} – ${labels.value[labels.value.length - 1] ?? ''}. ${props.datasets.map((dataset) => dataset.label).join(', ')}`,
);
</script>

<template>
    <Line :data="data" :options="options" :aria-label="ariaLabel" role="img" />
</template>
