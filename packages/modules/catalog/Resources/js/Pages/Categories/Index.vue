<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Pencil, Plus, Tag, Trash2 } from 'lucide-vue-next';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    categories: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'categoryName', label: t('categories.categoryName') },
    { key: 'parentCategory', label: t('categories.parentCategory'), cellClass: 'text-muted' },
    { key: 'title', label: t('products.title') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('categories');

const deleteCategory = async (category) => {
    if (await confirm.delete()) {
        router.delete(route('categories.destroy', category.id));
    }
};
</script>

<template>
    <Head :title="t('categories.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('categories.title') }}
                </h1>
                <!--  <Link
                    :href="route('categories.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                >
                    <Plus class="h-5 w-5" />
                    {{ t('categories.addCategory') }}
                </Link> -->
            </div>
        </template>

        <div class="flex justify-end gap-4 mb-6">
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('categories.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                 {{ t('categories.addCategory') }}
            </Link>
        </div>

        <!-- Categories Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="categories.data"
            :empty-title="t('categories.noCategories')"
            :paginator="categories"
        >
            <template #cell-categoryName="{ row: category }">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg"
                        :style="{ backgroundColor: category.color || 'var(--accent-fill)' }">
                        <span v-if="category.icon" class="text-lg text-white">{{ category.icon }}</span> <!-- allow-colour: icon over user-chosen colour -->
                        <Tag v-else class="h-5 w-5 text-white" /> <!-- allow-colour: icon over user-chosen colour -->
                    </div>
                    <span class="font-medium text-fg">{{ category.name }}</span>
                </div>
            </template>
            <template #cell-parentCategory="{ row: category }">
                {{ category.parent?.name || '-' }}
            </template>
            <template #cell-title="{ row: category }">
                <span
                    class="inline-flex items-center rounded-full bg-accent-wash px-2.5 py-0.5 text-xs font-medium text-accent">
                    {{ category.products_count || 0 }}
                </span>
            </template>
            <template #cell-actions="{ row: category }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('categories.edit', category.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteCategory(category)" :disabled="category.products_count > 0" :class="{ 'cursor-not-allowed opacity-50': category.products_count > 0 }" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="categories.data"
            :empty-title="t('categories.noCategories')"
            :paginator="categories"
        >
            <template #item="{ item: category }">
                <GridCard>
                    <template #title>
                        <span class="me-2 inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: category.color || 'var(--accent-fill)' }"></span>{{ category.icon }} {{ category.name }}
                    </template>
                    <template #subtitle>{{ category.parent?.name || '-' }}</template>
                    <template #badge>
                        <StatusBadge status="info">{{ category.products_count || 0 }}</StatusBadge>
                    </template>
                    <template #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('categories.edit', category.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteCategory(category)" :disabled="category.products_count > 0" :class="{ 'cursor-not-allowed opacity-50': category.products_count > 0 }" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
