<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    selected: {
        type: [Number, null],
        default: null,
    },
});

const emit = defineEmits(['change']);

const { t } = useI18n();
</script>

<template>
    <div class="mt-4 flex gap-2 overflow-x-auto pb-2">
        <!-- All Categories Button -->
        <button
            type="button"
            @click="emit('change', null)"
            class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition-colors"
            :class="selected === null
                ? 'bg-gold text-ink'
                : 'bg-hover-wash text-muted hover:bg-line'"
        >
            {{ t('pos.allCategories') }}
        </button>

        <!-- Category Buttons -->
        <button
            v-for="category in categories"
            :key="category.id"
            type="button"
            @click="emit('change', category.id)"
            class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition-colors"
            :class="selected === category.id
                ? 'bg-gold text-ink'
                : 'bg-hover-wash text-muted hover:bg-line'"
        >
            {{ category.name }}
        </button>
    </div>
</template>
