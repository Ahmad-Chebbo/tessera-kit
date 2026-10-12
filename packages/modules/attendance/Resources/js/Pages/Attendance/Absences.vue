<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { BadgeCheck, Undo2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import AttendanceTabs from '@modules/Attendance/Resources/js/Pages/Attendance/Partials/AttendanceTabs.vue';
import RangeFilters from '@modules/Attendance/Resources/js/Pages/Attendance/Partials/RangeFilters.vue';

defineProps({
    absences: Array,
    employees: Array,
    filters: Object,
    can: Object,
});

const { t } = useI18n();

const columns = computed(() => [
    { key: 'user', label: t('attendance.employee') },
    { key: 'date', label: t('attendance.date'), cellClass: 'text-sm text-muted' },
    { key: 'shift', label: t('attendance.shift') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const form = useForm({ user_id: '', date: '', reason: '' });
const showForm = ref(false);

const openExcuse = (absence) => {
    form.clearErrors();
    form.user_id = absence.user_id;
    form.date = absence.date;
    form.reason = absence.reason ?? '';
    showForm.value = true;
};

const submit = () => form.post(route('attendance.excuses.store'), { preserveScroll: true, onSuccess: () => (showForm.value = false) });

const removeExcuse = (absence) => router.delete(route('attendance.excuses.destroy', absence.excuse_id), { preserveScroll: true });
</script>

<template>
    <Head :title="t('attendance.absencesTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('attendance.absencesTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('attendance.absencesSubtitle') }}</p>
            </div>
        </template>

        <div class="mb-6">
            <AttendanceTabs current="attendance.absences" />
        </div>

        <RangeFilters class="mb-6" route-name="attendance.absences" :filters="filters" :employees="employees" />

        <DataTable :columns="columns" :rows="absences" :empty-title="t('attendance.noAbsences')">
            <template #cell-user="{ row }">{{ row.user }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.excused ? 'info' : 'danger'">
                    {{ row.excused ? `${t('attendance.excused')}: ${row.reason}` : t('attendance.unexcused') }}
                </StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.manage" class="flex items-center justify-end gap-2">
                    <IconButton :icon="BadgeCheck" :label="t('attendance.excuse')" @click="openExcuse(row)" />
                    <IconButton v-if="row.excused" :icon="Undo2" :label="t('attendance.removeExcuse')" @click="removeExcuse(row)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="showForm" max-width="md" @close="showForm = false">
            <form class="space-y-5 p-6" @submit.prevent="submit">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('attendance.excuseTitle') }}</h2>
                <div>
                    <InputLabel for="excuse_reason" :value="t('attendance.reason')" />
                    <TextInput id="excuse_reason" v-model="form.reason" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="form.errors.reason" class="mt-2" />
                    <InputError :message="form.errors.date" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="showForm = false">
                        {{ t('common.cancel') }}
                    </button>
                    <PrimaryButton :disabled="form.processing">{{ t('attendance.excuse') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
