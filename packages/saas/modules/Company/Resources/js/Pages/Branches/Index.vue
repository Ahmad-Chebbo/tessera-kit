<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Pencil, Phone, Plus, Trash2 } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@ahmad-chebbo/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@ahmad-chebbo/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@ahmad-chebbo/tessera-ui/Composables/useViewMode';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import CardGrid from '@ahmad-chebbo/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    branches: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'name', label: t('branches.name') },
    { key: 'address', label: t('branches.address'), cellClass: 'text-muted', wrap: true },
    { key: 'phone', label: t('branches.phone'), cellClass: 'text-muted' },
    { key: 'users', label: t('branches.users') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('branches');

const deleteBranch = async (branch) => {
    if (await confirm.delete()) {
        router.delete(route('branches.destroy', branch.id));
    }
};
</script>

<template>
    <Head :title="t('branches.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('branches.title') }}
                </h1>
            </div>
        </template>

        <div class="mb-6 flex justify-end gap-4">
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('branches.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                {{ t('branches.addBranch') }}
            </Link>
        </div>

        <!-- Branches Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="branches.data"
            :empty-title="t('branches.noBranches')"
            :paginator="branches"
        >
            <template #cell-name="{ row: branch }">
                <span class="font-medium text-fg">{{ branch.name }}</span>
            </template>
            <template #cell-address="{ row: branch }">
                {{ branch.address || '-' }}
            </template>
            <template #cell-phone="{ row: branch }">
                {{ branch.phone || '-' }}
            </template>
            <template #cell-users="{ row: branch }">
                <span
                    class="inline-flex items-center rounded-full bg-accent-wash px-2.5 py-0.5 text-xs font-medium text-accent">
                    {{ branch.users_count || 0 }}
                </span>
            </template>
            <template #cell-status="{ row: branch }">
                <span
                    :class="branch.is_active
                        ? 'inline-flex items-center rounded-full bg-status-success-wash px-2.5 py-0.5 text-xs font-medium text-status-success'
                        : 'inline-flex items-center rounded-full bg-hover-wash px-2.5 py-0.5 text-xs font-medium text-fg'">
                    {{ branch.is_active ? t('common.active') : t('common.inactive') }}
                </span>
            </template>
            <template #cell-actions="{ row: branch }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('branches.edit', branch.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteBranch(branch)" :disabled="branch.users_count > 0" :class="{ 'cursor-not-allowed opacity-50': branch.users_count > 0 }" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="branches.data"
            :empty-title="t('branches.noBranches')"
            :paginator="branches"
        >
            <template #item="{ item: branch }">
                <GridCard>
                    <template #title>{{ branch.name }}</template>
                    <template #subtitle>{{ branch.address || '-' }}</template>
                    <template #badge>
                        <StatusBadge :status="branch.is_active ? 'success' : 'neutral'">
                            {{ branch.is_active ? t('common.active') : t('common.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <div class="flex items-center gap-2 text-muted"><Phone class="h-3.5 w-3.5 shrink-0" />{{ branch.phone || '-' }}</div>
                        <div class="text-muted">{{ t('nav.users') }}: {{ branch.users_count || 0 }}</div>
                    </template>
                    <template #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('branches.edit', branch.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteBranch(branch)" :disabled="branch.users_count > 0" :class="{ 'cursor-not-allowed opacity-50': branch.users_count > 0 }" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
