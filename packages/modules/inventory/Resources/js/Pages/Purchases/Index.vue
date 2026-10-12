<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BranchFilter from '@/Components/BranchFilter.vue';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { PackageCheck, Plus } from 'lucide-vue-next';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    purchases: Object,
    suppliers: Array,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'referenceNumber', label: t('purchases.referenceNumber'), cellClass: 'text-sm font-medium text-fg' },
    { key: 'supplier', label: t('purchases.supplier'), cellClass: 'text-sm text-muted' },
    { key: 'location', label: t('inventory.location'), cellClass: 'text-sm text-muted' },
    { key: 'total', label: t('common.total'), cellClass: 'text-sm font-medium text-fg' },
    { key: 'date', label: t('common.date'), cellClass: 'text-sm text-muted' },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('purchases');

const statusTabs = computed(() => [
    { value: '', label: t('purchases.allStatus') },
    { value: 'pending', label: t('transfers.pending') },
    { value: 'received', label: t('purchases.received') },
    { value: 'cancelled', label: t('transfers.cancelled') },
]);
const { formatCurrency } = useCurrency();

const status = ref(props.filters?.status || '');
const supplierId = ref(props.filters?.supplier_id || '');
const branch = ref(props.filters?.branch_id ?? '');

const applyFilters = () => {
    router.get(route('purchases.index'), {
        status: status.value || undefined,
        supplier_id: supplierId.value || undefined,
        branch_id: branch.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([status, supplierId, branch], applyFilters);

const receivePurchase = async (purchase) => {
    if (await confirm({ message: t('purchases.confirmReceive'), confirmText: t('purchases.receivePurchase') })) {
        router.post(route('purchases.receive', purchase.id));
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'received':
            return t('purchases.received');
        case 'pending':
            return t('transfers.pending');
        case 'cancelled':
            return t('transfers.cancelled');
        default:
            return status;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'received':
            return 'bg-status-success-wash text-status-success';
        case 'pending':
            return 'bg-status-warn-wash text-status-warn';
        case 'cancelled':
            return 'bg-status-danger-wash text-status-danger';
        default:
            return 'bg-hover-wash text-fg';
    }
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <Head :title="t('purchases.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('purchases.title') }}
                </h1>
                <p class="text-sm text-muted">
                    {{ t('purchases.managePurchases') }}
                </p>
            </div>
        </template>

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-4">
                <BranchFilter v-model="branch" />
                <Select
                    v-model="supplierId"
                    class="w-full max-w-xs">
                    <option value="">{{ t('purchases.allSuppliers') }}</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                        {{ supplier.name }}
                    </option>
                </Select>
            </div>
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('purchases.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                {{ t('purchases.createPurchase') }}
            </Link>
        </div>

        <div class="mb-6">
            <FilterTabs v-model="status" :tabs="statusTabs" />
        </div>

        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="purchases.data"
            :empty-title="t('purchases.noPurchases')"
            :paginator="purchases"
        >
            <template #cell-referenceNumber="{ row: purchase }">
                {{ purchase.reference_number || `PO-${purchase.id}` }}
            </template>
            <template #cell-supplier="{ row: purchase }">
                {{ purchase.supplier?.name || '-' }}
            </template>
            <template #cell-location="{ row: purchase }">
                {{ purchase.location?.name || '-' }}
            </template>
            <template #cell-total="{ row: purchase }">
                {{ formatCurrency(purchase.total, purchase.currency || 'USD') }}
            </template>
            <template #cell-date="{ row: purchase }">
                {{ formatDate(purchase.created_at) }}
            </template>
            <template #cell-status="{ row: purchase }">
                <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="getStatusClass(purchase.status)"
                >
                    {{ getStatusLabel(purchase.status) }}
                </span>
            </template>
            <template #cell-actions="{ row: purchase }">
                <IconButton
                    v-if="purchase.status === 'pending'"
                    :icon="PackageCheck"
                    :label="t('purchases.receivePurchase')"
                    @click="receivePurchase(purchase)"
                />
            </template>
        </DataTable>
        <CardGrid v-else
            :items="purchases.data"
            :empty-title="t('purchases.noPurchases')"
            :paginator="purchases"
        >
            <template #item="{ item: purchase }">
                <GridCard>
                    <template #title>{{ purchase.reference_number || `PO-${purchase.id}` }}</template>
                    <template #subtitle>{{ purchase.supplier?.name || '-' }}</template>
                    <template #badge>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getStatusClass(purchase.status)">
                            {{ getStatusLabel(purchase.status) }}
                        </span>
                    </template>
                    <template #meta>
                        <div class="font-display text-xl text-fg">{{ formatCurrency(purchase.total, purchase.currency || 'USD') }}</div>
                        <div class="text-muted">{{ purchase.location?.name || '-' }}</div>
                        <div class="text-muted">{{ formatDate(purchase.created_at) }}</div>
                    </template>
                    <template v-if="purchase.status === 'pending'" #actions>
                        <IconButton :icon="PackageCheck" :label="t('purchases.receivePurchase')" @click="receivePurchase(purchase)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
