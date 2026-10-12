<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import FilterTabs from '@ahmad-chebbo/tessera-ui/Components/FilterTabs.vue';
import Card from '@ahmad-chebbo/tessera-ui/Components/Card.vue';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';

const props = defineProps({
    employee: Object,
    activity: Object,
    can: Object,
});

const { t } = useI18n();

const tab = ref('overview');
const tabs = computed(() => [
    { value: 'overview', label: t('employees.overview') },
    { value: 'activity', label: t('employees.activityTab') },
]);

const profile = computed(() => props.employee.profile ?? {});

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : '-');
const formatDateTime = (value) => new Date(value).toLocaleString();

const sections = computed(() => [
    {
        title: t('employees.employment'),
        rows: [
            [t('employees.employeeNumber'), profile.value.employee_number],
            [t('employees.jobTitle'), profile.value.job_title],
            [t('users.role'), props.employee.role],
            [t('users.branch'), props.employee.branch?.name],
            [t('employees.hireDate'), formatDate(profile.value.hire_date)],
            [t('employees.birthDate'), formatDate(profile.value.birth_date)],
            [t('employees.nationalId'), profile.value.national_id],
        ],
    },
    {
        title: t('employees.contact'),
        rows: [
            [t('users.email'), props.employee.email],
            [t('employees.phone'), profile.value.phone],
            [t('employees.address'), profile.value.address],
        ],
    },
    {
        title: t('employees.emergency'),
        rows: [
            [t('common.name'), profile.value.emergency_contact_name],
            [t('employees.phone'), profile.value.emergency_contact_phone],
        ],
    },
]);

const activityColumns = computed(() => [
    { key: 'event', label: t('employees.event') },
    { key: 'description', label: t('employees.details') },
    { key: 'created_at', label: t('employees.when'), cellClass: 'text-sm text-muted' },
]);
</script>

<template>
    <Head :title="employee.name" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gold text-sm font-medium text-ink">
                        {{ employee.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-normal text-fg">{{ employee.name }}</h1>
                        <p class="text-sm text-muted">{{ profile.job_title || employee.role }}</p>
                    </div>
                    <StatusBadge :status="employee.is_active ? 'success' : 'danger'">
                        {{ employee.is_active ? t('common.active') : t('common.inactive') }}
                    </StatusBadge>
                </div>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <FilterTabs v-model="tab" :tabs="tabs" />
            <IconButton v-if="can.edit" :icon="Pencil" :label="t('common.edit')" :href="route('users.edit', employee.id)" />
        </div>

        <div v-if="tab === 'overview'" class="grid gap-6 lg:grid-cols-3">
            <Card v-for="section in sections" :key="section.title">
                <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ section.title }}</h2>
                <dl class="space-y-3 text-sm">
                    <div v-for="[label, value] in section.rows" :key="label" class="flex justify-between gap-4">
                        <dt class="text-muted">{{ label }}</dt>
                        <dd class="text-end text-fg">{{ value || '-' }}</dd>
                    </div>
                </dl>
            </Card>
            <Card v-if="profile.notes" class="lg:col-span-3">
                <h2 class="mb-2 font-display text-lg font-normal text-fg">{{ t('employees.notes') }}</h2>
                <p class="whitespace-pre-line text-sm text-muted">{{ profile.notes }}</p>
            </Card>
        </div>

        <DataTable
            v-else
            :columns="activityColumns"
            :rows="activity.data"
            :empty-title="t('employees.noActivity')"
            :paginator="activity"
        >
            <template #cell-event="{ row }">
                <StatusBadge status="info">{{ t(`employees.events.${row.event}`, row.event_label) }}</StatusBadge>
            </template>
            <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </DataTable>
    </SidebarLayout>
</template>
