<script setup>
import ExportMenu from '@/Components/ExportMenu.vue';
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import BranchFilter from '@/Components/BranchFilter.vue';
import { useI18n } from 'vue-i18n';
import debounce from 'lodash/debounce';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import ViewToggle from '@ahmad-chebbo/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@ahmad-chebbo/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@ahmad-chebbo/tessera-ui/Composables/useViewMode';
import FilterTabs from '@ahmad-chebbo/tessera-ui/Components/FilterTabs.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import CardGrid from '@ahmad-chebbo/tessera-ui/Components/CardGrid.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';

const props = defineProps({
    movements: Object,
    locations: Array,
    movementTypes: Array,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'date', label: t('common.date'), cellClass: 'text-sm text-muted' },
    { key: 'productName', label: t('products.productName') },
    { key: 'location', label: t('inventory.location'), cellClass: 'text-muted' },
    { key: 'movementType', label: t('inventory.movementType') },
    { key: 'quantity', label: t('common.quantity') },
    { key: 'reference', label: t('inventory.reference'), cellClass: 'text-sm text-muted' },
]);
const { mode: viewMode, setMode: setViewMode } = useViewMode('movements');

const typeTabs = computed(() => [{ value: '', label: t('common.all') }, ...(props.movementTypes ?? [])]);

const locationId = ref(props.filters?.location_id || '');
const branch = ref(props.filters?.branch_id ?? '');
const type = ref(props.filters?.type || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const applyFilters = debounce(() => {
    router.get(route('inventory.movements'), {
        location_id: locationId.value || undefined,
        branch_id: branch.value || undefined,
        type: type.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([locationId, type, dateFrom, dateTo], applyFilters);
watch(branch, () => {
    locationId.value = '';
    applyFilters();
});

const getTypeClass = (type) => {
    const classes = {
        purchase: 'bg-status-success-wash text-status-success',
        sale: 'bg-status-info-wash text-status-info',
        transfer_in: 'bg-status-info-wash text-status-info',
        transfer_out: 'bg-status-warn-wash text-status-warn',
        adjustment: 'bg-status-warn-wash text-status-warn',
        return: 'bg-status-danger-wash text-status-danger',
    };
    return classes[type] || 'bg-hover-wash text-fg';
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <Head :title="t('inventory.stockMovements')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h2 class="font-display text-xl font-normal leading-tight text-fg">
                    {{ t('inventory.stockMovements') }}
                </h2>
            </div>
        </template>

        <div class="mb-4 flex justify-end print:hidden">
            <ExportMenu report="stock-movements" :filters="{ location_id: locationId, branch_id: branch, type, date_from: dateFrom, date_to: dateTo, product_id: filters?.product_id }" />
        </div>

        <div class="mb-6 flex items-center justify-between gap-4 flex-col sm:flex-row">
            <div class="flex flex-1 gap-4">
                <BranchFilter v-model="branch" />
                <Select v-model="locationId"
                    class="w-full max-w-xs">
                    <option value="">{{ t('common.all') }} {{ t('inventory.location') }}</option>
                    <option v-for="location in locations" :key="location.id" :value="location.id">
                        {{ location.name }}
                    </option>
                </Select>

                <DatePicker v-model="dateFrom" :placeholder="t('reports.fromDate')" :max="dateTo || null" class="w-full max-w-xs" />

                <DatePicker v-model="dateTo" :placeholder="t('reports.toDate')" :min="dateFrom || null" class="w-full max-w-xs" />
            </div>
        </div>


         <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <FilterTabs v-model="type" :tabs="typeTabs" />
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            </div>

        <!-- Movements Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="movements.data"
            :empty-title="t('common.noResults')"
            :paginator="movements"
        >
            <template #cell-date="{ row: movement }">
                {{ formatDate(movement.created_at) }}
            </template>
            <template #cell-productName="{ row: movement }">
                <span class="font-medium text-fg">
                    {{ movement.product?.name || '-' }}
                </span>
            </template>
            <template #cell-location="{ row: movement }">
                {{ movement.location?.name || '-' }}
            </template>
            <template #cell-movementType="{ row: movement }">
                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                    :class="getTypeClass(movement.type)">
                    {{ movement.type.replace('_', ' ') }}
                </span>
            </template>
            <template #cell-quantity="{ row: movement }">
                <span class="font-semibold"
                    :class="movement.quantity > 0 ? 'text-status-success' : 'text-status-danger'">
                    {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                </span>
            </template>
            <template #cell-reference="{ row: movement }">
                {{ movement.reference || '-' }}
            </template>
        </DataTable>
        <CardGrid v-else
            :items="movements.data"
            :empty-title="t('common.noResults')"
            :paginator="movements"
        >
            <template #item="{ item: movement }">
                <GridCard>
                    <template #title>{{ movement.product?.name || '-' }}</template>
                    <template #subtitle>{{ movement.location?.name || '-' }}</template>
                    <template #badge>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="getTypeClass(movement.type)">
                            {{ movement.type.replace('_', ' ') }}
                        </span>
                    </template>
                    <template #meta>
                        <div class="font-display text-2xl" :class="movement.quantity > 0 ? 'text-status-success' : 'text-status-danger'">
                            {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                        </div>
                        <div class="text-muted">{{ movement.reference || '-' }}</div>
                        <div class="text-muted">{{ formatDate(movement.created_at) }}</div>
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
