<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    node: Object,
});

const { t } = useI18n();
</script>

<template>
    <li class="relative">
        <div class="inline-flex flex-col rounded-lg border border-line bg-surface px-3 py-2">
            <span class="text-sm font-medium text-fg">{{ node.name }}</span>
            <span v-if="node.position" class="text-xs text-muted">{{ node.position }}</span>
            <span v-if="node.department" class="text-xs text-muted">{{ node.department }}</span>
            <span v-if="node.children.length" class="mt-1 text-xs text-accent">{{ t('organization.reportsCount', { count: node.children.length }) }}</span>
        </div>
        <ul v-if="node.children.length" class="ms-5 mt-2 space-y-2 border-s border-line ps-4">
            <OrgNode v-for="child in node.children" :key="child.id" :node="child" />
        </ul>
    </li>
</template>
