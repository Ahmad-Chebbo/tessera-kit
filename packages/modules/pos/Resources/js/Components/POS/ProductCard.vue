<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    currency: {
        type: String,
        default: 'USD',
    },
});

const emit = defineEmits(['add-to-cart']);

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const isOutOfStock = computed(() => props.product.stock <= 0);
const isLowStock = computed(() => props.product.stock > 0 && props.product.stock <= 5);
const isUnlimitedQuantity = computed(() => props.product.unlimited_quantity);

const stockStatus = computed(() => {
    if (isUnlimitedQuantity.value) return { text: t('products.unlimitedQuantity'), class: 'border border-status-success-border bg-surface text-status-success' };
    if (isOutOfStock.value) return { text: t('pos.outOfStock'), class: 'border border-status-danger-border bg-surface text-status-danger' };
    if (isLowStock.value) return { text: `${props.product.stock} ${t('pos.lowStock')}`, class: 'border border-status-warn-border bg-surface text-status-warn' };
    return { text: `${props.product.stock} ${t('pos.inStock')}`, class: 'border border-status-success-border bg-surface text-status-success' };
});

const handleClick = () => {
    if (!isOutOfStock.value || isUnlimitedQuantity.value) {
        emit('add-to-cart', props.product);
    }
};
</script>

<template>
    <button
        type="button"
        @click="handleClick"
        :disabled="isOutOfStock && !isUnlimitedQuantity"
        class="group relative flex flex-col overflow-hidden rounded-lg border bg-field transition-all hover:shadow-lift focus:outline-hidden focus:ring-2 focus:ring-ring-accent disabled:cursor-not-allowed disabled:opacity-60"
        :class="{ 'hover:border-gold-on': !isOutOfStock }"
    >
        <!-- Product Image -->
        <div class="relative aspect-square w-full overflow-hidden bg-hover-wash">
            <img
                :src="product.image_url"
                :alt="product.name"
                class="h-full w-full object-cover transition-transform group-hover:scale-105"
                loading="lazy"
            />
            <!-- Stock Badge -->
            <span
                class="absolute end-2 top-2 rounded-full px-2 py-0.5 text-xs font-medium"
                :class="stockStatus.class"
            >
                {{ stockStatus.text }}
            </span>
        </div>

        <!-- Product Info -->
        <div class="flex flex-1 flex-col p-3">
            <h3 class="mb-1 line-clamp-2 text-sm font-medium text-fg">
                {{ product.name }}
            </h3>
            <p v-if="product.category_name" class="mb-2 text-xs text-muted">
                {{ product.category_name }}
            </p>
            <div class="mt-auto">
                <p class="text-lg font-bold text-accent">
                    {{ formatCurrency(product.price, product.currency) }}
                </p>
                <p v-if="product.converted_price" class="text-xs text-muted">
                    {{ formatCurrency(product.converted_price, 'USD') }}
                </p>
            </div>
        </div>

        <!-- Hover Overlay -->
        <div
            v-if="!isOutOfStock || isUnlimitedQuantity"
            class="absolute inset-0 flex items-center justify-center bg-transparent transition-all group-hover:bg-accent-wash"
        >
            <span class="rounded-full bg-gold px-4 py-2 text-sm font-medium text-ink opacity-0 transition-opacity group-hover:opacity-100">
                {{ t('pos.addToCart') }}
            </span>
        </div>
    </button>
</template>
