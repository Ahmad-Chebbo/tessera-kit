<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import ProductGrid from '@modules/Pos/Resources/js/Components/POS/ProductGrid.vue';
import CategoryTabs from '@modules/Pos/Resources/js/Components/POS/CategoryTabs.vue';
import SearchBar from '@modules/Pos/Resources/js/Components/POS/SearchBar.vue';
import CartPanel from '@modules/Pos/Resources/js/Components/POS/CartPanel.vue';
import ReceiptModal from '@modules/Pos/Resources/js/Components/POS/ReceiptModal.vue';
import OfflineIndicator from '@modules/Pos/Resources/js/Components/POS/OfflineIndicator.vue';
import { useCart } from '@modules/Pos/Resources/js/Composables/useCart';
import { useBarcode } from '@modules/Pos/Resources/js/Composables/useBarcode';
import { useOfflineQueue } from '@modules/Pos/Resources/js/Composables/useOfflineQueue';
import { useCurrency } from '@/Composables/useCurrency';
import { Calculator, ClipboardList, LogOut, Plus, Trash2, X } from 'lucide-vue-next';
import FloatingCalculator from '@modules/Pos/Resources/js/Components/POS/FloatingCalculator.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import { useToast } from '@tessera/tessera-ui/Composables/useToast';
import ToastContainer from '@tessera/tessera-ui/Components/ToastContainer.vue';
import ConfirmDialog from '@tessera/tessera-ui/Components/ConfirmDialog.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import LanguageToggle from '@tessera/tessera-ui/Components/LanguageToggle.vue';
import ThemeToggle from '@tessera/tessera-ui/Components/ThemeToggle.vue';
import AttendanceToggle from '@/Components/AttendanceToggle.vue';

const props = defineProps({
    branch: Object,
    categories: Array,
    currency: String,
    locationId: Number,
    exchangeRates: Object,
    customers: { type: Array, default: () => [] },
    taxRate: { type: Number, default: 0 },
});

const showCalculator = ref(false);

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();
const { formatCurrency } = useCurrency();
const {
    cartItems,
    addItem,
    updateQuantity,
    clearCart,
    total,
    subtotal,
    discountAmount,
    getCheckoutPayload,
    setExchangeRates,
    snapshotCart,
    restoreCart,
    couponCode,
    selectedCustomerId,
    paymentType,
    setRulesPreview,
} = useCart();

const {
    isOnline,
    pendingCount,
    queueSale,
    syncSales,
    cacheProducts,
    getCachedProducts,
} = useOfflineQueue();

const products = ref([]);
const filteredProducts = ref([]);
const isLoading = ref(true);
const selectedCategory = ref(null);
const searchQuery = ref('');
const showReceipt = ref(false);
const lastOrder = ref(null);
const checkoutError = ref('');
const isProcessing = ref(false);
const showHeldOrders = ref(false);
const heldOrders = ref(props.heldOrders || []);
const showHoldModal = ref(false);
const holdOrderName = ref('');
const currentHeldOrderId = ref(null);
const newTabName = ref('');
const showTabNameModal = ref(false);

// ── Automatic discounts ──────────────────────────────────────────────────────
let previewTimer = null;
let previewRequest = 0;

const refreshDiscounts = async () => {
    const request = ++previewRequest;

    if (!isOnline.value || cartItems.value.length === 0) {
        setRulesPreview(null);
        return;
    }

    try {
        const response = await axios.post(route('pos.discounts.preview'), {
            items: cartItems.value.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
            customer_id: selectedCustomerId.value,
            coupon_code: couponCode.value.trim() || null,
            payment_type: paymentType.value,
        });

        if (request === previewRequest) {
            setRulesPreview(response.data);
        }
    } catch {
        if (request === previewRequest) {
            setRulesPreview(null);
        }
    }
};

watch(
    [() => cartItems.value.map((item) => `${item.product_id}:${item.quantity}`).join(','), selectedCustomerId, paymentType, couponCode, isOnline],
    () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshDiscounts, 350);
    },
);

// ── Multi-tab support ──────────────────────────────────────────────────────────
let tabIdCounter = 1;
const tabs = ref([{ id: 1, name: t('pos.order') + ' 1', heldOrderId: null }]);
const activeTabId = ref(1);

