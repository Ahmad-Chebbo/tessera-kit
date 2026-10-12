<script setup>
import { useI18n } from 'vue-i18n';
import ProductCard from './ProductCard.vue';
import { Inbox } from 'lucide-vue-next';

defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    currency: {
        type: String,
        default: 'USD',
    },
});

const emit = defineEmits(['add-to-cart']);

const { t } = useI18n();
</script>

<template>
    <div>
        <!-- Loading Skeleton -->
        <div
            v-if="loading"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <div
                v-for="n in 10"
                :key="n"
                class="animate-pulse rounded-2xl border bg-surface p-4"
            >
                <div class="mb-4 aspect-square rounded bg-hover-wash"></div>
                <div class="mb-2 h-4 rounded bg-hover-wash"></div>
                <div class="h-4 w-2/3 rounded bg-hover-wash"></div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="products.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <Inbox class="mb-4 h-16 w-16 text-faint" />
            <p class="text-lg font-medium text-fg">
                {{ t('common.noResults') }}
            </p>
            <p class="text-muted">
                {{ t('products.noProducts') }}
            </p>
        </div>

        <!-- Products Grid -->
        <div
            v-else
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <ProductCard
                v-for="product in products"
                :key="product.id"
                :product="product"
                :currency="currency"
                @add-to-cart="emit('add-to-cart', $event)"
            />
        </div>
    </div>
</template>
