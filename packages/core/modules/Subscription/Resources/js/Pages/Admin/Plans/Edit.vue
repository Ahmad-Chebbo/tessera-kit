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
import Card from '@ahmad-chebbo/tessera-ui/Components/Card.vue';

const props = defineProps({
    plan: Object,
    modules: Array,
});

const { t } = useI18n();

const form = useForm({
    name: props.plan?.name ?? '',
    slug: props.plan?.slug ?? '',
    description: props.plan?.description ?? '',
    price: props.plan?.price ?? 0,
    currency: props.plan?.currency ?? 'USD',
    billing_interval: props.plan?.billing_interval ?? 'monthly',
    trial_days: props.plan?.trial_days ?? 0,
    modules: props.plan?.modules ? [...props.plan.modules] : [],
    max_users: props.plan?.max_users ?? '',
    max_branches: props.plan?.max_branches ?? '',
    is_active: props.plan?.is_active ?? true,
    sort_order: props.plan?.sort_order ?? 0,
});

const toggleModule = (value) => {
    form.modules = form.modules.includes(value) ? form.modules.filter((module) => module !== value) : [...form.modules, value];
};

const submit = () => {
    const transform = (data) => ({ ...data, max_users: data.max_users === '' ? null : data.max_users, max_branches: data.max_branches === '' ? null : data.max_branches });

    if (props.plan) {
        form.transform(transform).put(route('admin.plans.update', props.plan.id));
    } else {
        form.transform(transform).post(route('admin.plans.store'));
    }
};

const title = computed(() => (props.plan ? t('plans.editPlan') : t('plans.addPlan')));
</script>

<template>
    <Head :title="title" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.plans.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <h1 class="font-display text-xl font-normal text-fg">{{ title }}</h1>
            </div>
        </template>

        <form class="mx-auto max-w-7xl space-y-6" @submit.prevent="submit">
            <Card>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="plan_name" :value="t('plans.name')" />
                        <TextInput id="plan_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_slug" :value="t('plans.slug')" />
                        <TextInput id="plan_slug" v-model="form.slug" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.slug" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="plan_description" :value="t('plans.description')" />
                        <TextInput id="plan_description" v-model="form.description" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_price" :value="t('plans.price')" />
                        <TextInput id="plan_price" v-model="form.price" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.price" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_currency" :value="t('plans.currency')" />
                        <TextInput id="plan_currency" v-model="form.currency" type="text" maxlength="3" class="mt-1 block w-full uppercase" required />
                        <InputError :message="form.errors.currency" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_interval" :value="t('plans.interval')" />
                        <Select id="plan_interval" v-model="form.billing_interval" class="mt-1 w-full">
                            <option value="monthly">{{ t('plans.monthly') }}</option>
                            <option value="yearly">{{ t('plans.yearly') }}</option>
                            <option value="lifetime">{{ t('plans.lifetime') }}</option>
                        </Select>
                        <InputError :message="form.errors.billing_interval" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_trial" :value="t('plans.trialDays')" />
                        <TextInput id="plan_trial" v-model="form.trial_days" type="number" min="0" max="365" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.trial_days" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_users" :value="t('plans.maxUsers')" />
                        <TextInput id="plan_users" v-model="form.max_users" type="number" min="1" class="mt-1 block w-full" :placeholder="t('plans.unlimitedHint')" />
                        <InputError :message="form.errors.max_users" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_branches" :value="t('plans.maxBranches')" />
                        <TextInput id="plan_branches" v-model="form.max_branches" type="number" min="1" class="mt-1 block w-full" :placeholder="t('plans.unlimitedHint')" />
                        <InputError :message="form.errors.max_branches" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="plan_sort" :value="t('plans.sortOrder')" />
                        <TextInput id="plan_sort" v-model="form.sort_order" type="number" min="0" class="mt-1 block w-full" />
                        <InputError :message="form.errors.sort_order" class="mt-2" />
                    </div>
                    <label class="flex items-center gap-2 self-end text-sm text-fg">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-line text-accent focus:ring-ring-accent" />
                        {{ t('plans.active') }}
                    </label>
                </div>
            </Card>

            <Card>
                <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ t('plans.modules') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label v-for="module in modules" :key="module.value" class="flex items-center gap-2 text-sm text-fg">
                        <input
                            type="checkbox"
                            class="rounded border-line text-accent focus:ring-ring-accent"
                            :checked="form.modules.includes(module.value)"
                            @change="toggleModule(module.value)"
                        />
                        {{ t(`plans.modulesNames.${module.value}`, module.label) }}
                    </label>
                </div>
                <InputError :message="form.errors.modules" class="mt-2" />
            </Card>

            <div class="flex justify-end gap-4">
                <Link :href="route('admin.plans.index')" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line">
                    {{ t('common.cancel') }}
                </Link>
                <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </SidebarLayout>
</template>