const switchTab = (tabId) => {
    if (tabId === activeTabId.value) return;
    // Save current cart into the active tab
    const current = tabs.value.find(t => t.id === activeTabId.value);
    if (current) {
        current.snapshot = snapshotCart();
        current.heldOrderId = currentHeldOrderId.value;
    }
    // Restore the new tab's cart
    const next = tabs.value.find(t => t.id === tabId);
    if (next) {
        restoreCart(next.snapshot || null);
        currentHeldOrderId.value = next.heldOrderId || null;
        activeTabId.value = tabId;
        checkoutError.value = '';
    }
};

const confirmTabName = () => {
    if (!newTabName.value.trim()) {
        newTabName.value = t('pos.order') + ' ' + (tabs.value.length + 1);
    }
    addTab(newTabName.value);
    showTabNameModal.value = false;
    newTabName.value = '';
};

const addTab = (name = null) => {
    // Save current cart
    const current = tabs.value.find(t => t.id === activeTabId.value);
    if (current) {
        current.snapshot = snapshotCart();
        current.heldOrderId = currentHeldOrderId.value;
    }
    tabIdCounter++;
    const newTab = { id: tabIdCounter, name: name || t('pos.order') + ' ' + tabIdCounter, snapshot: null, heldOrderId: null };
    tabs.value.push(newTab);
    clearCart();
    currentHeldOrderId.value = null;
    activeTabId.value = tabIdCounter;
    checkoutError.value = '';
};

const openCustomersPage = () => {
    window.open(route('customers.index'), '_blank');
};

const closeTab = (tabId) => {
    if (tabs.value.length === 1) return;
    const index = tabs.value.findIndex(t => t.id === tabId);
    if (index === -1) return;
    const isActive = activeTabId.value === tabId;
    tabs.value.splice(index, 1);
    if (isActive) {
        const nextTab = tabs.value[Math.max(0, index - 1)];
        restoreCart(nextTab.snapshot || null);
        currentHeldOrderId.value = nextTab.heldOrderId || null;
        activeTabId.value = nextTab.id;
        checkoutError.value = '';
    }
};

const loadProducts = async () => {
    isLoading.value = true;

    try {
        if (isOnline.value) {
            const params = new URLSearchParams();
            if (searchQuery.value) {
                params.append('search', searchQuery.value);
            }
            if (selectedCategory.value) {
                params.append('category_id', selectedCategory.value);
            }
            const response = await axios.get(route('pos.products') + (params.toString() ? `?${params.toString()}` : ''));
            products.value = response.data.products;
            // Cache products as plain objects to avoid DataCloneError
            await cacheProducts(response.data.products);
        } else {
            products.value = await getCachedProducts();
            // Filter locally when offline
            filterProducts();
        }
        filteredProducts.value = products.value;
    } catch (error) {
        console.error('Failed to load products:', error);
        toast.warning(t('toast.productsLoadFailed'));
        // Try to load from cache on error
        products.value = await getCachedProducts();
        filterProducts();
    } finally {
        isLoading.value = false;
    }
};

