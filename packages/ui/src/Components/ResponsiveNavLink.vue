<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full border-s-4 border-gold-on bg-accent-wash py-2 ps-3 pe-4 text-start text-base font-medium text-fg transition duration-150 ease-qayema focus:outline-hidden'
        : 'block w-full border-s-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus:outline-hidden',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
