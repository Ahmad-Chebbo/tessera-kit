<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@ahmad-chebbo/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@ahmad-chebbo/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@ahmad-chebbo/tessera-ui/Composables/useViewMode';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import CardGrid from '@ahmad-chebbo/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    locations: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'name', label: t('locations.name') },
    { key: 'branch', label: t('locations.branch'), cellClass: 'text-muted' },
    { key: 'type', label: t('locations.type') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('locations');

const deleteLocation = async (location) => {
    if (await confirm.delete()) {
        router.delete(route('locations.destroy', location.id));
    }
};
</script>

<template>
    <Head :title="t('locations.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('locations.title') }}
                </h1>
            </div>
        </template>

        <div class="mb-6 flex justify-end gap-4">
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('locations.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                {{ t('locations.addLocation') }}
            </Link>
        </div>

        <!-- Locations Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="locations.data"
            :empty-title="t('locations.noLocations')"
            :paginator="locations"
        >
            <template #cell-name="{ row: location }">
                <span class="font-medium text-fg">{{ location.name }}</span>
            </template>
            <template #cell-branch="{ row: location }">
                {{ location.branch?.name || '-' }}
            </template>
            <template #cell-type="{ row: location }">
                <span
                    class="inline-flex items-center rounded-full bg-status-info-wash px-2.5 py-0.5 text-xs font-medium text-status-info">
                    {{ location.type === 'warehouse' ? t('locations.warehouse') : t('locations.branch') }}
                </span>
            </template>
            <template #cell-status="{ row: location }">
                <span
                    :class="location.is_active
                        ? 'inline-flex items-center rounded-full bg-status-success-wash px-2.5 py-0.5 text-xs font-medium text-status-success'
                        : 'inline-flex items-center rounded-full bg-hover-wash px-2.5 py-0.5 text-xs font-medium text-fg'">
                    {{ location.is_active ? t('common.active') : t('common.inactive') }}
                </span>
            </template>
            <template #cell-actions="{ row: location }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('locations.edit', location.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteLocation(location)" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="locations.data"
            :empty-title="t('locations.noLocations')"
            :paginator="locations"
        >
            <template #item="{ item: location }">
                <GridCard>
                    <template #title>{{ location.name }}</template>
                    <template #subtitle>{{ location.branch?.name || '-' }}</template>
                    <template #badge>
                        <StatusBadge :status="location.is_active ? 'success' : 'neutral'">
                            {{ location.is_active ? t('common.active') : t('common.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <StatusBadge status="info">
                            {{ location.type === 'warehouse' ? t('locations.warehouse') : t('locations.branch') }}
                        </StatusBadge>
                    </template>
                    <template #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('locations.edit', location.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteLocation(location)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
