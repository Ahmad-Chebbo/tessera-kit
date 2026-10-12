import { ref, computed } from 'vue';

const cartItems = ref([]);
const discountType = ref(null);
const discountValue = ref(0);
const couponCode = ref('');
const selectedCustomerId = ref(null);
const paymentType = ref('cash');
const rulesPreview = ref(null);
const exchangeRates = ref({});
const systemCurrency = ref('USD');

export function useCart() {
    const setExchangeRates = (rates, currency) => {
        exchangeRates.value = rates;
        systemCurrency.value = currency;
    };

    const convertPrice = (price, fromCurrency, toCurrency) => {
        if (fromCurrency === toCurrency) {
            return price;
        }

        const rateKey = `${fromCurrency}_${toCurrency}`;
        const inverseKey = `${toCurrency}_${fromCurrency}`;

        if (exchangeRates.value[rateKey]) {
            return price * exchangeRates.value[rateKey];
        }

        if (exchangeRates.value[inverseKey]) {
            return price / exchangeRates.value[inverseKey];
        }

        return price;
    };

    const addItem = (product, unitId = null) => {
        // Find by product_id + unit_id combination
        const existingItem = cartItems.value.find(
            item => item.product_id === product.id && item.unit_id === unitId
        );

        if (existingItem) {
            if (existingItem.quantity < product.stock || product.unlimited_quantity) {
                existingItem.quantity++;
            }
        } else {
            if (product.stock > 0 || product.unlimited_quantity) {
                const productCurrency = product.currency || 'USD';
                const originalPrice = product.price;
                const priceInSystemCurrency = convertPrice(originalPrice, productCurrency, systemCurrency.value);

                // Find unit info if a unit is selected
                let unitInfo = null;
                if (unitId && product.sell_units) {
                    unitInfo = product.sell_units.find(u => u.id === unitId);
                }

                cartItems.value.push({
                    product_id: product.id,
                    name: product.name,
                    price: priceInSystemCurrency,
                    original_price: originalPrice,
                    original_currency: productCurrency,
                    cost_price: product.cost_price,
                    currency: systemCurrency.value,
                    image_url: product.image_url,
                    stock: product.stock,
                    quantity: 1,
                    unit_id: unitId,
                    unit_name: unitInfo?.name || null,
                    unit_abbreviation: unitInfo?.abbreviation || null,
                    unit_to_base_factor: unitInfo?.to_base_factor || null,
                    sell_units: product.sell_units || [],
                });
            }
        }
    };

    const removeItem = (productId, unitId = null) => {
        const index = cartItems.value.findIndex(
            item => item.product_id === productId && item.unit_id === unitId
        );
        if (index !== -1) {
            cartItems.value.splice(index, 1);
        }
    };

    const updateQuantity = (productId, quantity, unitId = null) => {
        const item = cartItems.value.find(
            i => i.product_id === productId && i.unit_id === unitId
        );
        if (item && !item.unlimited_quantity) {
            const newQuantity = Math.max(0.01, Math.min(quantity, item.stock));
            item.quantity = newQuantity;
        }
    };

    const changeUnit = (productId, oldUnitId, newUnitId) => {
        const item = cartItems.value.find(
            i => i.product_id === productId && i.unit_id === oldUnitId
        );
        if (!item) return;

        const unitInfo = (item.sell_units || []).find(u => u.id === newUnitId);
        item.unit_id = newUnitId || null;
        item.unit_name = unitInfo?.name || null;
        item.unit_abbreviation = unitInfo?.abbreviation || null;
        item.unit_to_base_factor = unitInfo?.to_base_factor || null;
    };

    const incrementQuantity = (productId, unitId = null) => {
        const item = cartItems.value.find(
            i => i.product_id === productId && i.unit_id === unitId
        );
        if (item && (item.quantity < item.stock || !item.unlimited_quantity)) {
            item.quantity++;
        }
    };

    const decrementQuantity = (productId, unitId = null) => {
        const item = cartItems.value.find(
            i => i.product_id === productId && i.unit_id === unitId
        );
        if (item && item.quantity > 1) {
            item.quantity--;
        }
    };

    const clearCart = () => {
        cartItems.value = [];
        discountType.value = null;
        discountValue.value = 0;
        couponCode.value = '';
        rulesPreview.value = null;
        selectedCustomerId.value = null;
        paymentType.value = 'cash';
    };

    const snapshotCart = () => ({
        items: JSON.parse(JSON.stringify(cartItems.value)),
        discountType: discountType.value,
        discountValue: discountValue.value,
        couponCode: couponCode.value,
        customerId: selectedCustomerId.value,
        paymentType: paymentType.value,
    });

    const restoreCart = (snapshot) => {
        cartItems.value = snapshot?.items ? JSON.parse(JSON.stringify(snapshot.items)) : [];
        discountType.value = snapshot?.discountType ?? null;
        discountValue.value = snapshot?.discountValue ?? 0;
        couponCode.value = snapshot?.couponCode ?? '';
        selectedCustomerId.value = snapshot?.customerId ?? null;
        paymentType.value = snapshot?.paymentType ?? 'cash';
        rulesPreview.value = null;
    };

    const setDiscount = (type, value) => {
        discountType.value = type;
        discountValue.value = value;
    };

    const clearDiscount = () => {
        discountType.value = null;
        discountValue.value = 0;
    };

    const subtotal = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    });

    const rulesDiscount = computed(() => Math.min(rulesPreview.value?.total ?? 0, subtotal.value));

    const discountAmount = computed(() => {
        if (!discountType.value || discountValue.value <= 0) {
            return 0;
        }

        const payable = subtotal.value - rulesDiscount.value;

        if (discountType.value === 'percentage') {
            return Math.round(payable * (discountValue.value / 100) * 100) / 100;
        }

        return Math.min(discountValue.value, payable);
    });

    const total = computed(() => {
        return Math.max(0, subtotal.value - rulesDiscount.value - discountAmount.value);
    });

    const setRulesPreview = (preview) => {
        rulesPreview.value = preview;
    };

    const rulesForItem = (index) => rulesPreview.value?.lines?.[index] ?? null;

    const itemCount = computed(() => {
        return cartItems.value.reduce((sum, item) => sum + item.quantity, 0);
    });

    const isEmpty = computed(() => {
        return cartItems.value.length === 0;
    });

    const getCheckoutPayload = () => {
        return {
            items: cartItems.value.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_id: item.unit_id,
                unit_price: item.price,
                original_price: item.original_price,
                original_currency: item.original_currency,
            })),
            discount_type: discountType.value,
            discount_value: discountValue.value,
            coupon_code: couponCode.value.trim() || null,
        };
    };

    return {
        cartItems,
        couponCode,
        selectedCustomerId,
        paymentType,
        rulesPreview,
        rulesDiscount,
        setRulesPreview,
        rulesForItem,
        discountType,
        discountValue,
        addItem,
        removeItem,
        updateQuantity,
        changeUnit,
        incrementQuantity,
        decrementQuantity,
        clearCart,
        setDiscount,
        clearDiscount,
        subtotal,
        discountAmount,
        total,
        itemCount,
        isEmpty,
        getCheckoutPayload,
        setExchangeRates,
        snapshotCart,
        restoreCart,
    };
}
