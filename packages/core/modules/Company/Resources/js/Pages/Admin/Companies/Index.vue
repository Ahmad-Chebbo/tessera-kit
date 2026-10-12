<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Eye, Pencil, Plus, Trash2, CreditCard } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@ahmad-chebbo/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@ahmad-chebbo/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@ahmad-chebbo/tessera-ui/Composables/useViewMode';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import CardGrid from '@ahmad-chebbo/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    companies: Object,
    filters: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'company', label: t('companies.company') },
    { key: 'currency', label: t('companies.currency'), cellClass: 'text-sm text-muted' },
    { key: 'branches', label: t('companies.branches') },
    { key: 'users', label: t('companies.users') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('companies');

const search = ref(props.filters?.search || '');

const applyFilters = () => {
    router.get(route('admin.companies.index'), {
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

const deleteCompany = async (company) => {
    if (await confirm.delete()) {
        router.delete(route('admin.companies.destroy', company.id));
    }
};
</script>

<template>
    <Head :title="t('companies.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('companies.title') }}
                </h1>
                <p class="text-sm text-muted">
                    {{ t('companies.manageCompanies') }}
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
                :href="route('admin.companies.create')"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
            >
                <Plus class="h-5 w-5" />
                {{ t('companies.addCompany') }}
            </Link>
        </div>

        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="companies.data"
            :empty-title="t('companies.noCompanies')"
            :paginator="companies"
        >
            <template #cell-company="{ row: company }">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gold text-sm font-medium text-ink">
                        {{ company.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-medium text-fg">{{ company.name }}</p>
                        <p class="text-sm text-muted">{{ company.email || '-' }}</p>
                    </div>
                </div>
            </template>
            <template #cell-currency="{ row: company }">
                {{ company.currency }}
            </template>
            <template #cell-branches="{ row: company }">
                <span class="inline-flex rounded-full bg-status-info-wash px-2.5 py-0.5 text-xs font-medium text-status-info">
                    {{ company.branches_count || 0 }}
                </span>
            </template>
            <template #cell-users="{ row: company }">
                <span class="inline-flex rounded-full bg-accent-wash px-2.5 py-0.5 text-xs font-medium text-accent">
                    {{ company.users_count || 0 }}
                </span>
            </template>
            <template #cell-status="{ row: company }">
                <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="company.is_active
                        ? 'bg-status-success-wash text-status-success'
                        : 'bg-status-danger-wash text-status-danger'"
                >
                    {{ company.is_active ? t('common.active') : t('common.inactive') }}
                </span>
            </template>
            <template #cell-actions="{ row: company }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="CreditCard" :label="t('plans.manage')" :href="route('admin.companies.subscription.edit', company.id)" />
                    <IconButton :icon="Eye" :label="t('common.view')" :href="route('admin.companies.show', company.id)" />
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.companies.edit', company.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteCompany(company)" :disabled="company.users_count > 0" :class="{ 'cursor-not-allowed opacity-50': company.users_count > 0 }" />
                </div>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="companies.data"
            :empty-title="t('companies.noCompanies')"
            :paginator="companies"
        >
            <template #item="{ item: company }">
                <GridCard>
                    <template #title>{{ company.name }}</template>
                    <template #subtitle>{{ company.email || '-' }}</template>
                    <template #badge>
                        <StatusBadge :status="company.is_active ? 'success' : 'danger'">
                            {{ company.is_active ? t('common.active') : t('common.inactive') }}
                        </StatusBadge>
                    </template>
                    <template #meta>
                        <div class="text-muted">{{ company.currency }}</div>
                        <div class="text-muted">{{ t('nav.branches') }}: {{ company.branches_count || 0 }} · {{ t('nav.users') }}: {{ company.users_count || 0 }}</div>
                    </template>
                    <template #actions>
                        <IconButton :icon="CreditCard" :label="t('plans.manage')" :href="route('admin.companies.subscription.edit', company.id)" />
                    <IconButton :icon="Eye" :label="t('common.view')" :href="route('admin.companies.show', company.id)" />
                        <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.companies.edit', company.id)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteCompany(company)" :disabled="company.users_count > 0" :class="{ 'cursor-not-allowed opacity-50': company.users_count > 0 }" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
