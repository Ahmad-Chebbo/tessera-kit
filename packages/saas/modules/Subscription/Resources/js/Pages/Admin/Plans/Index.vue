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
    plans: Array,
});

const { t } = useI18n();
const confirm = useConfirm();

const columns = computed(() => [
    { key: 'name', label: t('plans.name') },
    { key: 'price', label: t('plans.price') },
    { key: 'modules', label: t('plans.modules') },
    { key: 'limits', label: t('plans.maxUsers') },
    { key: 'subscriptions_count', label: t('plans.tenants') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const price = (plan) => {
    const suffix = plan.billing_interval === 'monthly' ? t('plans.perMonth') : plan.billing_interval === 'yearly' ? t('plans.perYear') : '';

    return `${plan.currency} ${Number(plan.price).toFixed(2)} ${suffix}`.trim();
};

const remove = async (plan) => {
    if (await confirm.delete(t('plans.confirmDelete'))) {
        router.delete(route('admin.plans.destroy', plan.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('plans.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('plans.title') }}</h1>
                <p class="text-sm text-muted">{{ t('plans.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex justify-end">
            <Link :href="route('admin.plans.create')" class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep">
                <Plus class="h-4 w-4" />
                {{ t('plans.addPlan') }}
            </Link>
        </div>

        <DataTable :columns="columns" :rows="plans" :empty-title="t('plans.noPlans')">
            <template #cell-name="{ row }">
                <p class="font-medium text-fg">{{ row.name }}</p>
                <p class="text-xs text-muted">{{ row.slug }}</p>
            </template>
            <template #cell-price="{ row }">{{ price(row) }}</template>
            <template #cell-modules="{ row }">{{ row.modules.length }}</template>
            <template #cell-limits="{ row }">
                {{ row.max_users ?? t('plans.unlimited') }} / {{ row.max_branches ?? t('plans.unlimited') }}
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">
                    {{ row.is_active ? t('common.active') : t('common.inactive') }}
                </StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('admin.plans.edit', row.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>
    </SidebarLayout>
</template>
