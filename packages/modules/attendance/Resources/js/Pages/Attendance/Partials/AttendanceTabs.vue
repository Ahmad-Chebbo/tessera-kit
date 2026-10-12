<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FilterTabs from '@ahmad-chebbo/tessera-ui/Components/FilterTabs.vue';

const props = defineProps({
    current: { type: String, required: true },
});

const { t } = useI18n();
const page = usePage();

const tabs = computed(() => {
    const permissions = page.props.auth.permissions;
    const list = [{ value: 'attendance.index', label: t('attendance.tabHistory') }];

    if (permissions.includes('view attendance')) {
        list.push({ value: 'attendance.absences', label: t('attendance.tabAbsences') });
        list.push({ value: 'attendance.report', label: t('attendance.tabReport') });
    }

    return list;
});

const go = (name) => {
    if (name !== props.current) {
        router.get(route(name));
    }
};
</script>

<template>
    <FilterTabs :model-value="current" :tabs="tabs" @update:model-value="go" />
</template>
