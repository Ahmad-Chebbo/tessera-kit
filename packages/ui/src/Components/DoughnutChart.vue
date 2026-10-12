<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';
import '../charts/register.js';
import { doughnutLegend, doughnutOptions } from '../charts/options.js';
import { paletteColor } from '../charts/theme.js';
import { useChartTheme } from '../Composables/useChartTheme.js';

const props = defineProps({
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
    format: { type: Function, required: true },
    label: { type: String, required: true },
});

const { t } = useI18n();
const theme = useChartTheme();

const colors = computed(() => props.labels.map((_, index) => paletteColor(theme.value, index)));
const total = computed(() => props.values.reduce((sum, value) => sum + value, 0));

const data = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            data: props.values,
            backgroundColor: colors.value,
            borderColor: theme.value.surface,
            borderWidth: 2,
            hoverOffset: 4,
        },
    ],
}));

const options = computed(() => doughnutOptions(theme.value, { format: props.format }));
const legend = computed(() => doughnutLegend(props.labels, props.values, colors.value, props.format));
const ariaLabel = computed(() => `${props.label}: ${legend.value.map((row) => `${row.label} ${row.percent}%`).join(', ')}`);
</script>

<template>
    <div class="flex h-full flex-col items-center justify-center gap-6 sm:flex-row">
        <div class="relative h-44 w-44 shrink-0">
            <Doughnut :data="data" :options="options" :aria-label="ariaLabel" role="img" />
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                <span data-total class="font-display text-xl text-fg">{{ format(total) }}</span>
                <span class="text-xs text-muted">{{ t('charts.total') }}</span>
            </div>
        </div>

        <ul class="w-full min-w-0 flex-1 space-y-2">
            <li v-for="(row, index) in legend" :key="`${index}-${row.label}`" data-legend-row class="flex items-center gap-3 text-sm">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: row.color }"></span>
                <span class="min-w-0 flex-1 truncate text-fg">{{ row.label }}</span>
                <span class="shrink-0 text-muted">{{ row.formatted }}</span>
                <span class="w-12 shrink-0 text-end text-faint">{{ row.percent }}%</span>
            </li>
        </ul>
    </div>
</template>
