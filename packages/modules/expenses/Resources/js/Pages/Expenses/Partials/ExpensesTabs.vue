<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';

const props = defineProps({
    current: { type: String, required: true },
});

const { t } = useI18n();

const tabs = computed(() => [
    { value: 'expenses.index', label: t('expenses.tabs.list') },
    { value: 'recurring-expenses.index', label: t('expenses.tabs.recurring') },
    { value: 'expense-categories.index', label: t('expenses.tabs.categories') },
    { value: 'expenses.report', label: t('expenses.tabs.report') },
    { value: 'expenses.statement', label: t('expenses.tabs.statement') },
]);

const go = (name) => {
    if (name !== props.current) {
        router.get(route(name));
    }
};
</script>

<template>
    <FilterTabs :model-value="current" :tabs="tabs" @update:model-value="go" />
</template>
