<script setup>
import ExportMenu from '@/Components/ExportMenu.vue';
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import StatCard from '@tessera/tessera-ui/Components/StatCard.vue';
import ChartCard from '@tessera/tessera-ui/Components/ChartCard.vue';
import BarChart from '@tessera/tessera-ui/Components/BarChart.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import AttendanceTabs from '@modules/Attendance/Resources/js/Pages/Attendance/Partials/AttendanceTabs.vue';
import RangeFilters from '@modules/Attendance/Resources/js/Pages/Attendance/Partials/RangeFilters.vue';
import { useDuration } from '@tessera/tessera-ui/Composables/useDuration';

const props = defineProps({
    report: Object,
    employees: Array,
    filters: Object,
});

const { t } = useI18n();
const { formatMinutes } = useDuration();

const hoursFormat = (value) => `${Number(value).toFixed(1)}h`;
const shortDate = (date) => new Date(`${date}T00:00:00`).toLocaleDateString(document.documentElement.lang || 'en', { month: 'short', day: 'numeric' });

const stats = computed(() => [
    { label: t('attendance.totalHours'), value: formatMinutes(props.report.totals.worked_minutes) },
    { label: t('attendance.records'), value: props.report.totals.records },
    { label: t('attendance.lateCount'), value: props.report.totals.late_count },
    { label: t('attendance.absences'), value: props.report.totals.absences },
    { label: t('attendance.excusedAbsences'), value: props.report.totals.excused },
]);

const columns = computed(() => [
    { key: 'name', label: t('attendance.employee') },
    { key: 'days_present', label: t('attendance.daysPresent') },
    { key: 'worked', label: t('attendance.hoursWorked') },
    { key: 'late_count', label: t('attendance.lateCount') },
    { key: 'late', label: t('attendance.lateMinutes') },
    { key: 'absences', label: t('attendance.absences') },
    { key: 'excused', label: t('attendance.excusedAbsences') },
]);
</script>

<template>
    <Head :title="t('attendance.reportTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('attendance.reportTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('attendance.reportSubtitle') }}</p>
            </div>
        </template>

        <div class="mb-4 flex justify-end print:hidden">
            <ExportMenu report="attendance" :filters="filters" />
        </div>

        <div class="mb-6">
            <AttendanceTabs current="attendance.report" />
        </div>

        <RangeFilters class="mb-6" route-name="attendance.report" :filters="filters" :employees="employees" />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard v-for="stat in stats" :key="stat.label" :label="stat.label" :value="stat.value" />
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ChartCard :title="t('attendance.hoursByEmployee')" :description="t('attendance.hoursByEmployeeDesc')" :empty="!report.employees.length" :empty-title="t('attendance.noReport')">
                <BarChart
                    horizontal
                    :labels="report.employees.map((employee) => employee.name)"
                    :datasets="[{ label: t('attendance.hours'), data: report.employees.map((employee) => Math.round(employee.worked_minutes / 6) / 10) }]"
                    :format="hoursFormat"
                    :label="t('attendance.hoursByEmployee')"
                />
            </ChartCard>
            <ChartCard :title="t('attendance.dailyHours')" :description="t('attendance.dailyHoursDesc')" :empty="!report.daily.length" :empty-title="t('attendance.noReport')">
                <BarChart
                    :labels="report.daily.map((day) => shortDate(day.date))"
                    :datasets="[{ label: t('attendance.hours'), data: report.daily.map((day) => day.hours) }]"
                    :format="hoursFormat"
                    :label="t('attendance.dailyHours')"
                />
            </ChartCard>
        </div>

        <DataTable :columns="columns" :rows="report.employees" :empty-title="t('attendance.noReport')">
            <template #cell-worked="{ row }">{{ formatMinutes(row.worked_minutes) }}</template>
            <template #cell-late="{ row }">{{ formatMinutes(row.late_minutes) }}</template>
        </DataTable>
    </SidebarLayout>
</template>
