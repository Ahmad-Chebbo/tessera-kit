<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-2 rounded-control border border-line-strong bg-transparent px-4 py-2 text-sm font-semibold text-fg transition duration-150 ease-qayema hover:bg-hover-wash focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on disabled:pointer-events-none disabled:opacity-50"
    >
        <slot />
    </button>
</template>
