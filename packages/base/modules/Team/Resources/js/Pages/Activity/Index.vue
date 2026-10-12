<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BranchFilter from '@/Components/BranchFilter.vue';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';

const props = defineProps({
    logs: Object,
    employees: Array,
    events: Array,
    filters: Object,
});

const { t } = useI18n();

const columns = computed(() => [
    { key: 'created_at', label: t('employees.when'), cellClass: 'text-sm text-muted' },
    { key: 'user', label: t('employees.employee') },
    { key: 'event', label: t('employees.event') },
    { key: 'description', label: t('employees.details') },
]);

const search = ref(props.filters?.search || '');
const userId = ref(props.filters?.user_id || '');
const branch = ref(props.filters?.branch_id ?? '');
const event = ref(props.filters?.event || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const applyFilters = () => {
    router.get(route('activity.index'), {
        search: search.value || undefined,
        user_id: userId.value || undefined,
        branch_id: branch.value || undefined,
        event: event.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
};

watch(branch, () => {
    userId.value = '';
});
watch([userId, branch, event, dateFrom, dateTo], applyFilters);

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

const formatDateTime = (value) => new Date(value).toLocaleString();
</script>

<template>
    <Head :title="t('employees.activityTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('employees.activityTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('employees.activitySubtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input
                v-model="search"
                type="text"
                :placeholder="t('common.search')"
                class="block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
            />
            <BranchFilter v-model="branch" class="w-full" />
            <Select v-model="userId" class="w-full">
                <option value="">{{ t('employees.allEmployees') }}</option>
                <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
            </Select>
            <Select v-model="event" class="w-full">
                <option value="">{{ t('employees.allEvents') }}</option>
                <option v-for="item in events" :key="item.value" :value="item.value">
                    {{ t(`employees.events.${item.value}`, item.label) }}
                </option>
            </Select>
            <DatePicker v-model="dateFrom" :placeholder="t('employees.dateFrom')" class="w-full" />
            <DatePicker v-model="dateTo" :placeholder="t('employees.dateTo')" class="w-full" />
        </div>

        <DataTable
            :columns="columns"
            :rows="logs.data"
            :empty-title="t('employees.noActivityLogs')"
            :paginator="logs"
        >
            <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
            <template #cell-user="{ row }">
                <Link v-if="row.user" :href="route('users.show', row.user.id)" class="font-medium text-fg hover:text-accent">
                    {{ row.user.name }}
                </Link>
                <span v-else class="text-muted">-</span>
            </template>
            <template #cell-event="{ row }">
                <StatusBadge status="info">{{ t(`employees.events.${row.event}`, row.event_label) }}</StatusBadge>
            </template>
        </DataTable>
    </SidebarLayout>
</template>
