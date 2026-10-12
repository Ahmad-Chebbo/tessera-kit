<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/Composables/useCurrency';
import debounce from 'lodash/debounce';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    customers: Object,
    filters: Object,
    ordersAvailable: { type: Boolean, default: true },
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'name', label: t('common.name'), cellClass: 'font-medium text-fg', wrap: true },
    { key: 'phone', label: t('common.phone'), cellClass: 'text-muted', wrap: true },
    { key: 'email', label: t('common.email'), cellClass: 'text-muted', wrap: true },
    ...(props.ordersAvailable ? [
        { key: 'title', label: t('orders.title'), cellClass: 'text-muted', wrap: true },
        { key: 'totalDebt', label: t('customers.totalDebt'), cellClass: 'font-medium', wrap: true },
    ] : []),
    { key: 'status', label: t('common.status'), wrap: true },
    { key: 'actions', label: t('common.actions'), align: 'end', wrap: true },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('customers');

const statusTabs = computed(() => [
    { value: '', label: t('common.all') },
    { value: 'active', label: t('common.active') },
    { value: 'inactive', label: t('common.inactive') },
]);
const { formatCurrency } = useCurrency();

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const applyFilters = debounce(() => {
    router.get(route('customers.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, status], applyFilters);

const deleteCustomer = async (customerId) => {
    if (!await confirm.delete(t('customers.confirmDelete'))) return;
    router.delete(route('customers.destroy', customerId));
};
</script>

<template>
    <Head :title="t('customers.title')" />

    <SidebarLayout>
        <template #header>
            <h2 class="font-display text-xl font-normal leading-tight text-fg">
                {{ t('customers.title') }}
            </h2>
        </template>

        <!-- Filters + Add Button -->
        <div class="mb-6 flex items-center gap-3">
            <input v-model="search" type="text" :placeholder="t('common.search')"
                class="block w-full max-w-xs rounded-2xl border border-line bg-surface p-2.5 text-sm" />
            <div class="ms-auto flex items-center gap-2">
                <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
                <Link :href="route('customers.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink hover:bg-gold-deep">
                    <Plus class="h-4 w-4" />
                    {{ t('customers.addCustomer') }}
                </Link>
            </div>
        </div>

        <div class="mb-6 flex flex-col gap-2">
            <FilterTabs v-model="status" :tabs="statusTabs" />
        </div>

        <DataTable
            v-if="viewMode === 'table'"
                :columns="tableColumns"
                :rows="customers.data"
                :empty-title="t('customers.noCustomers')"
                :paginator="customers"
            >
                <template #cell-name="{ row: customer }">
                    <Link :href="route('customers.show', customer.id)" class="hover:underline">
                        {{ customer.name }}
                    </Link>
                </template>
                <template #cell-phone="{ row: customer }">
                    {{ customer.phone || '-' }}
                </template>
                <template #cell-email="{ row: customer }">
                    {{ customer.email || '-' }}
                </template>
                <template #cell-title="{ row: customer }">
                    {{ customer.orders_count }}
                </template>
                <template #cell-totalDebt="{ row: customer }">
                    <span :class="customer.total_debt > 0 ? 'text-status-danger' : 'text-muted'">{{ formatCurrency(customer.total_debt || 0) }}</span>
                </template>
                <template #cell-status="{ row: customer }">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        :class="customer.is_active ? 'bg-status-success-wash text-status-success' : 'bg-hover-wash text-fg'">
                        {{ customer.is_active ? t('common.active') : t('common.inactive') }}
                    </span>
                </template>
                <template #cell-actions="{ row: customer }">
                    <div class="flex items-center justify-end gap-3">
                        <IconButton :icon="Eye" :label="t('common.view')" :href="route('customers.show', customer.id)" />
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('customers.edit', customer.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" type="button" @click="deleteCustomer(customer.id)" />
                    </div>
                </template>
        </DataTable>
        <CardGrid v-else
            :items="customers.data"
            :empty-title="t('customers.noCustomers')"
            :paginator="customers"
        >
            <template #item="{ item: customer }">
                <GridCard>
                    <template #title>
                        <Link :href="route('customers.show', customer.id)" class="hover:underline">{{ customer.name }}</Link>
                    </template>
                    <template #subtitle>{{ customer.email || customer.phone || '-' }}</template>
                    <template #badge>
                        <StatusBadge :status="customer.is_active ? 'success' : 'neutral'">
                            {{ customer.is_active ? t('common.active') : t('common.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <div class="text-muted">{{ customer.phone || '-' }}</div>
                        <template v-if="ordersAvailable">
                            <div class="text-muted">{{ t('orders.title') }}: {{ customer.orders_count }}</div>
                            <div class="font-medium" :class="customer.total_debt > 0 ? 'text-status-danger' : 'text-muted'">
                                {{ formatCurrency(customer.total_debt || 0) }}
                            </div>
                        </template>
                    </template>
                    <template #actions>
                        <IconButton :icon="Eye" :label="t('common.view')" :href="route('customers.show', customer.id)" />
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('customers.edit', customer.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" type="button" @click="deleteCustomer(customer.id)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
