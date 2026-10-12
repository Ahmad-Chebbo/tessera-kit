<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
        <div
            v-if="icon"
            class="flex h-12 w-12 items-center justify-center rounded-full border border-line bg-hover-wash text-muted"
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
        <p class="font-display text-xl font-normal text-fg">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2"><slot /></div>
    </div>
</template>