const filterProducts = () => {
    let result = [...products.value];

    if (selectedCategory.value) {
        result = result.filter(p => p.category_id === selectedCategory.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(p =>
            p.name.toLowerCase().includes(query) ||
            p.barcode?.toLowerCase().includes(query) ||
            p.sku?.toLowerCase().includes(query)
        );
    }

    filteredProducts.value = result;
};

const handleSearch = (query) => {
    searchQuery.value = query;
    if (isOnline.value) {
        loadProducts();
    } else {
        filterProducts();
    }
};

const handleCategoryChange = (categoryId) => {
    selectedCategory.value = categoryId;
    if (isOnline.value) {
        loadProducts();
    } else {
        filterProducts();
    }
};

const handleBarcodeScanned = async (barcode) => {
    // First try to find in current products
    let product = products.value.find(p => p.barcode === barcode);

    // If not found and online, search backend
    if (!product && isOnline.value) {
        try {
            const response = await axios.get(route('pos.products'), {
                params: { search: barcode }
            });
            if (response.data.products && response.data.products.length > 0) {
                product = response.data.products.find(p => p.barcode === barcode);
                if (product) {
                    // Add to products list
                    products.value.push(product);
                    // Re-cache all products
                    await cacheProducts(products.value);
                }
            }
        } catch (error) {
            console.error('Failed to search product by barcode:', error);
        }
    }

    if (product) {
        addItem(product);
    }
};

useBarcode(handleBarcodeScanned);

const handleCheckout = async ({ cashReceived, paymentType, customerId }) => {
    if (cartItems.value.length === 0) return;

    isProcessing.value = true;
    checkoutError.value = '';

    const payload = {
        ...getCheckoutPayload(),
        cash_received: cashReceived,
        payment_type: paymentType || 'cash',
        customer_id: customerId || null,
    };


    try {
        if (isOnline.value) {
            const response = await axios.post(route('pos.checkout'), payload);

            if (response.data.success) {
                lastOrder.value = response.data.order;
                showReceipt.value = true;
                toast.success(t('toast.saleCompleted'));
                clearCart();
                // Clear snapshot for this tab after successful checkout
                const activeTab = tabs.value.find(t => t.id === activeTabId.value);
                if (activeTab) { activeTab.snapshot = null; }

                // Delete held order if it was retrieved from hold
                if (currentHeldOrderId.value) {
                    try {
                        await axios.delete(route('pos.delete-held-order', currentHeldOrderId.value));
                        currentHeldOrderId.value = null;
                        await loadHeldOrders();
                    } catch (error) {
                        console.error('Failed to delete held order:', error);
                    }
                }

                await loadProducts(); // Refresh stock levels
            } else {
                checkoutError.value = response.data.error;
            }
        } else {
            // Queue for offline sync
            const offlineSale = await queueSale(payload);
            lastOrder.value = {
                order_number: offlineSale.offline_id,
                total: total.value,
                change: cashReceived - total.value,
                offline: true,
            };
            showReceipt.value = true;
            toast.info(t('toast.savedOffline'));
            clearCart();

            // Deduct from local stock cache
            cartItems.value.forEach(item => {
                const product = products.value.find(p => p.id === item.product_id);
                if (product) {
                    product.stock -= item.quantity;
                }
            });
        }
    } catch (error) {
        checkoutError.value = error.response?.data?.error || error.response?.data?.message || t('toast.serverError');
        toast.error(checkoutError.value);
    } finally {
        isProcessing.value = false;
    }
};

const closeReceipt = () => {
    showReceipt.value = false;
    lastOrder.value = null;
};

const loadHeldOrders = async () => {
    try {
        const response = await axios.get(route('pos.held-orders'));
        heldOrders.value = response.data.held_orders || [];
    } catch (error) {
        console.error('Failed to load held orders:', error);
    }
};

const handleHold = async () => {
    if (cartItems.value.length === 0) return;

    showHoldModal.value = true;
};

const confirmHold = async () => {
    if (cartItems.value.length === 0) return;

    isProcessing.value = true;
    checkoutError.value = '';

    const payload = {
        name: holdOrderName.value || null,
        items: cartItems.value.map(item => ({
            product_id: item.product_id,
            name: item.name,
            price: item.original_price || item.price,
            price_in_system_currency: item.price,
            currency: item.original_currency || props.currency,
            quantity: item.quantity,
        })),
        total: total.value,
    };

    try {
        const response = await axios.post(route('pos.hold'), payload);

        if (response.data.success) {
            clearCart();
            holdOrderName.value = '';
            showHoldModal.value = false;
            toast.success(t('toast.orderHeld'));
            await loadHeldOrders();
        } else {
            checkoutError.value = response.data.error || 'Failed to hold order';
        }
    } catch (error) {
        checkoutError.value = error.response?.data?.error || error.response?.data?.message || 'Failed to hold order';
        toast.error(checkoutError.value);
    } finally {
        isProcessing.value = false;
    }
};

const retrieveHeldOrder = async (heldOrder) => {
    try {
        const response = await axios.get(route('pos.retrieve-held-order', heldOrder.id));
        if (response.data.success) {
            clearCart();
            currentHeldOrderId.value = heldOrder.id;
            const order = response.data.held_order;
            order.items.forEach(item => {
                const product = products.value.find(p => p.id === item.product_id);
                if (product) {
                    addItem({
                        ...product,
                        price: item.price_in_system_currency || item.price,
                    });
                    // Restore the saved quantity (addItem always starts at 1)
                    if (item.quantity > 1) {
                        updateQuantity(item.product_id, item.quantity, null);
                    }
                }
            });
            showHeldOrders.value = false;
        }
    } catch (error) {
        checkoutError.value = error.response?.data?.error || 'Failed to retrieve held order';
    }
};

const deleteHeldOrder = async (heldOrder) => {
    if (!await confirm.delete(t('pos.confirmDeleteHeldOrder'))) {
        return;
    }

    try {
        await axios.delete(route('pos.delete-held-order', heldOrder.id));
        await loadHeldOrders();
        toast.success(t('toast.heldOrderDeleted'));
    } catch (error) {
        checkoutError.value = error.response?.data?.error || 'Failed to delete held order';
        toast.error(checkoutError.value);
    }
};

watch(isOnline, (online) => {
    if (online) {
        loadProducts();
    }
});

onMounted(() => {
    setExchangeRates(props.exchangeRates || {}, props.currency || 'USD');
    loadProducts();
    loadHeldOrders();
});
</script>

<template>
    <Head :title="t('pos.title')" />

        <div class="flex h-[calc(100vh)] overflow-hidden bg-bg">
            <ToastContainer />
            <ConfirmDialog />
            <!-- Left Panel: Products -->
            <div class="flex flex-1 flex-col overflow-hidden">
                <!-- Header with search and categories -->
                <div class="bg-surface p-4 border border-line">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <h1 class="font-display text-xl font-normal text-fg">
                                {{ branch.name }}
                            </h1>
                            <OfflineIndicator />
                        </div>

                        <div class="flex items-center">

                            <div class="me-2 inline-flex items-center">
                                <AttendanceToggle />
                            </div>

                            <div class="me-2 inline-flex items-center rounded-lg bg-hover-wash ">
                                <ThemeToggle :showLabel="false" />
                            </div>
                            <div class="me-2 inline-flex items-center rounded-lg bg-hover-wash ">
                                <LanguageToggle :showLabel="false" />
                            </div>

                            <button
                                type="button"
                                class="me-2 inline-flex items-center gap-2 rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-fg transition-colors hover:bg-line"
                                :title="t('pos.calculator.open')"
                                :aria-label="t('pos.calculator.open')"
                                @click="showCalculator = !showCalculator"
                            >
                                <Calculator class="h-5 w-5" />
                            </button>
                            <button
                                type="button"
                                @click="showHeldOrders = true"
                                class="inline-flex items-center gap-2 rounded-lg bg-status-warn px-4 py-2 text-sm font-medium text-surface transition-colors hover:opacity-90 focus:outline-hidden focus:ring-2 focus:ring-line disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="isProcessing"
                                :title="t('pos.hold')"
                                >
                                <ClipboardList class="h-5 w-5" />
                            </button>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                :title="t('nav.logout')"
                                class="inline-flex items-center gap-2 rounded-lg bg-danger px-4 py-2 text-sm font-medium text-paper transition-colors hover:opacity-90 focus:outline-hidden focus:ring-2 focus:ring-ring-danger disabled:opacity-50 disabled:cursor-not-allowed ms-2"
                            >
                                <LogOut class="h-5 w-5" />
                            </Link>
                        </div>

                    </div>
                    <!-- Multi-tab bar -->
                    <div class="mb-3 flex items-center gap-1 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            @click="switchTab(tab.id)"
                            class="group flex shrink-0 items-center gap-1 rounded-t-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="tab.id === activeTabId
                                ? 'border-b-surface bg-surface text-accent'
                                : 'border-transparent bg-hover-wash text-muted hover:bg-line'"
                        >
                            {{ tab.name }}
                            <span v-if="tabs.length > 1"
                                @click.stop="closeTab(tab.id)"
                                class="ms-1 rounded-full p-0.5 text-faint hover:bg-status-danger-wash hover:text-status-danger"
                            >
                                <X class="h-3 w-3" />
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="showTabNameModal = true"
                            class="flex shrink-0 items-center gap-1 rounded-lg bg-hover-wash px-2 py-1.5 text-sm text-muted hover:bg-accent-wash hover:text-accent"
                            :title="t('pos.newOrder')"
                        >
                            <Plus class="h-4 w-4" />
                        </button>
                    </div>

                    <SearchBar
                        :placeholder="t('pos.searchProducts')"
                        @search="handleSearch"
                    />

                    <CategoryTabs
                        :categories="categories"
                        :selected="selectedCategory"
                        @change="handleCategoryChange"
                    />
                </div>

                <!-- Products Grid -->
                <div class="flex-1 overflow-auto p-4">
                    <ProductGrid
                        :products="filteredProducts"
                        :loading="isLoading"
                        :currency="currency"
                        @add-to-cart="addItem"
                    />
                </div>
            </div>

            <!-- Right Panel: Cart -->
            <CartPanel
                :currency="currency"
                :checkout-error="checkoutError"
                :is-processing="isProcessing"
                :exchange-rates="exchangeRates"
                :customers="customers"
                @checkout="handleCheckout"
                @hold="handleHold"
                @open-customers="openCustomersPage"
            />
        </div>

        <FloatingCalculator
            :open="showCalculator"
            :total="total"
            :subtotal="subtotal"
            :tax-rate="taxRate"
            :currency="currency"
            @close="showCalculator = false"
        />

        <!-- Receipt Modal -->
        <ReceiptModal
            :show="showReceipt"
            :order="lastOrder"
            :currency="currency"
            @close="closeReceipt"
        />

        <!-- Hold Order Modal -->
        <div
            v-if="showHoldModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showHoldModal = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-pop">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">
                    {{ t('pos.holdOrder') }}
                </h3>
                <div class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-muted">
                        {{ t('pos.orderName') }} ({{ t('common.optional') }})
                    </label>
                    <input
                        v-model="holdOrderName"
                        type="text"
                        class="block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                        :placeholder="t('pos.orderNamePlaceholder')"
                    />
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="showHoldModal = false"
                        class="flex-1 rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line-strong"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="confirmHold"
                        :disabled="isProcessing"
                        class="flex-1 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink hover:bg-gold-deep disabled:opacity-50"
                    >
                        {{ t('pos.hold') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Held Orders Panel -->
        <div
            v-if="showHeldOrders"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showHeldOrders = false"
        >
            <div class="w-full max-w-7xl rounded-2xl bg-surface p-6 shadow-pop">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-display text-lg font-normal text-fg">
                        {{ t('pos.heldOrders') }}
                    </h3>
                    <button
                        type="button"
                        @click="showHeldOrders = false"
                        class="text-faint hover:text-fg"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>
                <div class="max-h-96 space-y-2 overflow-auto">
                    <div
                        v-for="order in heldOrders"
                        :key="order.id"
                        class="flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="flex-1">
                            <p class="font-medium text-fg">{{ order.name }}</p>
                            <p class="text-sm text-muted">
                                {{ formatCurrency(order.total, currency) }} • {{ order.created_at }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="retrieveHeldOrder(order)"
                                class="rounded-lg bg-gold px-3 py-1 text-sm text-ink hover:bg-gold-deep"
                            >
                                {{ t('pos.retrieve') }}
                            </button>
                            <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" type="button" @click="deleteHeldOrder(order)" />
                        </div>
                    </div>
                    <div v-if="heldOrders.length === 0" class="py-8 text-center text-muted">
                        {{ t('pos.noHeldOrders') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Name Modal -->
        <div
            v-if="showTabNameModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showTabNameModal = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-pop">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-lg font-normal text-fg">
                        {{ t('pos.enterTabName') }}
                    </h3>
                    <button
                        type="button"
                        @click="showTabNameModal = false"
                        class="text-faint hover:text-fg"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>
                <div class="mb-6">
                    <label for="tabName" class="mb-2 block text-sm font-medium text-muted">
                        {{ t('pos.tabName') }}
                    </label>
                    <input
                        id="tabName"
                        v-model="newTabName"
                        type="text"
                        class="block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                        :placeholder="t('pos.enterTabNamePlaceholder')"
                        @keyup.enter="confirmTabName"
                        autocomplete="off"
                    />
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="showTabNameModal = false"
                        class="flex-1 rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line-strong"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="confirmTabName"
                        class="flex-1 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink hover:bg-gold-deep"

                    >
                        {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </div>

</template>
