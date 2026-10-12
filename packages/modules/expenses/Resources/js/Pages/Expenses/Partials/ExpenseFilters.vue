<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';
import PeriodPresets from '@tessera/tessera-ui/Components/PeriodPresets.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';

const props = defineProps({
    routeName: { type: String, required: true },
    filters: { type: Object, required: true },
    presets: { type: Array, default: () => [] },
    categories: { type: Array, default: null },
    branches: { type: Array, default: null },
    employees: { type: Array, default: null },
    types: { type: Array, default: null },
    searchable: { type: Boolean, default: false },
});

const { t } = useI18n();

const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const categoryId = ref(props.filters.category_id || '');
const branchId = ref(props.filters.branch_id || '');
const employeeId = ref(props.filters.employee_id || '');
const type = ref(props.filters.type || '');
const search = ref(props.filters.search || '');

const apply = () => {
    router.get(route(props.routeName), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        category_id: categoryId.value || undefined,
        branch_id: branchId.value || undefined,
        employee_id: employeeId.value || undefined,
        type: type.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
};

watch([dateFrom, dateTo, categoryId, branchId, employeeId, type], apply);

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
</script>

<template>
    <div class="space-y-3">
        <PeriodPresets v-if="presets.length" :presets="presets" :from="dateFrom" :to="dateTo" @select="({ from, to }) => { dateFrom = from; dateTo = to; }" />
        <div class="flex flex-wrap items-center gap-3">
            <DatePicker v-model="dateFrom" :max="dateTo || null" class="w-44" />
            <DatePicker v-model="dateTo" :min="dateFrom || null" class="w-44" />
            <Select v-if="categories" v-model="categoryId" class="w-48">
                <option value="">{{ t('expenses.allCategories') }}</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </Select>
            <Select v-if="types" v-model="type" class="w-44">
                <option value="">{{ t('expenses.allTypes') }}</option>
                <option v-for="value in types" :key="value" :value="value">{{ t(`expenses.types.${value}`) }}</option>
            </Select>
            <Select v-if="branches && branches.length > 1" v-model="branchId" class="w-44">
                <option value="">{{ t('expenses.allBranches') }}</option>
                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
            </Select>
            <Select v-if="employees" v-model="employeeId" class="w-48">
                <option value="">{{ t('expenses.allEmployees') }}</option>
                <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
            </Select>
            <input
                v-if="searchable"
                v-model="search"
                type="text"
                :placeholder="t('expenses.search')"
                class="block w-64 rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
            />
        </div>
    </div>
</template>
