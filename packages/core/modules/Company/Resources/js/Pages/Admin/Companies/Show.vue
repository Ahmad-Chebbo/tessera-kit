<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';

const props = defineProps({
    company: Object,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'name', label: t('common.name'), cellClass: 'px-4 py-2 text-sm text-fg', headerClass: 'px-4 py-2' },
    { key: 'email', label: t('common.email'), cellClass: 'px-4 py-2 text-sm text-muted', headerClass: 'px-4 py-2' },
    { key: 'role', label: t('users.role'), cellClass: 'px-4 py-2', headerClass: 'px-4 py-2' },
    { key: 'status', label: t('common.status'), cellClass: 'px-4 py-2', headerClass: 'px-4 py-2' },
]);
</script>

<template>
    <Head :title="company.name" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.companies.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ company.name }}</h1>
                    <p class="text-sm text-muted">{{ t('companies.companyDetails') }}</p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Company Info Card -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-display text-lg font-normal text-fg">{{ t('companies.companyInfo') }}</h3>
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.companies.edit', company.id)" />
                </div>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.name') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ company.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.email') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ company.email || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.phone') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ company.phone || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.address') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ company.address || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('companies.currency') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ company.currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('companies.taxRate') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ company.tax_rate }}%</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.status') }}</dt>
                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="company.is_active
                                    ? 'bg-status-success-wash text-status-success'
                                    : 'bg-status-danger-wash text-status-danger'"
                            >
                                {{ company.is_active ? t('common.active') : t('common.inactive') }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Branches Card -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('companies.branches') }} ({{ company.branches?.length || 0 }})</h3>
                <div v-if="company.branches?.length" class="space-y-3">
                    <div
                        v-for="branch in company.branches"
                        :key="branch.id"
                        class="flex items-center justify-between rounded-lg bg-hover-wash p-3"
                    >
                        <div>
                            <p class="font-medium text-fg">{{ branch.name }}</p>
                            <p class="text-sm text-muted">{{ branch.address || t('common.noAddress') }}</p>
                        </div>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="branch.is_active
                                ? 'bg-status-success-wash text-status-success'
                                : 'bg-hover-wash text-fg'"
                        >
                            {{ branch.is_active ? t('common.active') : t('common.inactive') }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-sm text-muted">{{ t('companies.noBranches') }}</p>
            </div>

            <!-- Users Card -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('companies.users') }} ({{ company.users?.length || 0 }})</h3>
                <div v-if="company.users?.length" class="overflow-x-auto">
                    <DataTable
                        :columns="tableColumns"
                        :rows="company.users"
                        :card="false"
                    >
                        <template #cell-name="{ row: user }">
                            {{ user.name }}
                        </template>
                        <template #cell-email="{ row: user }">
                            {{ user.email }}
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
                    </DataTable>
                </div>
                <p v-else class="text-sm text-muted">{{ t('companies.noUsers') }}</p>
            </div>
        </div>
    </SidebarLayout>
</template>
