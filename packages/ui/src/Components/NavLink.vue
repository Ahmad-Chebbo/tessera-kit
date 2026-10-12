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
        ? 'inline-flex items-center border-b-2 border-gold-on px-1 pt-1 text-sm font-medium text-fg transition duration-150 ease-qayema focus:outline-hidden'
        : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-muted transition duration-150 ease-qayema hover:border-line-strong hover:text-fg focus:outline-hidden',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
