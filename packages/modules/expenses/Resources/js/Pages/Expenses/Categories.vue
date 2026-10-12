<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import Modal from '@ahmad-chebbo/tessera-ui/Components/Modal.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import ExpensesTabs from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpensesTabs.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';

const props = defineProps({
    categories: Array,
    types: Array,
    can: Object,
});

const { t } = useI18n();
const confirm = useConfirm();

const columns = computed(() => [
    { key: 'name', label: t('expenses.categoryName') },
    { key: 'type', label: t('expenses.type') },
    { key: 'affects_profit', label: t('expenses.affectsProfit') },
    { key: 'expenses_count', label: t('expenses.expensesCount') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const form = useForm({ name: '', description: '', type: 'operating', affects_profit: true, is_active: true });
const editing = ref(null);
const show = ref(false);

const defaultFor = (type) => props.types.find((candidate) => candidate.value === type)?.affects_profit ?? true;

watch(() => form.type, (type) => {
    if (!editing.value) {
        form.affects_profit = defaultFor(type);
    }
});

const open = (category = null) => {
    form.clearErrors();
    editing.value = category;
    form.name = category?.name ?? '';
    form.description = category?.description ?? '';
    form.type = category?.type ?? 'operating';
    form.affects_profit = category?.affects_profit ?? true;
    form.is_active = category?.is_active ?? true;
    show.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (show.value = false) };

    if (editing.value) {
        form.put(route('expense-categories.update', editing.value.id), options);
    } else {
        form.post(route('expense-categories.store'), options);
    }
};

const remove = async (category) => {
    if (await confirm.delete(t('expenses.confirmDeleteCategory'))) {
        router.delete(route('expense-categories.destroy', category.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('expenses.categoriesTitle')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('expenses.categoriesTitle') }}</h1>
                <p class="text-sm text-muted">{{ t('expenses.affectsProfitHint') }}</p>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <ExpensesTabs current="expense-categories.index" />
            <button v-if="can.edit" type="button" class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep" @click="open()">
                <Plus class="h-4 w-4" />
                {{ t('expenses.addCategory') }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="categories" :empty-title="t('expenses.noCategories')">
            <template #cell-name="{ row }">
                <p class="font-medium text-fg">{{ row.name }}</p>
                <p v-if="row.description" class="text-xs text-muted">{{ row.description }}</p>
            </template>
            <template #cell-type="{ row }">{{ t(`expenses.types.${row.type}`) }}</template>
            <template #cell-affects_profit="{ row }">
                <StatusBadge :status="row.affects_profit ? 'success' : 'neutral'">{{ row.affects_profit ? t('expenses.yes') : t('expenses.no') }}</StatusBadge>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? t('common.active') : t('common.inactive') }}</StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="can.edit" class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="open(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="show" max-width="md" @close="show = false">
            <form class="space-y-5 p-6" @submit.prevent="submit">
                <h2 class="font-display text-lg font-normal text-fg">{{ editing ? t('expenses.editCategory') : t('expenses.addCategory') }}</h2>
                <div>
                    <InputLabel for="category_name" :value="t('expenses.categoryName')" />
                    <TextInput id="category_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="category_type" :value="t('expenses.type')" />
                    <Select id="category_type" v-model="form.type" class="mt-1 w-full">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ t(`expenses.types.${type.value}`) }}</option>
                    </Select>
                    <InputError :message="form.errors.type" class="mt-2" />
                </div>
                <label class="flex items-start gap-2 text-sm text-fg">
                    <input v-model="form.affects_profit" type="checkbox" class="mt-0.5 rounded border-line text-accent focus:ring-ring-accent" />
                    <span>{{ t('expenses.affectsProfit') }}<span class="block text-xs text-muted">{{ t('expenses.affectsProfitHint') }}</span></span>
                </label>
                <div>
                    <InputLabel for="category_description" :value="t('expenses.description')" />
                    <TextInput id="category_description" v-model="form.description" type="text" class="mt-1 block w-full" />
                </div>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-line text-accent focus:ring-ring-accent" />
                    {{ t('common.active') }}
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="show = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
