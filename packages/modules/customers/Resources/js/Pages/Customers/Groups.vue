<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import { useConfirm } from '@tessera/tessera-ui/Composables/useConfirm';

defineProps({
    groups: Array,
});

const { t } = useI18n();
const confirm = useConfirm();

const columns = computed(() => [
    { key: 'name', label: t('discounts.groups.name') },
    { key: 'description', label: t('discounts.groups.description'), cellClass: 'text-sm text-muted' },
    { key: 'customers_count', label: t('discounts.groups.customers') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const form = useForm({ name: '', description: '' });
const editing = ref(null);
const show = ref(false);

const open = (group = null) => {
    form.clearErrors();
    editing.value = group;
    form.name = group?.name ?? '';
    form.description = group?.description ?? '';
    show.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (show.value = false) };

    if (editing.value) {
        form.put(route('customer-groups.update', editing.value.id), options);
    } else {
        form.post(route('customer-groups.store'), options);
    }
};

const remove = async (group) => {
    if (await confirm.delete(t('discounts.groups.confirmDelete'))) {
        router.delete(route('customer-groups.destroy', group.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="t('discounts.groups.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('discounts.groups.title') }}</h1>
                <p class="text-sm text-muted">{{ t('discounts.groups.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-6 flex justify-end">
            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep" @click="open()">
                <Plus class="h-4 w-4" />
                {{ t('discounts.groups.add') }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="groups" :empty-title="t('discounts.groups.none')">
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Pencil" :label="t('common.edit')" @click="open(row)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="remove(row)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="show" max-width="md" @close="show = false">
            <form class="space-y-5 p-6" @submit.prevent="submit">
                <h2 class="font-display text-lg font-normal text-fg">{{ editing ? t('discounts.groups.edit') : t('discounts.groups.add') }}</h2>
                <div>
                    <InputLabel for="group_name" :value="t('discounts.groups.name')" />
                    <TextInput id="group_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="group_description" :value="t('discounts.groups.description')" />
                    <TextInput id="group_description" v-model="form.description" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="show = false">{{ t('common.cancel') }}</button>
                    <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
