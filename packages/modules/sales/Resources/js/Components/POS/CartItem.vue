<script setup>
import { useCurrency } from '@/Composables/useCurrency';
import { Minus, Plus, Trash2 } from 'lucide-vue-next';
import Select from '@tessera/tessera-ui/Components/Select.vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    currency: {
        type: String,
        default: 'USD',
    },
});

const emit = defineEmits(['increment', 'decrement', 'remove', 'change-unit']);

const { formatCurrency } = useCurrency();

const onUnitChange = (unitId) => {
    emit('change-unit', unitId === '' || unitId === null ? null : Number(unitId));
};
</script>

<template>
    <div class="flex items-center gap-1 rounded border bg-hover-wash p-1.5">
        <!-- Product Image -->
        <img
            :src="item.image_url"
            :alt="item.name"
            class="h-8 w-8 rounded object-cover"
        />

        <!-- Product Info -->
        <div class="flex-1 min-w-0">
            <h4 class="truncate text-xs font-medium text-fg">
                {{ item.name }}
                <span v-if="item.unit_abbreviation" class="text-[10px] font-normal text-accent">
                    ({{ item.unit_abbreviation }})
                </span>
            </h4>
            <div class="flex items-center gap-1">
                <p class="text-xs text-muted">
                    {{ formatCurrency(item.price, currency) }}
                </p>
                <!-- Unit Selector (only if multiple sell units) -->
                <Select
                    v-if="item.sell_units && item.sell_units.length> 1"
                    size="sm"
                    :model-value="item.unit_id"
                    class="w-20"
                    @update:model-value="onUnitChange"
                >
                    <option
                        v-for="unit in item.sell_units"
                        :key="unit.id"
                        :value="unit.id"
                    >
                        {{ unit.abbreviation }}
                    </option>
                </Select>
            </div>
        </div>

        <!-- Quantity Controls -->
        <div class="flex items-center gap-1">
            <button
                type="button"
                @click="emit('decrement')"
                class="flex h-5 w-5 items-center justify-center rounded-full bg-hover-wash text-muted transition-colors hover:bg-line-strong"
            >
                <Minus class="h-3 w-3" />
            </button>

            <span class="w-5 text-center text-xs font-medium text-fg">
                {{ item.quantity }}
            </span>

            <button
                type="button"
                @click="emit('increment')"
                :disabled="item.quantity >= item.stock"
                class="flex h-5 w-5 items-center justify-center rounded-full bg-hover-wash text-muted transition-colors hover:bg-line-strong disabled:cursor-not-allowed disabled:opacity-50"
            >
                <Plus class="h-3 w-3" />
            </button>
        </div>

        <!-- Line Total -->
        <div class="text-end min-w-[3.75rem]">
            <p class="text-xs font-semibold text-fg">
                {{ formatCurrency(item.price * item.quantity, currency) }}
            </p>
        </div>

        <!-- Remove Button -->
        <button
            type="button"
            @click="emit('remove')"
            class="ms-1 text-xs text-status-danger hover:underline"
        >
            <Trash2 class="h-4 w-4" />
        </button>
    </div>
</template>
