<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'neutral',
        validator: (value) => ['danger', 'warn', 'success', 'info', 'neutral'].includes(value),
    },
});

const classes = computed(
    () =>
        ({
            danger: 'border-status-danger-border bg-status-danger-wash text-status-danger',
            warn: 'border-status-warn-border bg-status-warn-wash text-status-warn',
            success: 'border-status-success-border bg-status-success-wash text-status-success',
            info: 'border-status-info-border bg-status-info-wash text-status-info',
            neutral: 'border-line bg-hover-wash text-muted',
        })[props.status],
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="classes"
    >
        <slot />
    </span>
</template>
