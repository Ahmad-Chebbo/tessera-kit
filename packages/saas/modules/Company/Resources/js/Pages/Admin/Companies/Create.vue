<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import { ArrowLeft } from 'lucide-vue-next';
import Select from '@tessera/tessera-ui/Components/Select.vue';

const props = defineProps({
    plans: Array,
});

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    currency: 'USD',
    tax_rate: 0,
    is_active: true,
    plan_id: props.plans?.[0]?.id ?? '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
});

const submit = () => {
    form.post(route('admin.companies.store'));
};
</script>

<template>
    <Head :title="t('companies.addCompany')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.companies.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('companies.addCompany') }}</h1>
                    <p class="text-sm text-muted">{{ t('companies.createNewCompany') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Company Information -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('companies.companyInfo') }}</h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel for="name" :value="t('companies.companyName')" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autofocus
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="email" :value="t('common.email')" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.email" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="phone" :value="t('common.phone')" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.phone" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="address" :value="t('common.address')" />
                            <TextInput
                                id="address"
                                v-model="form.address"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.address" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="currency" :value="t('companies.currency')" />
                            <Select
                                id="currency"
                                v-model="form.currency"
                                class="mt-1 w-full"
                                required>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                                <option value="LBP">LBP - Lebanese Pound</option>
                                <option value="AED">AED - UAE Dirham</option>
                                <option value="SAR">SAR - Saudi Riyal</option>
                            </Select>
                            <InputError :message="form.errors.currency" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="tax_rate" :value="t('companies.taxRate')" />
                            <TextInput
                                id="tax_rate"
                                v-model="form.tax_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.tax_rate" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-line text-accent focus:ring-ring-accent"
                            />
                            <InputLabel for="is_active" :value="t('common.active')" />
                        </div>
                    </div>
                </div>

                <!-- Plan -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <InputLabel for="plan_id" :value="t('plans.plan')" />
                    <Select id="plan_id" v-model="form.plan_id" class="mt-1 w-full" required>
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                            {{ plan.name }} — {{ plan.currency }} {{ Number(plan.price).toFixed(2) }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.plan_id" class="mt-2" />
                </div>

                <!-- Admin User -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('companies.adminUser') }}</h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel for="admin_name" :value="t('companies.adminName')" />
                            <TextInput
                                id="admin_name"
                                v-model="form.admin_name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.admin_name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="admin_email" :value="t('companies.adminEmail')" />
                            <TextInput
                                id="admin_email"
                                v-model="form.admin_email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.admin_email" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="admin_password" :value="t('companies.adminPassword')" />
                            <TextInput
                                id="admin_password"
                                v-model="form.admin_password"
                                type="password"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.admin_password" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('admin.companies.index')"
                        class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ t('companies.createCompany') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </SidebarLayout>
</template>
