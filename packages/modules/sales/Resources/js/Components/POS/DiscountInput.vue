<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    currency: {
        type: String,
        default: 'USD',
    },
});

const emit = defineEmits(['apply', 'cancel']);

const { t } = useI18n();

const discountType = ref('percentage');
const discountValue = ref('');

const handleApply = () => {
    const value = parseFloat(discountValue.value) || 0;
    if (value > 0) {
        emit('apply', {
            type: discountType.value,
            value: value,
        });
    }
};
</script>

<template>
    <div class="rounded-lg bg-hover-wash p-3">
        <h4 class="mb-3 font-medium text-fg">
            {{ t('pos.applyDiscount') }}
        </h4>

        <!-- Discount Type -->
        <div class="mb-3 flex gap-2">
            <button
                type="button"
                @click="discountType = 'percentage'"
                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                :class="discountType === 'percentage'
                    ? 'bg-gold text-ink'
                    : 'bg-hover-wash text-muted hover:bg-line-strong'"
            >
                {{ t('pos.percentage') }} (%)
            </button>
            <button
                type="button"
                @click="discountType = 'fixed'"
                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                :class="discountType === 'fixed'
                    ? 'bg-gold text-ink'
                    : 'bg-hover-wash text-muted hover:bg-line-strong'"
            >
                {{ t('pos.fixed') }}
            </button>
        </div>

        <!-- Discount Value -->
        <div class="mb-3">
            <div class="relative">
                <input
                    v-model="discountValue"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="discountType === 'percentage' ? 100 : undefined"
                    class="block w-full rounded-lg border border-line bg-field p-2.5 pe-10 text-fg focus:border-gold-on focus:ring-ring-accent"
                    :placeholder="discountType === 'percentage' ? '10' : '5.00'"
                />
                <span class="absolute inset-y-0 end-0 flex items-center pe-3 text-muted">
                    {{ discountType === 'percentage' ? '%' : currency }}
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-2">
            <button
                type="button"
                @click="emit('cancel')"
                class="flex-1 rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line-strong"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="button"
                @click="handleApply"
                class="flex-1 rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink hover:bg-gold-deep"
            >
                {{ t('common.confirm') }}
            </button>
        </div>
    </div>
</template>
