<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { FileText, Paperclip, Pencil, Plus, Repeat, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatCard from '@tessera/tessera-ui/Components/StatCard.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import ExportMenu from '@/Components/ExportMenu.vue';
import ExpensesTabs from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpensesTabs.vue';
import ExpenseFilters from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpenseFilters.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    expenses: Object,
    summary: Object,
    filters: Object,
    presets: Array,
    lookups: Object,
    can: Object,
});

const { t } = useI18n();
const confirm = useConfirm();
const { formatCurrency } = useCurrency();

const columns = computed(() => [
    { key: 'expense_date', label: t('expenses.date'), cellClass: 'text-sm text-muted' },
    { key: 'description', label: t('expenses.description') },
    { key: 'category', label: t('expenses.category') },
    { key: 'branch', label: t('expenses.branch'), cellClass: 'text-sm text-muted' },
    { key: 'amount', label: t('expenses.amount') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const exportFilters = computed(() => ({ ...props.filters }));

const remove = async (expense) => {
    if (await confirm.delete(t('expenses.confirmDelete'))) {
        router.delete(route('expenses.destroy', expense.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('expenses.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('expenses.title') }}</h1>
                <p class="text-sm text-muted">{{ t('expenses.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <ExpensesTabs current="expenses.index" />
            <div class="flex items-center gap-2">
                <ExportMenu report="expenses" :filters="exportFilters" />
                <Link
                    v-if="can.create"
                    :href="route('expenses.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('expenses.add') }}
                </Link>
            </div>
        </div>

        <ExpenseFilters
            class="mb-6"
            route-name="expenses.index"
            :filters="filters"
            :presets="presets"
            :categories="lookups.categories"
            :branches="lookups.branches"
            :types="lookups.types"
            :employees="lookups.employees"
            searchable
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <StatCard :label="t('expenses.total')" :value="formatCurrency(summary.total)" />
            <StatCard :label="t('expenses.counted')" :value="formatCurrency(summary.counted)" />
            <StatCard :label="t('expenses.entries')" :value="summary.count" />
        </div>

        <DataTable :columns="columns" :rows="expenses.data" :empty-title="t('expenses.noExpenses')" :paginator="expenses">
            <template #cell-description="{ row }">
                <p class="font-medium text-fg">{{ row.description }}</p>
                <p class="flex flex-wrap items-center gap-2 text-xs text-muted">
                    <span v-if="row.reference">{{ row.reference }}</span>
                    <span v-if="row.employee">{{ row.employee.name }}</span>
                    <span v-if="row.spread_months" class="inline-flex items-center gap-1">{{ t('expenses.spreadOn', { months: row.spread_months }) }}</span>
                    <span v-if="row.from_recurring" class="inline-flex items-center gap-1"><Repeat class="h-3 w-3" />{{ t('expenses.fromRecurring') }}</span>
                    <a v-if="row.receipt_url" :href="row.receipt_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-accent hover:underline">
                        <Paperclip class="h-3 w-3" />{{ t('expenses.viewReceipt') }}
                    </a>
                </p>
            </template>
            <template #cell-category="{ row }">
                <div v-if="row.category" class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-fg">{{ row.category.name }}</span>
                    <StatusBadge :status="row.category.affects_profit ? 'success' : 'neutral'">
                        {{ row.category.affects_profit ? t('expenses.yes') : t('expenses.no') }}
                    </StatusBadge>
                </div>
            </template>
            <template #cell-branch="{ row }">{{ row.branch?.name ?? t('expenses.noBranch') }}</template>
            <template #cell-amount="{ row }"><span class="font-medium text-fg">{{ formatCurrency(row.amount) }}</span></template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton v-if="can.edit" :icon="Pencil" :label="t('common.edit')" :href="route('expenses.edit', row.id)" />
                    <IconButton v-if="can.delete" :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>
    </SidebarLayout>
</template>
