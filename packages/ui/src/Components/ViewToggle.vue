<script setup>
import { LayoutGrid, Table } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps({
    modelValue: { type: String, default: 'grid' },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const views = [
    { value: 'table', icon: Table, label: 'view.table' },
    { value: 'grid', icon: LayoutGrid, label: 'view.grid' },
];
</script>

<template>
    <div class="inline-flex items-center gap-0.5 rounded-control border border-line bg-surface p-0.5">
        <button
            v-for="view in views"
            :key="view.value"
            type="button"
            :aria-pressed="modelValue === view.value"
            :aria-label="t(view.label)"
            :title="t(view.label)"
            class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] transition-colors duration-150 ease-qayema"
            :class="
                modelValue === view.value
                    ? 'bg-accent-wash text-accent'
                    : 'text-muted hover:bg-hover-wash hover:text-fg'
            "
            @click="emit('update:modelValue', view.value)"
        >
            <component :is="view.icon" class="h-4 w-4" />
        </button>
    </div>
</template>
