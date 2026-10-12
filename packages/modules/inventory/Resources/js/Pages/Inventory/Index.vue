<script setup>
import ExportMenu from '@/Components/ExportMenu.vue';
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
// import BranchFilter from '@/Components/BranchFilter.vue';
import { useI18n } from 'vue-i18n';
import debounce from 'lodash/debounce';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { AlignLeft, ArrowLeftRight, ClipboardList, Package } from 'lucide-vue-next';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    products: Object,
    locations: Array,
    selectedLocationId: [Number, String],
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'productName', label: t('products.productName') },
    { key: 'sku', label: t('pos.sku'), cellClass: 'text-muted' },
    { key: 'category', label: t('products.category'), cellClass: 'text-muted' },
    { key: 'currentStock', label: t('inventory.currentStock') },
    { key: 'status', label: t('common.status') },
]);
const { mode: viewMode, setMode: setViewMode } = useViewMode('inventory');

const locationTabs = computed(() => [
    { value: 'all', label: t('common.all') },
    ...(props.locations ?? []).map((location) => ({ value: location.id, label: `${location.name} (${location.type})` })),
]);

const search = ref(props.filters?.search || '');
const locationId = ref(props.selectedLocationId || 'all');
const branch = ref(props.filters?.branch_id ?? '');

const applyFilters = debounce(() => {
    router.get(route('inventory.index'), {
        search: search.value || undefined,
        location_id: locationId.value || undefined,
        branch_id: branch.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([search, locationId], applyFilters);
watch(branch, () => {
    locationId.value = 'all';
    applyFilters();
});

const getStockStatus = (stock, minStock) => {
    if (stock <= 0) return { label: t('pos.outOfStock'), class: 'bg-status-danger-wash text-status-danger' };
    if (stock <= minStock) return { label: t('pos.lowStock'), class: 'bg-status-warn-wash text-status-warn' };
    return { label: t('pos.inStock'), class: 'bg-status-success-wash text-status-success' };
};
</script>

<template>
    <Head :title="t('inventory.title')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('inventory.title') }}
                </h1>
            </div>
        </template>

        <div class="mb-6 flex items-center justify-between gap-4 flex-col sm:flex-row">
            <div class="flex flex-1 gap-4">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('common.search')"
                    class="block w-full max-w-xs rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
                />
            </div>

            <ExportMenu report="stock-levels" :filters="{ search, location_id: locationId && locationId !== 'all' ? locationId : '', branch_id: branch }" />

            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('inventory.movements')"
                class="inline-flex items-center gap-2 rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted transition-colors hover:bg-line"
            >
                <ClipboardList class="h-5 w-5" />
                {{ t('inventory.stockMovements') }}
            </Link>
            <Link
                :href="route('inventory.adjust')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <!-- Adjustment/Settings icon (slider controls) -->
                <AlignLeft class="h-5 w-5" />
                {{ t('inventory.adjustment') }}
            </Link>
            <Link
                :href="route('inventory.transfer')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <ArrowLeftRight :size="20" />
                {{ t('transfers.transfer') }}
            </Link>
        </div>

        <div class="mb-6 flex flex-wrap items-center gap-3">
            <!-- <BranchFilter v-model="branch" /> -->
            <FilterTabs v-model="locationId" :tabs="locationTabs" />
        </div>

        <!-- Inventory Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="products.data"
            :empty-title="t('products.noProducts')"
            :paginator="products"
        >
            <template #cell-productName="{ row: product }">
                <div class="flex items-center gap-3">
                    <img :src="product.image_url" :alt="product.name"
                        class="h-10 w-10 rounded-lg object-cover" />
                    <div class="font-medium text-fg">
                        {{ product.name }}
                    </div>
                </div>
            </template>
            <template #cell-sku="{ row: product }">
                {{ product.sku || '-' }}
            </template>
            <template #cell-category="{ row: product }">
                {{ product.category?.name || '-' }}
            </template>
            <template #cell-currentStock="{ row: product }">
                <span class="text-lg font-semibold text-fg me-2">
                    {{ product.stock ?? 0 }}
                </span>
                <span class="text-sm text-muted">
                    {{ product.unit }}
                </span>
            </template>
            <template #cell-status="{ row: product }">
                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                    :class="getStockStatus(product.stock ?? 0, product.min_stock ?? 5).class">
                    {{ getStockStatus(product.stock ?? 0, product.min_stock ?? 5).label }}
                </span>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="products.data"
            :empty-title="t('products.noProducts')"
            :paginator="products"
        >
            <template #item="{ item: product }">
                <GridCard>
                    <template #media>
                        <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" loading="lazy" />
                        <div v-else class="flex h-full w-full items-center justify-center text-faint">
                            <Package class="h-10 w-10" />
                        </div>
                    </template>
                    <template #title>{{ product.name }}</template>
                    <template #subtitle>{{ product.category?.name || '-' }}</template>
                    <template #badge>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="getStockStatus(product.stock ?? 0, product.min_stock ?? 5).class">
                            {{ getStockStatus(product.stock ?? 0, product.min_stock ?? 5).label }}
                        </span>
                    </template>
                    <template #meta>
                        <span class="font-display text-2xl text-fg">{{ product.stock ?? 0 }}</span>
                        <span class="ms-1 text-muted">{{ product.unit }}</span>
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
