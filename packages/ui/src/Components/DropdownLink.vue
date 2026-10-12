<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus:bg-hover-wash focus:outline-hidden"
    >
        <slot />
    </Link>
</template>
