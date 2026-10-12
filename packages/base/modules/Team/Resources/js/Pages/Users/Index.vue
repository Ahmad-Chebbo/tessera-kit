<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    features: { type: Object, default: () => ({ branches: true, employeeProfiles: true, activityLog: true }) },
    users: Object,
    branches: Array,
    roles: Array,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'user', label: t('users.user') },
    ...(props.features.branches ? [{ key: 'branch', label: t('users.branch'), cellClass: 'text-sm text-muted' }] : []),
    { key: 'role', label: t('users.role') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('users');

const branchTabs = computed(() => [
    { value: '', label: t('users.allBranches') },
    ...props.branches.map((branch) => ({ value: branch.id, label: branch.name })),
]);

const roleTabs = computed(() => [
    { value: '', label: t('users.allRoles') },
    ...props.roles.map((r) => ({ value: r.name, label: r.name })),
]);

const search = ref(props.filters?.search || '');
const branchId = ref(props.filters?.branch_id || '');
const role = ref(props.filters?.role || '');

const applyFilters = () => {
    router.get(route('users.index'), {
        search: search.value || undefined,
        branch_id: branchId.value || undefined,
        role: role.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([branchId, role], applyFilters);

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

const deleteUser = async (user) => {
    if (await confirm.delete(t('users.confirmDelete'))) {
        router.delete(route('users.destroy', user.id));
    }
};
</script>

<template>
    <Head :title="t('users.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('users.title') }}
                </h1>
                <p class="text-sm text-muted">
                    {{ t('users.manageUsers') }}
                </p>
            </div>
        </template>

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('common.search')"
                    class="block w-full max-w-xs rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
                />
            </div>
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            <Link
                :href="route('users.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                {{ t('users.addUser') }}
            </Link>
        </div>

        <div class="mb-6 flex flex-col gap-2">
            <FilterTabs v-model="branchId" v-if="features.branches" :tabs="branchTabs" />
            <FilterTabs v-model="role" :tabs="roleTabs" />
        </div>

        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="users.data"
            :empty-title="t('users.noUsers')"
            :paginator="users"
        >
            <template #cell-user="{ row: user }">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gold text-sm font-medium text-ink">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <Link :href="route('users.show', user.id)" class="font-medium text-fg hover:text-accent">{{ user.name }}</Link>
                        <p class="text-sm text-muted">{{ features.employeeProfiles ? `${user.employee_profile?.employee_number ?? ''} · ` : '' }}{{ user.email }}</p>
                    </div>
                </div>
            </template>
            <template #cell-branch="{ row: user }">
                {{ user.branch?.name || '-' }}
            </template>
            <template #cell-role="{ row: user }">
                <span
                    v-if="user.roles?.length"
                    class="inline-flex rounded-full bg-accent-wash px-2.5 py-0.5 text-xs font-medium text-accent"
                >
                    {{ user.roles[0].name }}
                </span>
            </template>
            <template #cell-status="{ row: user }">
                <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="user.is_active
                        ? 'bg-status-success-wash text-status-success'
                        : 'bg-status-danger-wash text-status-danger'"
                >
                    {{ user.is_active ? t('common.active') : t('common.inactive') }}
                </span>
            </template>
            <template #cell-actions="{ row: user }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('users.edit', user.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteUser(user)" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="users.data"
            :empty-title="t('users.noUsers')"
            :paginator="users"
        >
            <template #item="{ item: user }">
                <GridCard>
                    <template #title>{{ user.name }}</template>
                    <template #subtitle>{{ user.email }}</template>
                    <template #badge>
                        <StatusBadge :status="user.is_active ? 'success' : 'danger'">
                            {{ user.is_active ? t('common.active') : t('common.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <div v-if="features.branches" class="text-muted">{{ user.branch?.name || '-' }}</div>
                        <StatusBadge v-if="user.roles?.length" status="info" class="mt-1">{{ user.roles[0].name }}</StatusBadge>
                    </template>
                    <template #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('users.edit', user.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteUser(user)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
