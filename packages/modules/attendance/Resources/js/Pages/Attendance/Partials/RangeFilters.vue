<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BranchFilter from '@/Components/BranchFilter.vue';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';

const props = defineProps({
    routeName: { type: String, required: true },
    filters: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
});

const { t } = useI18n();

const userId = ref(props.filters.user_id || '');
const branch = ref(props.filters.branch_id ?? '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

watch(branch, () => {
    userId.value = '';
});

watch([userId, branch, dateFrom, dateTo], () => {
    router.get(route(props.routeName), {
        user_id: userId.value || undefined,
        branch_id: branch.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <BranchFilter v-model="branch" />
        <Select v-if="employees.length" v-model="userId" class="w-56">
            <option value="">{{ t('attendance.allEmployees') }}</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
        </Select>
        <DatePicker v-model="dateFrom" :placeholder="t('attendance.dateFrom')" class="w-44" />
        <DatePicker v-model="dateTo" :placeholder="t('attendance.dateTo')" class="w-44" />
    </div>
</template>
