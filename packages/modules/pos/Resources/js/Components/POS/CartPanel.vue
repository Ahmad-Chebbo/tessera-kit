<script setup>
import { ref, computed, reactive, watch, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useCart } from '@modules/Pos/Resources/js/Composables/useCart';
import { useCurrency } from '@/Composables/useCurrency';
import CartItem from './CartItem.vue';
import DiscountInput from './DiscountInput.vue';
import { LoaderCircle, Percent, ShoppingCart, Ticket, User, X } from 'lucide-vue-next';
import { useToast } from '@tessera/tessera-ui/Composables/useToast';

const props = defineProps({
    currency: {
        type: String,
        default: 'USD',
    },
    checkoutError: {
        type: String,
        default: '',
    },
    isProcessing: {
        type: Boolean,
        default: false,
    },
    exchangeRates: {
        type: Object,
        default: () => ({}),
    },
    customers: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['checkout', 'hold', 'open-customers']);

const { t } = useI18n();
const toast = useToast();
const { formatCurrency } = useCurrency();
const {
    cartItems,
    removeItem,
    incrementQuantity,
    decrementQuantity,
    changeUnit,
    clearCart,
    setDiscount,
    clearDiscount,
    subtotal,
    discountAmount,
    discountType,
    discountValue,
    total,
    itemCount,
    isEmpty,
    couponCode,
    selectedCustomerId,
    paymentType,
    rulesPreview,
    rulesDiscount,
    rulesForItem,
} = useCart();

const cashReceived = ref('');
const panel = ref(null);
const footer = ref(null);
const customerSearch = ref('');
const showCustomerDropdown = ref(false);

// Quick-add customer modal
const showAddCustomerModal = ref(false);
const isCreatingCustomer = ref(false);
const createCustomerError = ref('');
const newCustomer = reactive({ name: '', phone: '', email: '' });
const createdCustomers = ref([]);

const allCustomers = computed(() => [...props.customers, ...createdCustomers.value]);

const selectedCustomer = computed(() =>
    allCustomers.value.find(c => c.id === selectedCustomerId.value) ?? null
);

const filteredCustomers = computed(() => {
    if (!customerSearch.value) return allCustomers.value;
    const q = customerSearch.value.toLowerCase();
    return allCustomers.value.filter(c =>
        c.name.toLowerCase().includes(q) || c.phone?.toLowerCase().includes(q)
    );
});

const openAddCustomerModal = () => {
    newCustomer.name = customerSearch.value;
    newCustomer.phone = '';
    newCustomer.email = '';
    createCustomerError.value = '';
    showCustomerDropdown.value = false;
    showAddCustomerModal.value = true;
};

const submitNewCustomer = async () => {
    if (!newCustomer.name.trim()) {
        createCustomerError.value = t('validation.required');
        return;
    }
    isCreatingCustomer.value = true;
    createCustomerError.value = '';
    try {
        const response = await axios.post(route('customers.store'), {
            name: newCustomer.name,
            phone: newCustomer.phone || null,
            email: newCustomer.email || null,
            is_active: true,
        });
        const customer = response.data.customer;
        createdCustomers.value.push(customer);
        selectedCustomerId.value = customer.id;
        showAddCustomerModal.value = false;
        toast.success(t('toast.customerAdded'));
    } catch (error) {
        createCustomerError.value = error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'Failed to create customer';
    } finally {
        isCreatingCustomer.value = false;
    }
};

const selectCustomer = (customer) => {
    selectedCustomerId.value = customer.id;
    customerSearch.value = '';
    showCustomerDropdown.value = false;
    panel.value = null;
};

const clearCustomer = () => {
    selectedCustomerId.value = null;
    customerSearch.value = '';
};

const onCustomerBlur = () => {
    window.setTimeout(() => { showCustomerDropdown.value = false; }, 200);
};

const change = computed(() => {
    const cash = parseFloat(cashReceived.value) || 0;
    return Math.max(0, cash - total.value);
});

const isDebt = computed(() => paymentType.value === 'debt');

const canCheckout = computed(() => {
    if (isEmpty.value || props.isProcessing) return false;
    if (paymentType.value === 'debt') return !!selectedCustomerId.value;
    const cash = parseFloat(cashReceived.value) || 0;
    return cash >= total.value;
});

const handleCheckout = () => {
    if (canCheckout.value) {
        emit('checkout', {
            cashReceived: parseFloat(cashReceived.value) || 0,
            paymentType: paymentType.value,
            customerId: selectedCustomerId.value,
        });
    }
};

const handleHold = () => {
    if (!isEmpty.value) {
        emit('hold');
    }
};

const handleApplyDiscount = ({ type, value }) => {
    setDiscount(type, value);
    panel.value = null;
};

const togglePanel = (name) => {
    panel.value = panel.value === name ? null : name;
};

const closePanelOnOutsideClick = (event) => {
    if (panel.value && footer.value && !footer.value.contains(event.target)) {
        panel.value = null;
    }
};

onMounted(() => document.addEventListener('pointerdown', closePanelOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closePanelOnOutsideClick));

const customerNeeded = computed(() => paymentType.value === 'debt' && !selectedCustomerId.value);
const couponTone = computed(() => {
    if (!couponCode.value.trim()) {
        return 'idle';
    }

    return rulesPreview.value?.coupon_status === 'applied' ? 'ok' : rulesPreview.value ? 'bad' : 'idle';
});
const hasAdjustments = computed(() => rulesDiscount.value > 0 || discountAmount.value > 0);
const shortcuts = computed(() => quickCashAmounts.value.slice(0, 3));

const handleClearCart = () => {
    clearCart();
    cashReceived.value = '';
};

// Reset cash received when cart changes
watch(total, () => {
    if (parseFloat(cashReceived.value) < total.value) {
        cashReceived.value = '';
    }
});

// Quick cash buttons
const quickCashAmounts = computed(() => {
    const amounts = [];
    const t = total.value;
    if (t > 0) {
        amounts.push(Math.ceil(t));
        amounts.push(Math.ceil(t / 5) * 5);
        amounts.push(Math.ceil(t / 10) * 10);
        amounts.push(Math.ceil(t / 50) * 50);
    }
    return [...new Set(amounts)].slice(0, 4);
});

const setQuickCash = (amount) => {
    cashReceived.value = amount.toString();
};
</script>

<template>
    <div class="flex w-96 flex-col border-s bg-surface shadow-pop">
        <!-- Cart Header -->
        <div class="flex items-center justify-between border-b p-4">
            <h2 class="font-display text-lg font-normal text-fg">
                {{ t('pos.cart') }}
                <span v-if="itemCount > 0" class="text-sm text-muted">
                    ({{ itemCount }})
                </span>
            </h2>
            <button
                v-if="!isEmpty"
                type="button"
                @click="handleClearCart"
                class="text-sm text-status-danger hover:underline"
            >
                {{ t('pos.clearCart') }}
            </button>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-auto p-4">
            <div v-if="isEmpty" class="flex flex-col items-center justify-center py-12 text-center">
                <ShoppingCart class="mb-4 h-16 w-16 text-faint" />
                <p class="text-muted">
                    {{ t('pos.emptyCart') }}
                </p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="(item, index) in cartItems" :key="`${item.product_id}-${item.unit_id}`">
                    <CartItem
                        :item="item"
                        :currency="currency"
                        @increment="incrementQuantity(item.product_id, item.unit_id)"
                        @decrement="decrementQuantity(item.product_id, item.unit_id)"
                        @remove="removeItem(item.product_id, item.unit_id)"
                        @change-unit="(newUnitId) => changeUnit(item.product_id, item.unit_id, newUnitId)"
                    />
                    <p
                        v-for="applied in rulesForItem(index)?.applied ?? []"
                        :key="applied.rule_id"
                        class="mt-0.5 flex justify-between ps-2 text-xs text-status-success"
                    >
                        <span class="truncate">{{ applied.rule_name }}</span>
                        <span>-{{ formatCurrency(applied.amount, currency) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Cart Footer -->
        <div v-if="!isEmpty" ref="footer" class="relative border-t border-line">
            <!-- Options open above the footer so they never push the cart around -->
            <div v-if="panel" class="absolute inset-x-3 bottom-full z-20 mb-2 max-h-80 overflow-auto rounded-xl border border-line bg-surface p-3 shadow-pop" @keydown.esc="panel = null">
                <template v-if="panel === 'customer'">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-fg">{{ t('pos.customer') }}</p>
                        <button type="button" class="text-xs text-accent hover:underline" @click="openAddCustomerModal">+ {{ t('pos.addCustomer') }}</button>
                    </div>
                    <div v-if="selectedCustomer" class="mb-2 flex items-center justify-between rounded-lg bg-accent-wash p-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-accent">{{ selectedCustomer.name }}</p>
                            <p v-if="selectedCustomer.phone" class="text-xs text-muted">{{ selectedCustomer.phone }}</p>
                        </div>
                        <button type="button" class="text-muted hover:text-status-danger" :aria-label="t('pos.removeCustomer')" @click="clearCustomer(); panel = null">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <input
                        v-model="customerSearch"
                        type="text"
                        autofocus
                        :placeholder="t('pos.searchCustomer')"
                        class="block w-full rounded-lg border border-line bg-field p-2 text-sm focus:border-gold-on focus:ring-ring-accent"
                    />
                    <div class="mt-2 max-h-40 overflow-auto">
                        <button
                            v-for="customer in filteredCustomers"
                            :key="customer.id"
                            type="button"
                            class="block w-full rounded-lg px-2 py-1.5 text-start text-sm hover:bg-hover-wash"
                            @click="selectCustomer(customer)"
                        >
                            <span class="font-medium text-fg">{{ customer.name }}</span>
                            <span v-if="customer.phone" class="ms-2 text-muted">{{ customer.phone }}</span>
                        </button>
                        <p v-if="!filteredCustomers.length" class="px-2 py-1.5 text-xs text-muted">{{ t('common.noResults') }}</p>
                    </div>
                    <p class="mt-2 text-xs" :class="isDebt ? 'text-status-warn' : 'text-muted'">
                        {{ isDebt ? t('pos.customerRequired') : t('pos.customerOptional') }}
                    </p>
                </template>

                <template v-else-if="panel === 'discount'">
                    <div v-if="discountType && discountAmount > 0" class="mb-2 flex items-center justify-between rounded-lg bg-status-success-wash p-2.5">
                        <span class="text-sm text-status-success">
                            {{ discountType === 'percentage' ? `${discountValue}%` : formatCurrency(discountValue, currency) }} {{ t('pos.discount') }}
                        </span>
                        <button type="button" class="text-sm text-status-danger hover:underline" @click="clearDiscount(); panel = null">
                            {{ t('pos.removeDiscount') }}
                        </button>
                    </div>
                    <DiscountInput :currency="currency" @apply="handleApplyDiscount" @cancel="panel = null" />
                </template>

                <template v-else-if="panel === 'coupon'">
                    <p class="mb-2 text-sm font-medium text-fg">{{ t('pos.coupon') }}</p>
                    <input
                        v-model="couponCode"
                        type="text"
                        dir="ltr"
                        maxlength="50"
                        autofocus
                        :placeholder="t('pos.couponCode')"
                        class="block w-full rounded-lg border border-line bg-field p-2 text-sm uppercase focus:border-gold-on focus:ring-ring-accent"
                        @keydown.enter="panel = null"
                    />
                    <p v-if="couponCode.trim() && rulesPreview" class="mt-2 text-xs" :class="couponTone === 'ok' ? 'text-status-success' : 'text-status-danger'">
                        {{ t(`pos.couponStatus.${rulesPreview.coupon_status}`) }}
                    </p>
                </template>
            </div>

            <div class="space-y-3 p-3">
                <p
                    v-for="suggestion in rulesPreview?.suggestions ?? []"
                    :key="suggestion.rule_id"
                    data-suggestion
                    class="rounded-lg bg-status-success-wash px-3 py-2 text-xs text-status-success"
                >
                    {{ t('pos.addFreeItem', { quantity: suggestion.quantity, products: suggestion.products.map((product) => product.name).join(' / '), rule: suggestion.rule_name }) }}
                </p>

                <!-- Sale options as compact chips -->
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        data-chip="customer"
                        class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="customerNeeded
                            ? 'border-status-warn-border bg-status-warn-wash text-status-warn'
                            : selectedCustomer ? 'border-gold-on bg-accent-wash text-accent' : 'border-line text-muted hover:bg-hover-wash'"
                        @click="togglePanel('customer')"
                    >
                        <User class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ selectedCustomer ? selectedCustomer.name : customerNeeded ? t('pos.customerRequired') : t('pos.customer') }}</span>
                    </button>
                    <button
                        type="button"
                        data-chip="discount"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="discountAmount > 0 ? 'border-status-success-border bg-status-success-wash text-status-success' : 'border-line text-muted hover:bg-hover-wash'"
                        @click="togglePanel('discount')"
                    >
                        <Percent class="h-3.5 w-3.5 shrink-0" />
                        {{ discountAmount > 0 ? (discountType === 'percentage' ? `${discountValue}%` : formatCurrency(discountValue, currency)) : t('pos.discount') }}
                    </button>
                    <button
                        type="button"
                        data-chip="coupon"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="couponTone === 'ok' ? 'border-status-success-border bg-status-success-wash text-status-success' : couponTone === 'bad' ? 'border-status-danger-border bg-status-danger-wash text-status-danger' : 'border-line text-muted hover:bg-hover-wash'"
                        @click="togglePanel('coupon')"
                    >
                        <Ticket class="h-3.5 w-3.5 shrink-0" />
                        <span dir="ltr" class="uppercase">{{ couponCode.trim() || t('pos.coupon') }}</span>
                    </button>
                </div>

                <!-- Totals -->
                <div class="space-y-1">
                    <template v-if="hasAdjustments">
                        <div class="flex justify-between text-xs text-muted">
                            <span>{{ t('common.subtotal') }}</span>
                            <span>{{ formatCurrency(subtotal, currency) }}</span>
                        </div>
                        <div v-if="rulesDiscount > 0" class="flex justify-between text-xs text-status-success">
                            <span>{{ t('pos.promotions') }}</span>
                            <span>-{{ formatCurrency(rulesDiscount, currency) }}</span>
                        </div>
                        <div v-if="discountAmount > 0" class="flex justify-between text-xs text-status-success">
                            <span>{{ t('pos.discount') }}</span>
                            <span>-{{ formatCurrency(discountAmount, currency) }}</span>
                        </div>
                    </template>
                    <div class="flex items-baseline justify-between text-fg">
                        <span class="text-sm font-medium">{{ t('common.total') }}</span>
                        <span class="text-2xl font-bold">{{ formatCurrency(total, currency) }}</span>
                    </div>
                </div>

                <!-- Payment -->
                <div class="flex overflow-hidden rounded-lg border border-line">
                    <button
                        type="button"
                        class="flex-1 py-1.5 text-sm font-medium transition-colors"
                        :class="paymentType === 'cash' ? 'bg-gold text-ink' : 'bg-surface text-muted hover:bg-hover-wash'"
                        @click="paymentType = 'cash'"
                    >
                        {{ t('orders.cash') }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 py-1.5 text-sm font-medium transition-colors"
                        :class="paymentType === 'debt' ? 'bg-status-warn text-surface' : 'bg-surface text-muted hover:bg-hover-wash'"
                        @click="paymentType = 'debt'"
                    >
                        {{ t('pos.debt') }}
                    </button>
                </div>

                <div v-if="paymentType === 'cash'" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <input
                            v-model="cashReceived"
                            type="number"
                            step="0.01"
                            min="0"
                            class="block w-full min-w-0 rounded-lg border border-line bg-hover-wash px-3 py-2 text-base font-medium text-fg focus:border-gold-on focus:ring-ring-accent"
                            :placeholder="`${t('pos.cashReceived')} ${formatCurrency(total, currency)}`"
                            :aria-label="t('pos.cashReceived')"
                        />
                        <div v-if="parseFloat(cashReceived) >= total" class="shrink-0 text-end leading-tight">
                            <p class="text-[10px] text-muted">{{ t('pos.change') }}</p>
                            <p class="text-sm font-bold text-status-info">{{ formatCurrency(change, currency) }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-for="amount in shortcuts"
                            :key="amount"
                            type="button"
                            class="min-w-0 flex-1 truncate rounded-lg bg-hover-wash px-2 py-1.5 text-xs font-medium text-muted hover:bg-line"
                            @click="setQuickCash(amount)"
                        >
                            {{ formatCurrency(amount, currency) }}
                        </button>
                    </div>
                </div>

                <div v-if="checkoutError" class="rounded-lg bg-status-danger-wash p-2.5 text-sm text-status-danger">
                    {{ checkoutError }}
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="isEmpty || isProcessing"
                        class="flex-1 rounded-lg bg-fg px-3 py-2.5 text-sm font-semibold text-bg transition-colors hover:opacity-90 focus:outline-hidden focus:ring-2 focus:ring-line disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleHold"
                    >
                        {{ t('pos.holdOrder') }}
                    </button>
                    <button
                        type="button"
                        :disabled="!canCheckout"
                        class="flex-[1.4] rounded-lg bg-gold px-3 py-2.5 text-base font-semibold text-ink transition-colors hover:bg-gold-deep focus:outline-hidden focus:ring-2 focus:ring-ring-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleCheckout"
                    >
                        <span v-if="isProcessing" class="flex items-center justify-center">
                            <LoaderCircle class="me-2 h-5 w-5 animate-spin" />
                            {{ t('common.loading') }}
                        </span>
                        <span v-else>{{ t('pos.completeSale') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick-Add Customer Modal -->
    <Teleport to="body">
        <div
            v-if="showAddCustomerModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showAddCustomerModal = false"
        >
            <div class="w-full max-w-sm rounded-2xl bg-surface p-6 shadow-pop">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">
                    {{ t('customers.addCustomer') }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-muted">
                            {{ t('common.name') }} *
                        </label>
                        <input
                            v-model="newCustomer.name"
                            type="text"
                            class="block w-full rounded-lg border border-line bg-hover-wash p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
                            :placeholder="t('common.name')"
                            @keydown.enter="submitNewCustomer"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-muted">
                            {{ t('common.phone') }}
                        </label>
                        <input
                            v-model="newCustomer.phone"
                            type="text"
                            class="block w-full rounded-lg border border-line bg-hover-wash p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
                            :placeholder="t('common.phone')"
                            @keydown.enter="submitNewCustomer"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-muted">
                            {{ t('common.email') }}
                        </label>
                        <input
                            v-model="newCustomer.email"
                            type="email"
                            class="block w-full rounded-lg border border-line bg-hover-wash p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
                            :placeholder="t('common.email')"
                            @keydown.enter="submitNewCustomer"
                        />
                    </div>
                    <div v-if="createCustomerError" class="rounded-lg bg-status-danger-wash p-2 text-sm text-status-danger">
                        {{ createCustomerError }}
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        @click="showAddCustomerModal = false"
                        class="flex-1 rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="submitNewCustomer"
                        :disabled="isCreatingCustomer"
                        class="flex-1 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink hover:bg-gold-deep disabled:opacity-50"
                    >
                        {{ isCreatingCustomer ? t('common.loading') : t('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
