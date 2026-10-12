<script setup>
import ExportMenu from '@/Components/ExportMenu.vue';
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, Lock, Printer } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import Card from '@tessera/tessera-ui/Components/Card.vue';
import StatCard from '@tessera/tessera-ui/Components/StatCard.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    shift: Object,
    summary: Object,
    orders: Array,
    can: Object,
});

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : '-');

const varianceLabel = computed(() => {
    if (props.shift.variance === null || props.shift.variance === 0) {
        return t('shifts.pos.balanced');
    }

    return props.shift.variance > 0 ? t('shifts.pos.over') : t('shifts.pos.short');
});

const varianceStatus = computed(() => {
    if (props.shift.variance === null) {
        return 'neutral';
    }

    return props.shift.variance === 0 ? 'success' : props.shift.variance > 0 ? 'info' : 'danger';
});

const stats = computed(() => [
    { label: t('shifts.pos.orders'), value: props.summary?.orders ?? 0 },
    { label: t('shifts.pos.items'), value: props.summary?.items ?? 0 },
    { label: t('shifts.pos.grossSales'), value: formatCurrency(props.summary?.gross_sales ?? 0) },
    { label: t('shifts.pos.discounts'), value: formatCurrency(props.summary?.discounts ?? 0) },
    { label: t('shifts.pos.cashSales'), value: formatCurrency(props.summary?.cash_sales ?? 0) },
    { label: t('shifts.pos.creditSales'), value: formatCurrency(props.summary?.credit_sales ?? 0) },
    { label: t('shifts.pos.voided'), value: `${props.summary?.voided_orders ?? 0} · ${formatCurrency(props.summary?.voided_total ?? 0)}` },
]);

const expectedNow = computed(() => props.shift.expected_cash ?? props.shift.opening_float + (props.summary?.cash_sales ?? 0));

const columns = computed(() => [
    { key: 'order_number', label: t('shifts.pos.orderNumber') },
    { key: 'created_at', label: t('shifts.pos.opened'), cellClass: 'text-sm text-muted' },
    { key: 'payment_type', label: t('shifts.pos.payment') },
    { key: 'total', label: t('shifts.pos.total') },
    { key: 'status', label: t('shifts.pos.status') },
]);

const printReport = () => window.print();
const showClose = ref(false);
const closeForm = useForm({ counted_cash: '', notes: '' });
const submitClose = () => closeForm.post(route('pos-shifts.close', props.shift.id), { onSuccess: () => (showClose.value = false) });
</script>

<template>
    <Head :title="t('shifts.pos.report')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('pos-shifts.index')" class="text-muted hover:text-fg print:hidden">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('shifts.pos.report') }}</h1>
                    <p class="text-sm text-muted">{{ shift.user?.name }} · {{ shift.branch?.name }}</p>
                </div>
                <StatusBadge :status="shift.status === 'open' ? 'success' : 'neutral'">
                    {{ shift.status === 'open' ? t('shifts.pos.open') : t('shifts.pos.closedStatus') }}
                </StatusBadge>
            </div>
        </template>

        <div class="mb-4 flex justify-end print:hidden">
            <ExportMenu report="pos-shift-closing" :filters="{ shift_id: shift.id }" />
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <p class="text-sm text-muted">
                {{ formatDateTime(shift.opened_at) }}
                <template v-if="shift.closed_at"> → {{ formatDateTime(shift.closed_at) }}</template>
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line"
                    @click="printReport"
                >
                    <Printer class="h-4 w-4" />
                    {{ t('shifts.pos.print') }}
                </button>
                <button
                    v-if="can.close"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                    @click="showClose = true"
                >
                    <Lock class="h-4 w-4" />
                    {{ t('shifts.pos.closeShift') }}
                </button>
            </div>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard v-for="stat in stats" :key="stat.label" :label="stat.label" :value="stat.value" />
        </div>

        <Card class="mb-6">
            <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ t('shifts.pos.reconciliation') }}</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('shifts.pos.openingFloat') }}</dt>
                    <dd class="text-fg">{{ formatCurrency(shift.opening_float) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('shifts.pos.cashSales') }}</dt>
                    <dd class="text-fg">{{ formatCurrency(summary?.cash_sales ?? 0) }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-line pt-3 font-medium">
                    <dt class="text-fg">{{ t('shifts.pos.expectedCash') }}</dt>
                    <dd class="text-fg">{{ formatCurrency(expectedNow) }}</dd>
                </div>
                <template v-if="shift.counted_cash !== null">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">{{ t('shifts.pos.countedCash') }}</dt>
                        <dd class="text-fg">{{ formatCurrency(shift.counted_cash) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-muted">{{ t('shifts.pos.variance') }}</dt>
                        <dd>
                            <StatusBadge :status="varianceStatus">{{ varianceLabel }} · {{ formatCurrency(shift.variance) }}</StatusBadge>
                        </dd>
                    </div>
                </template>
            </dl>
            <p v-if="shift.notes" class="mt-4 whitespace-pre-line text-sm text-muted">{{ shift.notes }}</p>
        </Card>

        <h2 class="mb-3 font-display text-lg font-normal text-fg">{{ t('shifts.pos.shiftOrders') }}</h2>
        <DataTable :columns="columns" :rows="orders" :empty-title="t('shifts.pos.noOrders')">
            <template #cell-order_number="{ row }">
                <Link :href="route('orders.show', row.id)" class="font-medium text-fg hover:text-accent">{{ row.order_number }}</Link>
            </template>
            <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
            <template #cell-payment_type="{ row }">{{ row.payment_type === 'debt' ? t('shifts.pos.debt') : t('shifts.pos.cash') }}</template>
            <template #cell-total="{ row }">{{ formatCurrency(row.total) }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.status === 'voided' ? 'danger' : row.status === 'debt' ? 'warn' : 'success'">{{ row.status }}</StatusBadge>
            </template>
        </DataTable>

        <Modal :show="showClose" max-width="md" @close="showClose = false">
            <form class="space-y-5 p-6" @submit.prevent="submitClose">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('shifts.pos.closeShift') }}</h2>
                <p class="text-sm text-muted">{{ t('shifts.pos.confirmClose') }}</p>
                <div>
                    <InputLabel for="counted_cash" :value="t('shifts.pos.countedCash')" />
                    <TextInput id="counted_cash" v-model="closeForm.counted_cash" type="number" step="0.01" min="0" class="mt-1 block w-full" required autofocus />
                    <InputError :message="closeForm.errors.counted_cash" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="close_notes" :value="t('shifts.pos.notes')" />
                    <textarea id="close_notes" v-model="closeForm.notes" rows="2" class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="showClose = false">
                        {{ t('common.cancel') }}
                    </button>
                    <PrimaryButton :disabled="closeForm.processing">{{ t('shifts.pos.closeShift') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
