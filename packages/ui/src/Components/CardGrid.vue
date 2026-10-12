<script setup>
import EmptyState from './EmptyState.vue';
import Pagination from './Pagination.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    itemKey: { type: [String, Function], default: 'id' },
    emptyTitle: { type: String, default: '' },
    paginator: { type: Object, default: null },
    cols: { type: String, default: 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' },
});

const keyOf = (item, index) => {
    if (typeof props.itemKey === 'function') {
        return props.itemKey(item, index);
    }

    return item?.[props.itemKey] ?? index;
};
</script>

<template>
    <div>
        <div v-if="items.length" data-grid class="grid grid-cols-1 gap-4" :class="cols">
            <template v-for="(item, index) in items" :key="keyOf(item, index)">
                <slot name="item" :item="item" :index="index" />
            </template>
        </div>
        <div v-else class="rounded-2xl border border-line bg-surface">
            <EmptyState :title="emptyTitle" />
        </div>
        <Pagination v-if="paginator" :paginator="paginator" :bordered="false" />
    </div>
</template>
