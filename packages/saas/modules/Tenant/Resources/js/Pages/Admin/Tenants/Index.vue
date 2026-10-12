<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Eye, Pencil, Plus, Trash2, CreditCard } from 'lucide-vue-next';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@tessera/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@tessera/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@tessera/tessera-ui/Composables/useViewMode';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import CardGrid from '@tessera/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    tenants: Object,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'tenant', label: t('tenants.tenant') },
    { key: 'currency', label: t('tenants.currency'), cellClass: 'text-sm text-muted' },
    { key: 'branches', label: t('tenants.branches') },
    { key: 'users', label: t('tenants.users') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('tenants');

const search = ref(props.filters?.search || '');

const applyFilters = () => {
    router.get(route('admin.tenants.index'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

const deleteTenant = async (tenant) => {
    if (await confirm.delete()) {
        router.delete(route('admin.tenants.destroy', tenant.id));
    }
};
</script>

<template>
    <Head :title="t('tenants.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('tenants.title') }}
                </h1>
                <p class="text-sm text-muted">
                    {{ t('tenants.manageTenants') }}
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
                :href="route('admin.tenants.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                {{ t('tenants.addTenant') }}
            </Link>
        </div>

        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="tenants.data"
            :empty-title="t('tenants.noTenants')"
            :paginator="tenants"
        >
            <template #cell-tenant="{ row: tenant }">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gold text-sm font-medium text-ink">
                        {{ tenant.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-medium text-fg">{{ tenant.name }}</p>
                        <p class="text-sm text-muted">{{ tenant.email || '-' }}</p>
                    </div>
                </div>
            </template>
            <template #cell-currency="{ row: tenant }">
                {{ tenant.currency }}
            </template>
            <template #cell-branches="{ row: tenant }">
                <span class="inline-flex rounded-full bg-status-info-wash px-2.5 py-0.5 text-xs font-medium text-status-info">
                    {{ tenant.branches_count || 0 }}
                </span>
            </template>
            <template #cell-users="{ row: tenant }">
                <span class="inline-flex rounded-full bg-accent-wash px-2.5 py-0.5 text-xs font-medium text-accent">
                    {{ tenant.users_count || 0 }}
                </span>
            </template>
            <template #cell-status="{ row: tenant }">
                <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="tenant.is_active
                        ? 'bg-status-success-wash text-status-success'
                        : 'bg-status-danger-wash text-status-danger'"
                >
                    {{ tenant.is_active ? t('common.active') : t('common.inactive') }}
                </span>
            </template>
            <template #cell-actions="{ row: tenant }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="CreditCard" :label="t('plans.manage')" :href="route('admin.tenants.subscription.edit', tenant.id)" />
                    <IconButton :icon="Eye" :label="t('common.view')" :href="route('admin.tenants.show', tenant.id)" />
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.tenants.edit', tenant.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteTenant(tenant)" :disabled="tenant.users_count > 0" :class="{ 'cursor-not-allowed opacity-50': tenant.users_count > 0 }" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="tenants.data"
            :empty-title="t('tenants.noTenants')"
            :paginator="tenants"
        >
            <template #item="{ item: tenant }">
                <GridCard>
                    <template #title>{{ tenant.name }}</template>
                    <template #subtitle>{{ tenant.email || '-' }}</template>
                    <template #badge>
                        <StatusBadge :status="tenant.is_active ? 'success' : 'danger'">
                            {{ tenant.is_active ? t('common.active') : t('common.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <div class="text-muted">{{ tenant.currency }}</div>
                        <div class="text-muted">{{ t('nav.branches') }}: {{ tenant.branches_count || 0 }} · {{ t('nav.users') }}: {{ tenant.users_count || 0 }}</div>
                    </template>
                    <template #actions>
                        <IconButton :icon="CreditCard" :label="t('plans.manage')" :href="route('admin.tenants.subscription.edit', tenant.id)" />
                    <IconButton :icon="Eye" :label="t('common.view')" :href="route('admin.tenants.show', tenant.id)" />
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.tenants.edit', tenant.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteTenant(tenant)" :disabled="tenant.users_count > 0" :class="{ 'cursor-not-allowed opacity-50': tenant.users_count > 0 }" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
