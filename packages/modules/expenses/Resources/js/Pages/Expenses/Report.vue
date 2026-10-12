<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import StatCard from '@tessera/tessera-ui/Components/StatCard.vue';
import ChartCard from '@tessera/tessera-ui/Components/ChartCard.vue';
import BarChart from '@tessera/tessera-ui/Components/BarChart.vue';
import DoughnutChart from '@tessera/tessera-ui/Components/DoughnutChart.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import ExportMenu from '@/Components/ExportMenu.vue';
import ExpensesTabs from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpensesTabs.vue';
import ExpenseFilters from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpenseFilters.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    report: Object,
    filters: Object,
    presets: Array,
    lookups: Object,
});

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const monthLabel = (month) => new Date(`${month}-01T00:00:00`).toLocaleDateString(document.documentElement.lang || 'en', { month: 'short', year: '2-digit' });

const columns = computed(() => [
    { key: 'name', label: t('expenses.tableCategory') },
    { key: 'type', label: t('expenses.type') },
    { key: 'affects_profit', label: t('expenses.affectsProfit') },
    { key: 'total', label: t('expenses.total') },
    { key: 'share', label: t('expenses.share') },
]);

const share = (row) => (props.report.total > 0 ? `${((row.total / props.report.total) * 100).toFixed(1)}%` : '0%');
const branchName = (branch) => branch.name ?? t('expenses.unassigned');
const exportFilters = computed(() => ({ ...props.filters }));
</script>

<template>
    <Head :title="t('expenses.reportTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('expenses.reportTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('expenses.reportSubtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <ExpensesTabs current="expenses.report" />
            <ExportMenu report="expenses-report" :filters="exportFilters" />
        </div>

        <ExpenseFilters class="mb-6" route-name="expenses.report" :filters="filters" :presets="presets" :categories="lookups.categories" :branches="lookups.branches" :types="lookups.types" />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard :label="t('expenses.total')" :value="formatCurrency(report.total)" />
            <StatCard :label="t('expenses.counted')" :value="formatCurrency(report.included)" />
            <StatCard :label="t('expenses.entries')" :value="report.count" />
            <StatCard :label="t('expenses.average')" :value="formatCurrency(report.average)" />
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <ChartCard class="lg:col-span-2" :title="t('expenses.byMonth')" :description="t('expenses.byMonthDesc')" :empty="!report.total" :empty-title="t('expenses.noReport')">
                <BarChart
                    :labels="report.by_month.map((row) => monthLabel(row.month))"
                    :datasets="[{ label: t('expenses.total'), data: report.by_month.map((row) => row.total) }, { label: t('expenses.counted'), data: report.by_month.map((row) => row.included), color: 'success' }]"
                    :format="formatCurrency"
                    :label="t('expenses.byMonth')"
                />
            </ChartCard>
            <ChartCard :title="t('expenses.byCategory')" :description="t('expenses.byCategoryDesc')" :empty="!report.by_category.length" :empty-title="t('expenses.noReport')">
                <DoughnutChart :labels="report.by_category.map((row) => row.name)" :values="report.by_category.map((row) => row.total)" :format="formatCurrency" :label="t('expenses.byCategory')" />
            </ChartCard>
        </div>

        <ChartCard v-if="report.by_branch.length > 1" class="mb-6" :title="t('expenses.byBranch')" :description="t('expenses.byBranchDesc')" height="h-64">
            <BarChart
                horizontal
                :labels="report.by_branch.map(branchName)"
                :datasets="[{ label: t('expenses.total'), data: report.by_branch.map((row) => row.total) }]"
                :format="formatCurrency"
                :label="t('expenses.byBranch')"
            />
        </ChartCard>

        <DataTable :columns="columns" :rows="report.by_category" :empty-title="t('expenses.noReport')">
            <template #cell-type="{ row }">{{ t(`expenses.types.${row.type}`) }}</template>
            <template #cell-affects_profit="{ row }">
                <StatusBadge :status="row.affects_profit ? 'success' : 'neutral'">{{ row.affects_profit ? t('expenses.yes') : t('expenses.no') }}</StatusBadge>
            </template>
            <template #cell-total="{ row }">{{ formatCurrency(row.total) }}</template>
            <template #cell-share="{ row }">{{ share(row) }}</template>
        </DataTable>
    </SidebarLayout>
</template>
