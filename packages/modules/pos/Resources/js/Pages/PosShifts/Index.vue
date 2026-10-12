<script setup>
import ExportMenu from '@/Components/ExportMenu.vue';
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import BranchFilter from '@/Components/BranchFilter.vue';
import { useI18n } from 'vue-i18n';
import { Eye, LockOpen, Lock } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DataTable from '@tessera/tessera-ui/Components/DataTable.vue';
import FilterTabs from '@tessera/tessera-ui/Components/FilterTabs.vue';
import IconButton from '@tessera/tessera-ui/Components/IconButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import Card from '@tessera/tessera-ui/Components/Card.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    shifts: Object,
    currentShift: Object,
    employees: Array,
    filters: Object,
    can: Object,
});

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const status = ref(props.filters?.status || '');
const userId = ref(props.filters?.user_id || '');
const branch = ref(props.filters?.branch_id ?? '');

const statusTabs = computed(() => [
    { value: '', label: t('shifts.pos.all') },
    { value: 'open', label: t('shifts.pos.open') },
    { value: 'closed', label: t('shifts.pos.closedStatus') },
]);

watch(branch, () => {
    userId.value = '';
});

watch([status, userId, branch], () => {
    router.get(route('pos-shifts.index'), {
        status: status.value || undefined,
        user_id: userId.value || undefined,
        branch_id: branch.value || undefined,
    }, { preserveState: true, replace: true });
});

const columns = computed(() => [
    { key: 'user', label: t('shifts.pos.cashier') },
    { key: 'opened_at', label: t('shifts.pos.opened'), cellClass: 'text-sm text-muted' },
    { key: 'closed_at', label: t('shifts.pos.closed'), cellClass: 'text-sm text-muted' },
    { key: 'expected_cash', label: t('shifts.pos.expectedCash') },
    { key: 'variance', label: t('shifts.pos.variance') },
    { key: 'status', label: t('shifts.pos.status') },
    { key: 'actions', label: t('common.actions'), align: 'end' },
]);

const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : '-');

const varianceStatus = (value) => (value === null ? 'neutral' : value === 0 ? 'success' : value > 0 ? 'info' : 'danger');

const openForm = useForm({ opening_float: '' });
const closeForm = useForm({ counted_cash: '', notes: '' });
const showOpen = ref(false);
const showClose = ref(false);

const submitOpen = () => {
    openForm.post(route('pos-shifts.open'), {
        preserveScroll: true,
        onSuccess: () => {
            showOpen.value = false;
            openForm.reset();
        },
    });
};

const submitClose = () => {
    closeForm.post(route('pos-shifts.close', props.currentShift.id), {
        onSuccess: () => {
            showClose.value = false;
        },
    });
};
</script>

<template>
    <Head :title="t('shifts.pos.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('shifts.pos.title') }}</h1>
                <p class="text-sm text-muted">{{ t('shifts.pos.subtitle') }}</p>
            </div>
        </template>

        <div class="mb-4 flex justify-end print:hidden">
            <ExportMenu report="pos-shifts" :filters="{ status, user_id: userId, branch_id: branch }" />
        </div>

        <Card v-if="can.open" class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div v-if="currentShift">
                    <p class="font-medium text-fg">{{ t('shifts.pos.openSince', { time: formatDateTime(currentShift.opened_at) }) }}</p>
                    <p class="text-sm text-muted">{{ t('shifts.pos.openingFloat') }}: {{ formatCurrency(currentShift.opening_float) }}</p>
                </div>
                <div v-else>
                    <p class="font-medium text-fg">{{ t('shifts.pos.noOpenShift') }}</p>
                    <p class="text-sm text-muted">{{ t('shifts.pos.noOpenShiftHint') }}</p>
                </div>
                <button
                    v-if="currentShift"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                    @click="showClose = true"
                >
                    <Lock class="h-4 w-4" />
                    {{ t('shifts.pos.closeShift') }}
                </button>
                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-gold px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-gold-deep"
                    @click="showOpen = true"
                >
                    <LockOpen class="h-4 w-4" />
                    {{ t('shifts.pos.openShift') }}
                </button>
            </div>
        </Card>

        <div class="mb-6 flex flex-wrap items-center gap-3">
            <FilterTabs v-model="status" :tabs="statusTabs" />
            <BranchFilter v-if="can.seeAll" v-model="branch" />
            <Select v-if="can.seeAll" v-model="userId" class="w-56">
                <option value="">{{ t('shifts.pos.allEmployees') }}</option>
                <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
            </Select>
        </div>

        <DataTable :columns="columns" :rows="shifts.data" :empty-title="t('shifts.pos.noShifts')" :paginator="shifts">
            <template #cell-user="{ row }">
                <p class="font-medium text-fg">{{ row.user?.name }}</p>
                <p class="text-sm text-muted">{{ row.branch?.name }}</p>
            </template>
            <template #cell-opened_at="{ row }">{{ formatDateTime(row.opened_at) }}</template>
            <template #cell-closed_at="{ row }">{{ formatDateTime(row.closed_at) }}</template>
            <template #cell-expected_cash="{ row }">
                {{ row.expected_cash === null ? '-' : formatCurrency(row.expected_cash) }}
            </template>
            <template #cell-variance="{ row }">
                <StatusBadge v-if="row.variance !== null" :status="varianceStatus(Number(row.variance))">
                    {{ formatCurrency(row.variance) }}
                </StatusBadge>
                <span v-else class="text-muted">-</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.status === 'open' ? 'success' : 'neutral'">
                    {{ row.status === 'open' ? t('shifts.pos.open') : t('shifts.pos.closedStatus') }}
                </StatusBadge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <IconButton :icon="Eye" :label="t('shifts.pos.viewReport')" :href="route('pos-shifts.show', row.id)" />
                </div>
            </template>
        </DataTable>

        <Modal :show="showOpen" max-width="md" @close="showOpen = false">
            <form class="space-y-5 p-6" @submit.prevent="submitOpen">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('shifts.pos.openShift') }}</h2>
                <div>
                    <InputLabel for="opening_float" :value="t('shifts.pos.openingFloat')" />
                    <TextInput id="opening_float" v-model="openForm.opening_float" type="number" step="0.01" min="0" class="mt-1 block w-full" required autofocus />
                    <InputError :message="openForm.errors.opening_float" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="showOpen = false">
                        {{ t('common.cancel') }}
                    </button>
                    <PrimaryButton :disabled="openForm.processing">{{ t('shifts.pos.openShift') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showClose" max-width="md" @close="showClose = false">
            <form class="space-y-5 p-6" @submit.prevent="submitClose">
                <h2 class="font-display text-lg font-normal text-fg">{{ t('shifts.pos.closeShift') }}</h2>
                <p class="text-sm text-muted">{{ t('shifts.pos.confirmClose') }}</p>
                <div>
                    <InputLabel for="counted_cash" :value="t('shifts.pos.countedCash')" />
                    <TextInput id="counted_cash" v-model="closeForm.counted_cash" type="number" step="0.01" min="0" class="mt-1 block w-full" required autofocus />
                    <InputError :message="closeForm.errors.counted_cash" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="close_notes" :value="t('shifts.pos.notes')" />
                    <textarea id="close_notes" v-model="closeForm.notes" rows="2" class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent" />
                    <InputError :message="closeForm.errors.notes" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line" @click="showClose = false">
                        {{ t('common.cancel') }}
                    </button>
                    <PrimaryButton :disabled="closeForm.processing">{{ t('shifts.pos.closeShift') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </SidebarLayout>
</template>
