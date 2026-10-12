<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';

const props = defineProps({ customer: Object, groups: { type: Array, default: () => [] } });
const { t } = useI18n();

const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone ?? '',
    email: props.customer.email ?? '',
    address: props.customer.address ?? '',
    customer_group_id: props.customer.customer_group_id ?? '',
    notes: props.customer.notes ?? '',
    is_active: props.customer.is_active,
});

const submit = () => form.transform((data) => ({ ...data, customer_group_id: data.customer_group_id || null })).put(route('customers.update', props.customer.id));
</script>

<template>
    <Head :title="t('customers.editCustomer')" />

    <SidebarLayout>
        <template #header>
            <h2 class="font-display text-xl font-normal leading-tight text-fg">
                {{ t('customers.editCustomer') }}: {{ customer.name }}
            </h2>
        </template>

        <div class="mx-auto max-w-7xl">
            <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 border border-line">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel :value="t('common.name')" />
                        <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel :value="t('common.phone')" />
                        <TextInput v-model="form.phone" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.phone" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel :value="t('common.email')" />
                        <TextInput v-model="form.email" type="email" class="mt-1 block w-full" />
                        <InputError :message="form.errors.email" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel :value="t('common.address')" />
                        <TextInput v-model="form.address" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.address" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel :value="t('discounts.group')" />
                        <Select v-model="form.customer_group_id" class="mt-1 w-full">
                            <option value="">{{ t('discounts.noGroup') }}</option>
                            <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
                        </Select>
                        <InputError :message="form.errors.customer_group_id" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel :value="t('common.notes')" />
                    <textarea v-model="form.notes" rows="3"
                        class="mt-1 block w-full rounded-md border-line focus:border-gold-on focus:ring-ring-accent" />
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>

                <div class="flex items-center gap-3">
                    <input v-model="form.is_active" type="checkbox" id="is_active"
                        class="h-4 w-4 rounded border-line text-accent focus:ring-ring-accent" />
                    <InputLabel for="is_active" :value="t('common.active')" class="mb-0" />
                </div>

                <div class="flex gap-3">
                    <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
                    <a :href="route('customers.index')" class="rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line-strong">
                        {{ t('common.cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </SidebarLayout>
</template>
