<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { ArrowLeft, Info, TriangleAlert, X } from 'lucide-vue-next';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';

const props = defineProps({
    suppliers: Array,
    locations: Array,
    products: Array,
    companyCurrency: {
        type: String,
        default: 'USD',
    },
    exchangeRates: {
        type: Array,
        default: () => [],
    },
    availableCurrencies: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'product', label: t('purchases.product'), cellClass: 'px-0 py-3', headerClass: 'px-0 py-2', wrap: true },
    { key: 'qty', label: t('purchases.qty'), align: 'center', cellClass: 'px-0 py-3 text-fg', headerClass: 'px-0 py-2', wrap: true },
    { key: 'col2', label: '', align: 'center', cellClass: 'px-0 py-3 text-fg', headerClass: 'px-0 py-2', wrap: true },
    { key: 'total', label: t('common.total'), align: 'end', cellClass: 'px-0 py-3 font-medium text-fg', headerClass: 'px-0 py-2', wrap: true },
    { key: 'col4', label: '', align: 'end', cellClass: 'px-0 py-3', headerClass: 'px-0 py-2', wrap: true },
]);
const { formatCurrency } = useCurrency();

const form = useForm({
    supplier_id: '',
    location_id: '',
    currency: props.companyCurrency,
    reference_number: '',
    notes: '',
    items: [],
});

const newItem = ref({
    product_id: '',
    quantity: 1,
    unit_cost: '',
    unit_id: '',
});

const selectedProduct = computed(() => {
    return props.products.find(p => p.id === parseInt(newItem.value.product_id));
});

const availableBuyUnits = computed(() => {
    if (!selectedProduct.value) return [];
    return selectedProduct.value.buy_units || [];
});

/**
 * Find the exchange rate between two currencies using the rates list.
 * Tries direct match, then inverse.
 */
const findRate = (from, to) => {
    if (from === to) return 1;

    const direct = props.exchangeRates.find(r => r.from === from && r.to === to);
    if (direct) return direct.rate;

    const inverse = props.exchangeRates.find(r => r.from === to && r.to === from);
    if (inverse && inverse.rate > 0) return 1 / inverse.rate;

    return null;
};

/**
 * Convert a product's cost_price from the product's currency to the purchase currency.
 */
const convertCostToPurchaseCurrency = (product) => {
    if (!product) return '';
    const productCurrency = product.currency || props.companyCurrency;
    const purchaseCurrency = form.currency;

    if (productCurrency === purchaseCurrency) {
        return product.cost_price;
    }

    const rate = findRate(productCurrency, purchaseCurrency);
    if (rate !== null) {
        return Math.round(product.cost_price * rate * 100) / 100;
    }

    // No exchange rate available, return original
    return product.cost_price;
};

const currentExchangeRate = computed(() => {
    if (form.currency === props.companyCurrency) return null;
    const rate = findRate(form.currency, props.companyCurrency);
    return rate;
});

const onProductChange = () => {
    if (selectedProduct.value) {
        newItem.value.unit_cost = convertCostToPurchaseCurrency(selectedProduct.value);
        newItem.value.unit_id = '';
    }
};

// When purchase currency changes, re-convert costs for items already in the newItem
watch(() => form.currency, () => {
    if (selectedProduct.value) {
        newItem.value.unit_cost = convertCostToPurchaseCurrency(selectedProduct.value);
    }
    clearItems();
});

const addItem = () => {
    if (!newItem.value.product_id || !newItem.value.quantity || !newItem.value.unit_cost) return;

    const product = props.products.find(p => p.id === parseInt(newItem.value.product_id));
    const unitId = newItem.value.unit_id ? parseInt(newItem.value.unit_id) : null;
    const unit = unitId ? availableBuyUnits.value.find(u => u.unit_id === unitId) : null;

    form.items.push({
        product_id: parseInt(newItem.value.product_id),
        product_name: product.name,
        product_sku: product.sku,
        product_currency: product.currency || props.companyCurrency,
        quantity: parseFloat(newItem.value.quantity),
        unit_cost: parseFloat(newItem.value.unit_cost),
        unit_id: unitId,
        unit_name: unit?.unit?.name || null,
        unit_abbreviation: unit?.unit?.abbreviation || null,
    });

    newItem.value = { product_id: '', quantity: 1, unit_cost: '', unit_id: '' };
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const totalAmount = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity * item.unit_cost), 0);
});

