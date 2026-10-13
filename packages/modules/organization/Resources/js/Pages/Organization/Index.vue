<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import OrgNode from '@modules/Organization/Resources/js/Pages/Organization/Partials/OrgNode.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';

const props = defineProps({
    departments: Array,
    positions: Array,
    employees: Array,
    can: Object,
});

const { t } = useI18n();
const confirm = useConfirm();

const tab = ref('chart');
const tabs = computed(() => [
    { value: 'chart', label: t('organization.tabChart') },
    { value: 'employees', label: t('organization.tabEmployees') },
    { value: 'departments', label: t('organization.tabDepartments') },
    { value: 'positions', label: t('organization.tabPositions') },
]);

const buttonClass = 'inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep';
const cancelClass = 'rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line';
const checkboxClass = 'rounded border-line text-accent focus:ring-ring-accent';

const departmentName = (id) => props.departments.find((department) => department.id === id)?.name ?? null;
const positionName = (id) => props.positions.find((position) => position.id === id)?.name ?? null;
const employeeName = (id) => props.employees.find((employee) => employee.id === id)?.name ?? null;

const chart = computed(() => {
    const nodes = Object.fromEntries(props.employees.map((employee) => [employee.id, {
        id: employee.id,
        name: employee.name,
        department: departmentName(employee.department_id),
        position: positionName(employee.position_id),
        children: [],
    }]));
    const roots = [];

    props.employees.forEach((employee) => {
        const parent = nodes[employee.manager_id];

        (parent ? parent.children : roots).push(nodes[employee.id]);
    });

    return roots;
});

const departmentTree = computed(() => {
    const byParent = {};

    props.departments.forEach((department) => {
        (byParent[department.parent_id ?? 0] ??= []).push(department);
    });

    const rows = [];
    const walk = (parentId, depth) => (byParent[parentId] ?? []).forEach((department) => {
        rows.push({ ...department, depth });
        walk(department.id, depth + 1);
    });
    walk(0, 0);

    return rows;
});

