<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';

const props = defineProps({
    balances: Array,
    requests: Array,
    approvals: Array,
    away: Array,
    types: Array,
    holidays: Array,
    weekend: Array,
    can: Object,
});

const { t, tm } = useI18n();
const confirm = useConfirm();

const tab = ref('mine');
const tabs = computed(() => [
    { value: 'mine', label: t('leave.tabMine') },
    ...(props.can.approve ? [{ value: 'approvals', label: `${t('leave.tabApprovals')}${props.approvals.length ? ` (${props.approvals.length})` : ''}` }] : []),
    { value: 'away', label: t('leave.tabAway') },
    ...(props.can.manage ? [{ value: 'types', label: t('leave.tabTypes') }, { value: 'holidays', label: t('leave.tabHolidays') }] : []),
]);

const buttonClass = 'inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep';
const cancelClass = 'rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line';
const checkboxClass = 'rounded border-line text-accent focus:ring-ring-accent';
const statusColor = { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'neutral' };

const activeTypes = computed(() => props.types.filter((type) => type.is_active));
const dayNames = computed(() => tm('leave.days'));
const weekendNames = computed(() => props.weekend.map((day) => dayNames.value[day]).join(', '));
const dates = (row) => (row.starts_on === row.ends_on ? row.starts_on : `${row.starts_on} → ${row.ends_on}`);

