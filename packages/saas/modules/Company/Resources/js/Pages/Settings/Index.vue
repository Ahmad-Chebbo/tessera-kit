<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useModules } from '@/Composables/useModules';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import FileUpload from '@ahmad-chebbo/tessera-ui/Components/FileUpload.vue';

const props = defineProps({
    company: Object,
    settings: Object,
});

const { t } = useI18n();
const modules = useModules();

const form = useForm({
    name: props.company?.name || '',
    currency: props.settings?.currency || 'USD',
    tax_rate: props.settings?.tax_rate || 0,
    receipt_header: props.settings?.receipt_header || '',
    receipt_footer: props.settings?.receipt_footer || '',
    require_pos_shift: props.settings?.require_pos_shift || false,
    logo: null,
});

const submit = () => {
    form.put(route('settings.update'));
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
    <Head :title="t('settings.title')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">
                    {{ t('settings.title') }}
                </h1>
                <p class="text-sm text-muted">
                    Configure your business settings
                </p>
            </div>
        </template>

        <div class="">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Company Settings -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">
                        Company Information
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Company Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Currency & Tax -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">
                        Currency & Tax
                    </h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="default_currency" :value="t('settings.currency')" />
                            <Select
                                id="currency"
                                v-model="form.currency"
                                class="mt-1 w-full">
                                <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                    {{ currency.code }} - {{ currency.name }}
                                </option>
                            </Select>
                            <InputError :message="form.errors.currency" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="tax_rate" :value="t('settings.taxRate')" />
                            <div class="relative mt-1">
                                <TextInput
                                    id="tax_rate"
                                    v-model="form.tax_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="block w-full pe-8"
                                />
                                <span class="absolute end-3 top-1/2 -translate-y-1/2 text-muted">%</span>
                            </div>
                            <InputError :message="form.errors.tax_rate" class="mt-2" />
                        </div>

                        <div v-if="modules.has('sales')" class="md:col-span-2">
                            <label class="flex items-start gap-3">
                                <input
                                    v-model="form.require_pos_shift"
                                    type="checkbox"
                                    class="mt-1 rounded border-line text-accent focus:ring-ring-accent"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-fg">{{ t('settings.requireShift') }}</span>
                                    <span class="block text-sm text-muted">{{ t('settings.requireShiftHint') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Receipt Settings -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">
                        Receipt Settings
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="receipt_header" :value="t('settings.receiptHeader')" />
                            <textarea
                                id="receipt_header"
                                v-model="form.receipt_header"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                                placeholder="Text to appear at the top of receipts"
                            ></textarea>
                            <InputError :message="form.errors.receipt_header" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="receipt_footer" :value="t('settings.receiptFooter')" />
                            <textarea
                                id="receipt_footer"
                                v-model="form.receipt_footer"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                                placeholder="Text to appear at the bottom of receipts (e.g., Thank you for your purchase!)"
                            ></textarea>
                            <InputError :message="form.errors.receipt_footer" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Image -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">
                        Company Logo
                    </h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="logo" value="Logo" />
                            <FileUpload id="logo" v-model="form.logo" :preview-url="company.logo_url" :error="form.errors.logo" class="mt-1" />
                        </div>
                    </div>
                </div>

                


                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">
                        {{ t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </SidebarLayout>
</template>
