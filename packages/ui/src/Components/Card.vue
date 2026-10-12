<script setup>
defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="rounded-2xl border border-line bg-surface">
        <header
            v-if="title || $slots.actions"
            class="flex items-start justify-between gap-4 border-b border-line-2 px-5 py-4"
        >
            <div class="min-w-0">
                <h2 v-if="title" class="font-display text-xs font-normal uppercase tracking-widest text-muted">
                    {{ title }}
                </h2>
                <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>
    </section>
</template>