const requestColumns = computed(() => [
    { key: 'type', label: t('leave.type') },
    { key: 'dates', label: t('leave.dates') },
    { key: 'days', label: t('leave.days_') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const approvalColumns = computed(() => [
    { key: 'employee', label: t('leave.employee') },
    { key: 'type', label: t('leave.type') },
    { key: 'dates', label: t('leave.dates') },
    { key: 'days', label: t('leave.days_') },
    { key: 'reason', label: t('leave.reason') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const awayColumns = computed(() => [
    { key: 'employee', label: t('leave.employee') },
    { key: 'type', label: t('leave.type') },
    { key: 'dates', label: t('leave.dates') },
]);
const typeColumns = computed(() => [
    { key: 'name', label: t('leave.typeName') },
    { key: 'days_per_year', label: t('leave.daysPerYear') },
    { key: 'is_paid', label: t('leave.paid') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const holidayColumns = computed(() => [
    { key: 'date', label: t('leave.date') },
    { key: 'name', label: t('leave.holidayName') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const requestForm = useForm({ leave_type_id: '', starts_on: '', ends_on: '', reason: '' });
const showRequest = ref(false);

const openRequest = () => {
    requestForm.reset();
    requestForm.clearErrors();
    requestForm.leave_type_id = activeTypes.value[0]?.id ?? '';
    showRequest.value = true;
};

const submitRequest = () => requestForm.post(route('leave.requests.store'), { preserveScroll: true, onSuccess: () => (showRequest.value = false) });

const cancelRequest = async (row) => {
    if (await confirm.delete(t('leave.confirmCancel'))) {
        router.post(route('leave.requests.cancel', row.id), {}, { preserveScroll: true });
    }
};

const approve = (row) => router.post(route('leave.requests.approve', row.id), {}, { preserveScroll: true });

const rejecting = ref(null);
const rejectForm = useForm({ note: '' });
const submitReject = () => rejectForm.post(route('leave.requests.reject', rejecting.value.id), { preserveScroll: true, onSuccess: () => (rejecting.value = null) });

const typeForm = useForm({ name: '', days_per_year: '', is_paid: true, is_active: true });
const editingType = ref(null);
const showType = ref(false);

const openType = (type = null) => {
    typeForm.clearErrors();
    editingType.value = type;
    typeForm.name = type?.name ?? '';
    typeForm.days_per_year = type?.days_per_year ?? '';
    typeForm.is_paid = type?.is_paid ?? true;
    typeForm.is_active = type?.is_active ?? true;
    showType.value = true;
};

const submitType = () => {
    const options = { preserveScroll: true, onSuccess: () => (showType.value = false) };
    const form = typeForm.transform((data) => ({ ...data, days_per_year: data.days_per_year === '' ? null : data.days_per_year }));

    if (editingType.value) {
        form.put(route('leave.types.update', editingType.value.id), options);
    } else {
        form.post(route('leave.types.store'), options);
    }
};

const removeType = async (type) => {
    if (await confirm.delete(t('leave.confirmDeleteType'))) {
        router.delete(route('leave.types.destroy', type.id), { preserveScroll: true });
    }
};

const holidayForm = useForm({ date: '', name: '' });
const showHoliday = ref(false);

const openHoliday = () => {
    holidayForm.reset();
    holidayForm.clearErrors();
    showHoliday.value = true;
};

const submitHoliday = () => holidayForm.post(route('leave.holidays.store'), { preserveScroll: true, onSuccess: () => (showHoliday.value = false) });

const removeHoliday = async (holiday) => {
    if (await confirm.delete(t('leave.confirmDeleteHoliday'))) {
        router.delete(route('leave.holidays.destroy', holiday.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('leave.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('leave.title') }}</h1>
                <p class="text-sm text-muted">{{ t('leave.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <FilterTabs v-model="tab" :tabs="tabs" />
            <button v-if="tab === 'mine'" type="button" :class="buttonClass" :disabled="!activeTypes.length" @click="openRequest()">
                <Plus class="h-4 w-4" />
                {{ t('leave.requestLeave') }}
            </button>
            <button v-if="can.manage && tab === 'types'" type="button" :class="buttonClass" @click="openType()">
                <Plus class="h-4 w-4" />
                {{ t('leave.addType') }}
            </button>
            <button v-if="can.manage && tab === 'holidays'" type="button" :class="buttonClass" @click="openHoliday()">
                <Plus class="h-4 w-4" />
                {{ t('leave.addHoliday') }}
            </button>
        </div>

        <template v-if="tab === 'mine'">
            <div v-if="balances.length" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="balance in balances" :key="balance.type_id" class="rounded-lg border border-line bg-surface p-4">
                    <p class="text-sm text-muted">{{ balance.name }}</p>
                    <p class="mt-1 font-display text-2xl text-fg">{{ balance.remaining === null ? t('leave.unlimited') : balance.remaining }}</p>
                    <p class="text-xs text-muted">
                        {{ t('leave.usedOf', { used: balance.used, total: balance.entitlement ?? '∞' }) }}<template v-if="balance.pending"> · {{ t('leave.pendingDays', { count: balance.pending }) }}</template>
                    </p>
                </div>
            </div>
            <p v-if="!activeTypes.length" class="mb-4 text-sm text-muted">{{ t('leave.noTypesYet') }}</p>

            <DataTable :columns="requestColumns" :rows="requests" :empty-title="t('leave.noRequests')">
                <template #cell-dates="{ row }">{{ dates(row) }}</template>
                <template #cell-status="{ row }">
                    <StatusBadge :status="statusColor[row.status]">{{ t(`leave.statuses.${row.status}`) }}</StatusBadge>
                    <p v-if="row.decision_note" class="mt-1 text-xs text-muted">{{ row.decision_note }}</p>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="row.can_cancel" class="flex items-center justify-end gap-2">
                        <IconButton :icon="X" :label="t('leave.cancelRequest')" variant="danger" @click="cancelRequest(row)" />
                    </div>
                </template>
            </DataTable>
        </template>

        <DataTable v-else-if="tab === 'approvals'" :columns="approvalColumns" :rows="approvals" :empty-title="t('leave.noApprovals')">
            <template #cell-dates="{ row }">{{ dates(row) }}</template>
            <template #cell-reason="{ row }">{{ row.reason ?? '—' }}</template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Check" :label="t('leave.approve')" @click="approve(row)" />
                    <IconButton :icon="X" :label="t('leave.reject')" variant="danger" @click="rejecting = row; rejectForm.reset(); rejectForm.clearErrors()" />
                </div>
            </template>
        </DataTable>

        <DataTable v-else-if="tab === 'away'" :columns="awayColumns" :rows="away" :empty-title="t('leave.noOneAway')">
            <template #cell-dates="{ row }">{{ dates(row) }}</template>
        </DataTable>

        <DataTable v-else-if="tab === 'types'" :columns="typeColumns" :rows="types" :empty-title="t('leave.noTypes')">
            <template #cell-days_per_year="{ row }">{{ row.days_per_year ?? t('leave.unlimited') }}</template>
            <template #cell-is_paid="{ row }">
                <StatusBadge :status="row.is_paid ? 'success' : 'neutral'">{{ row.is_paid ? t('leave.paid') : t('leave.unpaid') }}</StatusBadge>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? t('common.active') : t('common.inactive') }}</StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="openType(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="removeType(row)" />
                </div>
            </template>
        </DataTable>

        <template v-else-if="tab === 'holidays'">
            <p class="mb-4 text-sm text-muted">{{ t('leave.weekendHint', { days: weekendNames }) }}</p>
            <DataTable :columns="holidayColumns" :rows="holidays" :empty-title="t('leave.noHolidays')">
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="removeHoliday(row)" />
                    </div>
                </template>
            </DataTable>
        </template>

        <Modal :show="showRequest" max-width="md" @close="showRequest = false">
            <form class="space-y-5 p-6" @submit.prevent="submitRequest">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('leave.requestLeave') }}</h2>
                <div>
                    <InputLabel for="request_type" :value="t('leave.type')" />
                    <Select id="request_type" v-model="requestForm.leave_type_id" class="mt-1 w-full">
                        <option v-for="type in activeTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </Select>
                    <InputError :message="requestForm.errors.leave_type_id" class="mt-2" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="request_start" :value="t('leave.from')" />
                        <DatePicker id="request_start" v-model="requestForm.starts_on" class="mt-1 w-full" />
                        <InputError :message="requestForm.errors.starts_on" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="request_end" :value="t('leave.to')" />
                        <DatePicker id="request_end" v-model="requestForm.ends_on" class="mt-1 w-full" />
                        <InputError :message="requestForm.errors.ends_on" class="mt-2" />
                    </div>
                </div>
                <div>
                    <InputLabel for="request_reason" :value="t('leave.reason')" />
                    <TextInput id="request_reason" v-model="requestForm.reason" type="text" class="mt-1 block w-full" />
                    <InputError :message="requestForm.errors.reason" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="showRequest = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="requestForm.processing">{{ t('leave.send') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="rejecting !== null" max-width="md" @close="rejecting = null">
            <form class="space-y-5 p-6" @submit.prevent="submitReject">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('leave.reject') }}: {{ rejecting?.employee }}</h2>
                <div>
                    <InputLabel for="reject_note" :value="t('leave.note')" />
                    <TextInput id="reject_note" v-model="rejectForm.note" type="text" class="mt-1 block w-full" autofocus />
                    <InputError :message="rejectForm.errors.note || rejectForm.errors.request" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="rejecting = null">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="rejectForm.processing">{{ t('leave.reject') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showType" max-width="md" @close="showType = false">
            <form class="space-y-5 p-6" @submit.prevent="submitType">
                <h2 class="font-display text-lg font-normal text-fg">{{ editingType ? t('leave.editType') : t('leave.addType') }}</h2>
                <div>
                    <InputLabel for="type_name" :value="t('leave.typeName')" />
                    <TextInput id="type_name" v-model="typeForm.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="typeForm.errors.name || typeForm.errors.type" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="type_days" :value="t('leave.daysPerYear')" />
                    <TextInput id="type_days" v-model="typeForm.days_per_year" type="number" step="0.5" min="0" class="mt-1 block w-full" />
                    <p class="mt-1 text-xs text-muted">{{ t('leave.daysPerYearHint') }}</p>
                    <InputError :message="typeForm.errors.days_per_year" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="typeForm.is_paid" type="checkbox" :class="checkboxClass" />
                    {{ t('leave.paid') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="typeForm.is_active" type="checkbox" :class="checkboxClass" />
                    {{ t('common.active') }}
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="showType = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="typeForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showHoliday" max-width="md" @close="showHoliday = false">
            <form class="space-y-5 p-6" @submit.prevent="submitHoliday">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('leave.addHoliday') }}</h2>
                <div>
                    <InputLabel for="holiday_date" :value="t('leave.date')" />
                    <DatePicker id="holiday_date" v-model="holidayForm.date" class="mt-1 w-full" />
                    <InputError :message="holidayForm.errors.date" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="holiday_name" :value="t('leave.holidayName')" />
                    <TextInput id="holiday_name" v-model="holidayForm.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="holidayForm.errors.name" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="showHoliday = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="holidayForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