const employeeColumns = computed(() => [
    { key: 'name', label: t('organization.employee') },
    { key: 'department', label: t('organization.department') },
    { key: 'position', label: t('organization.position') },
    { key: 'manager', label: t('organization.manager') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const departmentColumns = computed(() => [
    { key: 'name', label: t('organization.departmentName') },
    { key: 'head', label: t('organization.head') },
    { key: 'employees_count', label: t('organization.employeesCount') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);
const positionColumns = computed(() => [
    { key: 'name', label: t('organization.positionName') },
    { key: 'department', label: t('organization.department') },
    { key: 'employees_count', label: t('organization.employeesCount') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const departmentForm = useForm({ name: '', parent_id: '', head_id: '', is_active: true });
const editingDepartment = ref(null);
const showDepartment = ref(false);

const openDepartment = (department = null) => {
    departmentForm.clearErrors();
    editingDepartment.value = department;
    departmentForm.name = department?.name ?? '';
    departmentForm.parent_id = department?.parent_id ?? '';
    departmentForm.head_id = department?.head_id ?? '';
    departmentForm.is_active = department?.is_active ?? true;
    showDepartment.value = true;
};

const submitDepartment = () => {
    const options = { preserveScroll: true, onSuccess: () => (showDepartment.value = false) };
    const form = departmentForm.transform((data) => ({ ...data, parent_id: data.parent_id || null, head_id: data.head_id || null }));

    if (editingDepartment.value) {
        form.put(route('organization.departments.update', editingDepartment.value.id), options);
    } else {
        form.post(route('organization.departments.store'), options);
    }
};

const removeDepartment = async (department) => {
    if (await confirm.delete(t('organization.confirmDeleteDepartment'))) {
        router.delete(route('organization.departments.destroy', department.id), { preserveScroll: true });
    }
};

const positionForm = useForm({ name: '', department_id: '', is_active: true });
const editingPosition = ref(null);
const showPosition = ref(false);

const openPosition = (position = null) => {
    positionForm.clearErrors();
    editingPosition.value = position;
    positionForm.name = position?.name ?? '';
    positionForm.department_id = position?.department_id ?? '';
    positionForm.is_active = position?.is_active ?? true;
    showPosition.value = true;
};

const submitPosition = () => {
    const options = { preserveScroll: true, onSuccess: () => (showPosition.value = false) };
    const form = positionForm.transform((data) => ({ ...data, department_id: data.department_id || null }));

    if (editingPosition.value) {
        form.put(route('organization.positions.update', editingPosition.value.id), options);
    } else {
        form.post(route('organization.positions.store'), options);
    }
};

const removePosition = async (position) => {
    if (await confirm.delete(t('organization.confirmDeletePosition'))) {
        router.delete(route('organization.positions.destroy', position.id), { preserveScroll: true });
    }
};

const employeeForm = useForm({ department_id: '', position_id: '', manager_id: '' });
const editingEmployee = ref(null);
const showEmployee = ref(false);

const openEmployee = (employee) => {
    employeeForm.clearErrors();
    editingEmployee.value = employee;
    employeeForm.department_id = employee.department_id ?? '';
    employeeForm.position_id = employee.position_id ?? '';
    employeeForm.manager_id = employee.manager_id ?? '';
    showEmployee.value = true;
};

const submitEmployee = () => {
    employeeForm
        .transform((data) => ({ department_id: data.department_id || null, position_id: data.position_id || null, manager_id: data.manager_id || null }))
        .put(route('organization.employees.assign', editingEmployee.value.id), { preserveScroll: true, onSuccess: () => (showEmployee.value = false) });
};
</script>

<template>
    <Head :title="t('organization.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('organization.title') }}</h1>
                <p class="text-sm text-muted">{{ t('organization.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <FilterTabs v-model="tab" :tabs="tabs" />
            <button v-if="can.manage && tab === 'departments'" type="button" :class="buttonClass" @click="openDepartment()">
                <Plus class="h-4 w-4" />
                {{ t('organization.addDepartment') }}
            </button>
            <button v-if="can.manage && tab === 'positions'" type="button" :class="buttonClass" @click="openPosition()">
                <Plus class="h-4 w-4" />
                {{ t('organization.addPosition') }}
            </button>
        </div>

        <div v-if="tab === 'chart'" class="overflow-x-auto rounded-lg border border-line bg-surface p-5">
            <ul v-if="chart.length" class="space-y-2">
                <OrgNode v-for="node in chart" :key="node.id" :node="node" />
            </ul>
            <p v-else class="text-sm text-muted">{{ t('organization.noEmployees') }}</p>
        </div>

        <DataTable v-else-if="tab === 'employees'" :columns="employeeColumns" :rows="employees" :empty-title="t('organization.noEmployees')">
            <template #cell-department="{ row }">{{ departmentName(row.department_id) ?? '—' }}</template>
            <template #cell-position="{ row }">{{ positionName(row.position_id) ?? '—' }}</template>
            <template #cell-manager="{ row }">{{ employeeName(row.manager_id) ?? '—' }}</template>
            <template #cell-actions="{ row }">
                <div v-if="can.manage" class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="openEmployee(row)" />
                </div>
            </template>
        </DataTable>

        <DataTable v-else-if="tab === 'departments'" :columns="departmentColumns" :rows="departmentTree" :empty-title="t('organization.noDepartments')">
            <template #cell-name="{ row }">
                <span class="font-medium text-fg" :style="{ paddingInlineStart: `${row.depth * 1.25}rem` }">{{ row.name }}</span>
            </template>
            <template #cell-head="{ row }">{{ row.head ?? '—' }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? t('common.active') : t('common.inactive') }}</StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.manage" class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="openDepartment(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="removeDepartment(row)" />
                </div>
            </template>
        </DataTable>

        <DataTable v-else :columns="positionColumns" :rows="positions" :empty-title="t('organization.noPositions')">
            <template #cell-department="{ row }">{{ row.department ?? '—' }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? t('common.active') : t('common.inactive') }}</StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.manage" class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="openPosition(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="removePosition(row)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="showDepartment" max-width="md" @close="showDepartment = false">
            <form class="space-y-5 p-6" @submit.prevent="submitDepartment">
                <h2 class="font-display text-lg font-normal text-fg">{{ editingDepartment ? t('organization.editDepartment') : t('organization.addDepartment') }}</h2>
                <div>
                    <InputLabel for="department_name" :value="t('organization.departmentName')" />
                    <TextInput id="department_name" v-model="departmentForm.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="departmentForm.errors.name" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="department_parent" :value="t('organization.parentDepartment')" />
                    <Select id="department_parent" v-model="departmentForm.parent_id" class="mt-1 w-full">
                        <option value="">{{ t('organization.none') }}</option>
                        <option v-for="department in departments.filter((candidate) => candidate.id !== editingDepartment?.id)" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </Select>
                    <InputError :message="departmentForm.errors.parent_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="department_head" :value="t('organization.head')" />
                    <Select id="department_head" v-model="departmentForm.head_id" class="mt-1 w-full">
                        <option value="">{{ t('organization.none') }}</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </Select>
                    <InputError :message="departmentForm.errors.head_id" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="departmentForm.is_active" type="checkbox" :class="checkboxClass" />
                    {{ t('common.active') }}
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="showDepartment = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="departmentForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showPosition" max-width="md" @close="showPosition = false">
            <form class="space-y-5 p-6" @submit.prevent="submitPosition">
                <h2 class="font-display text-lg font-normal text-fg">{{ editingPosition ? t('organization.editPosition') : t('organization.addPosition') }}</h2>
                <div>
                    <InputLabel for="position_name" :value="t('organization.positionName')" />
                    <TextInput id="position_name" v-model="positionForm.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="positionForm.errors.name" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="position_department" :value="t('organization.department')" />
                    <Select id="position_department" v-model="positionForm.department_id" class="mt-1 w-full">
                        <option value="">{{ t('organization.none') }}</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </Select>
                    <InputError :message="positionForm.errors.department_id" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="positionForm.is_active" type="checkbox" :class="checkboxClass" />
                    {{ t('common.active') }}
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="showPosition = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="positionForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showEmployee" max-width="md" @close="showEmployee = false">
            <form class="space-y-5 p-6" @submit.prevent="submitEmployee">
                <h2 class="font-display text-lg font-normal text-fg">{{ editingEmployee?.name }}</h2>
                <div>
                    <InputLabel for="employee_department" :value="t('organization.department')" />
                    <Select id="employee_department" v-model="employeeForm.department_id" class="mt-1 w-full">
                        <option value="">{{ t('organization.none') }}</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </Select>
                    <InputError :message="employeeForm.errors.department_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="employee_position" :value="t('organization.position')" />
                    <Select id="employee_position" v-model="employeeForm.position_id" class="mt-1 w-full">
                        <option value="">{{ t('organization.none') }}</option>
                        <option v-for="position in positions" :key="position.id" :value="position.id">{{ position.name }}</option>
                    </Select>
                    <InputError :message="employeeForm.errors.position_id" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="employee_manager" :value="t('organization.manager')" />
                    <Select id="employee_manager" v-model="employeeForm.manager_id" class="mt-1 w-full">
                        <option value="">{{ t('organization.none') }}</option>
                        <option v-for="employee in employees.filter((candidate) => candidate.id !== editingEmployee?.id)" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </Select>
                    <InputError :message="employeeForm.errors.manager_id" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :class="cancelClass" @click="showEmployee = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="employeeForm.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
