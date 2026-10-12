<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-2xl border border-line bg-surface p-5">
        <p class="text-xs text-muted">{{ label }}</p>
        <p class="mt-2 truncate font-display text-3xl font-normal text-fg">{{ value }}</p>
        <p v-if="hint || $slots.hint" class="mt-1 text-xs text-faint">
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>
