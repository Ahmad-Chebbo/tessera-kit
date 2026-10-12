<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Calculator, Delete, GripHorizontal, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { addTax, applyDiscount, removeTax, roundMoney, settle, tryEvaluate } from '@/utils/calculator';

const props = defineProps({
    open: { type: Boolean, default: false },
    total: { type: Number, default: 0 },
    subtotal: { type: Number, default: 0 },
    taxRate: { type: Number, default: 0 },
    currency: { type: String, default: 'USD' },
});

const emit = defineEmits(['close']);
const { t } = useI18n();

const POSITION_KEY = 'pos-calculator-position';
const WIDTH = 304;

const expression = ref('');
const justEvaluated = ref(false);
const taxInput = ref(String(props.taxRate || ''));
const discountInput = ref('');
const paidInput = ref('');
const position = ref({ x: 24, y: 96 });
const root = ref(null);

const result = computed(() => tryEvaluate(expression.value));
const display = computed(() => (expression.value === '' ? '0' : expression.value));
const resultText = computed(() => (result.value === null ? '' : format(result.value)));
const settlement = computed(() => settle(props.total, paidInput.value));

const format = (value) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 10 }).format(value);
const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: props.currency || 'USD' }).format(value);

const OPERATOR_KEYS = ['+', '−', '×', '÷'];
const keypad = [
    ['C', '⌫', '(', ')'],
    ['7', '8', '9', '÷'],
    ['4', '5', '6', '×'],
    ['1', '2', '3', '−'],
    ['0', '.', '%', '+'],
];

const press = (key) => {
    if (key === 'C') {
        expression.value = '';
        justEvaluated.value = false;

        return;
    }

    if (key === '⌫') {
        expression.value = expression.value.slice(0, -1);
        justEvaluated.value = false;

        return;
    }

    if (justEvaluated.value) {
        justEvaluated.value = false;

        if (!OPERATOR_KEYS.includes(key) && key !== '%') {
            expression.value = '';
        }
    }

    const last = expression.value.slice(-1);

    if (OPERATOR_KEYS.includes(key) && OPERATOR_KEYS.includes(last)) {
        expression.value = expression.value.slice(0, -1) + key;

        return;
    }

    if (expression.value === '' && OPERATOR_KEYS.includes(key) && key !== '−') {
        return;
    }

    expression.value += key;
};

const equals = () => {
    if (result.value === null) {
        return;
    }

    expression.value = String(result.value);
    justEvaluated.value = true;
};

const setValue = (value) => {
    expression.value = String(roundMoney(value));
    justEvaluated.value = true;
};

const current = () => (result.value ?? 0);
const useTotal = () => setValue(props.total);
const useSubtotal = () => setValue(props.subtotal);
const plusTax = () => setValue(addTax(current(), Number(taxInput.value) || 0));
const minusTax = () => setValue(removeTax(current(), Number(taxInput.value) || 0));
const minusDiscount = () => setValue(applyDiscount(current(), Number(discountInput.value) || 0));

const KEY_MAP = { '*': '×', '/': '÷', '-': '−', ',': '.' };

const onKeydown = (event) => {
    if (event.target instanceof HTMLInputElement) {
        return;
    }

    if (event.key === 'Escape') {
        emit('close');

        return;
    }

    if (event.key === 'Enter' || event.key === '=') {
        event.preventDefault();
        equals();

        return;
    }

    if (event.key === 'Backspace') {
        press('⌫');

        return;
    }

    if (event.key === 'Delete' || event.key.toLowerCase() === 'c') {
        press('C');

        return;
    }

    const key = KEY_MAP[event.key] ?? event.key;

    if (/^[0-9.()%+−×÷]$/.test(key)) {
        event.preventDefault();
        press(key);
    }
};

// Dragging
let dragOffset = null;

const clamp = (x, y) => ({
    x: Math.min(Math.max(0, x), Math.max(0, window.innerWidth - WIDTH)),
    y: Math.min(Math.max(0, y), Math.max(0, window.innerHeight - 120)),
});

const startDrag = (event) => {
    dragOffset = { x: event.clientX - position.value.x, y: event.clientY - position.value.y };
    event.currentTarget.setPointerCapture?.(event.pointerId);
};

const drag = (event) => {
    if (dragOffset) {
        position.value = clamp(event.clientX - dragOffset.x, event.clientY - dragOffset.y);
    }
};

const endDrag = () => {
    if (!dragOffset) {
        return;
    }

    dragOffset = null;

    try {
        localStorage.setItem(POSITION_KEY, JSON.stringify(position.value));
    } catch {
        // Position is a convenience only.
    }
};

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(POSITION_KEY) ?? 'null');

        if (saved && Number.isFinite(saved.x) && Number.isFinite(saved.y)) {
            position.value = clamp(saved.x, saved.y);
        }
    } catch {
        // Fall back to the default position.
    }
});

watch(
    () => props.open,
    async (open) => {
        if (open) {
            await nextTick();
            root.value?.focus();
        }
    },
);

onBeforeUnmount(() => {
    dragOffset = null;
});
</script>

