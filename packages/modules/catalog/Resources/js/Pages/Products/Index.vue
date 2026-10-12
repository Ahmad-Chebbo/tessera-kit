<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/Composables/useCurrency';
import debounce from 'lodash/debounce';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Package, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@ahmad-chebbo/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@ahmad-chebbo/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@ahmad-chebbo/tessera-ui/Composables/useViewMode';
import FilterTabs from '@ahmad-chebbo/tessera-ui/Components/FilterTabs.vue';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import CardGrid from '@ahmad-chebbo/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    products: Object,
    categories: Array,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'productName', label: t('products.productName') },
    { key: 'category', label: t('products.category'), cellClass: 'text-muted' },
    { key: 'sku', label: t('pos.sku'), cellClass: 'text-muted' },
    { key: 'price', label: t('common.price') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('products');

const categoryTabs = computed(() => [
    { value: '', label: t('pos.allCategories') },
    ...props.categories.map((category) => ({ value: category.id, label: category.name })),
]);

const statusTabs = computed(() => [
    { value: '', label: t('common.all') },
    { value: 'active', label: t('products.active') },
    { value: 'inactive', label: t('products.inactive') },
]);
const { formatCurrency } = useCurrency();

const search = ref(props.filters?.search || '');
const categoryId = ref(props.filters?.category_id || '');
const status = ref(props.filters?.status || '');

const applyFilters = debounce(() => {
    router.get(route('products.index'), {
        search: search.value || undefined,
        category_id: categoryId.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([search, categoryId, status], applyFilters);

const deleteProduct = async (product) => {
    if (await confirm.delete()) {
        router.delete(route('products.destroy', product.id));
    }
};
</script>

<template>
    <Head :title="t('products.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('products.title') }}
                </h1>
                 <p class="text-sm text-muted">
                    {{ t('products.manageProducts') }}
                </p>
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
             <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
             <Link
                    :href="route('products.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                >
                    <Plus class="h-5 w-5" />
                    {{ t('products.addProduct') }}
                </Link>
        </div>

        <div class="mb-6 flex flex-col gap-2">
            <FilterTabs v-model="categoryId" :tabs="categoryTabs" />
            <FilterTabs v-model="status" :tabs="statusTabs" />
        </div>

        <!-- Products Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="products.data"
            :empty-title="t('products.noProducts')"
            :paginator="products"
        >
            <template #cell-productName="{ row: product }">
                <div class="flex items-center gap-3">
                    <img
                        :src="product.image_url"
                        :alt="product.name"
                        class="h-10 w-10 rounded-lg object-cover"
                    />
                    <div>
                        <div class="font-medium text-fg">{{ product.name }}</div>
                        <div class="text-sm text-muted">{{ product.barcode }}</div>
                    </div>
                </div>
            </template>
            <template #cell-category="{ row: product }">
                {{ product.category?.name || '-' }}
            </template>
            <template #cell-sku="{ row: product }">
                {{ product.sku || '-' }}
            </template>
            <template #cell-price="{ row: product }">
                <div class="font-medium text-fg">
                    {{ formatCurrency(product.base_price, product.currency) }}
                </div>
                <div v-if="product.sale_price" class="text-sm text-status-success">
                    {{ t('products.salePrice') }}: {{ formatCurrency(product.sale_price, product.currency) }}
                </div>
            </template>
            <template #cell-status="{ row: product }">
                <span
                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                    :class="product.is_active
                        ? 'bg-status-success-wash text-status-success'
                        : 'bg-status-danger-wash text-status-danger'"
                >
                    {{ product.is_active ? t('products.active') : t('products.inactive') }}
                </span>
            </template>
            <template #cell-actions="{ row: product }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('products.edit', product.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteProduct(product)" />
                </div>
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
                        <StatusBadge :status="product.is_active ? 'success' : 'danger'">
                            {{ product.is_active ? t('products.active') : t('products.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <div class="font-medium text-fg">{{ formatCurrency(product.base_price, product.currency) }}</div>
                        <div v-if="product.sale_price" class="text-status-success">
                            {{ t('products.salePrice') }}: {{ formatCurrency(product.sale_price, product.currency) }}
                        </div>
                    </template>
                    <template #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('products.edit', product.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteProduct(product)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>

    </SidebarLayout>
</template>
