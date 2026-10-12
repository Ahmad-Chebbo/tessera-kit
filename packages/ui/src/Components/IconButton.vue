<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    icon: { type: [Object, Function], required: true },
    label: { type: String, required: true },
    href: { type: String, default: null },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'danger'].includes(value),
    },
});

const emit = defineEmits(['click']);

const classes = computed(() => [
    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-control text-muted transition-colors duration-150 ease-qayema focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-gold-on',
    props.variant === 'danger'
        ? 'hover:bg-status-danger-wash hover:text-status-danger'
        : 'hover:bg-hover-wash hover:text-fg',
]);
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        v-bind="href ? { href } : { type: 'button' }"
        :class="classes"
        :aria-label="label"
        :title="label"
        @click="emit('click', $event)"
    >
        <component :is="icon" class="h-4 w-4" />
    </component>
</template>
