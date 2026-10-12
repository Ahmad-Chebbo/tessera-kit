<script setup>
import ExportMenu from '@/Components/ExportMenu.vue';
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import Modal from '@ahmad-chebbo/tessera-ui/Components/Modal.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import AttendanceTabs from '@modules/Attendance/Resources/js/Pages/Attendance/Partials/AttendanceTabs.vue';
import RangeFilters from '@modules/Attendance/Resources/js/Pages/Attendance/Partials/RangeFilters.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';
import { useDuration } from '@ahmad-chebbo/tessera-ui/Composables/useDuration';

defineProps({
    records: Object,
    employees: Array,
    filters: Object,
    can: Object,
});

const { t } = useI18n();
const confirm = useConfirm();
const { formatMinutes } = useDuration();

const columns = computed(() => [
    { key: 'user', label: t('attendance.employee') },
    { key: 'work_date', label: t('attendance.date'), cellClass: 'text-sm text-muted' },
    { key: 'shift', label: t('attendance.shift') },
    { key: 'in', label: t('attendance.in') },
    { key: 'out', label: t('attendance.out') },
    { key: 'worked', label: t('attendance.worked') },
    { key: 'flags', label: t('attendance.late') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const formatTime = (value) => (value ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : null);

const form = useForm({ user_id: '', check_in_at: '', check_out_at: '', notes: '' });
const editing = ref(null);
const showForm = ref(false);

const openForm = (record = null) => {
    form.clearErrors();
    editing.value = record;
    form.user_id = record?.user?.id ?? '';
    form.check_in_at = record?.check_in_local ?? '';
    form.check_out_at = record?.check_out_local ?? '';
    form.notes = record?.notes ?? '';
    showForm.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    const payload = (data) => ({ ...data, check_out_at: data.check_out_at || null });

    if (editing.value) {
        form.transform(payload).put(route('attendance.update', editing.value.id), options);
    } else {
        form.transform(payload).post(route('attendance.store'), options);
    }
};

const remove = async (record) => {
    if (await confirm.delete(t('attendance.confirmDeleteRecord'))) {
        router.delete(route('attendance.destroy', record.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('attendance.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('attendance.title') }}</h1>
                <p class="text-sm text-muted">{{ t('attendance.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-4 flex justify-end print:hidden">
            <ExportMenu report="attendance-history" :filters="filters" />
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <AttendanceTabs current="attendance.index" />
            <button
                v-if="can.manage"
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                @click="openForm()"
            >
                <Plus class="h-4 w-4" />
                {{ t('attendance.addRecord') }}
            </button>
        </div>

        <RangeFilters class="mb-6" route-name="attendance.index" :filters="filters" :employees="employees" />

        <DataTable :columns="columns" :rows="records.data" :empty-title="t('attendance.noRecords')" :paginator="records">
            <template #cell-user="{ row }">
                <p class="font-medium text-fg">{{ row.user?.name }}</p>
                <p class="text-xs text-muted">{{ row.source === 'manager' ? t('attendance.manager') : t('attendance.self') }}</p>
            </template>
            <template #cell-shift="{ row }">{{ row.shift || t('attendance.noShift') }}</template>
            <template #cell-in="{ row }">{{ formatTime(row.check_in_at) }}</template>
            <template #cell-out="{ row }">
                <StatusBadge v-if="!row.check_out_at" status="success">{{ t('attendance.stillIn') }}</StatusBadge>
                <template v-else>{{ formatTime(row.check_out_at) }}</template>
            </template>
            <template #cell-worked="{ row }">{{ row.check_out_at ? formatMinutes(row.worked_minutes) : '-' }}</template>
            <template #cell-flags="{ row }">
                <div class="flex flex-wrap gap-1">
                    <StatusBadge v-if="row.late_minutes > 0" status="warn">{{ t('attendance.late') }} {{ formatMinutes(row.late_minutes) }}</StatusBadge>
                    <StatusBadge v-if="row.early_leave_minutes > 0" status="warn">{{ t('attendance.earlyLeave') }} {{ formatMinutes(row.early_leave_minutes) }}</StatusBadge>
                </div>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.manage" class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="openForm(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="showForm" max-width="md" @close="showForm = false">
            <form class="space-y-5 p-6" @submit.prevent="submit">
                <h2 class="font-display text-lg font-normal text-fg">{{ editing ? t('attendance.editRecord') : t('attendance.addRecord') }}</h2>
                <div>
                    <InputLabel for="record_user" :value="t('attendance.employee')" />
                    <Select id="record_user" v-model="form.user_id" class="mt-1 w-full" required>
                        <option value="">{{ t('common.select') }}</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </Select>
                    <InputError :message="form.errors.user_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="record_in" :value="t('attendance.checkInAt')" />
                    <DatePicker id="record_in" v-model="form.check_in_at" with-time class="mt-1 w-full" />
                    <InputError :message="form.errors.check_in_at" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="record_out" :value="t('attendance.checkOutAt')" />
                    <DatePicker id="record_out" v-model="form.check_out_at" with-time class="mt-1 w-full" />
                    <InputError :message="form.errors.check_out_at" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="record_notes" :value="t('attendance.notes')" />
                    <textarea id="record_notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent" />
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="showForm = false">
                        {{ t('common.cancel') }}
                    </button>
                    <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
