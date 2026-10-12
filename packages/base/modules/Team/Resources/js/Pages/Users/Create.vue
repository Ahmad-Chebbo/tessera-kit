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
import EmployeeProfileFields from '@modules/Team/Resources/js/Pages/Users/Partials/EmployeeProfileFields.vue';

const props = defineProps({
    features: { type: Object, default: () => ({ branches: true, employeeProfiles: true, activityLog: true }) },
    branches: Array,
    roles: Array,
});

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    branch_id: '',
    role: '',
    is_active: true,
    employee_number: '',
    phone: '',
    job_title: '',
    hire_date: '',
    birth_date: '',
    national_id: '',
    address: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    notes: '',
});

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <Head :title="t('users.addUser')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('users.addUser') }}</h1>
                    <p class="text-sm text-muted">{{ t('users.createNewUser') }}</p>
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
                    <InputLabel for="password" :value="t('users.password')" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
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
                        {{ t('users.createUser') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </SidebarLayout>
</template>
