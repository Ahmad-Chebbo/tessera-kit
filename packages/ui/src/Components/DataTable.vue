<script setup>
import Pagination from './Pagination.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: [String, Function], default: 'id' },
    rowClass: { type: Function, default: null },
    emptyTitle: { type: String, default: '' },
    paginator: { type: Object, default: null },
    card: { type: Boolean, default: true },
});

const keyOf = (row, index) => {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row, index);
    }

    return row?.[props.rowKey] ?? index;
};

const PADDING = { x: ['p', 'px', 'ps', 'pe'], y: ['p', 'py', 'pt', 'pb'] };

const overrides = (value, axis) =>
    (value ?? '')
        .split(/\s+/)
        .some((token) => PADDING[axis].some((prefix) => token.split(':').pop().startsWith(`${prefix}-`)));

const padding = (column, source, x, y) => [
    overrides(column[source], 'x') ? '' : x,
    overrides(column[source], 'y') ? '' : y,
];

const alignClass = (column) => ({ end: 'text-end', center: 'text-center' })[column.align] ?? 'text-start';
</script>

<template>
    <div :class="card ? 'overflow-hidden rounded-2xl border border-line bg-surface' : ''">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-hover-wash">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="text-xs font-medium uppercase tracking-wider text-muted"
                            :class="[padding(column, 'headerClass', 'px-6', 'py-3'), alignClass(column), column.headerClass]"
                        >
                            <slot :name="`head-${column.key}`" :column="column">{{ column.label }}</slot>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr
                        v-for="(row, index) in rows"
                        :key="keyOf(row, index)"
                        class="hover:bg-hover-wash"
                        :class="rowClass ? rowClass(row, index) : ''"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="[padding(column, 'cellClass', 'px-6', 'py-4'), column.wrap ? '' : 'whitespace-nowrap', alignClass(column), column.cellClass]"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" :index="index" :value="row[column.key]">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0 && (emptyTitle || $slots.empty)">
                        <td :colspan="columns.length" class="px-6 py-12 text-center text-muted">
                            <slot name="empty">{{ emptyTitle }}</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination v-if="paginator" :paginator="paginator" />
    </div>
</template>
