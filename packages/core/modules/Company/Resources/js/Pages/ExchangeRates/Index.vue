<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import { ArrowRight, Trash2 } from 'lucide-vue-next';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import { useConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';

const props = defineProps({
    exchangeRates: Array,
});

const { t } = useI18n();
const confirm = useConfirm();

const form = useForm({
    from_currency: 'USD',
    to_currency: 'LBP',
    rate: '',
});

const submit = () => {
    form.post(route('exchange-rates.store'), {
        onSuccess: () => form.reset('rate'),
    });
};

const deleteRate = async (rate) => {
    if (await confirm.delete()) {
        router.delete(route('exchange-rates.destroy', rate.id));
    }
};

const currencies = [
    { code: 'USD', name: 'US Dollar' },
    { code: 'EUR', name: 'Euro' },
    { code: 'GBP', name: 'British Pound' },
    { code: 'LBP', name: 'Lebanese Pound' },
    { code: 'AED', name: 'UAE Dirham' },
    { code: 'SAR', name: 'Saudi Riyal' },
];
</script>

<template>
    <Head title="Exchange Rates" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    Exchange Rates
                </h1>
                <p class="text-sm text-muted">
                    Manage currency exchange rates
                </p>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Add New Rate Form -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">
                    Add Exchange Rate
                </h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="from_currency" value="From Currency" />
                            <Select
                                id="from_currency"
                                v-model="form.from_currency"
                                class="mt-1 w-full">
                                <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                    {{ currency.code }}
                                </option>
                            </Select>
                            <InputError :message="form.errors.from_currency" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="to_currency" value="To Currency" />
                            <Select
                                id="to_currency"
                                v-model="form.to_currency"
                                class="mt-1 w-full">
                                <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                    {{ currency.code }}
                                </option>
                            </Select>
                            <InputError :message="form.errors.to_currency" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="rate" value="Exchange Rate" />
                        <TextInput
                            id="rate"
                            v-model="form.rate"
                            type="number"
                            step="0.000001"
                            min="0"
                            class="mt-1 block w-full"
                            placeholder="e.g., 89500 for USD to LBP"
                            required
                        />
                        <p class="mt-1 text-sm text-muted">
                            1 {{ form.from_currency }} = {{ form.rate || '?' }} {{ form.to_currency }}
                        </p>
                        <InputError :message="form.errors.rate" class="mt-2" />
                    </div>

                    <PrimaryButton :disabled="form.processing" class="w-full justify-center">
                        Add Rate
                    </PrimaryButton>
                </form>
            </div>

            <!-- Current Rates -->
            <div class="rounded-2xl border border-line bg-surface p-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">
                    Current Rates
                </h3>

                <div v-if="exchangeRates.length === 0" class="py-8 text-center text-muted">
                    No exchange rates configured
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="rate in exchangeRates"
                        :key="rate.id"
                        class="flex items-center justify-between rounded-lg border border-line p-4"
                    >
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent-wash text-sm font-bold text-accent">
                                {{ rate.from_currency }}
                            </div>
                            <ArrowRight class="h-5 w-5 text-faint rtl:rotate-180" />
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-status-success-wash text-sm font-bold text-status-success">
                                {{ rate.to_currency }}
                            </div>
                            <div>
                                <p class="font-medium text-fg">
                                    {{ Number(rate.rate).toLocaleString() }}
                                </p>
                                <p class="text-sm text-muted">
                                    1 {{ rate.from_currency }} = {{ Number(rate.rate).toLocaleString() }} {{ rate.to_currency }}
                                </p>
                            </div>
                        </div>
                        <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" @click="deleteRate(rate)" />
                    </div>
                </div>
            </div>
        </div>
    </SidebarLayout>
</template>
