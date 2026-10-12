<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';

const { t } = useI18n();

const form = useForm({
    name: '',
    address: '',
    phone: '',
    is_active: true,
});

const submit = () => {
    form.post(route('branches.store'));
};
</script>

<template>
    <Head :title="t('branches.addBranch')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h2 class="font-display text-xl font-normal leading-tight text-fg">
                    {{ t('branches.addBranch') }}
                </h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 border border-line">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Name -->
                <div class="md:col-span-2">
                    <InputLabel for="name" :value="t('branches.name')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <!-- Address -->
                <div class="md:col-span-2">
                    <InputLabel for="address" :value="t('branches.address')" />
                    <TextInput id="address" v-model="form.address" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.address" class="mt-2" />
                </div>

                <!-- Phone -->
                <div>
                    <InputLabel for="phone" :value="t('branches.phone')" />
                    <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.phone" class="mt-2" />
                </div>

                <!-- Status -->
                <div>
                    <InputLabel for="is_active" :value="t('common.status')" />
                    <Select id="is_active" v-model="form.is_active"
                        class="mt-1 w-full">
                        <option :value="true">{{ t('common.active') }}</option>
                        <option :value="false">{{ t('common.inactive') }}</option>
                    </Select>
                    <InputError :message="form.errors.is_active" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link :href="route('branches.index')"
                    class="rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line">
                    {{ t('common.cancel') }}
                </Link>
                <PrimaryButton :disabled="form.processing">
                    {{ t('common.save') }}
                </PrimaryButton>
            </div>
        </form>
    </SidebarLayout>
</template>