<template>
    <div
        v-if="open"
        ref="root"
        role="dialog"
        :aria-label="t('pos.calculator.title')"
        tabindex="0"
        class="fixed z-40 select-none rounded-2xl border border-line bg-surface shadow-pop outline-hidden"
        :style="{ left: `${position.x}px`, top: `${position.y}px`, width: `${WIDTH}px` }"
        @keydown="onKeydown"
    >
        <div
            class="flex cursor-move items-center justify-between gap-2 rounded-t-2xl border-b border-line bg-hover-wash px-3 py-2"
            @pointerdown="startDrag"
            @pointermove="drag"
            @pointerup="endDrag"
            @pointercancel="endDrag"
        >
            <span class="flex items-center gap-2 text-sm font-medium text-fg">
                <Calculator class="h-4 w-4 text-accent" />
                {{ t('pos.calculator.title') }}
            </span>
            <GripHorizontal class="h-4 w-4 text-faint" />
            <button type="button" class="text-muted hover:text-fg" :aria-label="t('common.close')" @pointerdown.stop @click="emit('close')">
                <X class="h-4 w-4" />
            </button>
        </div>

        <div class="space-y-3 p-3" dir="ltr">
            <div class="rounded-xl bg-field p-3 text-end">
                <p data-testid="expression" class="min-h-5 truncate text-sm text-muted">{{ display }}</p>
                <p data-testid="result" class="min-h-8 truncate font-display text-2xl text-fg">{{ resultText || '\u00a0' }}</p>
            </div>

            <div class="grid grid-cols-4 gap-2">
                <template v-for="row in keypad" :key="row.join('')">
                    <button
                        v-for="key in row"
                        :key="key"
                        type="button"
                        :data-key="key"
                        class="flex h-10 items-center justify-center rounded-lg text-base font-medium transition-colors"
                        :class="OPERATOR_KEYS.includes(key)
                            ? 'bg-accent-wash text-accent hover:bg-line'
                            : key === 'C' ? 'bg-status-danger-wash text-status-danger hover:bg-line' : 'bg-hover-wash text-fg hover:bg-line'"
                        @click="press(key)"
                    >
                        <Delete v-if="key === '⌫'" class="h-4 w-4" />
                        <template v-else>{{ key }}</template>
                    </button>
                </template>
                <button type="button" data-key="=" class="col-span-4 h-10 rounded-lg bg-gold text-base font-medium text-ink transition-colors hover:bg-gold-deep" @click="equals">=</button>
            </div>

            <div class="space-y-2 border-t border-line pt-3 text-xs" dir="auto">
                <div class="flex gap-2">
                    <button type="button" data-action="use-total" class="flex-1 rounded-lg bg-hover-wash px-2 py-1.5 font-medium text-fg hover:bg-line" @click="useTotal">
                        {{ t('pos.calculator.orderTotal') }}: {{ money(total) }}
                    </button>
                    <button type="button" data-action="use-subtotal" class="rounded-lg bg-hover-wash px-2 py-1.5 font-medium text-muted hover:bg-line" @click="useSubtotal">
                        {{ t('common.subtotal') }}
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <input v-model="taxInput" type="number" min="0" step="0.01" class="w-16 rounded-lg border border-line bg-field p-1.5 text-center" :aria-label="t('pos.calculator.taxRate')" />
                    <span class="text-muted">%</span>
                    <button type="button" data-action="add-tax" class="flex-1 rounded-lg bg-hover-wash px-2 py-1.5 font-medium text-fg hover:bg-line" @click="plusTax">{{ t('pos.calculator.addTax') }}</button>
                    <button type="button" data-action="remove-tax" class="flex-1 rounded-lg bg-hover-wash px-2 py-1.5 font-medium text-fg hover:bg-line" @click="minusTax">{{ t('pos.calculator.removeTax') }}</button>
                </div>

                <div class="flex items-center gap-2">
                    <input v-model="discountInput" type="number" min="0" max="100" step="0.01" class="w-16 rounded-lg border border-line bg-field p-1.5 text-center" :aria-label="t('pos.calculator.discountRate')" />
                    <span class="text-muted">%</span>
                    <button type="button" data-action="discount" class="flex-1 rounded-lg bg-hover-wash px-2 py-1.5 font-medium text-fg hover:bg-line" @click="minusDiscount">{{ t('pos.calculator.takeDiscount') }}</button>
                </div>

                <div class="rounded-lg bg-hover-wash p-2">
                    <label class="mb-1 block text-muted">{{ t('pos.calculator.customerPaid') }}</label>
                    <input v-model="paidInput" type="number" min="0" step="0.01" class="w-full rounded-lg border border-line bg-field p-1.5" :aria-label="t('pos.calculator.customerPaid')" />
                    <div class="mt-2 flex justify-between">
                        <span class="text-muted">{{ t('pos.change') }}</span>
                        <span data-testid="change" class="font-medium text-status-success">{{ money(settlement.change) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">{{ t('pos.calculator.remaining') }}</span>
                        <span data-testid="remaining" class="font-medium" :class="settlement.remaining > 0 ? 'text-status-warn' : 'text-fg'">{{ money(settlement.remaining) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
