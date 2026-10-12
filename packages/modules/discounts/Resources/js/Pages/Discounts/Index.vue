<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Copy, FlaskConical, Pause, Pencil, Play, Plus, Sparkles, Trash2 } from 'lucide-vue-next';
import TemplatePicker from '@modules/Discounts/Resources/js/Pages/Discounts/Partials/TemplatePicker.vue';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import { useCurrency } from '@/Composables/useCurrency';

defineProps({
    rules: Array,
    templates: { type: Array, default: () => [] },
    lookups: { type: Object, default: () => ({}) },
});

const showTemplates = ref(false);

const { t } = useI18n();
const confirm = useConfirm();
const { formatCurrency } = useCurrency();

const columns = computed(() => [
    { key: 'name', label: t('discounts.name') },
    { key: 'action', label: t('discounts.action') },
    { key: 'priority', label: t('discounts.priority') },
    { key: 'status', label: t('discounts.status') },
    { key: 'uses', label: t('discounts.uses') },
    { key: 'discounted', label: t('discounts.discounted') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const tone = { active: 'success', paused: 'neutral', scheduled: 'info', expired: 'danger' };

const summary = (rule) => {
    switch (rule.action_type) {
        case 'percent':
            return `${rule.action_value}%`;
        case 'fixed':
            return `${formatCurrency(rule.action_value)} · ${t(`discounts.${rule.action_config.apply_to ?? 'each_line'}`)}`;
        case 'price':
            return `${t('discounts.price')} ${formatCurrency(rule.action_value)}`;
        case 'buy_get_item':
            return `${rule.action_config.buy_quantity} → ${rule.action_config.get_quantity} ${t('discounts.freeItems')}`;
        default:
            return `${rule.action_config.buy_quantity} + ${rule.action_config.get_quantity}`;
    }
};

const remove = async (rule) => {
    if (await confirm.delete(t('discounts.confirmDelete'))) {
        router.delete(route('discounts.destroy', rule.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('discounts.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('discounts.title') }}</h1>
                <p class="text-sm text-muted">{{ t('discounts.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap justify-end gap-2">
            <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line inline-flex items-center gap-2" @click="showTemplates = true">
                <Sparkles class="h-4 w-4" />
                {{ t('discounts.fromTemplate') }}
            </button>
            <Link :href="route('discounts.simulator')" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line inline-flex items-center gap-2">
                <FlaskConical class="h-4 w-4" />
                {{ t('discounts.simulator') }}
            </Link>
            <Link :href="route('discounts.create')" class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep">
                <Plus class="h-4 w-4" />
                {{ t('discounts.addRule') }}
            </Link>
        </div>

        <DataTable :columns="columns" :rows="rules" :empty-title="t('discounts.noRules')">
            <template #cell-name="{ row }">
                <p class="font-medium text-fg">{{ row.name }}</p>
                <p v-if="row.coupon_code" class="text-xs text-muted">{{ row.coupon_code }}</p>
            </template>
            <template #cell-action="{ row }">{{ summary(row) }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="tone[row.status]">{{ t(`discounts.statuses.${row.status}`) }}</StatusBadge>
            </template>
            <template #cell-discounted="{ row }">{{ formatCurrency(row.total_discounted) }}</template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton
                        :icon="row.is_active ? Pause : Play"
                        :label="row.is_active ? t('discounts.pause') : t('discounts.activate')"
                        @click="router.post(route('discounts.toggle', row.id), {}, { preserveScroll: true })"
                    />
                    <IconButton :icon="Copy" :label="t('discounts.duplicate')" @click="router.post(route('discounts.duplicate', row.id))" />
                    <IconButton :icon="Pencil" :label="t('common.edit')" :href="route('discounts.edit', row.id)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>

        <TemplatePicker :show="showTemplates" :templates="templates" :lookups="lookups" @close="showTemplates = false" />
    </SidebarLayout>
</template>
