<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';

defineProps({
    roles: Array,
});

const { t } = useI18n();
const confirm = useConfirm();

const columns = computed(() => [
    { key: 'name', label: t('roles.name') },
    { key: 'users_count', label: t('roles.employees') },
    { key: 'permissions_count', label: t('roles.permissions') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const remove = async (role) => {
    if (await confirm.delete(t('roles.confirmDelete'))) {
        router.delete(route('roles.destroy', role.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('roles.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('roles.title') }}</h1>
                <p class="text-sm text-muted">{{ t('roles.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex justify-end">
            <Link
                :href="route('roles.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-4 w-4" />
                {{ t('roles.addRole') }}
            </Link>
        </div>

        <DataTable :columns="columns" :rows="roles" :empty-title="t('roles.noRoles')">
            <template #cell-name="{ row }">
                <span class="font-medium text-fg">{{ row.name }}</span>
                <StatusBadge v-if="row.is_mine" status="info" class="ms-2">{{ t('roles.yourRole') }}</StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('roles.edit', row.id)" />
                    <IconButton
                        v-if="!row.is_mine"
                        :icon="Trash2"
                        :label="t('common.delete')"
                        variant="danger"
                        @click="remove(row)"
                    />
                </div>
            </template>
        </DataTable>
    </SidebarLayout>
</template>
