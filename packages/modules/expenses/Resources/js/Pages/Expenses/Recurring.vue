<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Pause, Pencil, Play, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import ExpensesTabs from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpensesTabs.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    items: Array,
    lookups: Object,
    can: Object,
});

const { t } = useI18n();
const confirm = useConfirm();
const { formatCurrency } = useCurrency();

const columns = computed(() => [
    { key: 'description', label: t('expenses.description') },
    { key: 'amount', label: t('expenses.amount') },
    { key: 'frequency', label: t('expenses.frequency') },
    { key: 'next_run_on', label: t('expenses.nextRun'), cellClass: 'text-sm text-muted' },
    { key: 'runs_count', label: t('expenses.runs') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const today = new Date().toISOString().slice(0, 10);
const form = useForm({
    expense_category_id: '', amount: '', description: '', frequency: 'monthly', starts_on: today, ends_on: '',
    branch_id: '', employee_id: '', spread_months: '', notes: '', is_active: true,
});
const editing = ref(null);
const show = ref(false);

const category = computed(() => props.lookups.categories.find((candidate) => candidate.id === form.expense_category_id));
const isSalary = computed(() => category.value?.type === 'salaries');
const isAsset = computed(() => category.value?.type === 'assets');

const open = (item = null) => {
    form.clearErrors();
    editing.value = item;
    form.expense_category_id = item?.expense_category_id ?? '';
    form.amount = item?.amount ?? '';
    form.description = item?.description ?? '';
    form.frequency = item?.frequency ?? 'monthly';
    form.starts_on = item?.starts_on ?? today;
    form.ends_on = item?.ends_on ?? '';
    form.branch_id = item?.branch_id ?? '';
    form.employee_id = item?.employee_id ?? '';
    form.spread_months = item?.spread_months ?? '';
    form.notes = item?.notes ?? '';
    form.is_active = item?.is_active ?? true;
    show.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (show.value = false) };
    const transform = (data) => ({
        ...data,
        ends_on: data.ends_on || null,
        branch_id: data.branch_id || null,
        employee_id: isSalary.value ? data.employee_id || null : null,
        spread_months: isAsset.value ? data.spread_months || null : null,
    });

    if (editing.value) {
        form.transform(transform).put(route('recurring-expenses.update', editing.value.id), options);
    } else {
        form.transform(transform).post(route('recurring-expenses.store'), options);
    }
};

const remove = async (item) => {
    if (await confirm.delete(t('expenses.confirmDeleteRecurring'))) {
        router.delete(route('recurring-expenses.destroy', item.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('expenses.recurringTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('expenses.recurringTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('expenses.recurringHint') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <ExpensesTabs current="recurring-expenses.index" />
            <button v-if="can.edit" type="button" class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep" @click="open()">
                <Plus class="h-4 w-4" />
                {{ t('expenses.addRecurring') }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="items" :empty-title="t('expenses.noRecurring')">
            <template #cell-description="{ row }">
                <p class="font-medium text-fg">{{ row.description }}</p>
                <p class="text-xs text-muted">{{ row.category?.name }}<template v-if="row.employee"> · {{ row.employee.name }}</template></p>
            </template>
            <template #cell-amount="{ row }">{{ formatCurrency(row.amount) }}</template>
            <template #cell-frequency="{ row }">{{ t(`expenses.frequencies.${row.frequency}`) }}</template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? t('expenses.active') : t('expenses.paused') }}</StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.edit" class="flex items-center justify-end gap-2">
                    <IconButton
                        :icon="row.is_active ? Pause : Play"
                        :label="row.is_active ? t('expenses.pause') : t('expenses.resume')"
                        @click="router.post(route('recurring-expenses.toggle', row.id), {}, { preserveScroll: true })"
                    />
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="open(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="show" max-width="lg" @close="show = false">
            <form class="space-y-5 p-6" @submit.prevent="submit">
                <h2 class="font-display text-lg font-normal text-fg">{{ editing ? t('expenses.editRecurring') : t('expenses.addRecurring') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="rec_description" :value="t('expenses.description')" />
                        <TextInput id="rec_description" v-model="form.description" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rec_category" :value="t('expenses.category')" />
                        <Select id="rec_category" v-model="form.expense_category_id" class="mt-1 w-full" required>
                            <option value="">{{ t('common.select') }}</option>
                            <option v-for="option in lookups.categories" :key="option.id" :value="option.id">{{ option.name }}</option>
                        </Select>
                        <InputError :message="form.errors.expense_category_id" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rec_amount" :value="t('expenses.amount')" />
                        <TextInput id="rec_amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rec_frequency" :value="t('expenses.frequency')" />
                        <Select id="rec_frequency" v-model="form.frequency" class="mt-1 w-full" :disabled="!!editing">
                            <option v-for="value in lookups.frequencies" :key="value" :value="value">{{ t(`expenses.frequencies.${value}`) }}</option>
                        </Select>
                        <p v-if="editing" class="mt-1 text-xs text-muted">{{ t('expenses.scheduleLocked') }}</p>
                        <InputError :message="form.errors.frequency" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rec_starts" :value="t('expenses.startsOn')" />
                        <DatePicker id="rec_starts" v-model="form.starts_on" class="mt-1 w-full" :disabled="!!editing" />
                        <InputError :message="form.errors.starts_on" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rec_ends" :value="t('expenses.endsOn')" />
                        <DatePicker id="rec_ends" v-model="form.ends_on" class="mt-1 w-full" />
                        <InputError :message="form.errors.ends_on" class="mt-2" />
                    </div>
                    <div v-if="lookups.branches.length > 1">
                        <InputLabel for="rec_branch" :value="t('expenses.branch')" />
                        <Select id="rec_branch" v-model="form.branch_id" class="mt-1 w-full">
                            <option value="">{{ t('expenses.noBranch') }}</option>
                            <option v-for="branch in lookups.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </Select>
                    </div>
                    <div v-if="isSalary">
                        <InputLabel for="rec_employee" :value="t('expenses.employee')" />
                        <Select id="rec_employee" v-model="form.employee_id" class="mt-1 w-full">
                            <option value="">{{ t('common.select') }}</option>
                            <option v-for="employee in lookups.employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                        </Select>
                    </div>
                    <div v-if="isAsset">
                        <InputLabel for="rec_spread" :value="t('expenses.spreadMonths')" />
                        <TextInput id="rec_spread" v-model="form.spread_months" type="number" min="2" max="120" class="mt-1 block w-full" />
                        <InputError :message="form.errors.spread_months" class="mt-2" />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-line text-accent focus:ring-ring-accent" />
                    {{ t('expenses.active') }}
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="show = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
