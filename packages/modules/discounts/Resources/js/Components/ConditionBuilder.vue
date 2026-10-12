<script setup>
import ConditionGroup from '@modules/Discounts/Resources/js/Components/ConditionGroup.vue';

defineProps({
    modelValue: { type: Object, required: true },
    facts: { type: Array, required: true },
    lookups: { type: Object, default: () => ({}) },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <ConditionGroup
        :node="modelValue"
        :facts="facts"
        :lookups="lookups"
        :depth="0"
        @update:node="$emit('update:modelValue', $event)"
    />
</template>
