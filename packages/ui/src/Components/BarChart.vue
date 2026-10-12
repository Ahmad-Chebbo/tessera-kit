<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import '../charts/register.js';
import { cartesianOptions } from '../charts/options.js';
import { resolveColor } from '../charts/theme.js';
import { useChartTheme } from '../Composables/useChartTheme.js';

const props = defineProps({
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    format: { type: Function, required: true },
    label: { type: String, required: true },
    horizontal: { type: Boolean, default: false },
});

const theme = useChartTheme();

const data = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map((dataset, index) => ({
        label: dataset.label,
        data: dataset.data,
        backgroundColor: resolveColor(theme.value, dataset.color, index),
        borderRadius: 6,
        maxBarThickness: 28,
    })),
}));

const options = computed(() =>
    cartesianOptions(theme.value, {
        format: props.format,
        horizontal: props.horizontal,
        legend: props.datasets.length > 1,
    }),
);

const ariaLabel = computed(() => `${props.label}: ${props.labels.join(', ')}`);
</script>

<template>
    <Bar :data="data" :options="options" :aria-label="ariaLabel" role="img" />
</template>
