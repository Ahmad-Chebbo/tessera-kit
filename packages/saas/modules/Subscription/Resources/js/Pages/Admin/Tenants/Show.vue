<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';

const props = defineProps({
    tenant: Object,
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
    <Head :title="tenant.name" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.tenants.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ tenant.name }}</h1>
                    <p class="text-sm text-muted">{{ t('tenants.tenantDetails') }}</p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Tenant Info Card -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-display text-lg font-normal text-fg">{{ t('tenants.tenantInfo') }}</h3>
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.tenants.edit', tenant.id)" />
                </div>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.name') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ tenant.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.email') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ tenant.email || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.phone') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ tenant.phone || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.address') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ tenant.address || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('tenants.currency') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ tenant.currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('tenants.taxRate') }}</dt>
                        <dd class="mt-1 text-sm text-fg">{{ tenant.tax_rate }}%</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted">{{ t('common.status') }}</dt>
                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="tenant.is_active
                                    ? 'bg-status-success-wash text-status-success'
                                    : 'bg-status-danger-wash text-status-danger'"
                            >
                                {{ tenant.is_active ? t('common.active') : t('common.inactive') }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Branches Card -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('tenants.branches') }} ({{ tenant.branches?.length || 0 }})</h3>
                <div v-if="tenant.branches?.length" class="space-y-3">
                    <div
                        v-for="branch in tenant.branches"
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
                <p v-else class="text-sm text-muted">{{ t('tenants.noBranches') }}</p>
            </div>

            <!-- Users Card -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('tenants.users') }} ({{ tenant.users?.length || 0 }})</h3>
                <div v-if="tenant.users?.length" class="overflow-x-auto">
                    <DataTable
                        :columns="tableColumns"
                        :rows="tenant.users"
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
                <p v-else class="text-sm text-muted">{{ t('tenants.noUsers') }}</p>
            </div>
        </div>
    </SidebarLayout>
</template>
