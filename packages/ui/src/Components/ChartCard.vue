<script setup>
import { ChartColumn } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Card from './Card.vue';
import EmptyState from './EmptyState.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: null },
    height: { type: String, default: 'h-72' },
});

const { t } = useI18n();
</script>

<template>
    <Card :title="title" :description="description">
        <EmptyState v-if="empty" :icon="ChartColumn" :title="emptyTitle ?? t('charts.noData')" />
        <div v-else :class="height">
            <slot />
        </div>
    </Card>
</template>
