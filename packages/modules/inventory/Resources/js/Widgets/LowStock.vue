<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Pencil } from 'lucide-vue-next';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';

defineProps({
    data: { type: Array, default: () => [] },
});

const { t } = useI18n();

const columns = computed(() => [
    { key: 'productName', label: t('products.productName'), cellClass: 'text-sm font-medium text-fg' },
    { key: 'currentStock', label: t('inventory.currentStock'), cellClass: 'text-sm font-medium text-fg' },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
</script>

<template>
    <div class="mb-8 rounded-2xl border border-line bg-surface">
        <div class="border-b border-line p-6">
            <h3 class="font-display text-lg font-normal text-fg">
                Low Stock Products
            </h3>
        </div>

        <DataTable
            :columns="columns"
            :rows="data"
            :empty-title="t('dashboard.noLowStock')"
            :card="false"
        >
            <template #cell-productName="{ row: product }">
                {{ product.name }}
            </template>
            <template #cell-currentStock="{ row: product }">
                {{ product.stock }}
            </template>
            <template #cell-actions>
                <IconButton :icon="Pencil" :label="t('common.edit')" href="/inventory/adjust" target="_blank" />
            </template>
        </DataTable>
    </div>
</template>
