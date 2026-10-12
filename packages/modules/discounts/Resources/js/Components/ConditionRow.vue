<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';
import { changeField, changeOperator } from '@modules/Discounts/Resources/js/Components/conditionTree';

const props = defineProps({
    node: { type: Object, required: true },
    facts: { type: Array, required: true },
    lookups: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:node', 'remove']);
const { t, tm } = useI18n();

const fact = computed(() => props.facts.find((candidate) => candidate.key === props.node.field));
const isList = computed(() => ['in', 'not_in'].includes(props.node.operator));
const isRange = computed(() => props.node.operator === 'between');

const update = (changes) => emit('update:node', { ...props.node, ...changes });

const onField = (key) => emit('update:node', changeField(props.node, props.facts.find((candidate) => candidate.key === key)));
const onOperator = (operator) => emit('update:node', changeOperator(props.node, fact.value, operator));

const options = computed(() => {
    const current = fact.value;

    if (!current) {
        return [];
    }

    if (props.lookups[current.key]) {
        return props.lookups[current.key].map((record) => ({ value: record.id, label: record.name }));
    }

    if (current.key === 'context.weekday') {
        return tm('discounts.weekdays').map((label, index) => ({ value: index, label }));
    }

    if (current.key === 'context.payment_type') {
        return current.options.map((value) => ({ value, label: t(`discounts.payment.${value}`) }));
    }

    return [];
});

const labelOf = (value) => options.value.find((option) => String(option.value) === String(value))?.label ?? value;
const available = computed(() => options.value.filter((option) => !(props.node.value ?? []).some((picked) => String(picked) === String(option.value))));

const addPicked = (value) => {
    if (value !== '' && value !== null) {
        update({ value: [...(props.node.value ?? []), value] });
    }
};
const removePicked = (value) => update({ value: props.node.value.filter((picked) => String(picked) !== String(value)) });
const setRange = (index, value) => update({ value: props.node.value.map((entry, i) => (i === index ? value : entry)) });
const setWindow = (value) => update({ params: { ...(props.node.params ?? {}), window_days: value === '' ? undefined : Number(value) } });

const valueKind = computed(() => {
    if (fact.value?.type === 'bool') {
        return 'bool';
    }

    if (options.value.length) {
        return 'options';
    }

    return fact.value?.type ?? 'number';
});
</script>

<template>
    <div class="flex flex-wrap items-start gap-2 rounded-lg border border-line bg-bg p-2">
        <Select :model-value="node.field" size="sm" class="w-64" :placeholder="t('discounts.pickField')" @update:model-value="onField">
            <option v-for="candidate in facts" :key="candidate.key" :value="candidate.key">{{ t(`discounts.facts.${candidate.key}`) }}</option>
        </Select>

        <Select v-if="fact" :model-value="node.operator" size="sm" class="w-40" @update:model-value="onOperator">
            <option v-for="operator in fact.operators" :key="operator" :value="operator">{{ t(`discounts.operators.${operator}`) }}</option>
        </Select>

        <div v-if="fact" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <Select v-if="valueKind === 'bool'" :model-value="node.value" size="sm" class="w-28" @update:model-value="update({ value: $event })">
                <option :value="true">{{ t('discounts.yes') }}</option>
                <option :value="false">{{ t('discounts.no') }}</option>
            </Select>

            <template v-else-if="isRange">
                <DatePicker v-if="valueKind === 'date'" :model-value="node.value[0]" class="w-40" @update:model-value="setRange(0, $event)" />
                <TextInput v-else :model-value="String(node.value[0] ?? '')" :type="valueKind === 'time' ? 'text' : 'number'" :placeholder="valueKind === 'time' ? '09:00' : ''" class="w-28" @update:model-value="setRange(0, $event)" />
                <span class="text-xs text-muted">{{ t('discounts.and') }}</span>
                <DatePicker v-if="valueKind === 'date'" :model-value="node.value[1]" class="w-40" @update:model-value="setRange(1, $event)" />
                <TextInput v-else :model-value="String(node.value[1] ?? '')" :type="valueKind === 'time' ? 'text' : 'number'" :placeholder="valueKind === 'time' ? '17:00' : ''" class="w-28" @update:model-value="setRange(1, $event)" />
            </template>

            <template v-else-if="isList">
                <span
                    v-for="picked in node.value"
                    :key="picked"
                    class="inline-flex items-center gap-1 rounded-full bg-accent-wash px-2.5 py-1 text-xs text-accent"
                >
                    {{ labelOf(picked) }}
                    <button type="button" :aria-label="t('common.delete')" @click="removePicked(picked)"><X class="h-3 w-3" /></button>
                </span>
                <Select v-if="available.length" model-value="" size="sm" class="w-48" :placeholder="t('discounts.add')" @update:model-value="addPicked">
                    <option v-for="option in available" :key="option.value" :value="option.value">{{ option.label }}</option>
                </Select>
            </template>

            <Select v-else-if="valueKind === 'options'" :model-value="node.value" size="sm" class="w-56" :placeholder="t('discounts.pickValue')" @update:model-value="update({ value: $event })">
                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </Select>

            <DatePicker v-else-if="valueKind === 'date'" :model-value="node.value" class="w-40" @update:model-value="update({ value: $event })" />

            <TextInput v-else :model-value="String(node.value ?? '')" :type="valueKind === 'time' ? 'text' : 'number'" :placeholder="valueKind === 'time' ? '09:00' : ''" class="w-32" @update:model-value="update({ value: $event })" />

            <label v-if="fact.window" class="flex items-center gap-2 text-xs text-muted">
                {{ t('discounts.withinDays') }}
                <TextInput :model-value="String(node.params?.window_days ?? '')" type="number" min="1" max="3650" :placeholder="t('discounts.allTime')" class="w-28" @update:model-value="setWindow" />
            </label>
        </div>

        <button type="button" class="ms-auto p-1 text-muted hover:text-status-danger" :aria-label="t('discounts.removeCondition')" @click="emit('remove')">
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
