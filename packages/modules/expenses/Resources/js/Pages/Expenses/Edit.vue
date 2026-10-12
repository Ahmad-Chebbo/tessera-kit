<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';
import FileUpload from '@ahmad-chebbo/tessera-ui/Components/FileUpload.vue';
import Card from '@ahmad-chebbo/tessera-ui/Components/Card.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    expense: Object,
    lookups: Object,
    purchases: Array,
});

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    _method: props.expense ? 'put' : 'post',
    expense_category_id: props.expense?.expense_category_id ?? '',
    amount: props.expense?.amount ?? '',
    expense_date: props.expense?.expense_date ?? today,
    description: props.expense?.description ?? '',
    branch_id: props.expense?.branch_id ?? '',
    employee_id: props.expense?.employee_id ?? '',
    purchase_id: props.expense?.purchase_id ?? '',
    spread_months: props.expense?.spread_months ?? '',
    reference: props.expense?.reference ?? '',
    notes: props.expense?.notes ?? '',
    receipt: null,
    remove_receipt: false,
});

const categories = computed(() => props.lookups.categories.filter((category) => category.is_active || category.id === form.expense_category_id));
const category = computed(() => props.lookups.categories.find((candidate) => candidate.id === form.expense_category_id));
const isSalary = computed(() => category.value?.type === 'salaries');
const isAsset = computed(() => category.value?.type === 'assets');
const title = computed(() => (props.expense ? t('expenses.edit') : t('expenses.add')));

const pickPurchase = (id) => {
    form.purchase_id = id;
    const purchase = props.purchases.find((candidate) => candidate.id === id);

    if (!purchase) {
        return;
    }

    form.amount = purchase.total;
    form.expense_date = purchase.date;

    if (!form.description) {
        form.description = purchase.label;
    }

    const purchasesCategory = props.lookups.categories.find((candidate) => candidate.type === 'purchases' && candidate.is_active);

    if (purchasesCategory) {
        form.expense_category_id = purchasesCategory.id;
    }
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            branch_id: data.branch_id || null,
            employee_id: isSalary.value ? data.employee_id || null : null,
            purchase_id: data.purchase_id || null,
            spread_months: isAsset.value ? data.spread_months || null : null,
            receipt: data.receipt ?? undefined,
        }))
        .post(props.expense ? route('expenses.update', props.expense.id) : route('expenses.store'), { forceFormData: true });
};
</script>

<template>
    <Head :title="title" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('expenses.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <h1 class="font-display text-xl font-normal text-fg">{{ title }}</h1>
            </div>
        </template>

        <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
            <Card>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="expense_category" :value="t('expenses.category')" />
                        <Select id="expense_category" v-model="form.expense_category_id" class="mt-1 w-full" required>
                            <option value="">{{ t('common.select') }}</option>
                            <option v-for="option in categories" :key="option.id" :value="option.id">{{ option.name }}</option>
                        </Select>
                        <InputError :message="form.errors.expense_category_id" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="expense_amount" :value="t('expenses.amount')" />
                        <TextInput id="expense_amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="expense_date" :value="t('expenses.date')" />
                        <DatePicker id="expense_date" v-model="form.expense_date" class="mt-1 w-full" />
                        <InputError :message="form.errors.expense_date" class="mt-2" />
                    </div>
                    <div v-if="lookups.branches.length > 1">
                        <InputLabel for="expense_branch" :value="t('expenses.branch')" />
                        <Select id="expense_branch" v-model="form.branch_id" class="mt-1 w-full">
                            <option value="">{{ t('expenses.noBranch') }}</option>
                            <option v-for="branch in lookups.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </Select>
                        <InputError :message="form.errors.branch_id" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="expense_description" :value="t('expenses.description')" />
                        <TextInput id="expense_description" v-model="form.description" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                    <div v-if="isSalary" class="sm:col-span-2">
                        <InputLabel for="expense_employee" :value="t('expenses.employee')" />
                        <Select id="expense_employee" v-model="form.employee_id" class="mt-1 w-full">
                            <option value="">{{ t('common.select') }}</option>
                            <option v-for="employee in lookups.employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                        </Select>
                        <p class="mt-1 text-xs text-muted">{{ t('expenses.employeeHint') }}</p>
                        <InputError :message="form.errors.employee_id" class="mt-2" />
                    </div>
                    <div v-if="isAsset" class="sm:col-span-2">
                        <InputLabel for="expense_spread" :value="t('expenses.spreadMonths')" />
                        <TextInput id="expense_spread" v-model="form.spread_months" type="number" min="2" max="120" class="mt-1 block w-full max-w-xs" />
                        <p class="mt-1 text-xs text-muted">{{ t('expenses.spreadHint') }}</p>
                        <InputError :message="form.errors.spread_months" class="mt-2" />
                    </div>
                </div>
            </Card>

            <Card v-if="purchases.length">
                <InputLabel for="expense_purchase" :value="t('expenses.linkPurchase')" />
                <Select id="expense_purchase" :model-value="form.purchase_id" class="mt-1 w-full" @update:model-value="pickPurchase">
                    <option value="">{{ t('expenses.noPurchase') }}</option>
                    <option v-for="purchase in purchases" :key="purchase.id" :value="purchase.id">
                        {{ purchase.label }} — {{ formatCurrency(purchase.total) }} ({{ purchase.date }})
                    </option>
                </Select>
                <p class="mt-1 text-xs text-muted">{{ t('expenses.linkPurchaseHint') }}</p>
                <InputError :message="form.errors.purchase_id" class="mt-2" />
            </Card>

            <Card>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="expense_reference" :value="t('expenses.reference')" />
                        <TextInput id="expense_reference" v-model="form.reference" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.reference" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="expense_notes" :value="t('expenses.notes')" />
                        <textarea id="expense_notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent" />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="expense_receipt" :value="t('expenses.receipt')" />
                        <FileUpload id="expense_receipt" v-model="form.receipt" accept="image/*,application/pdf" :max-size-mb="5" :hint="t('expenses.receiptHint')" :error="form.errors.receipt" class="mt-1" />
                        <p v-if="expense?.receipt_url && !form.receipt" class="mt-2 flex flex-wrap items-center gap-4 text-sm">
                            <a :href="expense.receipt_url" target="_blank" rel="noopener" class="text-accent hover:underline">{{ t('expenses.viewReceipt') }}</a>
                            <label class="flex items-center gap-2 text-muted">
                                <input v-model="form.remove_receipt" type="checkbox" class="rounded border-line text-accent focus:ring-ring-accent" />
                                {{ t('expenses.removeReceipt') }}
                            </label>
                        </p>
                    </div>
                </div>
            </Card>

            <div class="flex justify-end gap-4">
                <Link :href="route('expenses.index')" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line">{{ t('common.cancel') }}</Link>
                <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </SidebarLayout>
</template>
