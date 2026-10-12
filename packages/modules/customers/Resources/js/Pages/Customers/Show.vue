<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/Composables/useCurrency';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import Pagination from '@ahmad-chebbo/tessera-ui/Components/Pagination.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';

const props = defineProps({
    customer: Object,
    orders: Object,
    stats: Object,
    ordersAvailable: { type: Boolean, default: true },
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'orderNumber', label: t('pos.orderNumber'), cellClass: 'font-medium text-fg', wrap: true },
    { key: 'total', label: t('common.total'), cellClass: 'font-medium text-fg', wrap: true },
    { key: 'paymentType', label: t('orders.paymentType'), wrap: true },
    { key: 'debtStatus', label: t('orders.debtStatus'), wrap: true },
    { key: 'paidDate', label: t('orders.paidDate'), cellClass: 'text-sm text-muted', wrap: true },
    { key: 'date', label: t('common.date'), cellClass: 'text-sm text-muted', wrap: true },
    { key: 'actions', label: t('common.actions'), align: 'end', wrap: true },
]);
const { formatCurrency } = useCurrency();

const getDebtStatusClass = (debtStatus) => debtStatus === 'paid'
    ? 'bg-status-success-wash text-status-success'
    : 'bg-status-danger-wash text-status-danger';

const markAsPaid = (orderId) => {
    router.post(route('orders.mark-paid', orderId), {}, { preserveState: false });
};
</script>

<template>
    <Head :title="customer.name" />

    <SidebarLayout>
        <template #header>
            <div class="flex justify-between gap-4">
                <div>
                    <h2 class="font-display text-xl font-normal text-fg">{{ customer.name }}</h2>
                    <p class="text-sm text-muted">{{ customer.phone }} {{ customer.email ? '· ' + customer.email : '' }}</p>
                </div>
            </div>
        </template>

        <!-- Stats -->
        <div v-if="ordersAvailable" class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-line bg-surface p-4">
                <p class="text-sm text-muted">{{ t('orders.title') }}</p>
                <p class="font-display mt-1 text-2xl font-normal text-fg">{{ stats.totalOrders }}</p>
            </div>
            <div class="rounded-2xl border border-line bg-surface p-4">
                <p class="text-sm text-muted">{{ t('customers.totalSpent') }}</p>
                <p class="font-display mt-1 text-2xl font-normal text-fg">{{ formatCurrency(stats.totalSpent) }}</p>
            </div>
            <div class="rounded-2xl border border-line bg-surface p-4">
                <p class="text-sm text-muted">{{ t('customers.totalDebt') }}</p>
                <p class="font-display mt-1 text-2xl font-normal" :class="stats.totalDebt > 0 ? 'text-status-danger' : 'text-status-success'">
                    {{ formatCurrency(stats.totalDebt) }}
                </p>
            </div>
            <div class="rounded-2xl border border-line bg-surface p-4">
                <p class="text-sm text-muted">{{ t('customers.paidDebt') }}</p>
                <p class="font-display mt-1 text-2xl font-normal text-status-success">{{ formatCurrency(stats.paidDebt) }}</p>
            </div>
        </div>

        <!-- Orders -->
        <div v-if="ordersAvailable" class="overflow-hidden rounded-2xl bg-surface border border-line">
            <div class="border-b border-line px-6 py-4">
                <h3 class="font-semibold text-fg">{{ t('orders.title') }}</h3>
            </div>
            <DataTable
                :columns="tableColumns"
                :rows="orders.data"
                :empty-title="t('orders.noOrders')"
                :card="false"
            >
                <template #cell-orderNumber="{ row: order }">
                    <a :href="route('orders.show', order.id)" target="_blank" class="text-accent hover:underline">
                        {{ order.order_number }}
                    </a>
                    <span v-if="order.status === 'voided'" class="inline-flex rounded-full px-2 py-1 text-xs font-medium bg-status-danger-wash text-status-danger">
                        {{ t('orders.voided') }}
                    </span>
                </template>
                <template #cell-total="{ row: order }">
                    {{ formatCurrency(order.total, currency) }}
                </template>
                <template #cell-paymentType="{ row: order }">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        :class="order.payment_type === 'debt' ? 'bg-status-warn-wash text-status-warn' : 'bg-status-info-wash text-status-info'">
                        {{ order.payment_type === 'debt' ? t('pos.debt') : t('orders.cash') }}
                    </span>
                </template>
                <template #cell-debtStatus="{ row: order }">
                    <span v-if="order.payment_type === 'debt'"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        :class="getDebtStatusClass(order.debt_status)">
                        {{ order.debt_status === 'paid' ? t('orders.paid') : t('orders.unpaid') }}
                    </span>
                    <span v-else class="text-faint">-</span>
                </template>
                <template #cell-paidDate="{ row: order }">
                    {{ order.debt_paid_at ? new Date(order.debt_paid_at).toLocaleDateString() : '-' }}
                </template>
                <template #cell-date="{ row: order }">
                    {{ new Date(order.created_at).toLocaleString() }}
                </template>
                <template #cell-actions="{ row: order }">
                    <div class="flex items-center justify-end gap-2">
                        <button
                            v-if="order.payment_type === 'debt' && order.debt_status === 'unpaid'"
                            type="button"
                            @click="markAsPaid(order.id)"
                            class="rounded bg-status-success px-2 py-1 text-xs text-surface hover:opacity-90"
                        >
                            {{ t('orders.markPaid') }}
                        </button>

                    </div>
                </template>
            </DataTable>

            <Pagination :paginator="orders" />
        </div>
    </SidebarLayout>
</template>
