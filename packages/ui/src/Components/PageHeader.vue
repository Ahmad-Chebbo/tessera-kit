<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    count: { type: [String, Number], default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <h1 class="truncate font-display text-3xl font-normal text-fg">{{ title }}</h1>
                <span
                    v-if="count !== null"
                    class="rounded-full border border-line bg-hover-wash px-2.5 py-0.5 text-xs text-muted"
                >
                    {{ count }}
                </span>
            </div>
            <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
