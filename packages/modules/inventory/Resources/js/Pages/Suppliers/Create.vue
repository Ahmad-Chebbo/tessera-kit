<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import { ArrowLeft } from 'lucide-vue-next';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    contact_person: '',
    notes: '',
});

const submit = () => {
    form.post(route('suppliers.store'));
};
</script>

<template>
    <Head title="Add Supplier" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('suppliers.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">Add Supplier</h1>
                    <p class="text-sm text-muted">Create a new supplier</p>
                </div>
            </div>
        </template>

        <form @submit.prevent="submit"
            class="space-y-6 rounded-2xl border border-line bg-surface p-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <InputLabel for="name" :value="t('common.name')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="phone" :value="t('common.phone')" />
                    <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.phone" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="contact_person" value="Contact Person" />
                    <TextInput id="contact_person" v-model="form.contact_person" type="text"
                        class="mt-1 block w-full" />
                    <InputError :message="form.errors.contact_person" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel for="address" :value="t('common.address')" />
                    <textarea id="address" v-model="form.address" rows="2"
                        class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"></textarea>
                    <InputError :message="form.errors.address" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel for="notes" :value="t('common.notes')" />
                    <textarea id="notes" v-model="form.notes" rows="3"
                        class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"></textarea>
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link :href="route('suppliers.index')"
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
