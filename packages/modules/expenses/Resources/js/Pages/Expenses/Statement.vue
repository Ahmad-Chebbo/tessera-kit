<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import StatCard from '@ahmad-chebbo/tessera-ui/Components/StatCard.vue';
import ChartCard from '@ahmad-chebbo/tessera-ui/Components/ChartCard.vue';
import BarChart from '@ahmad-chebbo/tessera-ui/Components/BarChart.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';
import PeriodPresets from '@ahmad-chebbo/tessera-ui/Components/PeriodPresets.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import ExpensesTabs from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpensesTabs.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    statement: Object,
    filters: Object,
    presets: Array,
    lookups: Object,
});

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const branchId = ref(props.filters.branch_id || '');

watch([dateFrom, dateTo, branchId], () => {
    router.get(route('expenses.statement'), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        branch_id: branchId.value || undefined,
    }, { preserveState: true, replace: true });
});

const monthLabel = (month) => new Date(`${month}-01T00:00:00`).toLocaleDateString(document.documentElement.lang || 'en', { month: 'short', year: '2-digit' });

const columns = computed(() => [
    { key: 'month', label: t('expenses.month') },
    { key: 'revenue', label: t('expenses.revenue') },
    { key: 'discounts', label: t('expenses.discounts') },
    { key: 'cogs', label: t('expenses.cogs') },
    { key: 'gross_profit', label: t('expenses.grossProfit') },
    { key: 'expenses', label: t('expenses.expensesLine') },
    { key: 'net_profit', label: t('expenses.netProfit') },
]);

const rows = computed(() => [...props.statement.months, { month: null, ...props.statement.totals }]);
const tone = (value) => (value >= 0 ? 'text-status-success' : 'text-status-danger');
</script>

<template>
    <Head :title="t('expenses.statementTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('expenses.statementTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('expenses.statementSubtitle') }}</p>
            </div>
        </template>

        <div class="mb-6">
            <ExpensesTabs current="expenses.statement" />
        </div>

        <div class="mb-6 space-y-3">
            <PeriodPresets v-if="presets.length" :presets="presets" :from="dateFrom" :to="dateTo" @select="({ from, to }) => { dateFrom = from; dateTo = to; }" />
            <div class="flex flex-wrap items-center gap-3">
                <DatePicker v-model="dateFrom" :max="dateTo || null" class="w-44" />
                <DatePicker v-model="dateTo" :min="dateFrom || null" class="w-44" />
                <Select v-if="lookups.branches.length > 1" v-model="branchId" class="w-48">
                    <option value="">{{ t('expenses.allBranches') }}</option>
                    <option v-for="branch in lookups.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </Select>
            </div>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard :label="t('expenses.netSales')" :value="formatCurrency(statement.totals.net_sales)" />
            <StatCard :label="t('expenses.grossProfit')" :value="formatCurrency(statement.totals.gross_profit)" />
            <StatCard :label="t('expenses.expensesCard')" :value="formatCurrency(statement.totals.expenses)" />
            <StatCard :label="t('expenses.netProfit')" :value="formatCurrency(statement.totals.net_profit)" />
        </div>

        <ChartCard class="mb-6" :title="t('expenses.netProfit')" :description="t('expenses.statementNote')" :empty="!statement.months.length">
            <BarChart
                :labels="statement.months.map((row) => monthLabel(row.month))"
                :datasets="[
                    { label: t('expenses.grossProfit'), data: statement.months.map((row) => row.gross_profit) },
                    { label: t('expenses.netProfit'), data: statement.months.map((row) => row.net_profit), color: 'success' },
                ]"
                :format="formatCurrency"
                :label="t('expenses.netProfit')"
            />
        </ChartCard>

        <DataTable :columns="columns" :rows="rows" :empty-title="t('expenses.noReport')">
            <template #cell-month="{ row }"><span :class="row.month ? '' : 'font-semibold text-fg'">{{ row.month ? monthLabel(row.month) : t('expenses.totals') }}</span></template>
            <template #cell-revenue="{ row }">{{ formatCurrency(row.revenue) }}</template>
            <template #cell-discounts="{ row }">{{ formatCurrency(row.discounts) }}</template>
            <template #cell-cogs="{ row }">{{ formatCurrency(row.cogs) }}</template>
            <template #cell-gross_profit="{ row }"><span :class="tone(row.gross_profit)">{{ formatCurrency(row.gross_profit) }}</span></template>
            <template #cell-expenses="{ row }">{{ formatCurrency(row.expenses) }}</template>
            <template #cell-net_profit="{ row }"><span class="font-semibold" :class="tone(row.net_profit)">{{ formatCurrency(row.net_profit) }}</span></template>
        </DataTable>
    </SidebarLayout>
</template>
