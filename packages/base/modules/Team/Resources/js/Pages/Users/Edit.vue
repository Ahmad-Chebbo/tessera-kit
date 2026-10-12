<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import { ArrowLeft } from 'lucide-vue-next';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import EmployeeProfileFields from '@modules/Team/Resources/js/Pages/Users/Partials/EmployeeProfileFields.vue';

const props = defineProps({
    features: { type: Object, default: () => ({ branches: true, employeeProfiles: true, activityLog: true }) },
    user: Object,
    branches: Array,
    roles: Array,
});

const { t } = useI18n();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    branch_id: props.user.branch_id || '',
    role: props.user.role || '',
    is_active: props.user.is_active,
    employee_number: props.user.profile?.employee_number ?? '',
    phone: props.user.profile?.phone ?? '',
    job_title: props.user.profile?.job_title ?? '',
    hire_date: props.user.profile?.hire_date?.slice(0, 10) ?? '',
    birth_date: props.user.profile?.birth_date?.slice(0, 10) ?? '',
    national_id: props.user.profile?.national_id ?? '',
    address: props.user.profile?.address ?? '',
    emergency_contact_name: props.user.profile?.emergency_contact_name ?? '',
    emergency_contact_phone: props.user.profile?.emergency_contact_phone ?? '',
    notes: props.user.profile?.notes ?? '',
});

const submit = () => {
    form.put(route('users.update', props.user.id));
};
</script>

<template>
    <Head :title="t('users.editUser')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('users.editUser') }}</h1>
                    <p class="text-sm text-muted">{{ user.name }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl">
            <form @submit.prevent="submit" class="space-y-6 rounded-2xl border border-line bg-surface p-6">
                <div>
                    <InputLabel for="name" :value="t('users.name')" />
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
                    <InputLabel for="email" :value="t('users.email')" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="password" :value="t('users.newPassword')" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        :placeholder="t('users.leaveBlank')"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div v-if="features.branches">
                    <InputLabel for="branch_id" :value="t('users.branch')" />
                    <Select
                        id="branch_id"
                        v-model="form.branch_id"
                        class="mt-1 w-full"
                        required>
                        <option value="">{{ t('users.selectBranch') }}</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.branch_id" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="role" :value="t('users.role')" />
                    <Select
                        id="role"
                        v-model="form.role"
                        class="mt-1 w-full"
                        required>
                        <option value="">{{ t('users.selectRole') }}</option>
                        <option v-for="r in roles" :key="r.id" :value="r.name">
                            {{ r.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.role" class="mt-2" />
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

                <EmployeeProfileFields v-if="features.employeeProfiles" :form="form" />

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('users.index')"
                        class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ t('users.updateUser') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </SidebarLayout>
</template>
