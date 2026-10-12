<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import ViewToggle from '@ahmad-chebbo/tessera-ui/Components/ViewToggle.vue';
import GridCard from '@ahmad-chebbo/tessera-ui/Components/GridCard.vue';
import { useViewMode } from '@ahmad-chebbo/tessera-ui/Composables/useViewMode';
import StatusBadge from '@ahmad-chebbo/tessera-ui/Components/StatusBadge.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';
import CardGrid from '@ahmad-chebbo/tessera-ui/Components/CardGrid.vue';

const props = defineProps({
    units: Array,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'unitName', label: t('units.unitName'), cellClass: 'text-sm font-medium text-fg' },
    { key: 'abbreviation', label: t('units.abbreviation'), cellClass: 'text-sm text-muted' },
    { key: 'type', label: t('units.type'), cellClass: 'text-sm' },
    { key: 'actions', label: t('common.actions'), align: 'end', cellClass: 'text-sm' },
]);
const confirm = useConfirm();
const { mode: viewMode, setMode: setViewMode } = useViewMode('units');

const showForm = ref(false);
const editingUnit = ref(null);

const form = useForm({
    name: '',
    abbreviation: '',
});

const startCreate = () => {
    editingUnit.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const startEdit = (unit) => {
    editingUnit.value = unit;
    form.name = unit.name;
    form.abbreviation = unit.abbreviation;
    form.clearErrors();
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    editingUnit.value = null;
    form.reset();
};

const submit = () => {
    if (editingUnit.value) {
        form.put(route('units.update', editingUnit.value.id), {
            onSuccess: () => cancelForm(),
        });
    } else {
        form.post(route('units.store'), {
            onSuccess: () => cancelForm(),
        });
    }
};

const deleteUnit = async (unit) => {
    if (await confirm.delete(t('units.confirmDelete'))) {
        router.delete(route('units.destroy', unit.id));
    }
};
</script>

<template>
    <Head :title="t('units.title')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl font-normal leading-tight text-fg">
                    {{ t('units.title') }}
                </h2>
                <button
                    v-if="!showForm"
                    type="button"
                    @click="startCreate"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                >
                    <Plus class="h-5 w-5" />
                    {{ t('units.addUnit') }}
                </button>
            </div>
        </template>

        <!-- Create/Edit Form -->
        <div v-if="showForm" class="mb-6 rounded-2xl bg-surface p-6 border border-line">
            <h3 class="font-display mb-4 text-lg font-normal text-fg">
                {{ editingUnit ? t('units.editUnit') : t('units.addUnit') }}
            </h3>
            <form @submit.prevent="submit" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <InputLabel for="name" :value="t('units.unitName')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div class="w-32">
                    <InputLabel for="abbreviation" :value="t('units.abbreviation')" />
                    <TextInput id="abbreviation" v-model="form.abbreviation" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.abbreviation" class="mt-1" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton :disabled="form.processing">
                        {{ t('common.save') }}
                    </PrimaryButton>
                    <button
                        type="button"
                        @click="cancelForm"
                        class="rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line"
                    >
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="mb-4 flex justify-end">
            <ViewToggle class="sm:ms-auto" :model-value="viewMode" @update:model-value="setViewMode" />
            </div>

        <!-- Units Table -->
        <DataTable
            v-if="viewMode === 'table'"
            :columns="tableColumns"
            :rows="units"
            :empty-title="t('units.noUnits')"
        >
            <template #cell-unitName="{ row: unit }">
                {{ unit.name }}
            </template>
            <template #cell-abbreviation="{ row: unit }">
                {{ unit.abbreviation }}
            </template>
            <template #cell-type="{ row: unit }">
                <span
                    :class="[
                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                        unit.company_id === null
                            ? 'bg-status-info-wash text-status-info'
                            : 'bg-hover-wash text-fg'
                    ]"
                >
                    {{ unit.company_id === null ? t('units.system') : t('units.custom') }}
                </span>
            </template>
            <template #cell-actions="{ row: unit }">
                <template v-if="unit.company_id !== null">
                    <IconButton :icon="Pencil" :label="t('common.edit')" type="button" @click="startEdit(unit)" />
                    <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" type="button" @click="deleteUnit(unit)" />
                </template>
                <span v-else class="text-faint">-</span>
            </template>
        </DataTable>
        <CardGrid v-else
            :items="units"
            :empty-title="t('units.noUnits')"
        >
            <template #item="{ item: unit }">
                <GridCard>
                    <template #title>{{ unit.name }}</template>
                    <template #subtitle>{{ unit.abbreviation }}</template>
                    <template #badge>
                        <StatusBadge :status="unit.company_id === null ? 'info' : 'neutral'">
                            {{ unit.company_id === null ? t('units.system') : t('units.custom') }}
                        </StatusBadge>
                    </template>
                    <template v-if="unit.company_id !== null" #actions>
                        <IconButton :icon="Pencil" :label="t('common.edit')" type="button" @click="startEdit(unit)" />
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" type="button" @click="deleteUnit(unit)" />
                    </template>
                </GridCard>
            </template>
        </CardGrid>
    </SidebarLayout>
</template>
