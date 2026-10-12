<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';
import Card from '@tessera/tessera-ui/Components/Card.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';

const props = defineProps({
    tenant: Object,
    subscription: Object,
    plans: Array,
    modules: Array,
    statuses: Array,
    history: Array,
});

const { t } = useI18n();

const overrides = props.subscription?.module_overrides ?? {};

const form = useForm({
    plan_id: props.subscription?.plan_id ?? props.plans.find((plan) => plan.is_active)?.id ?? '',
    status: props.subscription?.status ?? 'active',
    trial_ends_at: props.subscription?.trial_ends_at ?? '',
    ends_at: props.subscription?.ends_at ?? '',
    module_overrides: Object.fromEntries(props.modules.map((module) => [module.value, module.value in overrides ? (overrides[module.value] ? '1' : '0') : ''])),
    notes: props.subscription?.notes ?? '',
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            trial_ends_at: data.trial_ends_at || null,
            ends_at: data.ends_at || null,
            module_overrides: Object.fromEntries(Object.entries(data.module_overrides).map(([key, value]) => [key, value === '' ? null : value === '1'])),
        }))
        .put(route('admin.tenants.subscription.update', props.tenant.id));
};

const statusTone = (status) => ({ active: 'success', trialing: 'info', past_due: 'warn', canceled: 'neutral', expired: 'danger' })[status] ?? 'neutral';
const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : '-');
</script>

<template>
    <Head :title="t('plans.tenantSubscription')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.tenants.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('plans.tenantSubscription') }}</h1>
                    <p class="text-sm text-muted">{{ tenant.name }}</p>
                </div>
            </div>
        </template>

        <form class="mx-auto max-w-7xl space-y-6" @submit.prevent="submit">
            <Card>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="sub_plan" :value="t('plans.plan')" />
                        <Select id="sub_plan" v-model="form.plan_id" class="mt-1 w-full" required>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }}{{ plan.is_active ? '' : ` (${t('common.inactive')})` }}
                            </option>
                        </Select>
                        <InputError :message="form.errors.plan_id" class="mt-2" />
                        <p class="mt-1 text-xs text-muted">{{ t('plans.changePlanHint') }}</p>
                    </div>
                    <div>
                        <InputLabel for="sub_status" :value="t('plans.status')" />
                        <Select id="sub_status" v-model="form.status" class="mt-1 w-full" required>
                            <option v-for="status in statuses" :key="status" :value="status">{{ t(`plans.statuses.${status}`) }}</option>
                        </Select>
                        <InputError :message="form.errors.status" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="sub_trial" :value="t('plans.trialEnds')" />
                        <DatePicker id="sub_trial" v-model="form.trial_ends_at" with-time class="mt-1 w-full" />
                        <InputError :message="form.errors.trial_ends_at" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="sub_ends" :value="t('plans.endsAt')" />
                        <DatePicker id="sub_ends" v-model="form.ends_at" with-time class="mt-1 w-full" />
                        <InputError :message="form.errors.ends_at" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="sub_notes" :value="t('plans.notes')" />
                        <textarea id="sub_notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent" />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>
                </div>
            </Card>

            <Card>
                <h2 class="font-display text-lg font-normal text-fg">{{ t('plans.overrides') }}</h2>
                <p class="mb-4 text-sm text-muted">{{ t('plans.overridesHint') }}</p>
                <div class="space-y-3">
                    <div v-for="module in modules" :key="module.value" class="flex items-center justify-between gap-4">
                        <span class="text-sm text-fg">{{ t(`plans.modulesNames.${module.value}`, module.label) }}</span>
                        <Select v-model="form.module_overrides[module.value]" class="w-40">
                            <option value="">{{ t('plans.followPlan') }}</option>
                            <option value="1">{{ t('plans.forceOn') }}</option>
                            <option value="0">{{ t('plans.forceOff') }}</option>
                        </Select>
                    </div>
                </div>
            </Card>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
            </div>

            <Card v-if="history.length">
                <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ t('plans.history') }}</h2>
                <ul class="divide-y divide-line text-sm">
                    <li v-for="row in history" :key="row.id" class="flex items-center justify-between gap-4 py-2">
                        <span class="text-fg">{{ row.plan }}</span>
                        <span class="text-muted">{{ formatDate(row.starts_at) }} → {{ formatDate(row.ends_at) }}</span>
                        <StatusBadge :status="statusTone(row.status)">{{ t(`plans.statuses.${row.status}`) }}</StatusBadge>
                    </li>
                </ul>
            </Card>
        </form>
    </SidebarLayout>
</template>
