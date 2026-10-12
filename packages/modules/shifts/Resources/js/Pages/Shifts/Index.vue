<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import BranchFilter from '@/Components/BranchFilter.vue';
import { useI18n } from 'vue-i18n';
import { ChevronLeft, ChevronRight, Pencil, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import FilterTabs from '@ahmad-chebbo/tessera-ui/Components/FilterTabs.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import Modal from '@ahmad-chebbo/tessera-ui/Components/Modal.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';

const props = defineProps({
    weekStart: String,
    branchId: { type: [Number, String], default: null },
    days: Array,
    templates: Array,
    employees: Array,
    assignments: Array,
    cycles: Array,
    can: Object,
});

const { t, tm } = useI18n();
const confirm = useConfirm();

const tab = ref('roster');
const tabs = computed(() => [
    { value: 'roster', label: t('shifts.tabRoster') },
    { value: 'shifts', label: t('shifts.tabShifts') },
    { value: 'cycles', label: t('shifts.tabCycles') },
]);

const dayNames = computed(() => tm('shifts.days'));
const templateById = computed(() => Object.fromEntries(props.templates.map((template) => [template.id, template])));
const activeTemplates = computed(() => props.templates.filter((template) => template.is_active));

const assignmentsFor = (userId, date) =>
    props.assignments.filter((assignment) => assignment.user_id === userId && assignment.date === date);

const dayLabel = (date) => {
    const parsed = new Date(`${date}T00:00:00`);

    return `${dayNames.value[parsed.getDay()]} ${parsed.getDate()}`;
};

const branch = ref(props.branchId ?? '');

const goToWeek = (offset) => {
    const base = new Date(`${props.weekStart}T00:00:00`);
    base.setDate(base.getDate() + offset * 7);
    const iso = `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}-${String(base.getDate()).padStart(2, '0')}`;

    router.get(route('shifts.index'), { week: offset === 0 ? undefined : iso, branch_id: branch.value || undefined }, { preserveState: true, preserveScroll: true });
};

watch(branch, () => goToWeek(0));

const weekRange = computed(() => `${props.days[0]} → ${props.days[6]}`);

const assignForm = useForm({ user_id: '', shift_template_id: '', date: '' });
const showAssign = ref(false);

const openAssign = (userId = '', date = '') => {
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.user_id = userId;
    assignForm.date = date;
    showAssign.value = true;
};

const submitAssign = () =>
    assignForm.post(route('shifts.assignments.store'), {
        preserveScroll: true,
        onSuccess: () => (showAssign.value = false),
    });

const removeAssignment = async (assignment) => {
    if (await confirm.delete(t('shifts.confirmRemoveAssignment'))) {
        router.delete(route('shifts.assignments.destroy', assignment.id), { preserveScroll: true });
    }
};

const templateForm = useForm({ name: '', starts_at: '09:00', ends_at: '17:00', is_active: true });
const editingTemplate = ref(null);
const showTemplate = ref(false);

const openTemplate = (template = null) => {
    templateForm.clearErrors();
    editingTemplate.value = template;
    templateForm.name = template?.name ?? '';
    templateForm.starts_at = template?.starts_at ?? '09:00';
    templateForm.ends_at = template?.ends_at ?? '17:00';
    templateForm.is_active = template?.is_active ?? true;
    showTemplate.value = true;
};

const submitTemplate = () => {
    const options = { preserveScroll: true, onSuccess: () => (showTemplate.value = false) };

    if (editingTemplate.value) {
        templateForm.put(route('shifts.templates.update', editingTemplate.value.id), options);
    } else {
        templateForm.post(route('shifts.templates.store'), options);
    }
};

const deleteTemplate = async (template) => {
    if (await confirm.delete(t('shifts.confirmDeleteShift'))) {
        router.delete(route('shifts.templates.destroy', template.id), { preserveScroll: true });
    }
};

const hours = (template) => {
    const [startHour, startMinute] = template.starts_at.split(':').map(Number);
    const [endHour, endMinute] = template.ends_at.split(':').map(Number);
    let minutes = endHour * 60 + endMinute - (startHour * 60 + startMinute);

    if (minutes <= 0) {
        minutes += 24 * 60;
    }

    return (minutes / 60).toFixed(1).replace('.0', '');
};

const templateColumns = computed(() => [
    { key: 'name', label: t('shifts.shiftName') },
    { key: 'time', label: t('shifts.startsAt') },
    { key: 'hours', label: t('shifts.hours') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const cycleForm = useForm({ user_id: '', shift_template_id: '', weekdays: [1, 2, 3, 4, 5], starts_on: '', ends_on: '' });
const showCycle = ref(false);

const openCycle = () => {
    cycleForm.reset();
    cycleForm.clearErrors();
    showCycle.value = true;
};

const toggleWeekday = (day) => {
    cycleForm.weekdays = cycleForm.weekdays.includes(day)
        ? cycleForm.weekdays.filter((value) => value !== day)
        : [...cycleForm.weekdays, day];
};

const submitCycle = () =>
    cycleForm
        .transform((data) => ({ ...data, ends_on: data.ends_on || null }))
        .post(route('shifts.cycles.store'), { preserveScroll: true, onSuccess: () => (showCycle.value = false) });

const generateCycle = (cycle) => router.post(route('shifts.cycles.generate', cycle.id), {}, { preserveScroll: true });

const deleteCycle = async (cycle) => {
    if (await confirm.delete(t('shifts.confirmDeleteCycle'))) {
        router.delete(route('shifts.cycles.destroy', cycle.id), { preserveScroll: true });
    }
};

const cycleColumns = computed(() => [
    { key: 'user', label: t('shifts.employee') },
    { key: 'template', label: t('shifts.shift') },
    { key: 'weekdays', label: t('shifts.weekdays') },
    { key: 'range', label: t('shifts.startsOn') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const buttonClass = 'inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep';
const ghostClass = 'rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line';
</script>

<template>
    <Head :title="t('shifts.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('shifts.title') }}</h1>
                <p class="text-sm text-muted">{{ t('shifts.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <FilterTabs v-model="tab" :tabs="tabs" />
                <BranchFilter v-model="branch" />
            </div>
            <button v-if="can.manage && tab === 'roster'" type="button" :class="buttonClass" @click="openAssign()">
                <Plus class="h-4 w-4" />
                {{ t('shifts.assignEmployee') }}
            </button>
            <button v-if="can.manage && tab === 'shifts'" type="button" :class="buttonClass" @click="openTemplate()">
                <Plus class="h-4 w-4" />
                {{ t('shifts.addShift') }}
            </button>
            <button v-if="can.manage && tab === 'cycles'" type="button" :class="buttonClass" @click="openCycle()">
                <Plus class="h-4 w-4" />
                {{ t('shifts.addCycle') }}
            </button>
        </div>

        <template v-if="tab === 'roster'">
            <div class="mb-4 flex items-center gap-2">
                <IconButton :icon="ChevronLeft" :label="t('shifts.previousWeek')" class="rtl:rotate-180" @click="goToWeek(-1)" />
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-muted hover:bg-hover-wash" @click="goToWeek(0)">
                    {{ t('shifts.thisWeek') }}
                </button>
                <IconButton :icon="ChevronRight" :label="t('shifts.nextWeek')" class="rtl:rotate-180" @click="goToWeek(1)" />
                <span class="ms-2 text-sm text-muted">{{ weekRange }}</span>
            </div>

            <div v-if="employees.length === 0" class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">
                {{ t('shifts.noEmployees') }}
            </div>
            <div v-else class="overflow-x-auto rounded-2xl border border-line bg-surface">
                <div class="min-w-[56rem] text-sm" role="table" :style="{ '--roster-columns': '10rem repeat(7, minmax(7rem, 1fr))' }">
                    <div class="roster-row border-b border-line text-muted" role="row">
                        <div class="px-4 py-3 font-medium" role="columnheader">{{ t('shifts.employee') }}</div>
                        <div v-for="day in days" :key="day" class="px-2 py-3 font-medium" role="columnheader">{{ dayLabel(day) }}</div>
                    </div>
                    <div v-for="employee in employees" :key="employee.id" class="roster-row border-b border-line last:border-0" role="row">
                        <div class="truncate px-4 py-3 font-medium text-fg" role="cell">{{ employee.name }}</div>
                        <div v-for="day in days" :key="day" class="px-2 py-2" role="cell">
                            <div class="flex flex-col gap-1">
                                <span
                                    v-for="assignment in assignmentsFor(employee.id, day)"
                                    :key="assignment.id"
                                    class="inline-flex items-center justify-between gap-1 rounded-md bg-accent-wash px-2 py-1 text-xs text-accent"
                                >
                                    <span class="truncate">
                                        {{ templateById[assignment.shift_template_id]?.name }}
                                        <span class="text-muted">{{ templateById[assignment.shift_template_id]?.starts_at }}</span>
                                    </span>
                                    <button v-if="can.manage" type="button" :aria-label="t('common.delete')" @click="removeAssignment(assignment)">
                                        <X class="h-3 w-3" />
                                    </button>
                                </span>
                                <button
                                    v-if="can.manage"
                                    type="button"
                                    class="rounded-md border border-dashed border-line px-2 py-0.5 text-xs text-muted hover:bg-hover-wash"
                                    :aria-label="t('shifts.assign')"
                                    @click="openAssign(employee.id, day)"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <DataTable v-else-if="tab === 'shifts'" :columns="templateColumns" :rows="templates" :empty-title="t('shifts.noShifts')">
            <template #cell-time="{ row }">{{ row.starts_at }} – {{ row.ends_at }}</template>
            <template #cell-hours="{ row }">{{ hours(row) }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">
                    {{ row.is_active ? t('common.active') : t('common.inactive') }}
                </StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.manage" class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="openTemplate(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteTemplate(row)" />
                </div>
            </template>
        </DataTable>

        <template v-else>
            <p class="mb-4 text-sm text-muted">{{ t('shifts.cycleHint') }}</p>
            <DataTable :columns="cycleColumns" :rows="cycles" :empty-title="t('shifts.noCycles')">
                <template #cell-weekdays="{ row }">{{ row.weekdays.map((day) => dayNames[day]).join(', ') }}</template>
                <template #cell-range="{ row }">{{ row.starts_on }} → {{ row.ends_on || t('shifts.untilFurtherNotice') }}</template>
                <template #cell-actions="{ row }">
                    <div v-if="can.manage" class="flex items-center justify-end gap-2">
                        <IconButton :icon="RefreshCw" :label="t('shifts.generate')" @click="generateCycle(row)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteCycle(row)" />
                    </div>
                </template>
            </DataTable>
        </template>

        <Modal :show="showAssign" max-width="md" @close="showAssign = false">
            <form class="space-y-5 p-6" @submit.prevent="submitAssign">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('shifts.assignEmployee') }}</h2>
                <div>
                    <InputLabel for="assign_user" :value="t('shifts.employee')" />
                    <Select id="assign_user" v-model="assignForm.user_id" class="mt-1 w-full" required>
                        <option value="">{{ t('common.select') }}</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </Select>
                    <InputError :message="assignForm.errors.user_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="assign_shift" :value="t('shifts.shift')" />
                    <Select id="assign_shift" v-model="assignForm.shift_template_id" class="mt-1 w-full" required>
                        <option value="">{{ t('common.select') }}</option>
                        <option v-for="template in activeTemplates" :key="template.id" :value="template.id">
                            {{ template.name }} ({{ template.starts_at }}–{{ template.ends_at }})
                        </option>
                    </Select>
                    <InputError :message="assignForm.errors.shift_template_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="assign_date" :value="t('shifts.date')" />
                    <DatePicker id="assign_date" v-model="assignForm.date" class="mt-1 w-full" />
                    <InputError :message="assignForm.errors.date" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="ghostClass" @click="showAssign = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="assignForm.processing">{{ t('shifts.assign') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showTemplate" max-width="md" @close="showTemplate = false">
            <form class="space-y-5 p-6" @submit.prevent="submitTemplate">
                <h2 class="font-display text-lg font-normal text-fg">{{ editingTemplate ? t('shifts.editShift') : t('shifts.addShift') }}</h2>
                <div>
                    <InputLabel for="template_name" :value="t('shifts.shiftName')" />
                    <TextInput id="template_name" v-model="templateForm.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="templateForm.errors.name" class="mt-2" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="template_start" :value="t('shifts.startsAt')" />
                        <TextInput id="template_start" v-model="templateForm.starts_at" type="text" inputmode="numeric" placeholder="09:00" pattern="[0-2][0-9]:[0-5][0-9]" maxlength="5" class="mt-1 block w-full" required />
                        <InputError :message="templateForm.errors.starts_at" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="template_end" :value="t('shifts.endsAt')" />
                        <TextInput id="template_end" v-model="templateForm.ends_at" type="text" inputmode="numeric" placeholder="09:00" pattern="[0-2][0-9]:[0-5][0-9]" maxlength="5" class="mt-1 block w-full" required />
                        <InputError :message="templateForm.errors.ends_at" class="mt-2" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input id="template_active" v-model="templateForm.is_active" type="checkbox" class="rounded border-line text-accent focus:ring-ring-accent" />
                    <InputLabel for="template_active" :value="t('common.active')" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="ghostClass" @click="showTemplate = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="templateForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showCycle" max-width="md" @close="showCycle = false">
            <form class="space-y-5 p-6" @submit.prevent="submitCycle">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('shifts.addCycle') }}</h2>
                <p class="text-sm text-muted">{{ t('shifts.cycleHint') }}</p>
                <div>
                    <InputLabel for="cycle_user" :value="t('shifts.employee')" />
                    <Select id="cycle_user" v-model="cycleForm.user_id" class="mt-1 w-full" required>
                        <option value="">{{ t('common.select') }}</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </Select>
                    <InputError :message="cycleForm.errors.user_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="cycle_shift" :value="t('shifts.shift')" />
                    <Select id="cycle_shift" v-model="cycleForm.shift_template_id" class="mt-1 w-full" required>
                        <option value="">{{ t('common.select') }}</option>
                        <option v-for="template in activeTemplates" :key="template.id" :value="template.id">{{ template.name }}</option>
                    </Select>
                    <InputError :message="cycleForm.errors.shift_template_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel :value="t('shifts.weekdays')" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="(name, day) in dayNames"
                            :key="day"
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition-colors"
                            :class="cycleForm.weekdays.includes(day) ? 'border-gold-on bg-accent-wash text-accent' : 'border-line text-muted hover:bg-hover-wash'"
                            @click="toggleWeekday(day)"
                        >
                            {{ name }}
                        </button>
                    </div>
                    <InputError :message="cycleForm.errors.weekdays" class="mt-2" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="cycle_start" :value="t('shifts.startsOn')" />
                        <DatePicker id="cycle_start" v-model="cycleForm.starts_on" class="mt-1 w-full" />
                        <InputError :message="cycleForm.errors.starts_on" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="cycle_end" :value="t('shifts.endsOn')" />
                        <DatePicker id="cycle_end" v-model="cycleForm.ends_on" class="mt-1 w-full" />
                        <InputError :message="cycleForm.errors.ends_on" class="mt-2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="ghostClass" @click="showCycle = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="cycleForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>

<style scoped>
.roster-row {
    display: grid;
    grid-template-columns: var(--roster-columns);
    align-items: start;
}
</style>
