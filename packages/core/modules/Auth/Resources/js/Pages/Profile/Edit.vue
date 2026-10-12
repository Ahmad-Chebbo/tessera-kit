<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('nav.profile')" />

    <SidebarLayout>
        <template #header>
            <h1 class="font-display text-xl font-normal text-fg">{{ t('nav.profile') }}</h1>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <div class="rounded-2xl border border-line bg-surface p-4 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="rounded-2xl border border-line bg-surface p-4 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>


        </div>
    </SidebarLayout>
</template>