const submit = () => {
    form.post(route('purchases.store'));
};

const clearItems = () => {
    form.items = [];
    newItem.value = { product_id: '', quantity: 1, unit_cost: '', unit_id: '' };
};
</script>

<template>
    <Head :title="t('purchases.createPurchase')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('purchases.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('purchases.createPurchase') }}</h1>
                    <p class="text-sm text-muted">{{ t('purchases.createNewPurchase') }}</p>
                </div>
            </div>
        </template>

        <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Purchase Details -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('purchases.purchaseDetails') }}</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="supplier_id" :value="t('purchases.supplier')" />
                            <Select
                                id="supplier_id"
                                v-model="form.supplier_id"
                                class="mt-1 w-full"
                                required>
                                <option value="">{{ t('purchases.selectSupplier') }}</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </Select>
                            <InputError :message="form.errors.supplier_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="location_id" :value="t('inventory.location')" />
                            <Select
                                id="location_id"
                                v-model="form.location_id"
                                class="mt-1 w-full"
                                required>
                                <option value="">{{ t('purchases.selectLocation') }}</option>
                                <option v-for="location in locations" :key="location.id" :value="location.id">
                                    {{ location.name }} ({{ location.type }})
                                </option>
                            </Select>
                            <InputError :message="form.errors.location_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="currency" :value="t('purchases.purchaseCurrency')" />
                            <Select
                                id="currency"
                                v-model="form.currency"
                                class="mt-1 w-full"
                                required>
                                <option v-for="curr in availableCurrencies" :key="curr.code" :value="curr.code">
                                    {{ curr.code }} - {{ curr.name }}
                                </option>
                            </Select>
                            <InputError :message="form.errors.currency" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="reference_number" :value="t('purchases.referenceNumber')" />
                            <TextInput
                                id="reference_number"
                                v-model="form.reference_number"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="t('purchases.optional')"
                            />
                            <InputError :message="form.errors.reference_number" class="mt-2" />
                        </div>
                    </div>

                    <!-- Exchange Rate Notice -->
                    <div
                        v-if="currentExchangeRate && form.currency !== companyCurrency"
                        class="mt-4 flex items-center gap-2 rounded-lg bg-accent-wash p-3"
                    >
                        <Info class="h-5 w-5 shrink-0 text-status-info" />
                        <p class="text-sm text-status-info">
                            {{ t('purchases.exchangeRateInfo') }}: 1 {{ form.currency }} = {{ currentExchangeRate.toFixed(4) }} {{ companyCurrency }}
                        </p>
                    </div>
                    <div
                        v-else-if="form.currency !== companyCurrency && !currentExchangeRate"
                        class="mt-4 flex items-center gap-2 rounded-lg bg-status-warn-wash p-3"
                    >
                        <TriangleAlert class="h-5 w-5 shrink-0 text-status-warn" />
                        <p class="text-sm text-status-warn">
                            {{ t('purchases.noExchangeRate') }}
                        </p>
                    </div>
                </div>

                <!-- Add Products -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('purchases.addProducts') }}</h3>

                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <Select
                                v-model="newItem.product_id"
                                @change="onProductChange"
                                class="w-full">
                                <option value="">{{ t('purchases.selectProduct') }}</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} {{ product.sku ? `(${product.sku})` : '' }}
                                </option>
                            </Select>
                        </div>
                        <div v-if="availableBuyUnits.length > 0" class="w-32">
                            <Select
                                v-model="newItem.unit_id"
                                class="w-full">
                                <option value="">{{ t('units.baseUnit') }}</option>
                                <option v-for="pu in availableBuyUnits" :key="pu.unit_id" :value="pu.unit_id">
                                    {{ pu.unit?.name }} ({{ pu.unit?.abbreviation }})
                                </option>
                            </Select>
                        </div>
                        <div class="w-24">
                            <TextInput
                                v-model="newItem.quantity"
                                type="number"
                                min="0.0001"
                                step="any"
                                class="block w-full"
                                :placeholder="t('purchases.qty')"
                            />
                        </div>
                        <div class="w-32">
                            <TextInput
                                v-model="newItem.unit_cost"
                                type="number"
                                step="0.01"
                                min="0"
                                class="block w-full"
                                :placeholder="`${t('purchases.cost')} (${form.currency})`"
                            />
                        </div>
                        <button
                            type="button"
                            @click="addItem"
                            :disabled="!newItem.product_id || !newItem.unit_cost"
                            class="rounded-lg bg-gold px-4 py-2 text-ink hover:bg-gold-deep disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ t('purchases.addItem') }}
                        </button>
                    </div>

                    <!-- Product currency hint -->
                    <p
                        v-if="selectedProduct && selectedProduct.currency && selectedProduct.currency !== form.currency"
                        class="mt-2 text-xs text-muted"
                    >
                        {{ t('purchases.productCostIn') }} {{ selectedProduct.currency }}: {{ formatCurrency(selectedProduct.cost_price, selectedProduct.currency) }}
                    </p>

                    <InputError :message="form.errors.items" class="mt-2" />

                    <!-- Items List -->
                    <div v-if="form.items.length > 0" class="mt-6">
                        <DataTable
                            :columns="tableColumns"
                            :rows="form.items"
                            :row-key="(item, index) => index"
                            :card="false"
                        >
                            <template #cell-product="{ row: item, index: index }">
                                <p class="font-medium text-fg">{{ item.product_name }}</p>
                                <p v-if="item.product_sku" class="text-sm text-muted">{{ item.product_sku }}</p>
                            </template>
                            <template #cell-qty="{ row: item, index: index }">
                                {{ item.quantity }}
                                <span v-if="item.unit_abbreviation" class="text-xs text-accent">
                                    {{ item.unit_abbreviation }}
                                </span>
                            </template>
                            <template #cell-col2="{ row: item, index: index }">
                                {{ formatCurrency(item.unit_cost, form.currency) }}
                            </template>
                            <template #head-col2>
                                {{ t('purchases.unitCost') }} ({{ form.currency }})
                            </template>
                            <template #cell-total="{ row: item, index: index }">
                                {{ formatCurrency(item.quantity * item.unit_cost, form.currency) }}
                            </template>
                            <template #cell-col4="{ row: item, index: index }">
                                <button
                                    type="button"
                                    @click="removeItem(index)"
                                    class="text-status-danger hover:underline"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </template>
                        </DataTable>
                    </div>

                    <div v-else class="mt-6 rounded-lg border-2 border-dashed border-line p-8 text-center">
                        <p class="text-muted">{{ t('purchases.noProductsAdded') }}</p>
                    </div>
                </div>
            </div>

            <!-- Right Column - Summary -->
            <div class="space-y-6">
                <div class="sticky top-24 rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('purchases.orderSummary') }}</h3>

                    <div class="space-y-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">{{ t('purchases.totalProducts') }}</span>
                            <span class="font-medium text-fg">{{ form.items.length }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">{{ t('purchases.totalUnits') }}</span>
                            <span class="font-medium text-fg">
                                {{ form.items.reduce((sum, item) => sum + item.quantity, 0) }}
                            </span>
                        </div>
                        <div class="border-t border-line pt-4">
                            <div class="flex justify-between">
                                <span class="text-lg font-medium text-fg">{{ t('purchases.totalAmount') }}</span>
                                <span class="text-lg font-bold text-fg">
                                    {{ formatCurrency(totalAmount, form.currency) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <InputLabel for="notes" :value="t('common.notes')" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                            :placeholder="t('purchases.optionalNotes')"
                        ></textarea>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <Link
                            :href="route('purchases.index')"
                            class="flex-1 rounded-lg bg-hover-wash px-4 py-2.5 text-center text-sm font-medium text-muted hover:bg-line"
                        >
                            {{ t('common.cancel') }}
                        </Link>
                        <PrimaryButton
                            :disabled="form.processing || form.items.length === 0"
                            class="flex-1 justify-center"
                        >
                            {{ t('purchases.createOrder') }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </form>
    </SidebarLayout>
</template>
