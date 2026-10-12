
<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import debounce from 'lodash/debounce';
import { Mail, Pencil, Phone, Plus, Trash2, Truck } from 'lucide-vue-next';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    suppliers: Object,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'name', label: t('common.name') },
    { key: 'email', label: t('common.email'), cellClass: 'text-muted' },
    { key: 'phone', label: t('common.phone'), cellClass: 'text-muted' },
    { key: 'col3', label: 'Contact Person', cellClass: 'text-muted' },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('suppliers');

const search = ref(props.filters?.search || '');

const applyFilters = debounce(() => {
    router.get(route('suppliers.index'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([search], applyFilters);

const deleteSupplier = async (supplier) => {
    if (await confirm.delete()) {
        router.delete(route('suppliers.destroy', supplier.id));
    }
};
</script>

<template>
    <Head :title="t('nav.suppliers')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('nav.suppliers') }}
                </h1>
                <p class="text-sm text-muted">
                    Manage your suppliers
                </p>
            </div>
        </template>

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="w-full sm:w-96">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('common.search')"
                    class="block w-full max-w-xs rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
                />
            </div>
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('suppliers.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                Add Supplier
            </Link>
        </div>

        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="suppliers.data"
            :empty-title="t('suppliers.noSuppliers')"
            :paginator="suppliers"
        >
            <template #cell-name="{ row: supplier }">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-wash">
                        <Truck class="h-5 w-5 text-accent" />
                    </div>
                    <span class="font-medium text-fg">{{ supplier.name }}</span>
                </div>
            </template>
            <template #cell-email="{ row: supplier }">
                {{ supplier.email || '-' }}
            </template>
            <template #cell-phone="{ row: supplier }">
                {{ supplier.phone || '-' }}
            </template>
            <template #cell-col3="{ row: supplier }">
                {{ supplier.contact_person || '-' }}
            </template>
            <template #cell-actions="{ row: supplier }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('suppliers.edit', supplier.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteSupplier(supplier)" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="suppliers.data"
            :empty-title="t('suppliers.noSuppliers')"
            :paginator="suppliers"
        >
            <template #item="{ item: supplier }">
                <GridCard>
                    <template #title>{{ supplier.name }}</template>
                    <template #subtitle>{{ supplier.contact_person || '-' }}</template>
                    <template #meta>
                        <div class="flex items-center gap-2 text-muted"><Mail class="h-3.5 w-3.5 shrink-0" /><span class="truncate">{{ supplier.email || '-' }}</span></div>
                        <div class="flex items-center gap-2 text-muted"><Phone class="h-3.5 w-3.5 shrink-0" /><span class="truncate">{{ supplier.phone || '-' }}</span></div>
                    </template>
                    <template #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('suppliers.edit', supplier.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteSupplier(supplier)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
